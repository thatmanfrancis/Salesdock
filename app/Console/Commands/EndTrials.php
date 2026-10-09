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
            ->get();

        foreach ($trials as $subscription) {
            $tenant = $subscription->tenant;

            if ($subscription->trialEndsAt->isPast()) {
                $this->endTrial($subscription, $tenant);
                $ended++;

                continue;
            }

            if ($subscription->trialEndsAt->copy()->timezone('Africa/Lagos')->toDateString() === $remindOn) {
                $this->remind($subscription, $tenant);
                $reminded++;
            }
        }

        $this->info($reminded.' reminder(s) sent, '.$ended.' trial(s) expired.');

        return self::SUCCESS;
    }

    private function remind(Subscription $subscription, Tenant $tenant): void
    {
        $endsOn = $subscription->trialEndsAt->timezone('Africa/Lagos')->format('d M Y');

        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => 'SUBSCRIPTION_EXPIRING',
            'title' => 'Free trial ends soon',
            'message' => 'Your free trial ends on '.$endsOn.'. Choose a plan to keep using SalesDock.',
            'isRead' => false,
            'createdAt' => now(),
        ]);

        $owner = $this->owner($tenant);
        if (! $owner?->email) {
            return;
        }

        AuthMail::send($owner->email, $owner->name, 'Your SalesDock free trial ends in '.self::REMIND_DAYS.' days', [
            'preheader' => 'Choose a plan before '.$endsOn.' to keep using SalesDock.',
            'heading' => 'Your free trial ends soon',
            'kicker' => $tenant->name,
            'paragraphs' => [
                'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, every SalesDock feature has been unlocked for <strong>'.e($tenant->name).'</strong> during your free trial.',
                'The trial ends on <strong>'.$endsOn.'</strong>. Choose a plan before then to keep selling without interruption. When the trial ends, the dashboard stays locked until a plan is paid for. Your data stays safe.',
            ],
            'url' => route('billing'),
            'label' => 'Choose a plan',
        ]);
    }

    private function endTrial(Subscription $subscription, Tenant $tenant): void
    {
        $subscription->forceFill([
            'status' => 'EXPIRED',
            'currentPeriodEnd' => $subscription->trialEndsAt,
            'nextBillingDate' => null,
            'gracePeriodEndsAt' => null,
            'cancelAtPeriodEnd' => false,
        ])->save();

        Notification::query()->create([
            'tenantId' => $tenant->id,
            'type' => 'SUBSCRIPTION_EXPIRED',
            'title' => 'Free trial ended',
            'message' => 'Your free trial has ended. Choose a plan to unlock your dashboard again. Your data is safe.',
            'isRead' => false,
            'createdAt' => now(),
        ]);

        $owner = $this->owner($tenant);
        if ($owner?->email) {
            AuthMail::send($owner->email, $owner->name, 'Your SalesDock free trial has ended', [
                'preheader' => 'Choose a plan to keep using SalesDock.',
                'heading' => 'Free trial ended',
                'kicker' => $tenant->name,
                'paragraphs' => [
                    'Hi <strong style="color:#111827;">'.e($owner->name).'</strong>, the free trial for <strong>'.e($tenant->name).'</strong> has ended.',
                    'Choose a plan to unlock the POS, inventory and the rest of your dashboard again. Your products, sales and customers are all kept safe in the meantime.',
                ],
                'url' => route('billing'),
                'label' => 'Choose a plan',
            ]);
        }

        $this->line('Trial expired: '.$tenant->name);
    }

    private function owner(Tenant $tenant): ?User
    {
        return User::query()
            ->where('tenantId', $tenant->id)
            ->whereHas('role', fn ($query) => $query->where('name', 'Owner'))
            ->first();
    }
}
