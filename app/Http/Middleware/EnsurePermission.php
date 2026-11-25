<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_if($user === null, 401, __('auth.unauthenticated'));

        foreach ($permissions as $permission) {
            abort_unless(
                $user->hasPermission($permission),
                403,
                __('auth.forbidden')
            );
        }

        return $next($request);
    }
}
