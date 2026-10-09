<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Console\Command;

class DowngradeSubscriptions extends Command
{
    protected $signature = 'subscriptions:downgrade';

    protected $description = 'Expire PAST_DUE subscriptions whose grace period has ended; the shop stays locked until a paid plan is chosen';

    public function handle(): int
    {
        $subscriptions = Subscription::query()
            ->with('tenant')
            ->where('status', 'PAST_DUE')
            ->where('gracePeriodEndsAt', '<', now())
            ->get();

        $expired = 0;

        foreach ($subscriptions as $sub) {
            $sub->forceFill([
                'status' => 'EXPIRED',
                'nextBillingDate' => null,
                'cancelAtPeriodEnd' => false,
            ])->save();

            $tenant = $sub->tenant;
            if (! $tenant) {
                continue;
            }

            Notification::query()->create([
                'tenantId' => $tenant->id,
                'type' => 'SUBSCRIPTION_EXPIRED',
                'title' => 'Subscription ended',
                'message' => 'Your grace period ended. Choose a plan to unlock your dashboard again. Your data is safe.',
                'isRead' => false,
                'createdAt' => now(),
            ]);

            $owner = User::query()
                ->where('tenantId', $tenant->id)
                ->whereHas('role', fn ($q) => $q->where('name', 'Owner'))
                ->first();

            if ($owner?->email) {
                AuthMail::send($owner->email, $owner->name, 'Your SalesDock subscription has ended', [
                    'preheader' => 'Choose a plan to unlock your dashboard again.',
                    'heading' => 'Subscription ended',
                    'kicker' => $tenant->name,
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, the grace period for <strong>'.e($tenant->name).'</strong> has ended.',
                        'The dashboard is locked until a plan is paid for. Your products, sales and customers are all kept safe, and everything comes back as soon as you renew.',
                    ],
                    'url' => route('billing'),
                    'label' => 'Choose a plan',
                ]);
            }

            $expired++;
            $this->line('Expired: '.$tenant->name);
        }

        $this->info($expired.' subscription(s) expired.');

        return self::SUCCESS;
    }
}
