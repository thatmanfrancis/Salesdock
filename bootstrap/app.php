<?php

use App\Http\Middleware\EnsureReviewer;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\ShareWorkspace;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'reviewer' => EnsureReviewer::class,
            'workspace' => ShareWorkspace::class,
            'super' => EnsureSuperAdmin::class,
        ]);
        $middleware->validateCsrfTokens(except: ['billing/webhook']);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function () {
            $user = Auth::user();

            return $user instanceof User ? $user->homePath() : '/dashboard';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
