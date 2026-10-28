<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\PasskeyCeremony;
use App\QrPageData;
use App\QrPairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Throwable;

class QrPairingController extends Controller
{
    public function create(QrPageData $pages): View
    {
        return $pages->render('pair', 'Log je computer in met een bestaande passkey');
    }

    public function store(Request $request, QrPairing $pairing): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);
        $user = User::query()->where('email', Str::lower(trim($data['email'])))->where('registration_status', 'approved')->first();
        abort_unless($user && $user->passkeys()->exists(), 422, 'QR-inloggen is alleen beschikbaar met een al gekoppelde passkey.');

        return response()->json($pairing->create($request, $user, 'login'));
    }

    public function options(Request $request, QrPairing $pairing, PasskeyCeremony $ceremony): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        return $pairing->lock($data['token'], function () use ($data, $request, $pairing, $ceremony): JsonResponse {
            $pending = $pairing->inspect($data['token'], 'login');
            $pairing->confirmCode($pending, $data['code']);
            abort_unless($pending['passkey_id'] === null, 410);
            $user = $pairing->user($pending);
            abort_unless($user->passkeys()->exists(), 403);

            return response()->json($ceremony->begin($request, $user, 'login', $data['token']));
        });
    }

    public function update(Request $request, QrPairing $pairing, PasskeyCeremony $ceremony, FindPasskeyToAuthenticateAction $find): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64'], 'credential' => ['required', 'string', 'json', 'max:65536']]);

        return $pairing->lock($data['token'], function () use ($data, $request, $pairing, $ceremony, $find): JsonResponse {
            $pending = $pairing->inspect($data['token'], 'login');
            $user = $pairing->user($pending);
            $options = $ceremony->consume($request, $user, 'login', $data['token']);
            try {
                $passkey = $find->execute($data['credential'], $options);
            } catch (Throwable) {
                $passkey = null;
            }
            if (! $passkey || (int) $passkey->authenticatable_id !== $user->id) {
                throw ValidationException::withMessages(['credential' => 'De passkey kon niet voor dit account worden geverifieerd.']);
            }
            $pairing->approve($data['token'], $passkey);

            return response()->json(['message' => 'Bevestigd. Je oorspronkelijke computer wordt nu ingelogd.']);
        });
    }

    public function consume(Request $request, QrPairing $pairing): JsonResponse
    {
        $user = $pairing->consume($request);
        if (! $user) {
            return response()->json(['status' => 'pending']);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('qr_fingerprint');

        return response()->json(['status' => 'approved', 'redirect' => route('account.index')]);
    }

    public function destroy(Request $request, QrPairing $pairing): JsonResponse
    {
        $pairing->cancel($request, 'login');

        return response()->json(['message' => 'Inlogverzoek geannuleerd.']);
    }
}
