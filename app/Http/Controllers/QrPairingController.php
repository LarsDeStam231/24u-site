<?php

namespace App\Http\Controllers;

use App\QrPageData;
use App\QrPairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QrPairingController extends Controller
{
    public function create(QrPageData $pages): View
    {
        return $pages->render('pair', 'Log je computer in');
    }

    public function store(Request $request, QrPairing $pairing): JsonResponse
    {
        $request->validate(['student_only' => ['sometimes', 'boolean']]);

        return response()->json($pairing->create($request));
    }

    public function update(Request $request, QrPairing $pairing): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        $pairing->approve($data['token'], $data['code'], $request->user());

        return response()->json(['message' => 'Bevestigd. Je eigen computer wordt nu ingelogd.']);
    }

    public function consume(Request $request, QrPairing $pairing): JsonResponse
    {
        $user = $pairing->consume($request);
        if (! $user) {
            return response()->json(['status' => 'pending']);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('qr_fingerprint', $user->login_token_hash);

        return response()->json(['status' => 'approved', 'redirect' => route('account.index')]);
    }
}
