@extends('layouts.app')

@section('title', 'Order #'.$ref.' | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('orders') }}">Back</a>
            <strong>Order #{{ $ref }}</strong>
            <span class="badge {{ strtolower($order->status) }}">{{ $order->status }}</span>
        </div>

        <div class="order-grid">
            <div class="order-main">
                <section class="card orders">
                    <p class="kicker band">Items</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="num">Qty</th>
                                <th class="num">Unit price</th>
                                <th class="num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>{{ $names[$item->productId] ?? '—' }}</td>
                                    <td class="num">{{ $item->quantity }}</td>
                                    <td class="num">{{ $money($item->unitPrice) }}</td>
                                    <td class="num">{{ $money($item->lineTotal) }}</td>
                                </tr>
                            @empty
                                <tr><td class="empty" colspan="4">No items on this order.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="totals">
                        <div><span>Subtotal</span><span>{{ $money($order->netAmount) }}</span></div>
                        <div><span>VAT</span><span>{{ $money($order->taxAmount) }}</span></div>
                        @if ((float) $order->discountAmount > 0)
                            <div class="off"><span>Discount</span><span>−{{ $money($order->discountAmount) }}</span></div>
                        @endif
                        <div class="grand"><span>Total</span><span>{{ $money($order->totalAmount) }}</span></div>
                    </div>
                </section>

                @if ($transactions->isNotEmpty())
                    <section class="card orders">
                        <p class="kicker band">Transactions</p>
                        @foreach ($transactions as $txn)
                            <div class="txn">
                                <span>{{ ucfirst(strtolower(str_replace('_', ' ', $txn->method))) }} via {{ ucfirst(strtolower(str_replace('_', ' ', $txn->gateway))) }}</span>
                                <strong>{{ $money($txn->amount) }}</strong>
                                <span class="badge {{ strtolower($txn->status) }}">{{ $txn->status }}</span>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if ($open)
                    <section class="card">
                        <p class="kicker">Update status</p>
                        <form method="post" action="{{ route('orders.status', $order) }}">
                            @csrf
                            <x-ui.select name="status" label="Status" :value="old('status', '')" :options="$choices" />
                            <label>Reason <input name="reason" value="{{ old('reason') }}" maxlength="500"></label>
                            <button type="submit">Update</button>
                        </form>
                        <form method="post" action="{{ route('orders.status', $order) }}">
                            @csrf
                            <input type="hidden" name="status" value="CANCELLED">
                            <button class="quiet" type="submit">Cancel order</button>
                        </form>
                    </section>
                @endif

                @if ($shop && $order->status === 'COMPLETED')
                    <section class="card void-card">
                        <p class="kicker">Void invoice</p>
                        <p class="muted">Reverses this completed sale. Stock goes back and the sale leaves the books. This cannot be undone.</p>
                        <form method="post" action="{{ route('orders.void', $order) }}">
                            @csrf
                            <label>Reason <input name="reason" value="{{ old('reason') }}" maxlength="500" required placeholder="Reason for voiding"></label>
                            @if ($needsPin)
                                <label>Supervisor PIN <input name="pin" inputmode="numeric" autocomplete="off" required></label>
                            @endif
                            <button class="danger" type="submit">Void this invoice</button>
                        </form>
                    </section>
                @endif

                @if ($canRequestRefund)
                    <section class="card">
                        <p class="kicker">Request refund</p>
                        <form method="post" action="{{ route('refunds.store') }}" data-busy>
                            @csrf
                            <input type="hidden" name="orderId" value="{{ $order->id }}">
                            <label>Amount <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', $order->netAmount) }}" required></label>
                            <label>Reason <input name="reason" value="{{ old('reason') }}" required></label>
                            <label>Notes <textarea name="notes">{{ old('notes') }}</textarea></label>
                            <button type="submit" data-loading="Submitting…"><span data-label>Request refund</span></button>
                        </form>
                    </section>
                @endif
            </div>

            <aside class="order-side">
                <section class="card">
                    <p class="kicker">Order info</p>
                    <div class="info-row"><span>Channel</span><strong>{{ $order->channel }}</strong></div>
                    <div class="info-row"><span>Date</span><strong>{{ $order->createdAt?->format('d M Y, H:i') }}</strong></div>
                    <div class="info-row"><span>Cashier</span><strong>{{ $cashier ?: '—' }}</strong></div>
                    <div class="info-row"><span>Payment ref</span><strong>{{ $order->paymentRef ?: '—' }}</strong></div>
                    @if ($order->cancelReason)
                        <div class="info-row"><span>Cancel reason</span><strong>{{ $order->cancelReason }}</strong></div>
                    @endif
                    @if ($order->returnReason)
                        <div class="info-row"><span>Return reason</span><strong>{{ $order->returnReason }}</strong></div>
                    @endif
                </section>

                <section class="card">
                    <p class="kicker">Customer</p>
                    <div class="info-row"><span>Name</span><strong>{{ $order->customerName ?: '—' }}</strong></div>
                    <div class="info-row"><span>Phone</span><strong>{{ $order->customerPhone ?: '—' }}</strong></div>
                    <div class="info-row"><span>Email</span><strong>{{ $order->customerEmail ?: '—' }}</strong></div>
                </section>

                <section class="card">
                    <p class="kicker">Summary</p>
                    <div class="info-row"><span>Items</span><strong>{{ $items->count() }}</strong></div>
                    <div class="info-row grand"><span>Total</span><strong>{{ $money($order->totalAmount) }}</strong></div>
                </section>
            </aside>
        </div>
    </div>
    <script>
        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
                form.querySelectorAll('button').forEach((other) => { other.disabled = true; });
            });
        });
    </script>
@endsection
