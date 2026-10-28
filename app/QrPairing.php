<?php

namespace App;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SensitiveParameter;

class QrPairing
{
    public function __construct(private QrImage $images) {}

    /** @return array{qr: array{url: string, image: string}, code: string, expiresAt: int} */
    public function create(Request $request): array
    {
        if ($previous = $request->session()->get('qr_pairing_key')) {
            Cache::forget($previous);
        }
        $token = Str::random(64);
        $key = $this->key($token);
        $owner = Str::random(64);
        $code = (string) random_int(100000, 999999);
        $expires = now()->addMinutes(2);
        Cache::put($key, ['owner' => hash('sha256', $owner), 'code' => $code, 'user_id' => null,
            'student_only' => $request->boolean('student_only'), 'expires' => $expires->timestamp], $expires);
        $request->session()->put(['qr_pairing_key' => $key, 'qr_pairing_owner' => $owner]);

        return ['qr' => $this->images->forUrl(route('qr.pair').'#token='.$token), 'code' => $code, 'expiresAt' => $expires->timestamp];
    }

    /** @return array<string, mixed> */
    public function inspect(#[SensitiveParameter] string $token): array
    {
        $data = Cache::get($this->key($token));
        abort_unless(is_array($data) && $data['expires'] > now()->timestamp, 410, 'Deze koppelcode is verlopen.');

        return $data;
    }

    public function approve(#[SensitiveParameter] string $token, #[SensitiveParameter] string $code, User $user): void
    {
        $key = $this->key($token);
        Cache::lock($key.':lock', 10)->block(3, function () use ($token, $code, $user, $key): void {
            $data = $this->inspect($token);
            abort_unless($data['user_id'] === null && hash_equals($data['code'], $code), 422, 'Controleer de code op je computer.');
            abort_unless($user->isApproved() && (! $data['student_only'] || $user->role === 'student'), 403);
            $data['user_id'] = $user->id;
            $data['fingerprint'] = $user->login_token_hash;
            Cache::put($key, $data, $data['expires'] - now()->timestamp);
        });
    }

    public function consume(Request $request): ?User
    {
        $key = $request->session()->get('qr_pairing_key');
        $owner = $request->session()->get('qr_pairing_owner');
        abort_unless(is_string($key) && is_string($owner), 410);

        return Cache::lock($key.':lock', 10)->block(3, function () use ($key, $owner, $request): ?User {
            $data = Cache::get($key);
            abort_unless(is_array($data) && $data['expires'] > now()->timestamp
                && hash_equals($data['owner'], hash('sha256', $owner)), 410);
            if ($data['user_id'] === null) {
                return null;
            }
            $user = User::find($data['user_id']);
            abort_unless($user && $user->isApproved() && $user->login_token_hash
                && hash_equals($user->login_token_hash, (string) $data['fingerprint']), 403);
            Cache::forget($key);
            $request->session()->forget(['qr_pairing_key', 'qr_pairing_owner']);

            return $user;
        });
    }

    private function key(#[SensitiveParameter] string $token): string
    {
        return 'qr:pairing:'.hash('sha256', $token);
    }
}
