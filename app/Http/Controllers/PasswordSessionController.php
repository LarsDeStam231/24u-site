<?php

namespace App\Http\Controllers;

use App\EventPageData;
use App\QrPairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordSessionController extends Controller
{
    public function create(EventPageData $eventPageData): View
    {
        $pageData = $eventPageData->forPage('home');
        $pageData['page'] = [
            'name' => 'password-login',
            'heading' => 'Inloggen met e-mail en wachtwoord',
            'description' => 'Log in op je goedgekeurde account met je e-mailadres en wachtwoord.',
            'documentTitle' => 'Inloggen met wachtwoord — DCTerra 24 Uur Live',
            'canonical' => route('login'),
            'submitUrl' => route('password.login.store'),
            'optionsUrl' => route('login.options'),
            'mailUrl' => route('password.email'),
            'pairCreateUrl' => route('qr.pair.store'),
            'pairConsumeUrl' => route('qr.pair.consume'),
            'pairCancelUrl' => route('qr.pair.destroy'),
        ];

        return view('site', compact('pageData'));
    }

    public function store(Request $request, QrPairing $pairing): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
        ]);

        if (! Auth::attempt([
            'email' => Str::lower(trim($data['email'])),
            'password' => $data['password'],
            'registration_status' => 'approved',
        ])) {
            throw ValidationException::withMessages([
                'email' => 'De inloggegevens kloppen niet of je account is nog niet goedgekeurd.',
            ]);
        }

        $pairing->cancel($request, 'login');
        $pairing->cancel($request, 'registration');
        $request->session()->regenerate();
        $request->session()->forget('qr_fingerprint');

        return response()->json(['redirect' => route('account.index')]);
    }
}
