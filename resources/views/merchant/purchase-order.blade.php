@extends('layouts.app')

@section('title', 'PO #'.$ref.' | SalesDock')

@php
    $money = fn ($amount) => '₦'.number_format((float) $amount, 2);
    $urgencies = ['LOW' => 'Low', 'MEDIUM' => 'Medium', 'HIGH' => 'High', 'CRITICAL' => 'Critical', 'OUT_OF_STOCK' => 'Out of stock'];
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('inventory.orders') }}">Back</a>
            <strong>PO #{{ $ref }}</strong>
            <span class="badge {{ strtolower($order->status) }}">{{ ucfirst(strtolower($order->status)) }}</span>
            <div class="head-actions">
                @if ($order->status === 'DRAFT')
                    <form method="post" action="{{ route('inventory.orders.send', $order) }}" data-busy>
                        @csrf
                        <button type="submit" data-loading="Sending…"><span data-label>Mark as sent</span></button>
                    </form>
                @endif
                @if (in_array($order->status, ['DRAFT', 'SENT'], true))
                    <button type="button" class="quiet" data-cancel-open>Cancel</button>
                @endif
            </div>
        </div>

        <div class="order-grid">
            <div class="order-main">
                <section class="card orders">
                    <p class="kicker band">Line items</p>
                    <form method="post" action="{{ route('inventory.orders.receive', $order) }}" data-busy>
                        @csrf
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="num">Ordered</th>
                                    <th class="num">Received</th>
                                    <th class="num">Unit cost</th>
                                    <th class="num">Line total</th>
                                    @if ($order->status === 'SENT')
                                        <th class="num">Receive qty</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    @php $left = max(0, (int) $item->quantityOrdered - (int) $item->quantityReceived); @endphp
                                    <tr>
                                        <td>{{ $names[$item->productId] ?? '—' }}</td>
                                        <td class="num">{{ $item->quantityOrdered }}</td>
                                        <td class="num">{{ (int) $item->quantityReceived }}</td>
                                        <td class="num">{{ $money($item->unitCost) }}</td>
                                        <td class="num">{{ $money($item->lineTotal) }}</td>
                                        @if ($order->status === 'SENT')
                                            <td class="num">
                                                <input class="receive" type="number" name="received[{{ $item->id }}]" min="0" max="{{ $left }}" step="1" value="{{ $left }}">
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr><td class="empty" colspan="{{ $order->status === 'SENT' ? 6 : 5 }}">No lines on this order.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="totals">
                            <div class="grand"><span>Total</span><span>{{ $money($order->totalCost) }}</span></div>
                            @if ($order->status === 'SENT')
                                <div class="ui-modal-actions">
                                    <button type="submit" data-loading="Receiving…"><span data-label>Receive goods</span></button>
                                </div>
                            @endif
                        </div>
                    </form>
                </section>
            </div>

            <aside class="order-side">
                <section class="card">
                    <p class="kicker">Order details</p>
                    <div class="info-row"><span>Supplier</span><strong>{{ $supplier->name ?? '—' }}</strong></div>
                    <div class="info-row"><span>Urgency</span><strong>{{ $urgencies[$order->urgency] ?? $order->urgency }}</strong></div>
                    <div class="info-row"><span>Created</span><strong>{{ $order->createdAt?->format('d M Y, H:i') }}</strong></div>
                    @if ($order->sentAt)
                        <div class="info-row"><span>Sent</span><strong>{{ $order->sentAt?->format('d M Y, H:i') }}</strong></div>
                    @endif
                    @if ($order->receivedAt)
                        <div class="info-row"><span>Received</span><strong>{{ $order->receivedAt?->format('d M Y, H:i') }}</strong></div>
                    @endif
                    @if ($order->notes)
                        <div class="info-row"><span>Notes</span><strong>{{ $order->notes }}</strong></div>
                    @endif
                </section>
                <section class="card">
                    <p class="kicker">Supplier contact</p>
                    <div class="info-row"><span>Email</span><strong>{{ $supplier->contactEmail ?? '—' }}</strong></div>
                    <div class="info-row"><span>Phone</span><strong>{{ $supplier->contactPhone ?? '—' }}</strong></div>
                </section>
            </aside>
        </div>
    </div>

    @if (in_array($order->status, ['DRAFT', 'SENT'], true))
        <div class="ui-modal" data-cancel-modal hidden>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ route('inventory.orders.cancel', $order) }}" data-busy>
                @csrf
                <div class="ui-filter-head">
                    <strong>Cancel purchase order</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <p class="ask">Are you sure you want to cancel PO #{{ $ref }}?</p>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Keep it</button>
                    <button type="submit" class="danger" data-loading="Cancelling…"><span data-label>Cancel</span></button>
                </div>
            </form>
        </div>
    @endif
    <script>
        const cancelModal = document.querySelector('[data-cancel-modal]');
        const close = (modal) => { if (modal && !modal.querySelector('button.is-busy')) modal.hidden = true; };
        document.querySelector('[data-cancel-open]')?.addEventListener('click', () => { if (cancelModal) cancelModal.hidden = false; });
        cancelModal?.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => close(cancelModal)));
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
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close(cancelModal);
        });
    </script>
@endsection
