<?php

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->attributes->get('current_user');

        if (!$user || !in_array($user->role->name, $roles, true)) {
            throw new ForbiddenException();
        }

        return $next($request);
    }
}
