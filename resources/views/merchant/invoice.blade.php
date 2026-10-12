@extends('layouts.app')

@section('title', $invoice->invoiceNumber.' | SalesDock')

@php
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

        <div class="invoice-actions">
            <a class="btn" href="{{ route('billing.invoice.document', ['invoice' => $invoice, 'print' => 1]) }}" target="_blank" rel="noopener">Download PDF</a>
            @if ($invoice->status === 'PENDING' && (float) $invoice->amount > 0 && $canChange)
                <a class="btn ghost" href="{{ route('billing.pay', $invoice) }}">Pay now</a>
            @endif
        </div>

        @include('merchant.partials.invoice-sheet')
    </div>
@endsection
