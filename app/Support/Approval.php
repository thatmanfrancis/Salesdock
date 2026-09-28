<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Approval
{
    public static function canAuthorize(User $user): bool
    {
        $user->loadMissing('role');
        if ($user->isSuperAdmin || $user->role?->name === 'Owner') {
            return true;
        }

        return in_array($user->role?->role, ['SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    public static function authorize(Request $request, string $tenantId, ?string $branchId): User
    {
        $data = $request->validate([
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        $approver = User::query()
            ->with('role')
            ->where('tenantId', $tenantId)
            ->where('pin', hash('sha256', $data['pin']))
            ->where('isActive', true)
            ->get()
            ->first(function (User $user) use ($branchId) {
                if (! self::canAuthorize($user)) {
                    return false;
                }

                $global = $user->isSuperAdmin || $user->role?->name === 'Owner' || in_array($user->role?->role, ['ADMIN', 'SUPER_ADMIN'], true);
                if ($global || ! $branchId || ! $user->branchId) {
                    return true;
                }

                return $user->branchId === $branchId;
            });

        if (! $approver) {
            throw ValidationException::withMessages([
                'pin' => 'That PIN is not a supervisor, manager, or owner for this shop.',
            ]);
        }

        return $approver;
    }

    public static function log(Request $request, string $tenantId, ?string $userId, string $supervisorId, string $action, array $details): void
    {
        AuditLog::query()->create([
            'tenantId' => $tenantId,
            'userId' => $userId,
            'supervisorId' => $supervisorId,
            'action' => $action,
            'details' => $details,
            'ipAddress' => $request->ip(),
            'userAgent' => substr((string) $request->userAgent(), 0, 255),
            'timestamp' => now(),
        ]);
    }
}
