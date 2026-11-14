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

    protected $description = 'End paid access after the grace period while preserving Basic free selling tools';

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
                'autoRenew' => false,
                'recurringAuthorization' => null,
                'recurringAmount' => null,
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
                'message' => 'Your paid grace period ended. Basic free access keeps POS and basic shop tools available. Upgrade to restore advanced features. Your data is safe.',
                'isRead' => false,
                'createdAt' => now(),
            ]);

            $owner = User::query()
                ->where('tenantId', $tenant->id)
                ->whereHas('role', fn ($q) => $q->where('name', 'Owner'))
                ->first();

            if ($owner?->email) {
                AuthMail::send($owner->email, $owner->name, 'Your SalesDock subscription has ended', [
                    'preheader' => 'Basic free access keeps you selling. Upgrade to restore advanced features.',
                    'heading' => 'Subscription ended',
                    'kicker' => $tenant->name,
                    'paragraphs' => [
                        'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, the grace period for <strong>'.e($tenant->name).'</strong> has ended.',
                        'You can keep selling with Basic free access. Advanced paid features and the online storefront are unavailable until you subscribe again. Your products, sales, customers and invoices are retained.',
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
