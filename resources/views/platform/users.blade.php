@extends('layouts.app')

@section('title', 'Users | SalesDock')

@php
    $when = fn ($date) => $date?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: 'Never';
    $reopen = old('form') === 'edit' && $errors->any();
@endphp

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Users</h1>
                <span class="tally">{{ number_format($total) }} total</span>
            </div>
            <x-ui.filter :action="route('admin.users')" :active="$filtered" label="Filter users">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name or email" maxlength="80">
                </div>
                <x-ui.select name="shop" label="Shop" :value="$shop" :options="$shops" />
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last login</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $group = null; @endphp
                    @forelse ($people as $person)
                        @if ($group !== ($person->tenantId ?? 'platform'))
                            @php $group = $person->tenantId ?? 'platform'; @endphp
                            <tr class="group">
                                <td colspan="6">
                                    @if ($person->tenant)
                                        <a href="{{ route('admin.tenants.show', $person->tenant) }}">{{ $person->tenant->name }}</a>
                                        <span class="muted">{{ $person->tenant->slug }}</span>
                                    @else
                                        Platform
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td>
                                {{ $person->name }}
                                @if ($person->isSuperAdmin || $person->role?->role === 'SUPER_ADMIN')
                                    <span class="pill">Platform</span>
                                @endif
                                @if ($person->id === $self)
                                    <span class="pill main">You</span>
                                @endif
                                @if ($person->twoFaEnabled)
                                    <span class="pill">2FA</span>
                                @endif
                                <div class="muted">{{ $person->email }}</div>
                            </td>
                            <td>{{ $person->role?->name ?: '—' }}</td>
                            <td><span class="badge {{ $person->isActive ? 'active' : 'draft' }}">{{ $person->isActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $when($person->lastLoginAt) }}</td>
                            <td>{{ $person->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="detail" data-edit data-url="{{ route('admin.users.update', $person) }}" data-name="{{ $person->name }}" data-email="{{ $person->email }}">Edit</button>
                                    @if ($person->id !== $self)
                                        @if ($person->isActive)
                                            <button type="button" class="detail" data-ask data-url="{{ route('admin.users.update', $person) }}" data-action="inactive" data-title="Mark inactive" data-copy="Mark {{ $person->name }} inactive? They will not be able to sign in." data-label="Mark inactive" data-loading="Saving…">Mark inactive</button>
                                        @else
                                            <button type="button" class="detail" data-ask data-url="{{ route('admin.users.update', $person) }}" data-action="active" data-title="Mark active" data-copy="Mark {{ $person->name }} active?" data-label="Mark active" data-loading="Saving…">Mark active</button>
                                        @endif
                                    @endif
                                    @if ($person->email)
                                        <button type="button" class="detail" data-ask data-url="{{ route('admin.users.update', $person) }}" data-action="reset" data-title="Send password reset" data-copy="Send a password reset email to {{ $person->email }}?" data-label="Send email" data-loading="Sending…">Reset password</button>
                                    @endif
                                    @if ($person->twoFaEnabled)
                                        <button type="button" class="detail" data-ask data-url="{{ route('admin.users.update', $person) }}" data-action="twofa" data-title="Remove two-factor" data-copy="Remove two-factor from {{ $person->name }}? They can sign in with their password until they turn it on again." data-label="Remove 2FA" data-loading="Removing…" data-tone="danger">Remove 2FA</button>
                                    @endif
                                    @if ($person->id !== $self)
                                        <button type="button" class="quiet" data-ask data-url="{{ route('admin.users.destroy', $person) }}" data-method="DELETE" data-title="Delete person" data-copy="Delete {{ $person->name }}? This cannot be undone." data-label="Delete" data-loading="Deleting…" data-tone="danger">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No users.</td>
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

    <div class="ui-modal" data-edit-modal @unless ($reopen) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $reopen ? route('admin.users.update', old('user')) : route('admin.users') }}" data-busy>
            @csrf
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="form" value="edit">
            <input type="hidden" name="user" value="{{ old('user') }}">
            <div class="ui-filter-head">
                <strong>Edit person</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-field">
                <span>Name</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
            </div>
            <div class="ui-field">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required maxlength="255">
            </div>
            <div class="ui-field">
                <span>New password</span>
                <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Leave blank to keep the current password">
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-ask-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('admin.users') }}" data-busy>
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
        const editModal = document.querySelector('[data-edit-modal]');
        const editForm = editModal.querySelector('form');
        const editSubmit = editForm.querySelector('[type="submit"]');
        const closeEdit = () => { if (!editSubmit.classList.contains('is-busy')) editModal.hidden = true; };
        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                editForm.action = button.dataset.url;
                editForm.querySelector('[name="user"]').value = button.dataset.url.split('/').pop();
                editForm.querySelector('[name="name"]').value = button.dataset.name || '';
                editForm.querySelector('[name="email"]').value = button.dataset.email || '';
                editForm.querySelector('[name="password"]').value = '';
                editModal.hidden = false;
            });
        });
        editModal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeEdit));

        const askModal = document.querySelector('[data-ask-modal]');
        const askForm = askModal.querySelector('form');
        const askTitle = askModal.querySelector('[data-ask-title]');
        const askCopy = askModal.querySelector('[data-ask-copy]');
        const askMethod = askModal.querySelector('[data-ask-method]');
        const askAction = askModal.querySelector('[data-ask-action]');
        const askSubmit = askForm.querySelector('[type="submit"]');
        const askLabel = askSubmit.querySelector('[data-label]');
        const closeAsk = () => { if (!askSubmit.classList.contains('is-busy')) askModal.hidden = true; };
        document.querySelectorAll('[data-ask]').forEach((button) => {
            button.addEventListener('click', () => {
                askForm.action = button.dataset.url;
                askMethod.value = button.dataset.method || 'POST';
                askAction.value = button.dataset.action || '';
                askAction.disabled = button.dataset.action ? false : true;
                askTitle.textContent = button.dataset.title || 'Are you sure?';
                askCopy.textContent = button.dataset.copy || '';
                askLabel.textContent = button.dataset.label || 'Yes';
                askSubmit.dataset.loading = button.dataset.loading || 'Saving…';
                askSubmit.classList.toggle('danger', button.dataset.tone === 'danger');
                askModal.hidden = false;
            });
        });
        askModal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', closeAsk));
        document.querySelectorAll('form[data-busy]').forEach((form) => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('[type="submit"]');
                const label = button.querySelector('[data-label]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection
