<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Nav;
use App\Support\Notices;
use App\Support\Permissions;
use App\Support\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ShareWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role', 'tenant');
        if ($user && $user->tenantId && ! $user->isSuperAdmin && ! $user->tenant) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => Tenant::CLOSED_MESSAGE]);
        }
        $role = $user?->isSuperAdmin ? 'SUPER_ADMIN' : ($user?->role?->role ?? 'CASHIER');
        $roleName = $user?->role?->name;
        $permissions = Permissions::normalize($user?->role?->permissions ?? []);
        $subscription = $user?->tenantId
            ? Subscription::query()->with('plan')->where('tenantId', $user->tenantId)->first()
            : null;
        $features = $subscription?->features() ?? [];
        $status = $subscription?->status;
        $ended = (bool) $subscription?->hasEnded();
        $blocked = $ended || in_array($status, ['SUSPENDED', 'CANCELLED'], true);
        $allowedWhileBlocked = $request->routeIs('suspended', 'logout') || $request->is('billing', 'billing/*', 'support', 'support/*');

        if ($user && $role !== 'SUPER_ADMIN' && $blocked && ! $allowedWhileBlocked) {
            if ($role === 'ADMIN') {
                return redirect()->route('billing')->withErrors(['plan' => $ended
                    ? 'Your subscription has ended. Choose a plan to keep using SalesDock.'
                    : 'This subscription is not active. Choose a plan to continue.']);
            }

            return redirect()->route('suspended');
        }

        $path = '/'.ltrim($request->path(), '/');
        $bell = $user && $user->tenantId && $role !== 'CLIENT' ? Notices::preview($user) : null;

        if ($request->routeIs('suspended', 'unauthorized', 'not-eligible', 'logout')) {
            view()->share($this->shared($role, $roleName ?? $role, $permissions, $subscription, false, $bell, $features));

            return $next($request);
        }

        if ($user && ! Nav::allows($role, $path, $permissions, $roleName, $features, false)) {
            return redirect()->route('unauthorized');
        }

        if ($user && ! in_array($role, ['SUPER_ADMIN', 'CLIENT'], true) && ! PlanFeatures::covers($path, $features)) {
            return redirect()->route('not-eligible');
        }

        $needsPlan = $role !== 'SUPER_ADMIN' && $status === null;
        $onBilling = $request->is('billing', 'billing/*');
        if ($needsPlan && ! $onBilling && ! $request->is('logout') && ! $request->isMethod('GET')) {
            return redirect()->route('billing')->withErrors(['plan' => 'Choose a plan before using the registers.']);
        }

        view()->share($this->shared(
            $role,
            $roleName ?? $role,
            $permissions,
            $subscription,
            $needsPlan && ! $onBilling,
            $bell,
            $features
        ));

        return $next($request);
    }

    private function shared(string $role, string $roleName, array $permissions, $subscription, bool $paywallLocked, ?array $bell, array $features): array
    {
        return [
            'role' => $role,
            'roleName' => $roleName,
            'permissions' => $permissions,
            'subscription' => $subscription,
            'paywallLocked' => $paywallLocked,
            'nav' => Nav::items($role, $permissions, $roleName, $features),
            'opensStorefront' => PlanFeatures::includes($features, ['Storefront / Online Store', 'White-label Storefront']),
            'bell' => $bell,
        ];
    }
}
