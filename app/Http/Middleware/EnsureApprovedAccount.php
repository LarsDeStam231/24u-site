<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isApproved(), 403, 'Je account is nog niet goedgekeurd.');
        $fingerprint = $request->session()->get('qr_fingerprint');
        if ($fingerprint !== null) {
            abort(403, 'Deze oude QR-sessie is vervallen. Log opnieuw in met je wachtwoord of passkey.');
        }

        return $next($request);
    }
}
