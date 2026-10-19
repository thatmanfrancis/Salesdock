<?php

namespace App\Services;

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

    /**
     * Saves bank details on the storefront config and creates or updates the active gateway subaccount.
     *
     * @param  array{bankCode: string, bankAccount: string, accountName?: ?string, bankName?: ?string}  $details
     */
    public function sync(Tenant $tenant, StorefrontConfig $store, array $details): StorefrontConfig
    {
        $gateway = $this->payments->active();
        $bankCode = trim((string) $details['bankCode']);
        $accountNumber = preg_replace('/\D+/', '', (string) $details['bankAccount']) ?? '';
        if (strlen($accountNumber) < 10) {
            throw new RuntimeException('Enter a valid 10-digit Nigerian account number.');
        }

        $banks = $gateway->banks();
        $bankName = $banks[$bankCode] ?? trim((string) ($details['bankName'] ?? ''));
        if ($bankCode === '' || $bankName === '') {
            throw new RuntimeException('Choose a bank from the list.');
        }

        $accountName = trim((string) ($details['accountName'] ?? ''));
        if ($gateway->ready()) {
            try {
                $resolved = $gateway->resolveAccount($bankCode, $accountNumber);
                $accountName = $resolved;
            } catch (Throwable $e) {
                if ($accountName === '') {
                    throw $e;
                }
                Log::notice($gateway->label().' account resolve skipped; using typed name', ['tenant' => $tenant->id, 'error' => $e->getMessage()]);
            }
        }

        if ($accountName === '') {
            throw new RuntimeException('Enter the account name as it appears on the bank account.');
        }

        $store->fill([
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
