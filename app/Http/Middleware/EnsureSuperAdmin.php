<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');

        if (! $user || (! $user->isSuperAdmin && $user->role?->role !== 'SUPER_ADMIN')) {
            abort(403);
        }

        return $next($request);
    }
}
