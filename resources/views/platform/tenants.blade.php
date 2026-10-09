@extends('layouts.app')

@section('title', 'Tenants | SalesDock')

@php
    $tone = [
        'APPROVED' => 'approved',
        'PENDING' => 'pending',
        'REJECTED' => 'rejected',
    ];
    $approvalLabel = [
        'APPROVED' => 'Approved',
        'PENDING' => 'Pending',
        'REJECTED' => 'Rejected',
    ];
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Tenants</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.tenants')" :active="$filtered" label="Filter tenants">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, slug, or email" maxlength="80">
                </div>
                <x-ui.select name="approval" label="Approval" :value="$approval" :options="['' => 'All', 'APPROVED' => 'Approved', 'PENDING' => 'Pending', 'REJECTED' => 'Rejected']" />
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'deleted' => 'Deleted']" />
            </x-ui.filter>
        </div>

        <section class="card orders tenants">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Email</th>
                        <th>Approval</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr>
                            <td><a href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a></td>
                            <td>{{ $tenant->slug }}</td>
                            <td>{{ $tenant->email ?: '—' }}</td>
                            <td><span class="badge {{ $tone[$tenant->approvalStatus] ?? 'draft' }}">{{ $approvalLabel[$tenant->approvalStatus] ?? $tenant->approvalStatus }}</span></td>
                            <td>
                                @if ($tenant->trashed())
                                    <span class="badge overdue">Deleted</span>
                                    <div class="muted">Erased {{ $tenant->purgesAt()->timezone('Africa/Lagos')->format('d M Y') }}</div>
                                @else
                                    <span class="badge {{ $tenant->isActive ? 'active' : 'cancelled' }}">{{ $tenant->isActive ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </td>
                            <td>{{ $tenant->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                            <td>
                                @php($own = $tenant->id === $ownTenantId)
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $tenant->name }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <a href="{{ route('admin.tenants.show', $tenant) }}">View shop</a>
                                        @if ($tenant->trashed())
                                            <button type="button" data-ask data-url="{{ route('admin.tenants.restore', $tenant) }}" data-method="POST" data-title="Restore shop" data-copy="Restore {{ $tenant->name }}? The shop, its storefront and every login come back exactly as they were." data-label="Restore" data-loading="Restoring…">Restore</button>
                                            @unless ($own)
                                                <button type="button" class="danger" data-ask data-url="{{ route('admin.tenants.erase', $tenant) }}" data-method="DELETE" data-title="Delete permanently" data-copy="Erase {{ $tenant->name }} for good? Products, orders, customers, staff, invoices and every other record are removed. This cannot be undone." data-label="Delete permanently" data-loading="Erasing…" data-tone="danger">Delete permanently</button>
                                            @endunless
                                        @else
                                            @if ($tenant->approvalStatus === 'PENDING')
                                                <button type="button" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="approve" data-title="Approve shop" data-copy="Approve {{ $tenant->name }}?" data-label="Approve" data-loading="Approving…">Approve</button>
                                                <button type="button" class="danger" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="reject" data-title="Reject shop" data-copy="Reject {{ $tenant->name }}? This cannot be undone." data-label="Reject" data-loading="Rejecting…" data-tone="danger">Reject</button>
                                            @endif
                                            @if (! $tenant->isActive)
                                                <button type="button" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="activate" data-title="Turn shop on" data-copy="Turn {{ $tenant->name }} back on?" data-label="Reactivate" data-loading="Turning on…">Reactivate</button>
                                            @elseif (! $own)
                                                <button type="button" class="danger" data-ask data-url="{{ route('admin.tenants.update', $tenant) }}" data-method="POST" data-action="suspend" data-title="Suspend shop" data-copy="Suspend {{ $tenant->name }}? The shop cannot sell until you turn it back on." data-label="Suspend" data-loading="Suspending…" data-tone="danger">Suspend</button>
                                            @endif
                                            @unless ($own)
                                                <button type="button" class="danger" data-ask data-url="{{ route('admin.tenants.destroy', $tenant) }}" data-method="DELETE" data-title="Delete shop" data-copy="Delete {{ $tenant->name }}? The shop and storefront go offline and everyone is signed out. You can restore it within {{ \App\Models\Tenant::RESTORE_DAYS }} days; after that all its data is erased." data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete account</button>
                                            @endunless
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No tenants.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <nav class="pager" aria-label="Pages">
                @if ($page > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
                @else
                    <span class="off">Previous</span>
                @endif
                <span>Page {{ $page }} of {{ $pages }}</span>
                @if ($page < $pages)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
                @else
                    <span class="off">Next</span>
                @endif
            </nav>
        </section>
    </div>

    <div class="ui-modal" data-ask-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.tenants') }}" data-busy>
            @csrf
            <input type="hidden" name="_method" value="POST" data-ask-method>
            <input type="hidden" name="action" value="" data-ask-action>
            <input type="hidden" name="from" value="list">
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
        {
            const closeMenus = () => {
                document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
                document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
            };
            const placeMenu = (button, menu) => {
                const box = button.getBoundingClientRect();
                const width = menu.offsetWidth || 160;
                const height = menu.offsetHeight || 0;
                const below = box.bottom + 6;
                const top = below + height > window.innerHeight - 8 ? Math.max(8, box.top - height - 6) : below;
                menu.style.position = 'fixed';
                menu.style.top = top + 'px';
                menu.style.left = Math.min(Math.max(8, box.right - width), window.innerWidth - width - 8) + 'px';
                menu.style.right = 'auto';
                menu.style.zIndex = '30';
            };
            document.querySelectorAll('[data-more]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const menu = button.parentElement.querySelector('.more-menu');
                    const show = menu.hidden;
                    closeMenus();
                    menu.hidden = !show;
                    button.setAttribute('aria-expanded', show ? 'true' : 'false');
                    if (show) {
                        requestAnimationFrame(() => placeMenu(button, menu));
                    }
                });
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('.more')) closeMenus();
            });
            window.addEventListener('resize', closeMenus);
            document.querySelector('.workspace')?.addEventListener('scroll', closeMenus, { passive: true });

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
                    closeMenus();
                    form.action = button.dataset.url;
                    method.value = button.dataset.method || 'POST';
                    action.value = button.dataset.action || '';
                    action.disabled = !button.dataset.action;
                    title.textContent = button.dataset.title || 'Are you sure?';
                    copy.textContent = button.dataset.copy || '';
                    label.textContent = button.dataset.label || 'Yes';
                    submit.dataset.loading = button.dataset.loading || 'Saving…';
                    submit.classList.toggle('danger', button.dataset.tone === 'danger');
                    modal.hidden = false;
                });
            });
            modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeModal));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) closeModal();
            });
            form.addEventListener('submit', () => {
                if (submit.disabled) return;
                submit.disabled = true;
                submit.classList.add('is-busy');
                label.textContent = submit.dataset.loading || 'Saving…';
            });
        }
    </script>
@endsection
