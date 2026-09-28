@extends('layouts.app')

@section('title', $invoice->invoiceNumber.' | SalesDock')

@php
    $amount = (float) $invoice->amount;
    $digits = abs($amount - round($amount)) < 0.001 ? 0 : 2;
    $money = '₦'.number_format($amount, $digits);
    $when = fn ($value) => $value?->timezone('Africa/Lagos')->format('d M Y') ?: '—';
    $status = $invoiceStatuses[$invoice->status] ?? $invoice->status;
    $tone = $invoice->status === 'PAID' ? 'active' : ($invoice->status === 'PENDING' ? 'pending' : 'cancelled');
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('billing') }}">Back</a>
            <strong>{{ $invoice->invoiceNumber }}</strong>
            <span class="badge {{ $tone }}">{{ $status }}</span>
        </div>

        <section class="card">
            <div class="supplier-facts">
                <div>
                    <span>Shop</span>
                    <strong>{{ $tenant->name }}</strong>
                </div>
                <div>
                    <span>Plan</span>
                    <strong>{{ $plan?->name ?: '—' }}</strong>
                </div>
                <div>
                    <span>Cycle</span>
                    <strong>{{ $cycles[$invoice->billingCycle] ?? $invoice->billingCycle }}</strong>
                </div>
                <div>
                    <span>Amount</span>
                    <strong>{{ $money }}</strong>
                </div>
                <div>
                    <span>Period</span>
                    <strong>{{ $when($invoice->periodStart) }} – {{ $when($invoice->periodEnd) }}</strong>
                </div>
                <div>
                    <span>Created</span>
                    <strong>{{ $when($invoice->createdAt) }}</strong>
                </div>
                @if ($invoice->paidAt)
                    <div>
                        <span>Paid</span>
                        <strong>{{ $when($invoice->paidAt) }}</strong>
                    </div>
                @endif
                @if ($invoice->failureReason)
                    <div class="wide">
                        <span>Note</span>
                        <strong>{{ $invoice->failureReason }}</strong>
                    </div>
                @endif
            </div>
            @if ($invoice->status === 'PENDING' && $amount > 0 && $canChange)
                <div class="invoice-actions">
                    <a class="btn" href="{{ route('billing.pay', $invoice) }}">Pay now</a>
                </div>
            @endif
        </section>
    </div>
@endsection
