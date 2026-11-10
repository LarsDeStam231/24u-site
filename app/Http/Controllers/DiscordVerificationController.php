<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiscordVerificationController extends Controller
{
    /**
     * Link a Discord account to the approved user that owns the given Discord code.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'discordUserId' => ['required', 'string', 'regex:/^\d{5,32}$/'],
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            $user = User::query()
                ->where('discord_code', Str::upper(trim($data['code'])))
                ->where('registration_status', 'approved')
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return response()->json(['message' => 'Deze code is ongeldig.'], 404);
            }

            if ($user->discord_user_id !== null && $user->discord_user_id !== $data['discordUserId']) {
                return response()->json(['message' => 'Deze code is al gekoppeld aan een ander Discord-account.'], 409);
            }

            $alreadyLinked = $user->discord_user_id === $data['discordUserId'];

            if (! $alreadyLinked) {
                User::query()->where('discord_user_id', $data['discordUserId'])
                    ->update(['discord_user_id' => null, 'discord_verified_at' => null]);
                $user->discord_user_id = $data['discordUserId'];
                $user->discord_verified_at = now();
                $user->save();
            }

            return response()->json([
                'name' => $user->name ?: Str::before($user->email, '@'),
                'role' => $user->role,
                'alreadyLinked' => $alreadyLinked,
            ]);
        });
    }
}
