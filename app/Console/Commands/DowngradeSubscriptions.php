<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DowngradeSubscriptions extends Command
{
    protected $signature = 'subscriptions:downgrade';

    protected $description = 'Downgrade PAST_DUE subscriptions whose grace period has ended to the free/starter plan';

    public function handle(): int
    {
        $now = now();

        // Lowest-priced active plan = starter / free tier
        $freePlan = Plan::query()
            ->where('isActive', true)
            ->orderBy('monthlyPrice')
            ->first();

        if (! $freePlan) {
            $this->error('No active plans found. Cannot downgrade.');
            return self::FAILURE;
        }

        $subscriptions = Subscription::query()
            ->with('tenant')
            ->where('status', 'PAST_DUE')
            ->where('gracePeriodEndsAt', '<', $now)
            ->get();

        $downgraded = 0;

        foreach ($subscriptions as $sub) {
            $wasOnPlan = $sub->planId;
            $alreadyFree = $wasOnPlan === $freePlan->id;

            // New period: 30 days from now on the free plan
            $newStart = $now->copy();
            $newEnd   = $now->copy()->addMonth();

            $sub->forceFill([
                'status'             => 'ACTIVE',
                'planId'             => $freePlan->id,
                'billingCycle'       => 'MONTHLY',
                'currentPeriodStart' => $newStart,
                'currentPeriodEnd'   => $newEnd,
                'gracePeriodEndsAt'  => $newEnd->copy()->addDays(7),
                'lastRenewedAt'      => $now,
                'nextBillingDate'    => $newEnd,
                'cancelAtPeriodEnd'  => false,
            ])->save();

            $tenant = $sub->tenant;
            if (! $tenant) {
                continue;
            }

            if (! $alreadyFree) {
                // In-app notification
                Notification::query()->create([
                    'tenantId'  => $tenant->id,
                    'type'      => 'SUBSCRIPTION_EXPIRED',
                    'title'     => 'Moved to free plan',
                    'message'   => 'Your grace period ended. Your account has been moved to the ' . $freePlan->name . ' plan. Upgrade anytime to restore full access.',
                    'isRead'    => false,
                    'createdAt' => now(),
                ]);

                // Email the owner
                $owner = User::query()
                    ->where('tenantId', $tenant->id)
                    ->whereHas('role', fn ($q) => $q->where('name', 'Owner'))
                    ->first();

                if ($owner?->email) {
                    AuthMail::send($owner->email, $owner->name, 'Your SalesDock account has been moved to the free plan', [
                        'preheader'  => 'Upgrade anytime to restore full access.',
                        'heading'    => 'Moved to free plan',
                        'kicker'     => $tenant->name,
                        'paragraphs' => [
                            'Hi <strong style="color:#111827;">' . e($owner->name) . '</strong>, your grace period has ended.',
                            'Your account for <strong>' . e($tenant->name) . '</strong> has been automatically moved to the <strong>' . e($freePlan->name) . '</strong> plan. Your data is safe and your store is still accessible — but some premium features have been restricted.',
                            'You can upgrade to restore full access at any time from your billing page.',
                        ],
                        'url'        => route('billing'),
                        'label'      => 'Upgrade now',
                        'note'       => 'No action is required if you intended to use the free plan.',
                    ]);
                }
            }

            $downgraded++;
            $this->line('Downgraded to ' . $freePlan->name . ': ' . ($tenant->name ?? $sub->tenantId));
        }

        $this->info($downgraded . ' subscription(s) downgraded.');

        return self::SUCCESS;
    }
}
