<?php

namespace App\Contracts;

use Illuminate\Http\Request;

/**
 * Hosted checkout + collection subaccounts for storefront payouts.
 * Implementations: Flutterwave and Paystack. Only one is active at a time.
 */
interface PaymentGateway
{
    public function name(): string;

    public function label(): string;

    public function ready(): bool;

    /**
     * Column on storefront_config that holds this gateway's subaccount id.
     */
    public function subaccountColumn(): string;

    /**
     * Nigerian banks for payout onboarding: code => name.
     *
     * @return array<string, string>
     */
    public function banks(): array;

    public function resolveAccount(string $bankCode, string $accountNumber): string;

    /**
     * @param  array{bankCode: string, accountNumber: string, businessName: string, email: string, mobile: string, contactName?: ?string}  $shop
     */
    public function createSubaccount(array $shop): string;

    /**
     * @param  array{bankCode: string, accountNumber: string, businessName: string, email: string, mobile: string}  $shop
     */
    public function updateSubaccount(string $subaccountId, array $shop): void;

    /**
     * @param  array{reference: string, amount: float, email: string, name?: ?string, phone?: ?string, redirect: string, title: string, description: string, subaccountId?: ?string}  $payment
     */
    public function checkoutLink(array $payment): string;

    /**
     * Normalized payment: id, tx_ref, amount (NGN), currency, status, created_at, gateway.
     *
     * @return array{id: string, tx_ref: string, amount: float, currency: string, status: string, created_at: ?string, gateway: string}
     */
    public function verifyTransaction(string $transactionId): array;

    /**
     * @return array{id: string, tx_ref: string, amount: float, currency: string, status: string, created_at: ?string, gateway: string}
     */
    public function verifyByReference(string $txRef): array;

    public function successful(?string $status): bool;

    public function confirms(array $payment, string $reference, float $expectedAmount): bool;

    public function webhookTrusted(Request $request): bool;

    public function attemptReference(string $reference): string;

    public function baseReference(string $txRef): string;
}
