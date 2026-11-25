<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if (! $request->attributes->has('request_id')) {
            $request->attributes->set(
                'request_id',
                $request->headers->get('X-Request-Id') ?: (string) str()->uuid()
            );
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $request->attributes->get('request_id'));

        return $response;
    }
}
