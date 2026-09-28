<?php

namespace App\Console\Commands;

use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Move ACTIVE subscriptions whose period has ended to PAST_DUE and start the 7-day grace clock';

    public function handle(): int
    {
        $now = now();

        // Find ACTIVE subscriptions whose period ended and grace hasn't been set / is now
        $subscriptions = Subscription::query()
            ->with('tenant')
            ->where('status', 'ACTIVE')
            ->where('currentPeriodEnd', '<', $now)
            ->get();

        $moved = 0;

        foreach ($subscriptions as $sub) {
            $gracePeriodEndsAt = $sub->currentPeriodEnd->copy()->addDays($sub->gracePeriodDays ?? 7);

            $sub->forceFill([
                'status'            => 'PAST_DUE',
                'gracePeriodEndsAt' => $gracePeriodEndsAt,
            ])->save();

            $tenant = $sub->tenant;
            if (! $tenant) {
                continue;
            }

            $daysLeft = (int) $now->diffInDays($gracePeriodEndsAt, false);

            // In-app notification
            Notification::query()->create([
                'tenantId'  => $tenant->id,
                'type'      => 'SUBSCRIPTION_EXPIRING',
                'title'     => 'Subscription expired',
                'message'   => 'Your plan has expired. You have ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' to renew before being moved to the free plan.',
                'isRead'    => false,
                'createdAt' => now(),
            ]);

            // Email the tenant owner
            $owner = User::query()
                ->where('tenantId', $tenant->id)
                ->whereHas('role', fn ($q) => $q->where('name', 'Owner'))
                ->first();

            if ($owner?->email) {
                AuthMail::send($owner->email, $owner->name, 'Your SalesDock subscription has expired', [
                    'preheader'  => 'Renew within ' . $daysLeft . ' days to keep full access.',
                    'heading'    => 'Subscription expired',
                    'kicker'     => $tenant->name,
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">' . e($owner->name) . '</strong>, your SalesDock subscription for <strong>' . e($tenant->name) . '</strong> has expired.',
                        'You have <strong>' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . '</strong> to renew before your account is automatically moved to the free plan. Your data stays safe throughout.',
                    ],
                    'url'        => route('billing'),
                    'label'      => 'Renew now',
                    'note'       => 'If you have already paid, please ignore this message — your subscription will update shortly.',
                ]);
            }

            $moved++;
            $this->line('Marked PAST_DUE: ' . $tenant->name . ' (grace ends ' . $gracePeriodEndsAt->format('d M Y') . ')');
        }

        $this->info($moved . ' subscription(s) moved to PAST_DUE.');

        return self::SUCCESS;
    }
}
