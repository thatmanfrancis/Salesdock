<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Support\Nav;
use App\Support\Notices;
use App\Support\Permissions;
use App\Support\PlanFeatures;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShareWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user?->loadMissing('role', 'tenant');
        $role = $user?->isSuperAdmin ? 'SUPER_ADMIN' : ($user?->role?->role ?? 'CASHIER');
        $roleName = $user?->role?->name;
        $permissions = Permissions::normalize($user?->role?->permissions ?? []);
        $subscription = $user?->tenantId
            ? Subscription::query()->with('plan')->where('tenantId', $user->tenantId)->first()
            : null;
        $features = is_array($subscription?->plan?->features) ? $subscription->plan->features : [];
        $status = $subscription?->status;
        $blocked = in_array($status, ['SUSPENDED', 'EXPIRED', 'CANCELLED'], true);

        if ($user && ! in_array($role, ['SUPER_ADMIN', 'ADMIN'], true) && $blocked && ! $request->routeIs('suspended') && ! $request->is('billing', 'billing/*')) {
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
