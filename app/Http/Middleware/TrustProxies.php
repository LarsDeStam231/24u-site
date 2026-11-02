<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustProxies extends Middleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Forwarded-Host') !== parse_url(config('app.url'), PHP_URL_HOST)) {
            $request->headers->remove('X-Forwarded-Host');
        }

        return parent::handle($request, $next);
    }
}
