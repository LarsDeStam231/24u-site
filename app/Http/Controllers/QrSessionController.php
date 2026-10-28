<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\QrPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QrSessionController extends Controller
{
    public function create(Request $request, QrPageData $pages): View
    {
        return $pages->render('login', 'Inloggen met jouw QR-code', ['studentOnly' => $request->routeIs('students.login')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/^[A-Za-z0-9]+$/']]);
        $user = User::query()->where('login_token_hash', hash('sha256', $data['token']))
            ->where('registration_status', 'approved')->first();
        abort_unless($user && (! $request->routeIs('students.login.store') || $user->role === 'student'), 422, 'Deze inlogcode is ongeldig.');
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('qr_fingerprint', $user->login_token_hash);

        return response()->json(['redirect' => route('account.index')]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['redirect' => route('login')]);
    }
}
