<?php

namespace App;

use App\Mail\QrActivationMail;
use App\Mail\ShareableQrCodesMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use SensitiveParameter;

class QrCredentials
{
    public function __construct(private QrImage $images) {}

    public function invite(User $user): void
    {
        abort_unless($user->isApproved(), 403);
        Cache::lock('qr:credentials:'.$user->id, 15)->block(3, function () use ($user): void {
            $token = Str::random(64);
            Cache::put('qr:activation:'.$user->id, [
                'hash' => hash('sha256', $token), 'email' => $user->email,
            ], now()->addHours(24));

            Mail::to($user->email)->send(new QrActivationMail(route('qr.activate').'#token='.$user->id.'.'.$token));
        });
    }

    /** @return array{user: User, codes: array<string, array{url: string, image: string}>} */
    public function activate(#[SensitiveParameter] string $token, string $name): array
    {
        [$id, $secret] = array_pad(explode('.', $token, 2), 2, '');
        abort_unless(ctype_digit($id) && preg_match('/^[A-Za-z0-9]{64}$/', $secret), 422, 'Deze activatielink is ongeldig of verlopen.');

        return Cache::lock('qr:credentials:'.$id, 15)->block(3, function () use ($id, $secret, $name): array {
            $invitation = Cache::get('qr:activation:'.$id);
            $user = User::find($id);
            abort_unless($user && $user->isApproved() && is_array($invitation)
                && hash_equals($invitation['hash'], hash('sha256', $secret))
                && $invitation['email'] === $user->email, 422, 'Deze activatielink is ongeldig of verlopen.');

            DB::transaction(function () use ($user, $name): void {
                $user->name = $name;
                $user->email_verified_at ??= now();
                $this->ensurePublicCodes($user);
            });
            Cache::forget('qr:activation:'.$id);

            return ['user' => $user, 'codes' => $this->publicCodes($user)];
        });
    }

    public function ensurePublicCodes(User $user): void
    {
        $user->attendance_token ??= Str::random(64);
        if ($user->role === 'student') {
            $user->donation_token ??= Str::random(64);
            $user->student_number = Str::before($user->email, '@');
        }
        $user->save();
    }

    /**
     * Give the user a unique, easy to type code to verify themselves with the Discord bot.
     */
    public function ensureDiscordCode(User $user): string
    {
        while ($user->discord_code === null) {
            $code = Str::upper(Str::password(8, letters: true, numbers: true, symbols: false, spaces: false));
            $code = strtr($code, ['0' => '2', 'O' => 'P', '1' => '3', 'I' => 'J', 'L' => 'K']);

            if (! User::query()->where('discord_code', $code)->exists()) {
                $user->discord_code = $code;
                $user->save();
            }
        }

        return $user->discord_code;
    }

    public function emailShareableCodesOnce(User $user): void
    {
        if ($user->qr_codes_emailed_at) {
            return;
        }

        Cache::lock('qr:welcome-mail:'.$user->id, 120)->get(function () use ($user): void {
            $user = $user->fresh();
            if (! $user || ! $user->isApproved() || ! $user->password || $user->qr_codes_emailed_at) {
                return;
            }

            $codes = [];
            foreach ($this->shareableCodeUrls() as $key => $url) {
                $codes[$key] = ['url' => $url, 'image' => $this->images->pngForUrl($url)];
            }

            Mail::to($user->email)->send(new ShareableQrCodesMail($user->name, $codes, $this->ensureDiscordCode($user)));
            $user->qr_codes_emailed_at = now();
            $user->save();
        });
    }

    /** @return array<string, array{url: string, image: string}> */
    public function shareableCodes(): array
    {
        $codes = [];
        foreach ($this->shareableCodeUrls() as $key => $url) {
            $codes[$key] = $this->images->forUrl($url);
        }

        return $codes;
    }

    /** @return array{website: string, foundationDonation: string} */
    private function shareableCodeUrls(): array
    {
        return [
            'website' => rtrim(rtrim(config('app.url'), '/').route('home', [], false), '/'),
            'foundationDonation' => config('event.event.foundation.donateUrl'),
        ];
    }

    /** @return array<string, array{url: string, image: string}> */
    public function publicCodes(User $user): array
    {
        $codes = [];
        if ($user->attendance_token) {
            $codes['attendance'] = $this->images->forUrl(route('attendance.index').'#token='.$user->attendance_token);
        }
        if ($user->donation_token && $user->role === 'student') {
            $donationUrl = rtrim(config('event.event.publicUrl'), '/').route('students.donation', $user->donation_token, false);
            $codes['donation'] = $this->images->forUrl($donationUrl);
        }

        return $codes;
    }
}
