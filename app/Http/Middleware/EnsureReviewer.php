<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReviewer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role');
        $role = $user?->role?->role;

        if (! $user || (! $user->isSuperAdmin && $role !== 'SUPER_ADMIN')) {
            abort(403);
        }

        return $next($request);
    }
}
