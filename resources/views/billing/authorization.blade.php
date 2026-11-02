@extends('layouts.app')
@section('title', 'Card authorisation record | SalesDock')
@section('content')
    <article class="page">
        <section class="card">
            <h1>Recurring card payment authorisation</h1>
            <p>SalesDock · The Town Growers Hub Ltd</p>
            <p>Record version {{ $consent['version'] }} · Invoice {{ $invoice->invoiceNumber }}</p>
            <dl>
                <dt>Business</dt><dd>{{ $invoice->tenant?->name }}</dd>
                <dt>Authorised amount</dt><dd>NGN {{ number_format((float) $consent['amount'], 2) }}</dd>
                <dt>Billing cycle</dt><dd>{{ $consent['cycle'] }}</dd>
                <dt>Provider</dt><dd>{{ ucfirst($consent['provider']) }}</dd>
                <dt>Electronic acceptance</dt><dd>{{ \Illuminate\Support\Carbon::parse($consent['acceptedAt'])->timezone('Africa/Lagos')->format('d M Y H:i:s T') }}</dd>
                <dt>Authorising user reference</dt><dd>{{ $consent['userId'] }}</dd>
                <dt>Card</dt><dd>{{ $consent['card']['brand'] ?? 'Card' }} ending {{ $consent['card']['last4'] ?? 'not available' }}</dd>
            </dl>
            <h2>Accepted authorisation text</h2>
            @if (! empty($consent['pricing']))
                <p>Agreed payment schedule: {{ \App\Services\SubscriptionPricing::description($consent['pricing']) }}</p>
            @endif
            <p>{{ $consent['text'] }}</p>
            <p>This record documents electronic acceptance; automatic renewal is enabled only after the payment provider confirms a reusable card authorisation. It is not a direct-debit mandate for the merchant's collection bank account.</p>
            <p>Current automatic renewal status: {{ $subscription?->autoRenew ? 'Enabled' : 'Off' }}.</p>
            @if ($subscription?->autoRenewCancelledAt)
                <p>Automatic renewal was cancelled on {{ $subscription->autoRenewCancelledAt->timezone('Africa/Lagos')->format('d M Y H:i:s T') }}.</p>
            @endif
            <p><a href="{{ route('recurring.terms') }}">Current recurring card payment terms</a> · <a href="{{ route('billing') }}">Back to Billing</a></p>
            <button type="button" onclick="window.print()">Print or save as PDF</button>
        </section>
    </article>
@endsection
