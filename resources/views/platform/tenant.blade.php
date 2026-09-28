@extends('layouts.app')

@section('title', $tenant->name.' | SalesDock')

@php
    $show = fn ($value) => $value ?: '—';
    $tone = [
        'APPROVED' => 'approved',
        'PENDING' => 'pending',
        'REJECTED' => 'rejected',
    ];
    $approval = [
        'APPROVED' => 'Approved',
        'PENDING' => 'Pending',
        'REJECTED' => 'Rejected',
    ];
    $cycles = [
        'MONTHLY' => 'Monthly',
        'QUARTERLY' => 'Quarterly',
        'ANNUALLY' => 'Annually',
    ];
    $statuses = [
        'TRIALING' => 'Trialing',
        'ACTIVE' => 'Active',
        'PAST_DUE' => 'Past due',
        'SUSPENDED' => 'Suspended',
        'CANCELLED' => 'Cancelled',
        'EXPIRED' => 'Expired',
    ];
    $subTone = [
        'ACTIVE' => 'active',
        'TRIALING' => 'pending',
        'PAST_DUE' => 'pending',
        'SUSPENDED' => 'overdue',
        'CANCELLED' => 'cancelled',
        'EXPIRED' => 'cancelled',
    ];
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('admin.tenants') }}">Back</a>
            <strong>{{ $tenant->name }}</strong>
            <span class="badge {{ $tenant->isActive ? 'active' : 'cancelled' }}">{{ $tenant->isActive ? 'Active' : 'Inactive' }}</span>
            <span class="badge {{ $tone[$tenant->approvalStatus] ?? 'draft' }}">{{ $approval[$tenant->approvalStatus] ?? $tenant->approvalStatus }}</span>
            <div class="head-actions">
                @if ($tenant->approvalStatus === 'PENDING')
                    <button type="button" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="approve" data-title="Approve shop" data-copy="Approve {{ $tenant->name }}?" data-label="Approve" data-loading="Approving…">Approve</button>
                    <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="reject" data-title="Reject shop" data-copy="Reject {{ $tenant->name }}? This cannot be undone." data-label="Reject" data-loading="Rejecting…" data-tone="danger">Reject</button>
                @endif
                @if ($tenant->isActive)
                    @unless ($own)
                        <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="suspend" data-title="Suspend shop" data-copy="Suspend {{ $tenant->name }}? The shop cannot sell until you turn it back on." data-label="Suspend" data-loading="Suspending…" data-tone="danger">Suspend</button>
                    @endunless
                @else
                    <button type="button" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="activate" data-title="Turn shop on" data-copy="Turn {{ $tenant->name }} back on?" data-label="Reactivate" data-loading="Turning on…">Reactivate</button>
                @endif
                @unless ($own)
                    <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.destroy', $tenant) }}" data-method="DELETE" data-title="Delete shop" data-copy="Delete {{ $tenant->name }}? This removes the shop and everything on it. This cannot be undone." data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete</button>
                @endunless
            </div>
        </div>

        <div class="order-grid">
            <div class="order-main">
                <section class="card">
                    <p class="kicker band">Business</p>
                    <div class="supplier-facts">
                        <div><span>Slug</span><strong>{{ $tenant->slug }}</strong></div>
                        <div><span>Email</span><strong>{{ $show($tenant->email) }}</strong></div>
                        <div><span>Phone</span><strong>{{ $show($tenant->phone) }}</strong></div>
                        <div><span>Website</span><strong>{{ $show($tenant->website) }}</strong></div>
                        <div><span>TIN</span><strong>{{ $show($tenant->tin) }}</strong></div>
                        <div><span>RC number</span><strong>{{ $show($tenant->rcNumber) }}</strong></div>
                        <div class="wide"><span>Address</span><strong>{{ $show($tenant->address) }}</strong></div>
                        <div><span>Joined</span><strong>{{ $tenant->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</strong></div>
                    </div>
                </section>

                <section class="card orders">
                    <p class="kicker band">Branches</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tenant->branches as $branch)
                                <tr>
                                    <td>
                                        {{ $branch->name }}
                                        @if ($branch->isMain)
                                            <span class="pill">Main</span>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $branch->isActive ? 'active' : 'cancelled' }}">{{ $branch->isActive ? 'Active' : 'Inactive' }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="empty" colspan="2">No branches.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="card orders">
                    <p class="kicker band">Users</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($people as $person)
                                <tr>
                                    <td>
                                        {{ $person->name }}
                                        @if ($person->isSuperAdmin)
                                            <span class="pill">Platform</span>
                                        @endif
                                        @if ($person->id === $me)
                                            <span class="pill">You</span>
                                        @endif
                                    </td>
                                    <td>{{ $person->email }}</td>
                                    <td>{{ $person->role?->name ?: '—' }}</td>
                                    <td><span class="badge {{ $person->isActive ? 'active' : 'cancelled' }}">{{ $person->isActive ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        @if ($person->id !== $me)
                                            <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.users.destroy', [$tenant, $person]) }}" data-method="DELETE" data-title="Delete person" data-copy="Delete {{ $person->name }}? This cannot be undone." data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="empty" colspan="5">No users.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="order-side">
                <section class="card">
                    <p class="kicker">Usage</p>
                    <div class="info-row"><span>Users</span><strong>{{ number_format($tenant->usersCount) }}</strong></div>
                    <div class="info-row"><span>Orders</span><strong>{{ number_format($tenant->ordersCount) }}</strong></div>
                    <div class="info-row"><span>Products</span><strong>{{ number_format($tenant->productsCount) }}</strong></div>
                </section>
                <section class="card">
                    <p class="kicker">Subscription</p>
                    @if ($tenant->subscription)
                        <div class="info-row"><span>Plan</span><strong>{{ $tenant->subscription->plan?->name ?: '—' }}</strong></div>
                        <div class="info-row"><span>Tier</span><strong>{{ $tenant->subscription->plan?->tier ? ucfirst(strtolower($tenant->subscription->plan->tier)) : '—' }}</strong></div>
                        <div class="info-row"><span>Billing</span><strong>{{ $cycles[$tenant->subscription->billingCycle] ?? $tenant->subscription->billingCycle }}</strong></div>
                        <div class="info-row"><span>Period end</span><strong>{{ $tenant->subscription->currentPeriodEnd?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</strong></div>
                        <div class="info-row"><span>Status</span><strong><span class="badge {{ $subTone[$tenant->subscription->status] ?? 'draft' }}">{{ $statuses[$tenant->subscription->status] ?? $tenant->subscription->status }}</span></strong></div>
                    @else
                        <p class="empty">No subscription on this shop.</p>
                    @endif
                </section>

                <section class="card">
                    <p class="kicker">Plan Override</p>
                    <p class="muted" style="margin-bottom:0.75rem">Change this shop's plan immediately without requiring payment. A reason is mandatory and logged to the audit trail.</p>
                    @if ($plans->isEmpty())
                        <p class="muted">No active plans.</p>
                    @else
                    <form method="post" action="{{ route('admin.tenants.subscription', $tenant) }}" data-busy>
                        @csrf
                        <div class="ui-field">
                            <span>Plan <span class="req">*</span></span>
                            <select name="plan_id">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}"
                                        @if ($tenant->subscription?->planId === $plan->id) selected @endif>
                                        {{ $plan->name }} — ₦{{ number_format((float)$plan->monthlyPrice, 0) }}/mo
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ui-field" style="margin-top:0.5rem">
                            <span>Billing cycle <span class="req">*</span></span>
                            <select name="billing_cycle">
                                <option value="MONTHLY" @if(($tenant->subscription?->billingCycle ?? 'MONTHLY') === 'MONTHLY') selected @endif>Monthly</option>
                                <option value="QUARTERLY" @if($tenant->subscription?->billingCycle === 'QUARTERLY') selected @endif>Quarterly</option>
                                <option value="ANNUALLY" @if($tenant->subscription?->billingCycle === 'ANNUALLY') selected @endif>Annually</option>
                            </select>
                        </div>
                        <div class="ui-field" style="margin-top:0.5rem">
                            <span>Reason <span class="req">*</span></span>
                            <textarea name="reason" rows="3" maxlength="500" required placeholder="e.g. Courtesy upgrade for beta testing…"></textarea>
                        </div>
                        <div style="margin-top:0.65rem">
                            <button type="submit" data-loading="Applying…"><span data-label>Apply override</span></button>
                        </div>
                    </form>
                    @endif
                </section>
            </aside>
        </div>
    </div>

    <div class="ui-modal" data-ask-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.tenants.update', $tenant) }}" data-busy>
            @csrf
            <input type="hidden" name="_method" value="POST" data-ask-method>
            <input type="hidden" name="action" value="" data-ask-action>
            <div class="ui-filter-head">
                <strong data-ask-title>Are you sure?</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-ask-copy></p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Yes</span></button>
            </div>
        </form>
    </div>

    <script>
        const modal = document.querySelector('[data-ask-modal]');
        const form = modal.querySelector('form');
        const title = modal.querySelector('[data-ask-title]');
        const copy = modal.querySelector('[data-ask-copy]');
        const method = modal.querySelector('[data-ask-method]');
        const action = modal.querySelector('[data-ask-action]');
        const submit = form.querySelector('[type="submit"]');
        const label = submit.querySelector('[data-label]');
        const closeModal = () => { if (!submit.classList.contains('is-busy')) modal.hidden = true; };
        document.querySelectorAll('[data-ask]').forEach((button) => {
            button.addEventListener('click', () => {
                form.action = button.dataset.url;
                method.value = button.dataset.method || 'POST';
                action.value = button.dataset.action || '';
                action.disabled = button.dataset.action ? false : true;
                title.textContent = button.dataset.title || 'Are you sure?';
                copy.textContent = button.dataset.copy || '';
                label.textContent = button.dataset.label || 'Yes';
                submit.dataset.loading = button.dataset.loading || 'Saving…';
                submit.classList.toggle('danger', button.dataset.tone === 'danger');
                modal.hidden = false;
            });
        });
        modal.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });
        form.addEventListener('submit', () => {
            if (submit.disabled) return;
            submit.disabled = true;
            submit.classList.add('is-busy');
            label.textContent = submit.dataset.loading || 'Saving…';
        });
    </script>
@endsection
