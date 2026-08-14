<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $connect = app()->isLocal() ? "'self' ws: http: https:" : "'self' https:";
        $scripts = app()->isLocal() ? "'self' 'unsafe-inline' http:" : "'self' 'unsafe-inline'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; img-src 'self' data: blob: https://tile.openstreetmap.org; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src $scripts; connect-src $connect");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Clinic-Build', (string) config('release.id'));
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $camera = $request->routeIs('public.verify.*') ? '(self)' : '()';
        $response->headers->set('Permissions-Policy', "camera=$camera, microphone=(), geolocation=(), payment=()");

        return $response;
    }
}
