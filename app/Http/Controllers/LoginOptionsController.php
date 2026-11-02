<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LoginOptionsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);
        $user = User::query()->where('email', Str::lower(trim($data['email'])))
            ->where('registration_status', 'approved')->first();

        return response()->json([
            'hasPassword' => $user?->password !== null && $user?->password !== '',
            'hasPasskeys' => $user?->passkeys()->exists() ?? false,
        ]);
    }
}
