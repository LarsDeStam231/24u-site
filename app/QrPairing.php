<?php

namespace App;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SensitiveParameter;
use Spatie\LaravelPasskeys\Models\Passkey;

class QrPairing
{
    public function __construct(private QrImage $images) {}

    /** @return array{qr: array{url: string, image: string}, code: string, expiresAt: int} */
    public function create(Request $request, User $user, string $purpose): array
    {
        abort_unless(in_array($purpose, ['login', 'registration'], true) && $user->isApproved(), 403);
        abort_unless($purpose === 'login' ? $user->passkeys()->exists() : filled($user->password), 422,
            'Stel eerst een wachtwoord in en voeg vanuit je account een passkey toe.');

        if ($previous = $request->session()->get('qr_'.$purpose.'_key')) {
            Cache::forget($previous);
        }
        $token = Str::random(64);
        $owner = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $expires = now()->addMinutes($purpose === 'login' ? 5 : 10);
        Cache::put($this->key($token), [
            'purpose' => $purpose, 'user_id' => $user->id, 'owner' => hash('sha256', $owner),
            'code' => $code, 'expires' => $expires->timestamp, 'fingerprint' => $this->fingerprint($user),
            'passkey_id' => null,
        ], $expires);
        $request->session()->put(['qr_'.$purpose.'_key' => $this->key($token), 'qr_'.$purpose.'_owner' => $owner]);

        return [
            'qr' => $this->images->forUrl(rtrim(config('app.url'), '/').route($purpose === 'login' ? 'qr.pair' : 'passkeys.registration', [], false).'#token='.$token),
            'code' => $code, 'expiresAt' => $expires->timestamp,
        ];
    }

    /** @return array{purpose: string, user_id: int, owner: string, code: string, expires: int, fingerprint: string, passkey_id: ?int} */
    public function inspect(#[SensitiveParameter] string $token, string $purpose): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/', $token), 410, 'Deze QR-code is ongeldig of verlopen.');
        $data = Cache::get($this->key($token));
        abort_unless(is_array($data) && $data['purpose'] === $purpose && $data['expires'] > now()->timestamp, 410,
            'Deze QR-code is ongeldig of verlopen.');

        return $data;
    }

    /** @param array{user_id: int, fingerprint: string} $data */
    public function user(array $data): User
    {
        $user = User::find($data['user_id']);
        abort_unless($user && $user->isApproved() && hash_equals($data['fingerprint'], $this->fingerprint($user)), 403,
            'Het account is gewijzigd. Begin opnieuw.');

        return $user;
    }

    /** @param array{code: string} $data */
    public function confirmCode(array $data, #[SensitiveParameter] string $code): void
    {
        abort_unless(hash_equals($data['code'], $code), 422, 'Controleer de code op je computer.');
    }

    public function approve(#[SensitiveParameter] string $token, Passkey $passkey): void
    {
        $data = $this->inspect($token, 'login');
        $user = $this->user($data);
        abort_unless($data['passkey_id'] === null && (int) $passkey->authenticatable_id === $user->id, 403);
        $data['passkey_id'] = $passkey->id;
        Cache::put($this->key($token), $data, $data['expires'] - now()->timestamp);
    }

    public function consume(Request $request): ?User
    {
        $key = $request->session()->get('qr_login_key');
        $owner = $request->session()->get('qr_login_owner');
        abort_unless(is_string($key) && is_string($owner), 410);

        return Cache::lock($key.':lock', 15)->block(3, function () use ($key, $owner, $request): ?User {
            $data = Cache::get($key);
            abort_unless(is_array($data) && $data['purpose'] === 'login' && $data['expires'] > now()->timestamp
                && hash_equals($data['owner'], hash('sha256', $owner)), 410);
            $user = $this->user($data);
            abort_unless($user->passkeys()->exists(), 403);
            if ($data['passkey_id'] === null) {
                return null;
            }
            abort_unless($user->passkeys()->whereKey($data['passkey_id'])->exists(), 403);
            Cache::forget($key);
            $request->session()->forget(['qr_login_key', 'qr_login_owner']);

            return $user;
        });
    }

    public function lock(#[SensitiveParameter] string $token, Closure $callback): mixed
    {
        return Cache::lock($this->key($token).':lock', 15)->block(3, $callback);
    }

    public function forget(#[SensitiveParameter] string $token): void
    {
        Cache::forget($this->key($token));
    }

    public function cancel(Request $request, string $purpose): void
    {
        if ($key = $request->session()->pull('qr_'.$purpose.'_key')) {
            Cache::forget($key);
        }
        $request->session()->forget('qr_'.$purpose.'_owner');
    }

    public function fingerprint(User $user): string
    {
        return hash('sha256', $user->id.'|'.$user->email.'|'.($user->password ?? ''));
    }

    private function key(#[SensitiveParameter] string $token): string
    {
        return 'qr:passkey:'.hash('sha256', $token);
    }
}
