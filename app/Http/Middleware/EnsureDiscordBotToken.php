<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDiscordBotToken
{
    /**
     * Only allow requests that carry the shared Discord bot API key as a bearer token.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.discord.bot_api_key');
        $token = $request->bearerToken();

        abort_unless(
            is_string($apiKey) && $apiKey !== '' && is_string($token) && hash_equals($apiKey, $token),
            401,
            'Ongeldige API-sleutel.',
        );

        return $next($request);
    }
}
