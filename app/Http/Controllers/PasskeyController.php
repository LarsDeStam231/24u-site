<?php

namespace App\Http\Controllers;

use App\Models\Passkey;
use App\Notifications\PasskeyChanged;
use App\QrPageData;
use App\QrPairing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasskeyController extends Controller
{
    public function index(Request $request, QrPageData $pages): View
    {
        return $pages->render('passkeys', 'Mijn passkeys', [
            'passkeys' => $request->user()->passkeys()->latest()->get(['id', 'name', 'created_at', 'last_used_at'])->map(fn (Passkey $key): array => [
                'id' => $key->id, 'name' => $key->name, 'createdAt' => $key->created_at->toIso8601String(),
                'lastUsedAt' => $key->last_used_at?->toIso8601String(), 'deleteUrl' => route('passkeys.destroy', $key->id),
            ])->all(),
        ]);
    }

    public function store(Request $request, QrPairing $pairing): JsonResponse
    {
        return response()->json($pairing->create($request, $request->user(), 'registration'));
    }

    public function destroy(Request $request, string $passkey): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'max:4096', 'current_password']]);
        $key = $request->user()->passkeys()->findOrFail($passkey);
        abort_unless(filled($request->user()->password), 422, 'Stel eerst een wachtwoord in om toegang tot je account te behouden.');
        $name = $key->name;
        $key->delete();
        $request->user()->notify(new PasskeyChanged('verwijderd', $name));

        return response()->json(['message' => 'Passkey verwijderd.']);
    }
}
