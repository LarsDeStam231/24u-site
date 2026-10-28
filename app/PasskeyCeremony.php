<?php

namespace App;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SensitiveParameter;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;

class PasskeyCeremony
{
    /** @return array<string, mixed> */
    public function begin(Request $request, User $user, string $purpose, #[SensitiveParameter] string $token): array
    {
        abort_unless($request->getHost() === config('passkeys.relying_party.id'), 422,
            'Gebruik voor passkeys het vaste websiteadres uit APP_URL.');
        abort_unless(str_starts_with(config('app.url'), 'https://'), 422,
            'Stel APP_URL in op het vaste HTTPS-adres van deze website voordat je passkeys gebruikt.');

        if ($purpose === 'registration') {
            $options = json_decode(app(GeneratePasskeyRegisterOptionsAction::class)->execute($user), true, flags: JSON_THROW_ON_ERROR);
            $options['authenticatorSelection']['userVerification'] = 'required';
            $options['challenge'] = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $options['pubKeyCredParams'] = [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]];
            $options['excludeCredentials'] = $user->passkeys()->get()->map(fn (Passkey $key): array => [
                'type' => 'public-key', 'id' => rtrim(strtr(base64_encode($key->data->publicKeyCredentialId), '+/', '-_'), '='),
            ])->all();
        } else {
            $options = new PublicKeyCredentialRequestOptions(
                challenge: random_bytes(32), rpId: config('passkeys.relying_party.id'),
                allowCredentials: $user->passkeys()->get()->map(fn (Passkey $key): PublicKeyCredentialDescriptor => new PublicKeyCredentialDescriptor(
                    'public-key', $key->data->publicKeyCredentialId, $key->data->transports,
                ))->all(), userVerification: 'required', timeout: 60000,
            );
            $options = json_decode(Serializer::make()->toJson($options), true, flags: JSON_THROW_ON_ERROR);
        }
        $options['timeout'] = 60000;
        $sessionKey = $this->sessionKey($purpose, $token);
        if ($previous = $request->session()->get($sessionKey)) {
            Cache::forget('passkey:ceremony:'.$previous);
        }
        $id = Str::random(64);
        Cache::put('passkey:ceremony:'.$id, ['options' => json_encode($options, JSON_THROW_ON_ERROR), 'user_id' => $user->id], now()->addMinutes(2));
        $request->session()->put($sessionKey, $id);

        return $options;
    }

    public function consume(Request $request, User $user, string $purpose, #[SensitiveParameter] string $token): string
    {
        $id = $request->session()->pull($this->sessionKey($purpose, $token));
        abort_unless(is_string($id), 410, 'Begin de verificatie opnieuw.');
        $ceremony = Cache::pull('passkey:ceremony:'.$id);
        abort_unless(is_array($ceremony) && $ceremony['user_id'] === $user->id, 410, 'De verificatie is verlopen.');

        return $ceremony['options'];
    }

    private function sessionKey(string $purpose, #[SensitiveParameter] string $token): string
    {
        return 'passkey_ceremony_'.$purpose.'_'.hash('sha256', $token);
    }
}
