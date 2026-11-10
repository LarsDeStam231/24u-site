<?php

namespace App\Http\Controllers;

use App\Notifications\PasskeyChanged;
use App\PasskeyCeremony;
use App\QrPageData;
use App\QrPairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Throwable;

class PasskeyRegistrationController extends Controller
{
    public function create(QrPageData $pages): View
    {
        return $pages->render('passkey-registration', 'Koppel een passkey aan jouw account');
    }

    public function authorizeAccount(Request $request, QrPairing $pairing): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'], 'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'], 'password' => ['required', 'string', 'max:4096'],
        ]);

        return $pairing->lock($data['token'], function () use ($data, $request, $pairing): JsonResponse {
            $pending = $pairing->inspect($data['token'], 'registration');
            $pairing->confirmCode($pending, $data['code']);
            $user = $pairing->user($pending);
            if (! hash_equals($user->email, Str::lower(trim($data['email']))) || ! $user->password || ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['email' => 'Gebruik het e-mailadres en wachtwoord van het account dat deze QR-code heeft aangemaakt.']);
            }
            $request->session()->put($this->grantKey($data['token']), [
                'fingerprint' => $pairing->fingerprint($user), 'expires' => now()->addMinutes(5)->timestamp,
            ]);

            return response()->json(['message' => 'Account gecontroleerd. Je kunt nu een passkey maken.']);
        });
    }

    public function options(Request $request, QrPairing $pairing, PasskeyCeremony $ceremony): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);

        return $pairing->lock($data['token'], function () use ($data, $request, $pairing, $ceremony): JsonResponse {
            $pending = $pairing->inspect($data['token'], 'registration');
            $this->checkGrant($request, $data['token'], $pending['fingerprint']);

            return response()->json($ceremony->begin($request, $pairing->user($pending), 'registration', $data['token']));
        });
    }

    public function store(Request $request, QrPairing $pairing, PasskeyCeremony $ceremony, StorePasskeyAction $store): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'], 'name' => ['required', 'string', 'max:120'],
            'credential' => ['required', 'string', 'json', 'max:65536'],
        ]);

        return $pairing->lock($data['token'], function () use ($data, $request, $pairing, $ceremony, $store): JsonResponse {
            $pending = $pairing->inspect($data['token'], 'registration');
            $this->checkGrant($request, $data['token'], $pending['fingerprint']);
            $user = $pairing->user($pending);
            $options = $ceremony->consume($request, $user, 'registration', $data['token']);
            try {
                $store->execute($user, $data['credential'], $options, config('passkeys.relying_party.id'), ['name' => trim($data['name'])]);
            } catch (Throwable) {
                throw ValidationException::withMessages(['credential' => 'De passkey kon niet worden opgeslagen. Probeer de verificatie opnieuw.']);
            }
            $pairing->forget($data['token']);
            $request->session()->forget($this->grantKey($data['token']));
            $user->notify(new PasskeyChanged('toegevoegd', trim($data['name'])));

            return response()->json(['message' => 'Passkey toegevoegd. Je kunt deze nu vanuit je account beheren.']);
        });
    }

    private function grantKey(string $token): string
    {
        return 'passkey_registration_'.hash('sha256', $token);
    }

    private function checkGrant(Request $request, string $token, string $fingerprint): void
    {
        $grant = $request->session()->get($this->grantKey($token));
        abort_unless(is_array($grant) && $grant['expires'] > now()->timestamp && hash_equals($fingerprint, $grant['fingerprint']), 403,
            'Bevestig eerst op deze telefoon je e-mailadres en wachtwoord.');
    }
}
