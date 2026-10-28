<?php

namespace App\Http\Controllers;

use App\QrPageData;
use App\QrPairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class QrSessionController extends Controller
{
    public function create(Request $request, QrPageData $pages): View
    {
        return $pages->render('login', 'Inloggen met een bestaande passkey', ['studentOnly' => $request->routeIs('students.login')]);
    }

    public function store(Request $request): JsonResponse
    {
        abort(410, 'Vaste inlog-QR-codes zijn vervallen. Log in met je wachtwoord of een bestaande passkey.');
    }

    public function destroy(Request $request, QrPairing $pairing): JsonResponse
    {
        $pairing->cancel($request, 'login');
        $pairing->cancel($request, 'registration');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['redirect' => route('login')]);
    }
}
