<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAwayCheckIn
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->session()->has('away_prompt')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Continue from the welcome back screen first.'], 409);
            }

            return redirect()->route('welcome-back');
        }

        return $next($request);
    }
}
