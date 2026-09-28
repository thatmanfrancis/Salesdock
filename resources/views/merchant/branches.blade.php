@extends('layouts.app')

@section('title', 'Branches | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            <button type="button" data-branch-open @disabled($full) @if ($full) title="Your plan allows {{ $limit }} {{ $limit === 1 ? 'branch' : 'branches' }}." @endif>Add branch</button>
            <span class="plan-pill @if ($full) full @endif">{{ $slots }} / {{ $limit }} branches</span>
            <x-ui.filter :action="route('branches')" :active="$filtered" label="Filter branches">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Branch name">
                </div>
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" />
            </x-ui.filter>
        </div>

        <section class="card orders branches">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th class="num">Staff</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>
                                {{ $branch->name }}
                                @if ($branch->isMain)
                                    <span class="pill main">Main</span>
                                @endif
                            </td>
                            <td class="clip">{{ $branch->address ?: '—' }}</td>
                            <td>{{ $branch->phone ?: '—' }}</td>
                            <td class="num">{{ $branch->staffCount }}</td>
                            <td><span class="badge {{ $branch->isActive ? 'active' : 'cancelled' }}">{{ $branch->isActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $branch->createdAt?->timezone('Africa/Lagos')->format('d M Y') }}</td>
                            <td>
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $branch->name }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <button type="button" data-edit data-url="{{ route('branches.update', $branch) }}" data-id="{{ $branch->id }}" data-name="{{ $branch->name }}" data-address="{{ $branch->address }}" data-phone="{{ $branch->phone }}" data-main="{{ $branch->isMain ? '1' : '0' }}">Edit</button>
                                        @unless ($branch->isMain)
                                            <button type="button" class="{{ $branch->isActive ? 'danger' : '' }}" data-status data-url="{{ route('branches.status', $branch) }}" data-name="{{ $branch->name }}" data-active="{{ $branch->isActive ? '1' : '0' }}">{{ $branch->isActive ? 'Deactivate' : 'Activate' }}</button>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No branches found.</td>
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

    @php
        $creating = old('form') === 'create' && $errors->any();
        $editing = old('form') === 'edit' && $errors->any();
    @endphp

    <div class="ui-modal" data-branch-modal @unless ($creating || $editing) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $editing ? route('branches.update', old('branch')) : route('branches.store') }}" data-busy data-branch-form>
            @csrf
            <input type="hidden" name="_method" value="{{ $editing ? 'PUT' : 'POST' }}" data-method>
            <input type="hidden" name="form" value="{{ $editing ? 'edit' : 'create' }}" data-form>
            <input type="hidden" name="branch" value="{{ $editing ? old('branch') : '' }}" data-branch-id>
            <div class="ui-filter-head">
                <strong data-branch-title>{{ $editing ? 'Edit branch' : 'Add branch' }}</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-modal-grid">
                <label class="wide">
                    <span>Branch name <span class="req">*</span></span>
                    <input name="name" value="{{ $creating || $editing ? old('name') : '' }}" placeholder="Ikeja" required data-field="name">
                </label>
                <label class="wide">
                    <span>Address</span>
                    <input name="address" value="{{ $creating || $editing ? old('address') : '' }}" placeholder="Street, city" data-field="address">
                </label>
                <label>
                    <span>Phone</span>
                    <input name="phone" value="{{ $creating || $editing ? old('phone') : '' }}" placeholder="0803 000 0000" data-field="phone">
                </label>
                <label class="check">
                    <input type="checkbox" name="isMain" value="1" data-field="main" @checked(($creating || $editing) && old('isMain'))>
                    <span>Main branch</span>
                </label>
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="{{ $editing ? 'Saving…' : 'Creating…' }}" data-save><span data-label>{{ $editing ? 'Save' : 'Create' }}</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-status-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('branches.store') }}" data-busy data-status-form>
            @csrf
            <div class="ui-filter-head">
                <strong data-status-title>Deactivate branch</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask" data-status-ask>Deactivate this branch?</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-status-submit data-loading="Saving…"><span data-label>Deactivate</span></button>
            </div>
        </form>
    </div>

    <script>
        const closeMenus = () => {
            document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
            document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        const closeModal = (modal) => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
        const modal = document.querySelector('[data-branch-modal]');
        const form = modal.querySelector('[data-branch-form]');
        const statusModal = document.querySelector('[data-status-modal]');
        document.querySelectorAll('[data-more]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const menu = button.parentElement.querySelector('.more-menu');
                const show = menu.hidden;
                closeMenus();
                menu.hidden = !show;
                button.setAttribute('aria-expanded', show ? 'true' : 'false');
            });
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.more')) closeMenus();
        });
        const openCreate = () => {
            form.action = @json(route('branches.store'));
            form.querySelector('[data-method]').value = 'POST';
            form.querySelector('[data-form]').value = 'create';
            form.querySelector('[data-branch-id]').value = '';
            modal.querySelector('[data-branch-title]').textContent = 'Add branch';
            form.querySelector('[data-field="name"]').value = '';
            form.querySelector('[data-field="address"]').value = '';
            form.querySelector('[data-field="phone"]').value = '';
            form.querySelector('[data-field="main"]').checked = false;
            const save = form.querySelector('[data-save]');
            save.dataset.loading = 'Creating…';
            save.querySelector('[data-label]').textContent = 'Create';
            modal.hidden = false;
        };
        document.querySelectorAll('[data-branch-open]').forEach((button) => {
            button.addEventListener('click', () => { if (!button.disabled) openCreate(); });
        });
        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                form.action = button.dataset.url;
                form.querySelector('[data-method]').value = 'PUT';
                form.querySelector('[data-form]').value = 'edit';
                form.querySelector('[data-branch-id]').value = button.dataset.id;
                modal.querySelector('[data-branch-title]').textContent = 'Edit branch';
                form.querySelector('[data-field="name"]').value = button.dataset.name || '';
                form.querySelector('[data-field="address"]').value = button.dataset.address || '';
                form.querySelector('[data-field="phone"]').value = button.dataset.phone || '';
                form.querySelector('[data-field="main"]').checked = button.dataset.main === '1';
                const save = form.querySelector('[data-save]');
                save.dataset.loading = 'Saving…';
                save.querySelector('[data-label]').textContent = 'Save';
                modal.hidden = false;
            });
        });
        document.querySelectorAll('[data-status]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                const active = button.dataset.active === '1';
                statusModal.querySelector('[data-status-form]').action = button.dataset.url;
                statusModal.querySelector('[data-status-title]').textContent = active ? 'Deactivate branch' : 'Activate branch';
                statusModal.querySelector('[data-status-ask]').textContent = (active ? 'Deactivate ' : 'Activate ') + button.dataset.name + '?';
                const submit = statusModal.querySelector('[data-status-submit]');
                submit.classList.toggle('danger', active);
                submit.dataset.loading = active ? 'Deactivating…' : 'Activating…';
                submit.querySelector('[data-label]').textContent = active ? 'Deactivate' : 'Activate';
                statusModal.hidden = false;
            });
        });
        [modal, statusModal].forEach((box) => {
            box.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', () => closeModal(box)));
        });
        document.querySelectorAll('form[data-busy]').forEach((box) => {
            box.addEventListener('submit', () => {
                const button = box.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection