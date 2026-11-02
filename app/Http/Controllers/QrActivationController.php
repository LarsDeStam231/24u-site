<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\QrCredentials;
use App\QrPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QrActivationController extends Controller
{
    public function create(QrPageData $pages): View
    {
        return $pages->render('activate', 'Activeer jouw QR-codes');
    }

    public function store(Request $request, QrCredentials $credentials): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:100', 'regex:/^\d+\.[A-Za-z0-9]{64}$/'],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
        ]);
        $result = $credentials->activate($data['token'], trim($data['name']));

        return response()->json(['codes' => $result['codes'], 'accountUrl' => route('login')]);
    }

    public function invite(Request $request, QrCredentials $credentials): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $user = User::query()->where('email', Str::lower($data['email']))->where('registration_status', 'approved')->first();
        if ($user) {
            $credentials->invite($user);
        }

        return response()->json(['message' => 'Als dit e-mailadres bij een goedgekeurd account hoort, ontvang je een activatielink.'], 202);
    }
}
