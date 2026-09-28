@extends('layouts.app')

@section('title', 'Roles | SalesDock')

@php
    $creating = old('form') === 'create' && $errors->any();
    $people = fn ($count) => ((int) $count === 1 ? '1 person' : ((int) $count).' people');
@endphp

@section('content')
    <div class="page">
        <div class="page-tools">
            <button type="button" data-role-open>New role</button>
        </div>

        <div class="settings">
            <nav class="settings-nav" aria-label="Roles">
                @forelse ($roles as $role)
                    <a href="{{ route('roles', ['role' => $role->id]) }}" @class(['on' => $picked && $picked->id === $role->id, 'role' => true])>
                        <span>{{ $role->name }}</span>
                        @if ($role->isDefault)
                            <i class="pill">Default</i>
                        @endif
                        <em>{{ $people($role->peopleCount) }}</em>
                    </a>
                @empty
                    <p class="empty-note">No roles.</p>
                @endforelse
            </nav>

            <div class="settings-main">
                @if (! $picked)
                    <section class="card roles-empty">
                        <p>Select a role to edit permissions.</p>
                    </section>
                @else
                    <section class="card">
                        <div class="role-head">
                            <div>
                                <strong>{{ $picked->name }}</strong>
                                <span>{{ $levels[$picked->role] ?? $picked->role }} · {{ $people($picked->peopleCount) }}</span>
                            </div>
                            @if ($canDelete)
                                <button type="button" class="tool danger" data-delete-open>Delete</button>
                            @endif
                        </div>
                        @if ($locked)
                            <p class="note soon">The owner role stays on every ability.</p>
                        @endif
                        <form method="post" action="{{ route('roles.update', $picked) }}" data-busy>
                            @csrf
                            @method('PUT')
                            @foreach ($details as $key => [$label, $description])
                                <label class="perm">
                                    <span>
                                        <strong>{{ $label }}</strong>
                                        <em>{{ $description }}</em>
                                    </span>
                                    <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(! empty($flags[$key])) @disabled($locked)>
                                </label>
                            @endforeach
                            @unless ($locked)
                                <div class="ui-modal-actions">
                                    <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                                </div>
                            @endunless
                        </form>
                    </section>
                @endif
            </div>
        </div>
    </div>

    <div class="ui-modal" data-role-modal @unless ($creating) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('roles.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="create">
            <div class="ui-filter-head">
                <strong>New role</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <label>
                <span>Name <span class="req">*</span></span>
                <input name="name" value="{{ $creating ? old('name') : '' }}" placeholder="Senior cashier" required maxlength="120">
            </label>
            <x-ui.select name="role" label="Level" :required="true" :value="$creating ? old('role', 'CASHIER') : 'CASHIER'" :options="$levels" />
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Adding…"><span data-label>Add role</span></button>
            </div>
        </form>
    </div>

    @if ($canDelete)
        <div class="ui-modal" data-delete-modal hidden>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ route('roles.destroy', $picked) }}" data-busy>
                @csrf
                @method('DELETE')
                <div class="ui-filter-head">
                    <strong>Delete role</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <p class="ask">Delete {{ $picked->name }}? This cannot be undone.</p>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" class="danger" data-loading="Deleting…"><span data-label>Delete</span></button>
                </div>
            </form>
        </div>
    @endif

    <script>
        const openModal = (box) => { if (box) box.hidden = false; };
        const closeModal = (box) => { if (box && !box.querySelector('button.is-busy')) box.hidden = true; };
        const createModal = document.querySelector('[data-role-modal]');
        const deleteModal = document.querySelector('[data-delete-modal]');
        document.querySelector('[data-role-open]')?.addEventListener('click', () => openModal(createModal));
        document.querySelector('[data-delete-open]')?.addEventListener('click', () => openModal(deleteModal));
        document.querySelectorAll('[data-close]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.closest('.ui-modal')));
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
