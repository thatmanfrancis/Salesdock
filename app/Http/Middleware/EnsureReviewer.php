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

        if (! $user || (! $user->isSuperAdmin && ! in_array($role, ['ADMIN', 'SUPER_ADMIN'], true))) {
            abort(403);
        }

        return $next($request);
    }
}
