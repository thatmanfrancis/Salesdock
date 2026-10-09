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
            @if ($tenant->trashed())
                <span class="badge overdue">Deleted</span>
            @else
                <span class="badge {{ $tenant->isActive ? 'active' : 'cancelled' }}">{{ $tenant->isActive ? 'Active' : 'Inactive' }}</span>
                <span class="badge {{ $tone[$tenant->approvalStatus] ?? 'draft' }}">{{ $approval[$tenant->approvalStatus] ?? $tenant->approvalStatus }}</span>
            @endif
            <div class="head-actions">
                @if ($tenant->trashed())
                    <button type="button" data-ask data-url="{{ route('admin.tenants.restore', $tenant) }}" data-method="POST" data-title="Restore shop" data-copy="Restore {{ $tenant->name }}? The shop, its storefront and every login come back exactly as they were." data-label="Restore" data-loading="Restoring…">Restore</button>
                    <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.erase', $tenant) }}" data-method="DELETE" data-title="Delete permanently" data-copy="Erase {{ $tenant->name }} for good? Products, orders, customers, staff, invoices and every other record are removed. This cannot be undone." data-label="Delete permanently" data-loading="Erasing…" data-tone="danger">Delete permanently</button>
                @elseif ($tenant->approvalStatus === 'PENDING')
                    <button type="button" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="approve" data-title="Approve shop" data-copy="Approve {{ $tenant->name }}?" data-label="Approve" data-loading="Approving…">Approve</button>
                    <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="reject" data-title="Reject shop" data-copy="Reject {{ $tenant->name }}? This cannot be undone." data-label="Reject" data-loading="Rejecting…" data-tone="danger">Reject</button>
                @endif
                @if ($tenant->isActive && ! $tenant->trashed())
                    @unless ($own)
                        <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="suspend" data-title="Suspend shop" data-copy="Suspend {{ $tenant->name }}? The shop cannot sell until you turn it back on." data-label="Suspend" data-loading="Suspending…" data-tone="danger">Suspend</button>
                    @endunless
                @elseif (! $tenant->trashed())
                    <button type="button" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="activate" data-title="Turn shop on" data-copy="Turn {{ $tenant->name }} back on?" data-label="Reactivate" data-loading="Turning on…">Reactivate</button>
                @endif
                @unless ($own || $tenant->trashed())
                    <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.destroy', $tenant) }}" data-method="DELETE" data-title="Delete shop" data-copy="Delete {{ $tenant->name }}? The shop and storefront go offline and everyone is signed out. You can restore it within {{ \App\Models\Tenant::RESTORE_DAYS }} days; after that all its data is erased." data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete</button>
                @endunless
            </div>
        </div>

        @if ($tenant->trashed())
            <section class="card deleted-note">
                <strong>This shop was deleted on {{ $tenant->deletedAt->timezone('Africa/Lagos')->format('d M Y, g:i a') }}{{ $deletedByName ? ' by '.$deletedByName : '' }}.</strong>
                <p>Nobody can sign in and the storefront is offline. Restore it any time before {{ $tenant->purgesAt()->timezone('Africa/Lagos')->format('d M Y') }}. After that date the shop and everything tied to it is erased automatically.</p>
            </section>
        @endif

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
                                        @if ($person->id !== $me && ! $tenant->trashed())
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

                <section class="card orders">
                    <div class="band-head">
                        <p class="kicker band">Suppliers</p>
                        @unless ($tenant->trashed())
                            <button type="button" class="tool" data-supplier-add>Add supplier</button>
                        @endunless
                    </div>
                    @if ($suppliers->isNotEmpty())
                        <p class="hint admin-restock">Restock drafts are <strong>{{ $tenant->restockDrafts ? 'on' : 'off' }}</strong> for this shop{{ $tenant->restockDrafts ? '' : ', so no supplier gets drafts' }}.</p>
                    @endif
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th class="num">Products</th>
                                <th class="num">Low</th>
                                <th class="num">Drafts</th>
                                <th>Restock</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($suppliers as $supplier)
                                <tr>
                                    <td>
                                        {{ $supplier->name }}
                                        @unless ($supplier->isActive)
                                            <span class="pill">Deactivated</span>
                                        @endunless
                                    </td>
                                    <td>{{ $show($supplier->contactEmail) }}</td>
                                    <td>{{ $show($supplier->contactPhone ?: $supplier->contactWhatsapp) }}</td>
                                    <td class="num">{{ number_format((int) $supplier->productsCount) }}</td>
                                    <td class="num {{ (int) $supplier->lowCount > 0 ? 'stock low' : '' }}">{{ number_format((int) $supplier->lowCount) }}</td>
                                    <td class="num">{{ number_format((int) $supplier->draftsCount) }}</td>
                                    <td>
                                        @if (! $supplier->isActive)
                                            <span class="badge draft">—</span>
                                        @elseif (! $tenant->restockDrafts)
                                            <span class="badge draft">Off for shop</span>
                                        @elseif ($supplier->restockDrafts)
                                            <span class="badge active">On</span>
                                        @else
                                            <span class="badge draft">Off</span>
                                        @endif
                                    </td>
                                    <td class="row-actions">
                                        @unless ($tenant->trashed())
                                            @if ($supplier->isActive)
                                                <button type="button" class="quiet" data-supplier-edit data-url="{{ route('admin.tenants.suppliers.update', [$tenant, $supplier]) }}" data-id="{{ $supplier->id }}" data-name="{{ $supplier->name }}" data-email="{{ $supplier->contactEmail }}" data-phone="{{ $supplier->contactPhone }}" data-whatsapp="{{ $supplier->contactWhatsapp }}" data-address="{{ $supplier->address }}" data-bank="{{ $supplier->bankName }}" data-account="{{ $supplier->bankAccount }}" data-notes="{{ $supplier->notes }}" data-restock="{{ $supplier->restockDrafts ? '1' : '0' }}">Edit</button>
                                                <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.suppliers.destroy', [$tenant, $supplier]) }}" data-method="DELETE" data-title="Remove supplier" data-copy="Remove {{ $supplier->name }} from {{ $tenant->name }}? They leave the shop's supplier list and get no more restock drafts. Past purchase orders stay, and you can restore them here." data-label="Remove" data-loading="Removing…" data-tone="danger">Remove</button>
                                            @else
                                                <button type="button" class="quiet" data-ask data-url="{{ route('admin.tenants.suppliers.restore', [$tenant, $supplier]) }}" data-method="POST" data-title="Restore supplier" data-copy="Restore {{ $supplier->name }} to {{ $tenant->name }}'s supplier list?" data-label="Restore" data-loading="Restoring…">Restore</button>
                                            @endif
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="empty" colspan="8">No suppliers.</td>
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
                    <div class="info-row"><span>Suppliers</span><strong>{{ number_format($suppliers->where('isActive', true)->count()) }}</strong></div>
                </section>
                <section class="card">
                    <p class="kicker">Latest check-in</p>
                    @if ($latestCheckIn)
                        <div class="info-row"><span>Person</span><strong>{{ $latestCheckIn->user?->name ?: '—' }}</strong></div>
                        <div class="info-row"><span>Days away</span><strong>{{ number_format($latestCheckIn->daysAway) }}</strong></div>
                        <div class="info-row"><span>Date</span><strong>{{ $latestCheckIn->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</strong></div>
                        <p class="muted" style="margin-top:0.5rem">{{ $latestCheckIn->reason ?: 'No reason given.' }}</p>
                        <a href="{{ route('admin.check-ins', ['q' => $tenant->name]) }}">All check-ins</a>
                    @else
                        <p class="empty">No check-ins yet.</p>
                    @endif
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

                @unless ($tenant->trashed())
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
                @endunless
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

    @unless ($tenant->trashed())
        @php
            $supplierForm = in_array(old('form'), ['admin-supplier-create', 'admin-supplier-edit'], true) && $errors->any() ? old('form') : null;
        @endphp
        <div class="ui-modal" data-supplier-modal @unless ($supplierForm) hidden @endunless>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ $supplierForm === 'admin-supplier-edit' && old('supplier') ? route('admin.tenants.suppliers.update', [$tenant, old('supplier')]) : route('admin.tenants.suppliers.store', $tenant) }}" data-busy data-supplier-form>
                @csrf
                <input type="hidden" name="_method" value="{{ $supplierForm === 'admin-supplier-edit' ? 'PUT' : 'POST' }}" data-supplier-method>
                <input type="hidden" name="form" value="{{ $supplierForm ?? 'admin-supplier-create' }}" data-supplier-kind>
                <input type="hidden" name="supplier" value="{{ old('supplier') }}" data-supplier-id>
                <div class="ui-filter-head">
                    <strong data-supplier-title>{{ $supplierForm === 'admin-supplier-edit' ? 'Edit supplier' : 'Add supplier' }}</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <p class="hint">Changes apply to {{ $tenant->name }} straight away and are recorded in the audit log.</p>
                @include('merchant.partials.supplier-fields', ['form' => $supplierForm ?? '__none__', 'supplier' => null])
                <label class="switch">
                    <span>Draft an order when stock runs low</span>
                    <input type="checkbox" name="restockDrafts" value="1" @checked($supplierForm ? (bool) old('restockDrafts') : true) data-supplier-restock>
                </label>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                </div>
            </form>
        </div>
        <script>
            {
                const supplierModal = document.querySelector('[data-supplier-modal]');
                const supplierForm = supplierModal.querySelector('[data-supplier-form]');
                const fill = (data) => {
                    supplierForm.querySelectorAll('[data-field]').forEach((field) => {
                        field.value = data[field.dataset.field] || '';
                    });
                    supplierForm.querySelector('[data-supplier-restock]').checked = data.restock !== '0';
                };
                const openSupplier = (mode, data) => {
                    const editing = mode === 'edit';
                    supplierForm.action = editing ? data.url : @json(route('admin.tenants.suppliers.store', $tenant));
                    supplierForm.querySelector('[data-supplier-method]').value = editing ? 'PUT' : 'POST';
                    supplierForm.querySelector('[data-supplier-kind]').value = editing ? 'admin-supplier-edit' : 'admin-supplier-create';
                    supplierForm.querySelector('[data-supplier-id]').value = editing ? data.id : '';
                    supplierModal.querySelector('[data-supplier-title]').textContent = editing ? 'Edit supplier' : 'Add supplier';
                    fill(data);
                    supplierModal.hidden = false;
                    supplierForm.querySelector('[data-field="name"]').focus();
                };
                const closeSupplier = () => { if (!supplierModal.querySelector('button.is-busy')) supplierModal.hidden = true; };
                document.querySelector('[data-supplier-add]')?.addEventListener('click', () => openSupplier('add', { restock: '1' }));
                document.querySelectorAll('[data-supplier-edit]').forEach((button) => {
                    button.addEventListener('click', () => openSupplier('edit', button.dataset));
                });
                supplierModal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeSupplier));
                supplierForm.addEventListener('submit', () => {
                    const submit = supplierForm.querySelector('[type="submit"]');
                    if (submit.disabled) return;
                    submit.classList.add('is-busy');
                    submit.querySelector('[data-label]').textContent = submit.dataset.loading;
                    setTimeout(() => supplierForm.querySelectorAll('button').forEach((button) => { button.disabled = true; }), 0);
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') closeSupplier();
                });
            }
        </script>
    @endunless

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
