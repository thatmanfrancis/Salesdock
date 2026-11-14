<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Console\Command;

class EndTrials extends Command
{
    /**
     * Days before the end of a trial when the owner is reminded.
     */
    private const REMIND_DAYS = 3;

    protected $signature = 'subscriptions:end-trials';

    protected $description = 'Remind owners before their free trial ends and expire ended trials until a paid plan is chosen';

    public function handle(): int
    {
        $reminded = 0;
        $ended = 0;
        $remindOn = now('Africa/Lagos')->addDays(self::REMIND_DAYS)->toDateString();

        $trials = Subscription::query()
            ->with('tenant')
            ->whereHas('tenant')
            ->where('status', 'TRIALING')
            ->whereNotNull('trialEndsAt')
            ->lazyById();

        foreach ($trials as $subscription) {
            $tenant = $subscription->tenant;

            if (! $subscription->onTrial()) {
                $this->endTrial($subscription, $tenant);
                $ended++;

                continue;
            }

            if ($subscription->trialEndsAt->copy()->timezone('Africa/Lagos')->toDateString() === $remindOn
                && $this->remind($subscription, $tenant)) {
                $reminded++;
            }
        }

        $this->info($reminded.' reminder(s) sent, '.$ended.' trial(s) expired.');

        return self::SUCCESS;
    }

    private function remind(Subscription $subscription, Tenant $tenant): bool
    {
        $endsOn = $subscription->trialEndsAt->timezone('Africa/Lagos')->format('d M Y');
        $daysRemaining = $subscription->trialDaysRemaining();
        $timeLeft = $daysRemaining === 1 ? '1 day' : $daysRemaining.' days';
        $dayStart = now('Africa/Lagos')->startOfDay()->setTimezone(config('app.timezone'));
        if (Notification::query()->where('tenantId', $tenant->id)
            ->where('type', 'SUBSCRIPTION_EXPIRING')->where('title', 'Free trial ends soon')
            ->where('createdAt', '>=', $dayStart)->exists()) {
            return false;
        }

        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => 'SUBSCRIPTION_EXPIRING',
            'title' => 'Free trial ends soon',
            'message' => 'Your free trial has '.$timeLeft.' left and ends on '.$endsOn.'. Choose a paid plan to keep advanced features.',
            'isRead' => false,
            'createdAt' => now(),
        ]);

        $owner = $this->owner($tenant);
        if (! $owner?->email) {
            return true;
        }

        AuthMail::send($owner->email, $owner->name, 'Your SalesDock free trial has '.$timeLeft.' left', [
            'preheader' => 'Choose a plan before '.$endsOn.' to keep using SalesDock.',
            'heading' => 'Your free trial ends soon',
            'kicker' => $tenant->name,
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, every SalesDock feature has been unlocked for <strong>'.e($tenant->name).'</strong> during your free trial.',
                'You have <strong>'.$timeLeft.' left</strong>. The trial ends on '.$endsOn.'. Choose a paid plan to keep advanced features. Basic free access keeps POS, basic inventory, customers and sales history available after the trial.',
            ],
            'url' => route('billing'),
            'label' => 'Select a subscription plan',
        ]);

        return true;
    }

    private function endTrial(Subscription $subscription, Tenant $tenant): void
    {
        $subscription->forceFill([
            'status' => 'EXPIRED',
            'autoRenew' => false,
            'recurringAuthorization' => null,
            'recurringAmount' => null,
            'currentPeriodEnd' => $subscription->trialEndsAt,
            'nextBillingDate' => null,
            'gracePeriodEndsAt' => null,
            'cancelAtPeriodEnd' => false,
        ])->save();

        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => 'SUBSCRIPTION_EXPIRED',
            'title' => 'Free trial ended',
            'message' => 'Your free trial has ended. Basic free access keeps you selling. Choose a paid plan for advanced features. Your data is safe.',
            'isRead' => false,
            'createdAt' => now(),
        ]);

        $owner = $this->owner($tenant);
        if ($owner?->email) {
            AuthMail::send($owner->email, $owner->name, 'Your SalesDock free trial has ended', [
                'preheader' => 'Keep selling on Basic free access, or upgrade for advanced features.',
                'heading' => 'Free trial ended',
                'kicker' => $tenant->name,
                'paragraphs' => [
                    'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, the free trial for <strong>'.e($tenant->name).'</strong> has ended.',
                    'You can keep selling with Basic free access: POS, basic inventory, customers, sales history and refunds. Choose a paid plan to restore advanced features. Your existing data is retained.',
                ],
                'url' => route('billing'),
                'label' => 'Select a subscription plan',
            ]);
        }

        $this->line('Trial expired: '.$tenant->name);
    }

    private function owner(Tenant $tenant): ?User
    {
        return User::query()
            ->where('tenantId', $tenant->id)
            ->where('isActive', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Owner'))
            ->first();
    }
}
