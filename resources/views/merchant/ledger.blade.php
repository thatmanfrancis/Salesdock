@extends('layouts.app')

@section('title', 'Ledger #'.$ref.' | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $channelLabel = [
        'POS' => 'POS',
        'ONLINE' => 'Online',
        'WHATSAPP' => 'WhatsApp',
        'INSTAGRAM' => 'Instagram',
        'LINK' => 'Link',
    ];
    $payment = trim((string) $entry->paymentMethod);
    $payment = $payment === '' ? '—' : ucwords(strtolower(str_replace('_', ' ', $payment)));
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('financials') }}">Back</a>
            <strong>Entry #{{ $ref }}</strong>
        </div>

        <div class="order-grid">
            <div class="order-main">
                <section class="card orders">
                    <p class="kicker band">Line items</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="num">Qty</th>
                                <th class="num">Selling price</th>
                                <th class="num">Cost</th>
                                <th class="num">VAT</th>
                                <th class="num">Line total</th>
                                <th class="num">Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entry->items as $item)
                                <tr>
                                    <td>{{ $item->productName }}</td>
                                    <td class="num">{{ $item->quantity }}</td>
                                    <td class="num">{{ $money($item->sellingPrice) }}</td>
                                    <td class="num">{{ $money($item->costPrice) }}</td>
                                    <td class="num vat">{{ $money($item->vatAmount) }}</td>
                                    <td class="num">{{ $money($item->lineTotal) }}</td>
                                    <td class="num profit">{{ $money($item->grossProfit) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="empty" colspan="7">No lines on this entry.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="order-side">
                <section class="card">
                    <p class="kicker">Order info</p>
                    <div class="info-row"><span>Order ref</span><strong>{{ $ref }}</strong></div>
                    <div class="info-row"><span>Channel</span><strong>{{ $channelLabel[$entry->channel] ?? ($entry->channel ?: '—') }}</strong></div>
                    <div class="info-row"><span>Payment</span><strong>{{ $payment }}</strong></div>
                    <div class="info-row"><span>Cashier</span><strong>{{ $entry->cashier?->name ?: '—' }}</strong></div>
                    <div class="info-row"><span>Customer</span><strong>{{ $entry->customerName ?: '—' }}</strong></div>
                    <div class="info-row"><span>Date</span><strong>{{ $entry->createdAt?->format('d M Y, H:i') ?: '—' }}</strong></div>
                </section>

                <section class="card">
                    <p class="kicker">Financials</p>
                    <div class="info-row"><span>Gross revenue</span><strong>{{ $money($entry->grossRevenue) }}</strong></div>
                    <div class="info-row"><span>Net revenue</span><strong class="net">{{ $money($entry->netRevenue) }}</strong></div>
                    <div class="info-row"><span>Total VAT</span><strong class="vat">{{ $money($entry->totalVat) }}</strong></div>
                    <div class="info-row"><span>Total COGS</span><strong>{{ $money($entry->totalCogs) }}</strong></div>
                    <div class="info-row grand"><span>Gross profit</span><strong class="profit">{{ $money($entry->grossProfit) }}</strong></div>
                </section>
            </aside>
        </div>
    </div>
@endsection
