<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Multiple middleware arguments are ANDed. A pipe inside one argument
     * means OR, e.g. permission:laboratory.manage|settings.manage.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_if($user === null, 401, __('auth.unauthenticated'));

        foreach ($permissions as $group) {
            $options = array_values(array_filter(explode('|', $group), static fn ($value) => $value !== ''));

            $allowed = false;
            foreach ($options as $permission) {
                if ($user->hasPermission($permission)) {
                    $allowed = true;
                    break;
                }
            }

            abort_unless($allowed, 403, __('auth.forbidden'));
        }

        return $next($request);
    }
}
