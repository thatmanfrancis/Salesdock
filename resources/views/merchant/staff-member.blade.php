@extends('layouts.app')

@section('title', $member->name.' | SalesDock')

@php
    $editing = old('form') === 'edit' && $errors->any();
    $when = fn ($date) => $date ? $date->timezone('Africa/Lagos')->format('d M Y') : 'Never';
@endphp

@section('content')
    <div class="page">
        <div class="order-head">
            <a class="back" href="{{ route('staff') }}">Back</a>
            <strong>{{ $member->name }}</strong>
            <span class="badge {{ $member->isActive ? 'active' : 'cancelled' }}">{{ $member->isActive ? 'Active' : 'Inactive' }}</span>
            @if ($canWrite)
                <div class="head-actions">
                    <button type="button" class="tool" data-edit-open>Edit</button>
                    @unless ($isSelf || $isOwner)
                        <button type="button" class="tool @if ($member->isActive) danger @endif" data-status-open>{{ $member->isActive ? 'Deactivate' : 'Reactivate' }}</button>
                    @endunless
                </div>
            @endif
        </div>

        <div class="order-grid">
            <div class="order-main">
                <section class="card">
                    <p class="kicker">Profile</p>
                    <div class="supplier-facts">
                        <div>
                            <span>Email</span>
                            <strong>{{ $member->email }}</strong>
                        </div>
                        <div>
                            <span>Phone</span>
                            <strong>{{ $member->phone ?: '—' }}</strong>
                        </div>
                        <div>
                            <span>Role</span>
                            <strong>{{ $member->role?->name ?: '—' }}</strong>
                        </div>
                        <div>
                            <span>Branch</span>
                            <strong>{{ $member->branch?->name ?: '—' }}</strong>
                        </div>
                        <div>
                            <span>Last login</span>
                            <strong>{{ $when($member->lastLoginAt) }}</strong>
                        </div>
                        <div>
                            <span>Joined</span>
                            <strong>{{ $when($member->createdAt) }}</strong>
                        </div>
                        <div>
                            <span>Two-factor</span>
                            <strong>{{ $member->twoFaEnabled ? 'On' : 'Off' }}</strong>
                        </div>
                    </div>
                </section>

                <section class="card orders">
                    <p class="kicker band">Activity</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Module</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activity as $row)
                                <tr>
                                    <td>{{ $row->timestamp ? $row->timestamp->timezone('Africa/Lagos')->format('d M Y, H:i') : '—' }}</td>
                                    <td>{{ ucfirst(strtolower($row->module)) }}</td>
                                    <td>{{ $row->description }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="empty" colspan="3">No activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>

            <aside class="order-side">
                <section class="card staff-id">
                    <div class="face" aria-hidden="true">{{ $initials }}</div>
                    <strong>{{ $member->name }}</strong>
                    <em>{{ $member->email }}</em>
                    <div class="pills">
                        <span class="pill">{{ $member->role?->name ?: '—' }}</span>
                        @if ($member->branch?->name)
                            <span class="pill">{{ $member->branch->name }}</span>
                        @endif
                    </div>
                </section>
                <section class="card secure">
                    <p class="kicker">Security</p>
                    <div class="secure-row">
                        <span>Two-factor</span>
                        <span class="badge {{ $member->twoFaEnabled ? 'active' : 'cancelled' }}">{{ $member->twoFaEnabled ? 'On' : 'Off' }}</span>
                    </div>
                    @if ($canWrite)
                        <button type="button" class="tool" data-pin-open>Reset PIN</button>
                    @endif
                </section>
            </aside>
        </div>
    </div>

    @if ($canWrite)
        <div class="ui-modal" data-edit-modal @unless ($editing) hidden @endunless>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ route('staff.update', $member) }}" data-busy>
                @csrf
                @method('PUT')
                <input type="hidden" name="form" value="edit">
                <div class="ui-filter-head">
                    <strong>Edit staff</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <div class="ui-modal-grid">
                    <label class="wide">
                        <span>Full name <span class="req">*</span></span>
                        <input name="name" value="{{ $editing ? old('name') : $member->name }}" placeholder="Full name" required>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input name="phone" value="{{ $editing ? old('phone') : $member->phone }}" placeholder="0803 000 0000">
                    </label>
                    @unless ($isOwner)
                        <x-ui.select name="roleId" label="Role" :required="true" :value="$editing ? old('roleId', $member->roleId) : $member->roleId" :options="$roles->all()" />
                    @endunless
                    <x-ui.select name="branchId" label="Branch" :required="true" :value="$editing ? old('branchId', $member->branchId) : ($member->branchId ?? '')" :options="$branches->all()" />
                    <label class="wide">
                        <span>New password</span>
                        <input type="password" name="password" placeholder="Leave blank to keep the current password" minlength="8">
                    </label>
                </div>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                </div>
            </form>
        </div>

        @unless ($isSelf || $isOwner)
            <div class="ui-modal" data-status-modal hidden>
                <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
                <form class="ui-modal-sheet" method="post" action="{{ route('staff.status', $member) }}" data-busy>
                    @csrf
                    <div class="ui-filter-head">
                        <strong>{{ $member->isActive ? 'Deactivate staff' : 'Reactivate staff' }}</strong>
                        <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                    </div>
                    <p class="ask">
                        @if ($member->isActive)
                            Deactivate {{ $member->name }}? They will lose access immediately.
                        @else
                            Reactivate {{ $member->name }}?
                        @endif
                    </p>
                    <div class="ui-modal-actions">
                        <button type="button" data-close>Cancel</button>
                        <button type="submit" class="@if ($member->isActive) danger @endif" data-loading="{{ $member->isActive ? 'Deactivating…' : 'Reactivating…' }}"><span data-label>{{ $member->isActive ? 'Deactivate' : 'Reactivate' }}</span></button>
                    </div>
                </form>
            </div>
        @endunless

        <div class="ui-modal" data-pin-modal hidden>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ route('staff.pin', $member) }}" data-busy>
                @csrf
                <div class="ui-filter-head">
                    <strong>Reset PIN</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <p class="ask">Reset the PIN for {{ $member->name }}? They will set a new one the next time they sign in.</p>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Resetting…"><span data-label>Reset PIN</span></button>
                </div>
            </form>
        </div>

        <script>
            const bind = (modal, opener) => {
                if (!modal || !opener) return;
                const close = () => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
                opener.addEventListener('click', () => { modal.hidden = false; });
                modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', close));
                modal.querySelector('form').addEventListener('submit', () => {
                    const button = modal.querySelector('[type="submit"]');
                    if (!button || button.disabled) return;
                    button.disabled = true;
                    button.classList.add('is-busy');
                    const label = button.querySelector('[data-label]');
                    if (label) label.textContent = button.dataset.loading || 'Saving…';
                });
            };
            bind(document.querySelector('[data-edit-modal]'), document.querySelector('[data-edit-open]'));
            bind(document.querySelector('[data-status-modal]'), document.querySelector('[data-status-open]'));
            bind(document.querySelector('[data-pin-modal]'), document.querySelector('[data-pin-open]'));
        </script>
    @endif
@endsection
