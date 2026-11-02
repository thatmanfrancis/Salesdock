<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\StorefrontConfig;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Keeps a shop's storefront bank details in sync with the active payment gateway's
 * collection subaccount so online card payments settle to their account (minus the platform split).
 */
class StorePayouts
{
    public function __construct(private PaymentManager $payments) {}

    public function resolveAccount(string $bankCode, string $accountNumber): string
    {
        $gateway = $this->payments->active();
        if (! preg_match('/^[0-9]{10}$/', $accountNumber)) {
            throw new RuntimeException('Enter a valid 10-digit Nigerian account number.');
        }
        if (! array_key_exists($bankCode, $gateway->banks())) {
            throw new RuntimeException('Choose a bank from the list.');
        }
        if (! $gateway->ready()) {
            throw new RuntimeException('Account verification is unavailable until SalesDock configures the payment provider.', 503);
        }

        try {
            return $gateway->resolveAccount($bankCode, $accountNumber);
        } catch (RuntimeException $exception) {
            if (in_array($exception->getCode(), [401, 403], true)) {
                throw new RuntimeException('The payment provider rejected SalesDock’s API credentials or account-verification permissions. Ask SalesDock support to check the provider settings.', 503, $exception);
            }
            if ($exception->getCode() >= 500 || $exception->getCode() === 429) {
                throw new RuntimeException('Bank verification is temporarily unavailable. Please try again shortly. '.$exception->getMessage(), 503, $exception);
            }
            $configuration = PlatformSetting::payment();
            $secret = $configuration[$gateway->name()]['secret_key'];
            if (str_contains($secret, '_TEST') || str_starts_with($secret, 'sk_test_')) {
                throw new RuntimeException('The payment provider is in test mode. Use a provider test bank account, or ask SalesDock to enable live payments before connecting a real account.', previous: $exception);
            }

            throw new RuntimeException('We could not verify this bank account. Check the selected bank and account number, then try again. '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Saves bank details on the storefront config and creates or updates the active gateway subaccount.
     *
     * @param  array{bankCode: string, bankAccount: string, accountName?: ?string, bankName?: ?string}  $details
     */
    public function sync(Tenant $tenant, StorefrontConfig $store, array $details): StorefrontConfig
    {
        $gateway = $this->payments->active();
        $bankCode = trim((string) $details['bankCode']);
        $accountNumber = trim((string) $details['bankAccount']);
        if (! preg_match('/^[0-9]{10}$/', $accountNumber)) {
            throw new RuntimeException('Enter a valid 10-digit Nigerian account number.');
        }

        $banks = $gateway->banks();
        $bankName = $banks[$bankCode] ?? '';
        if ($bankCode === '' || $bankName === '') {
            throw new RuntimeException('Choose a bank from the list.');
        }

        $accountName = $this->resolveAccount($bankCode, $accountNumber);

        $store->fill([
            'storeName' => $store->storeName ?: $tenant->name,
            'bankCode' => $bankCode,
            'bankName' => $bankName,
            'bankAccount' => $accountNumber,
            'accountName' => $accountName,
            'updatedAt' => now(),
        ]);

        if ($gateway->ready()) {
            $payload = [
                'bankCode' => $bankCode,
                'accountNumber' => $accountNumber,
                'businessName' => $store->storeName ?: $tenant->name,
                'email' => (string) ($tenant->email ?: 'shops@salesdock.ng'),
                'mobile' => preg_replace('/\D+/', '', (string) ($tenant->phone ?: '08000000000')) ?: '08000000000',
                'contactName' => $accountName,
            ];

            $column = $gateway->subaccountColumn();

            try {
                if ($store->{$column}) {
                    $gateway->updateSubaccount($store->{$column}, $payload);
                } else {
                    $store->{$column} = $gateway->createSubaccount($payload);
                }
            } catch (Throwable $e) {
                Log::warning($gateway->label().' subaccount sync failed', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
                throw new RuntimeException('Bank details were checked, but we could not set up payouts yet: '.$e->getMessage());
            }
        }

        $store->save();

        return $store;
    }
}
