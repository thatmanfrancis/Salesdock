<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\Notification;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuthMail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** @phpstan-import-type Pricing from SubscriptionPricing */
class RecurringBilling
{
    public const TERMS_VERSION = '2026-11-02.3';

    public function __construct(private PaymentManager $payments) {}

    public function canAuthorize(?User $user, string $tenantId): bool
    {
        return $user && $user->tenantId === $tenantId && $user->loadMissing('role')->role?->name === 'Owner';
    }

    public function supportsCycle(string $cycle): bool
    {
        return $this->payments->recurringEnabled()
            && ! ($this->payments->active()->name() === 'flutterwave' && $cycle === 'ANNUALLY');
    }

    private function authorizationExpired(array $authorization, string $provider): bool
    {
        if ($provider === 'flutterwave' && Carbon::parse($authorization['acquiredAt'] ?? '2000-01-01')->addYear()->lte(now())) {
            return true;
        }
        if (preg_match('/^(\d{1,2})\/(\d{2}|\d{4})$/', $authorization['expires'] ?? '', $parts)) {
            $month = (int) $parts[1];
            $year = (int) $parts[2] + (strlen($parts[2]) === 2 ? 2000 : 0);

            return $month < 1 || $month > 12 || Carbon::create($year, $month, 1)->endOfMonth()->isPast();
        }

        return false;
    }

    public static function periodEnd(Carbon $start, string $cycle, ?int $anchorDay = null): Carbon
    {
        $end = $start->copy()->addMonthsNoOverflow(match ($cycle) {
            'QUARTERLY' => 3, 'ANNUALLY' => 12, default => 1,
        });

        return $end->day(min($anchorDay ?? $start->day, $end->daysInMonth));
    }

    /** @param Pricing|null $pricing */
    public static function consentText(float $amount, string $cycle, ?array $pricing = null, bool $includesCurrentPayment = true): string
    {
        $interval = match ($cycle) {
            'QUARTERLY' => 'three months', 'ANNUALLY' => 'year', default => 'month'
        };

        $schedule = $pricing && $pricing['introRemaining'] > 0
            ? SubscriptionPricing::description($pricing, $includesCurrentPayment)
            : 'NGN '.number_format($amount, 2, '.', '').' every '.$interval.'.';

        return 'I am authorised to use this card for this business. I authorise The Town Growers Hub Ltd (SalesDock) to charge '.$schedule.' Renewals fall on my subscription renewal date until I cancel in Billing. This authorises software subscription payments only, not debits from my collection bank account. Only the disclosed discount expiry is authorised; other price or plan changes require fresh approval. No repeated automatic charge attempts are authorised. I accept the Recurring Card Payment Authorisation, version '.self::TERMS_VERSION.'.';
    }

    public function fingerprint(string $provider): string
    {
        return hash('sha256', PlatformSetting::payment()[$provider]['secret_key']);
    }

    /** @return array{available: bool, reason?: string, invoice?: BillingInvoice, pricing?: array<string, mixed>, amount?: float, token?: string} */
    public function renewalSetup(Subscription $subscription): array
    {
        if (! $this->supportsCycle($subscription->billingCycle)) {
            return ['available' => false, 'reason' => 'Automatic renewal is not available for the current payment provider or billing interval. You can still renew by card manually.'];
        }
        if ($subscription->status !== 'ACTIVE' || ! $subscription->currentPeriodEnd?->isFuture()) {
            return ['available' => false, 'reason' => 'Pay for a subscription by card first. You can enable automatic renewal during checkout or return here after payment.'];
        }
        if ($this->hasUncertainRenewal($subscription->tenantId)) {
            return ['available' => false, 'reason' => 'A renewal payment is awaiting confirmation. Wait for its result before enabling automatic renewal.'];
        }
        $subscription->loadMissing('plan');
        if (! $subscription->plan?->isActive) {
            return ['available' => false, 'reason' => 'Choose an available plan before enabling automatic renewal.'];
        }
        $provider = $this->payments->active()->name();
        $invoice = BillingInvoice::query()->where('tenantId', $subscription->tenantId)->where('subscriptionId', $subscription->id)
            ->where('status', 'PAID')->where('gateway', strtoupper($provider))->where('billingCycle', $subscription->billingCycle)
            ->where('periodStart', $subscription->currentPeriodStart)->where('periodEnd', $subscription->currentPeriodEnd)
            ->latest('paidAt')->first();
        if (! $invoice) {
            return ['available' => false, 'reason' => 'No card payment for this paid period is available with the current provider. Enable automatic renewal at your next card checkout.'];
        }
        if (($invoice->billingMetadata['paymentMethod'] ?? null) === 'TRANSFER') {
            return ['available' => false, 'reason' => 'Your previous subscription payment was a bank transfer, so there is no saved card for automatic renewal. Your paid access is unchanged. Renew by card when this paid period ends and opt in at checkout; you do not need to pay again now.'];
        }
        $pricing = SubscriptionPricing::quote($subscription->plan, $subscription->billingCycle, $subscription->tenantId);
        $amount = SubscriptionPricing::amount($pricing);
        if ($amount <= 0) {
            return ['available' => false, 'reason' => 'This plan is not available on the current billing interval. Choose another plan or interval.'];
        }
        $token = hash_hmac('sha256', json_encode([$subscription->id, $subscription->planId, $subscription->currentPeriodEnd->toISOString(),
            $subscription->autoRenew, $subscription->recurringConsent['revokedAt'] ?? null,
            $invoice->id, $invoice->gatewayRef, $pricing, $provider, $this->fingerprint($provider)], JSON_THROW_ON_ERROR), (string) config('app.key'));

        return ['available' => true, 'invoice' => $invoice, 'pricing' => $pricing, 'amount' => $amount, 'token' => $token];
    }

    public function enable(Subscription $subscription, Request $request): void
    {
        abort_unless($this->canAuthorize($request->user(), $subscription->tenantId), 403);
        $data = $request->validate(['authorizationVersion' => ['required', 'in:'.self::TERMS_VERSION],
            'acceptRenewal' => ['required', 'accepted'], 'setupToken' => ['required', 'string']]);
        $setup = $this->renewalSetup($subscription->fresh());
        if (! $setup['available'] || ! hash_equals($setup['token'], $data['setupToken'])) {
            throw ValidationException::withMessages(['autoRenew' => $setup['reason'] ?? 'Your billing details changed. Reload Billing and review the current amount and date.']);
        }
        $invoice = $setup['invoice'];
        $gateway = $this->payments->active();
        try {
            $payment = ! empty($invoice->billingMetadata['attemptReference'])
                ? $gateway->verifyByReference($invoice->billingMetadata['attemptReference'])
                : $gateway->verifyTransaction($invoice->gatewayRef);
        } catch (Throwable) {
            throw ValidationException::withMessages(['autoRenew' => 'We could not verify your previous subscription payment with the provider. Nothing was charged and your paid access is unchanged. If you paid by transfer, there is no saved card: renew by card when your paid period ends and opt in then. Otherwise, try again later or contact support.']);
        }
        if ($gateway->confirms($payment, $invoice->invoiceNumber, (float) $invoice->amount)
            && (string) ($payment['id'] ?? '') === $invoice->gatewayRef
            && abs((float) ($payment['amount'] ?? 0) - (float) $invoice->amount) < 0.009
            && ($payment['method'] ?? '') === 'TRANSFER') {
            $invoice->forceFill(['billingMetadata' => array_merge($invoice->billingMetadata ?? [], ['paymentMethod' => 'TRANSFER'])])->save();
            throw ValidationException::withMessages(['autoRenew' => 'Your previous subscription was paid by bank transfer. That payment remains valid, but it cannot provide a saved card for automatic renewal. Renew by card when your paid period ends and opt in at checkout. Nothing was charged today.']);
        }
        $authorization = $payment['recurring'] ?? [];
        $authorization['acquiredAt'] = $invoice->paidAt?->toISOString() ?? $payment['created_at'] ?? '2000-01-01';
        if (! $gateway->confirms($payment, $invoice->invoiceNumber, (float) $invoice->amount)
            || (string) ($payment['id'] ?? '') !== $invoice->gatewayRef || ($payment['method'] ?? '') !== 'CARD'
            || abs((float) ($payment['amount'] ?? 0) - (float) $invoice->amount) > 0.009
            || empty($authorization['token']) || ! filter_var($authorization['email'] ?? '', FILTER_VALIDATE_EMAIL)
            || $this->authorizationExpired($authorization, $gateway->name())) {
            throw ValidationException::withMessages(['autoRenew' => 'That payment does not provide a valid reusable card authorisation. Automatic renewal remains off. Opt in at your next card checkout.']);
        }
        DB::transaction(function () use ($subscription, $request, $setup, $gateway, $authorization, $data): void {
            $invoice = BillingInvoice::query()->lockForUpdate()->findOrFail($setup['invoice']->id);
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $current = $this->renewalSetup($locked);
            if (! $current['available'] || ! hash_equals($current['token'], $data['setupToken'])) {
                throw ValidationException::withMessages(['autoRenew' => 'Your billing details changed. Reload Billing before enabling automatic renewal.']);
            }
            $consent = ['version' => self::TERMS_VERSION, 'text' => self::consentText($setup['amount'], $locked->billingCycle, $setup['pricing'], false),
                'acceptedAt' => now()->toISOString(), 'userId' => $request->user()->id, 'ip' => $request->ip(),
                'userAgent' => mb_substr((string) $request->userAgent(), 0, 255), 'amount' => (string) $setup['amount'], 'currency' => 'NGN',
                'cycle' => $locked->billingCycle, 'provider' => $gateway->name(), 'fingerprint' => $this->fingerprint($gateway->name()),
                'pricing' => $setup['pricing'], 'invoiceId' => $setup['invoice']->id, 'billingDay' => $locked->recurringConsent['billingDay'] ?? $locked->currentPeriodStart->day,
                'adminCancellationVersion' => $locked->recurringConsent['adminCancellationVersion'] ?? null,
                'firstRenewalAt' => $locked->currentPeriodEnd->toISOString(),
                'card' => array_intersect_key($authorization, array_flip(['last4', 'brand', 'expires']))];
            $locked->forceFill(['autoRenew' => true, 'recurringProvider' => $gateway->name(), 'recurringAuthorization' => $authorization,
                'recurringAmount' => $setup['amount'], 'recurringConsent' => $consent, 'billingPricing' => $setup['pricing'],
                'recurringRemindedAt' => null, 'autoRenewCancelledAt' => null])->save();
            $metadata = $invoice->billingMetadata ?? [];
            $metadata['renewalAuthorizations'][] = $consent;
            $invoice->forceFill(['billingMetadata' => $metadata])->save();
            $this->notice($locked, 'AUTO_RENEW_ENABLED', 'Automatic renewal is enabled. Your next software payment is NGN '.number_format($setup['amount'], 2).' on '.$locked->currentPeriodEnd->timezone('Africa/Lagos')->format('d M Y').'. Nothing was charged today.');
        });
    }

    public function hasUncertainRenewal(string $tenantId): bool
    {
        return BillingInvoice::query()->where('tenantId', $tenantId)->where('status', 'PENDING')
            ->where('billingMetadata->kind', 'automatic')
            ->whereIn('billingMetadata->chargeState', ['STARTING', 'UNCERTAIN'])->exists();
    }

    public function startCheckout(BillingInvoice $invoice, Request $request): string
    {
        $data = $request->validate(['autoRenew' => ['nullable', 'boolean']]);
        $autoRenew = (bool) ($data['autoRenew'] ?? false);
        if ($autoRenew) {
            abort_unless($this->canAuthorize($request->user(), $invoice->tenantId), 403);
            if (! $this->supportsCycle($invoice->billingCycle)) {
                throw ValidationException::withMessages(['payment' => 'Automatic renewal is unavailable for this provider or interval. You can make a one-time card payment.']);
            }
            $request->validate(['authorizationVersion' => ['required', 'in:'.self::TERMS_VERSION]]);
        }

        return Cache::lock('billing-checkout-'.$invoice->id, 60)->block(3, function () use ($invoice, $request, $autoRenew): string {
            $invoice->refresh();
            if ($invoice->status !== 'PENDING' || $this->hasUncertainRenewal($invoice->tenantId)) {
                throw new RuntimeException('A payment is already complete or an automatic renewal is awaiting confirmation. Check Billing before paying again.');
            }
            $metadata = $invoice->billingMetadata ?? [];
            $currentSubscription = Subscription::query()->where('tenantId', $invoice->tenantId)->first();
            $adminCancellationVersion = $currentSubscription?->recurringConsent['adminCancellationVersion'] ?? null;
            if (isset($metadata['checkoutState']) && $adminCancellationVersion
                && ($metadata['adminCancellationVersion'] ?? null) !== $adminCancellationVersion) {
                throw new RuntimeException('This checkout predates an admin cancellation. Choose a plan in Billing to open a new checkout.');
            }
            if (($metadata['checkoutState'] ?? '') === 'READY' && ! empty($metadata['checkoutUrl'])) {
                return $metadata['checkoutUrl'];
            }
            if (in_array($metadata['checkoutState'] ?? '', ['STARTING', 'UNCERTAIN'], true)) {
                throw new RuntimeException('This checkout is awaiting confirmation. Do not pay again until its status has been checked.');
            }
            $gateway = $this->payments->active();
            $pricing = SubscriptionPricing::forInvoice($invoice);
            $subscription = Subscription::query()->where('tenantId', $invoice->tenantId)->first();
            if ($subscription?->autoRenew && ($subscription->planId !== $pricing['planId'] || $subscription->billingCycle !== $invoice->billingCycle)) {
                $this->pause($subscription, 'Automatic renewal of your previous plan is paused while you change plans. Complete the new card checkout and approve its renewal schedule to enable it again.');
            }
            $tenant = Tenant::query()->findOrFail($invoice->tenantId);
            $email = $request->user()?->tenantId === $tenant->id ? $request->user()->email : $tenant->email;
            $metadata = array_merge($metadata, [
                'kind' => 'manual', 'cardOnly' => true, 'provider' => $gateway->name(),
                'adminCancellationVersion' => $adminCancellationVersion,
                'planId' => $metadata['planId'] ?? $invoice->gatewayRef,
                'pricing' => $pricing,
                'baseSubscription' => $subscription ? [$subscription->planId, $subscription->currentPeriodStart?->toISOString(), $subscription->currentPeriodEnd?->toISOString()] : null,
                'checkoutState' => 'STARTING', 'attemptReference' => $gateway->attemptReference($invoice->invoiceNumber),
                'autoRenewRequested' => $autoRenew,
                'consent' => $autoRenew ? [
                    'version' => self::TERMS_VERSION, 'text' => self::consentText((float) $invoice->amount, $invoice->billingCycle, $pricing),
                    'pricing' => $pricing,
                    'acceptedAt' => now()->toISOString(), 'userId' => $request->user()->id,
                    'ip' => $request->ip(), 'userAgent' => mb_substr((string) $request->userAgent(), 0, 255),
                    'amount' => (string) $invoice->amount, 'currency' => 'NGN', 'cycle' => $invoice->billingCycle,
                    'provider' => $gateway->name(), 'fingerprint' => $this->fingerprint($gateway->name()),
                    'invoiceId' => $invoice->id,
                    'adminCancellationVersion' => $adminCancellationVersion,
                    'billingDay' => ($invoice->periodStart ?? now())->day,
                ] : null,
            ]);
            $invoice->forceFill(['billingMetadata' => $metadata, 'gateway' => strtoupper($gateway->name())])->save();
            try {
                $link = $gateway->checkoutLink([
                    'reference' => $invoice->invoiceNumber, 'attemptReference' => $metadata['attemptReference'],
                    'amount' => (float) $invoice->amount, 'email' => (string) $email, 'name' => $tenant->name,
                    'redirect' => route('billing.verify', ['ref' => $invoice->invoiceNumber]),
                    'title' => 'SalesDock', 'description' => 'Software subscription '.$invoice->invoiceNumber, 'cardOnly' => true,
                ]);
            } catch (Throwable $exception) {
                $invoice->forceFill(['billingMetadata' => array_merge($metadata, ['checkoutState' => 'UNCERTAIN'])])->save();
                throw new RuntimeException('We could not open the card checkout. The checkout status must be checked before another attempt.', previous: $exception);
            }
            $invoice->refresh();
            $invoice->forceFill(['billingMetadata' => array_merge($invoice->billingMetadata ?? [], ['checkoutState' => 'READY', 'checkoutUrl' => $link])])->save();

            return $link;
        });
    }

    /** @param array<string, mixed> $payment */
    public function recordPayment(BillingInvoice $invoice, Subscription $subscription, array $payment): void
    {
        $metadata = $invoice->billingMetadata ?? [];
        $metadata['chargeState'] = 'PAID';
        if (($metadata['kind'] ?? '') === 'automatic') {
            $pricing = SubscriptionPricing::afterPayment(SubscriptionPricing::forInvoice($invoice));
            $subscription->forceFill(['recurringRemindedAt' => null, 'billingPricing' => $pricing,
                'recurringAmount' => SubscriptionPricing::amount($pricing)])->save();
            $invoice->forceFill(['billingMetadata' => $metadata])->save();
            $this->sendRenewalReceipt($invoice, $subscription);

            return;
        }
        $consent = $metadata['consent'] ?? null;
        $pricing = SubscriptionPricing::afterPayment(SubscriptionPricing::forInvoice($invoice));
        $authorization = $payment['recurring'] ?? null;
        $revokedAt = $subscription->recurringConsent['revokedAt'] ?? $subscription->autoRenewCancelledAt;
        $cancelled = $revokedAt && $consent && Carbon::parse($consent['acceptedAt'])->lte(Carbon::parse($revokedAt));
        $eligible = ($metadata['autoRenewRequested'] ?? false) && $consent && ! $cancelled
            && $this->supportsCycle($invoice->billingCycle)
            && $this->payments->active()->name() === ($metadata['provider'] ?? null)
            && hash_equals($consent['fingerprint'], $this->fingerprint($metadata['provider']))
            && ($payment['method'] ?? null) === 'CARD'
            && ! empty($authorization['token']) && filter_var($authorization['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $subscription->forceFill([
            'autoRenew' => (bool) $eligible,
            'recurringProvider' => $eligible ? $metadata['provider'] : null,
            'recurringAuthorization' => $eligible ? array_merge($authorization, ['acquiredAt' => now()->toISOString()]) : null,
            'recurringConsent' => $consent ?? $subscription->recurringConsent,
            'recurringAmount' => $eligible ? SubscriptionPricing::amount($pricing) : null,
            'billingPricing' => $pricing,
            'recurringRemindedAt' => null,
        ])->save();
        if ($eligible) {
            $metadata['consent']['card'] = array_intersect_key($authorization, array_flip(['last4', 'brand', 'expires']));
            $subscription->forceFill(['recurringConsent' => $metadata['consent']])->save();
            $this->notice($subscription, 'AUTO_RENEW_ENABLED', 'Automatic renewal is enabled. Your next software payment is due '.$subscription->currentPeriodEnd->timezone('Africa/Lagos')->format('d M Y').'. You can cancel in Billing.');
        } elseif ($metadata['autoRenewRequested'] ?? false) {
            $this->notice($subscription, 'AUTO_RENEW_UNAVAILABLE', 'Your software payment succeeded, but automatic renewal could not be enabled. Renew manually from Billing.');
        }
        $invoice->forceFill(['billingMetadata' => $metadata])->save();
    }

    public function cancel(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription): void {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $consent = $subscription->recurringConsent ?? [];
            $consent['revokedAt'] = now()->toISOString();
            $subscription->forceFill(['autoRenew' => false, 'recurringAuthorization' => null,
                'recurringConsent' => $consent, 'autoRenewCancelledAt' => now()])->save();
            $this->notice($subscription, 'AUTO_RENEW_CANCELLED', 'Automatic renewal is off. Your paid access remains until the current period ends. A payment already submitted to the provider may still complete.');
        });
    }

    public function process(Subscription $subscription): void
    {
        Cache::lock('subscription-renewal-'.$subscription->id, 90)->get(function () use ($subscription): void {
            $subscription->refresh()->loadMissing('plan', 'tenant');
            if (! $subscription->autoRenew || $subscription->currentPeriodEnd->isFuture()) {
                return;
            }
            $reference = 'REN-'.substr(hash('sha256', $subscription->id.'|'.$subscription->currentPeriodEnd->toISOString()), 0, 32);
            $existing = BillingInvoice::query()->where('invoiceNumber', $reference)->first();
            if ($existing && in_array($existing->billingMetadata['chargeState'] ?? '', ['STARTING', 'UNCERTAIN'], true)) {
                $this->reconcile($existing);

                return;
            }
            $authorization = $subscription->recurringAuthorization;
            $price = (float) match ($subscription->billingCycle) {
                'QUARTERLY' => $subscription->plan?->quarterlyPrice, 'ANNUALLY' => $subscription->plan?->annualPrice,
                default => $subscription->plan?->monthlyPrice,
            };
            if (! $subscription->tenant?->isActive || ! in_array($subscription->status, ['ACTIVE', 'PAST_DUE'], true)
                || ! $this->payments->recurringEnabled() || $subscription->recurringProvider !== $this->payments->active()->name()
                || $subscription->currentPeriodEnd->copy()->addDays(7)->isPast()
                || $price <= 0 || abs($price - (float) ($subscription->billingPricing['regularAmount'] ?? $subscription->recurringAmount)) > 0.009
                || ! SubscriptionPricing::matchesAuthorisation($subscription)
                || ($subscription->billingPricing && ($subscription->billingPricing['planId'] !== $subscription->planId
                    || $subscription->billingPricing['cycle'] !== $subscription->billingCycle
                    || abs(SubscriptionPricing::amount($subscription->billingPricing) - (float) $subscription->recurringAmount) > 0.009))
                || empty($authorization['token']) || empty($authorization['email'])
                || ! hash_equals($subscription->recurringConsent['fingerprint'] ?? '', $this->fingerprint($subscription->recurringProvider))
                || $this->authorizationExpired($authorization, $subscription->recurringProvider)) {
                $this->pause($subscription, 'Automatic renewal needs a fresh card authorisation or updated billing settings. Please renew manually in Billing.');

                return;
            }
            if ($existing) {
                return;
            }
            $invoice = DB::transaction(function () use ($subscription, $reference): BillingInvoice {
                $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
                if (! $locked->autoRenew) {
                    throw new RuntimeException('Automatic renewal was cancelled.');
                }

                return BillingInvoice::query()->firstOrCreate(['invoiceNumber' => $reference], [
                    'tenantId' => $locked->tenantId, 'subscriptionId' => $locked->id, 'status' => 'PENDING',
                    'billingCycle' => $locked->billingCycle, 'amount' => $locked->recurringAmount, 'currency' => 'NGN',
                    'gateway' => strtoupper($locked->recurringProvider), 'gatewayRef' => $locked->planId,
                    'periodStart' => $locked->currentPeriodEnd,
                    'periodEnd' => self::periodEnd($locked->currentPeriodEnd, $locked->billingCycle, $locked->recurringConsent['billingDay'] ?? null),
                    'dueDate' => $locked->currentPeriodEnd,
                    'billingMetadata' => ['kind' => 'automatic', 'cardOnly' => true, 'provider' => $locked->recurringProvider,
                        'adminCancellationVersion' => $locked->recurringConsent['adminCancellationVersion'] ?? null,
                        'pricing' => $locked->billingPricing ?? SubscriptionPricing::forInvoice(new BillingInvoice(['amount' => $locked->recurringAmount, 'billingCycle' => $locked->billingCycle, 'gatewayRef' => $locked->planId])),
                        'planId' => $locked->planId, 'chargeState' => 'STARTING', 'consent' => $locked->recurringConsent],
                ]);
            });
            if (! $invoice->wasRecentlyCreated) {
                $this->reconcile($invoice);

                return;
            }
            if (! $subscription->fresh()->autoRenew) {
                $invoice->forceFill(['status' => 'FAILED', 'failureReason' => 'Authorisation withdrawn before submission', 'failedAt' => now()])->save();

                return;
            }
            try {
                $this->payments->driver($subscription->recurringProvider)->chargeAuthorization([
                    'reference' => $invoice->invoiceNumber, 'amount' => (float) $invoice->amount,
                    'email' => $authorization['email'], 'token' => $authorization['token'],
                ]);
            } catch (Throwable $exception) {
                Log::warning('Automatic renewal submission requires reconciliation', ['invoice' => $invoice->id, 'provider' => $subscription->recurringProvider, 'status' => $exception->getCode()]);
            }
            $this->reconcile($invoice);
        });
    }

    public function reconcile(BillingInvoice $invoice): void
    {
        $invoice->refresh();
        if ($invoice->status === 'PAID') {
            return;
        }
        $metadata = $invoice->billingMetadata ?? [];
        try {
            $gateway = $this->payments->driver($metadata['provider']);
            $payment = $gateway->verifyByReference($invoice->invoiceNumber);
            if ($gateway->successful($payment['status'] ?? null)) {
                if (app(BillingService::class)->settlePayment($payment, $gateway, app(SaleRecorder::class)) === 'invoice') {
                    return;
                }
            } elseif (in_array(strtolower($payment['status'] ?? ''), ['failed', 'abandoned', 'cancelled', 'canceled'], true)) {
                $invoice->forceFill(['status' => 'FAILED', 'failedAt' => now(), 'failureReason' => 'Automatic card renewal failed',
                    'billingMetadata' => array_merge($metadata, ['chargeState' => 'FAILED'])])->save();
                $subscription = Subscription::query()->where('tenantId', $invoice->tenantId)->first();
                if ($subscription) {
                    $this->pause($subscription, 'Your automatic renewal failed. No further automatic attempts will be made. Renew manually in Billing.');
                }

                return;
            }
        } catch (Throwable $exception) {
            Log::warning('Automatic renewal verification pending', ['invoice' => $invoice->id, 'status' => $exception->getCode()]);
        }
        $invoice->refresh();
        if ($invoice->status !== 'PAID') {
            $alreadyUncertain = ($invoice->billingMetadata['chargeState'] ?? '') === 'UNCERTAIN';
            $invoice->forceFill(['billingMetadata' => array_merge($invoice->billingMetadata ?? [], ['chargeState' => 'UNCERTAIN'])])->save();
            $subscription = Subscription::query()->where('tenantId', $invoice->tenantId)->first();
            if (! $alreadyUncertain && $subscription) {
                $this->notice($subscription, 'RENEWAL_CONFIRMATION_PENDING', 'An automatic renewal is awaiting provider confirmation. Do not pay again until it is confirmed. Contact support if this remains pending.');
            }
        }
    }

    public function sendReminder(Subscription $subscription): void
    {
        if ($subscription->recurringRemindedAt || ! $subscription->autoRenew || ! $subscription->currentPeriodEnd->isFuture()) {
            return;
        }
        $claimed = Subscription::query()->whereKey($subscription->id)->whereNull('recurringRemindedAt')->update(['recurringRemindedAt' => now()]);
        if ($claimed) {
            $this->notice($subscription, 'AUTO_RENEW_REMINDER', 'Your software subscription will renew for NGN '.number_format((float) $subscription->recurringAmount, 2).' on '.$subscription->currentPeriodEnd->timezone('Africa/Lagos')->format('d M Y').'. To stop future automatic charges, turn off automatic renewal in Billing before renewal.');
        }
    }

    private function sendRenewalReceipt(BillingInvoice $invoice, Subscription $subscription): void
    {
        $owner = User::query()->where('tenantId', $subscription->tenantId)
            ->whereHas('role', fn ($query) => $query->where('name', 'Owner'))->first();
        if (! $owner) {
            return;
        }
        $summary = [
            'Invoice' => $invoice->invoiceNumber,
            'Amount paid' => 'NGN '.number_format((float) $invoice->amount, 2),
            'Plan' => $subscription->plan?->name ?? 'SalesDock subscription',
            'Billing interval' => ucfirst(strtolower($invoice->billingCycle)),
            'Paid period' => $invoice->periodStart->timezone('Africa/Lagos')->format('d M Y').' – '.$invoice->periodEnd->timezone('Africa/Lagos')->format('d M Y'),
            'Payment provider' => ucfirst(strtolower($invoice->gateway)),
        ];
        if ($subscription->autoRenew) {
            $summary['Next renewal'] = 'NGN '.number_format((float) $subscription->recurringAmount, 2).' on '.$subscription->currentPeriodEnd->timezone('Africa/Lagos')->format('d M Y');
        } else {
            $summary['Automatic renewal'] = 'Off. No further automatic charges are scheduled.';
        }
        DB::afterCommit(function () use ($owner, $invoice, $summary): void {
            try {
                AuthMail::send($owner->email, $owner->name, 'SalesDock renewal payment received', [
                    'heading' => 'Your subscription has renewed',
                    'paragraphs' => ['We received your automatic card payment. Your SalesDock subscription is active for the paid period below.'],
                    'summaryTitle' => 'Payment receipt', 'summary' => $summary,
                    'url' => route('billing.invoice', $invoice), 'label' => 'View invoice',
                    'note' => 'Manage or turn off future automatic renewals in Billing.',
                ]);
            } catch (Throwable $exception) {
                Log::error('Renewal receipt could not be sent', ['invoice' => $invoice->id, 'status' => $exception->getCode()]);
            }
        });
    }

    private function pause(Subscription $subscription, string $message): void
    {
        $subscription->forceFill(['autoRenew' => false, 'recurringAuthorization' => null])->save();
        $this->notice($subscription, 'AUTO_RENEW_PAUSED', $message);
    }

    private function notice(Subscription $subscription, string $type, string $message): void
    {
        Notification::query()->create(['tenantId' => $subscription->tenantId, 'type' => 'SYSTEM', 'title' => Str::headline($type), 'message' => mb_substr($message, 0, 255), 'entityId' => $subscription->id, 'createdAt' => now()]);
        $owner = User::query()->where('tenantId', $subscription->tenantId)->whereHas('role', fn ($query) => $query->where('name', 'Owner'))->first();
        if ($owner) {
            DB::afterCommit(fn () => AuthMail::send($owner->email, $owner->name, 'SalesDock subscription update', ['heading' => 'Subscription update',
                'paragraphs' => [e($message)], 'url' => route('billing'), 'label' => 'Manage billing']));
        }
    }
}
