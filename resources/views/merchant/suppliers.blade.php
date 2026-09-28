@extends('layouts.app')

@section('title', 'Suppliers | SalesDock')

@section('content')
    @php $editing = old('form') === 'edit' && $errors->any(); @endphp
    <div class="page">
        <div class="page-tools">
            <button type="button" data-supplier-open>Add supplier</button>
            <x-ui.filter :action="route('suppliers')" :active="$filtered" label="Filter suppliers">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, email, phone, or WhatsApp">
                </div>
            </x-ui.filter>
        </div>

        <section class="card orders suppliers">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Bank</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td>
                            <td>{{ $supplier->contactEmail ?: '—' }}</td>
                            <td>{{ $supplier->contactPhone ?: '—' }}</td>
                            <td>{{ $supplier->bankName ?: '—' }}</td>
                            <td>
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $supplier->name }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <button type="button" data-edit data-url="{{ route('suppliers.update', $supplier) }}" data-id="{{ $supplier->id }}" data-name="{{ $supplier->name }}" data-email="{{ $supplier->contactEmail }}" data-phone="{{ $supplier->contactPhone }}" data-whatsapp="{{ $supplier->contactWhatsapp }}" data-address="{{ $supplier->address }}" data-bank="{{ $supplier->bankName }}" data-account="{{ $supplier->bankAccount }}" data-notes="{{ $supplier->notes }}">Edit</button>
                                        <button type="button" class="danger" data-remove data-url="{{ route('suppliers.destroy', $supplier) }}" data-name="{{ $supplier->name }}">Deactivate</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="5">No suppliers found.</td>
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

    <div class="ui-modal" data-create-modal @unless (old('form') === 'create' && $errors->any()) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('suppliers.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="create">
            <div class="ui-filter-head">
                <strong>New supplier</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @include('merchant.partials.supplier-fields', ['form' => 'create', 'supplier' => null])
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Creating…"><span data-label>Create</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-edit-modal @unless ($editing) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $editing ? route('suppliers.update', old('supplier')) : route('suppliers.store') }}" data-busy data-edit-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="form" value="edit">
            <input type="hidden" name="supplier" value="{{ old('supplier') }}">
            <div class="ui-filter-head">
                <strong>Edit supplier</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            @include('merchant.partials.supplier-fields', ['form' => 'edit', 'supplier' => null])
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-delete-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('suppliers.store') }}" data-busy data-delete-form>
            @csrf
            @method('DELETE')
            <div class="ui-filter-head">
                <strong>Deactivate supplier</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">Are you sure you want to deactivate <strong data-delete-name>this supplier</strong>? They leave the list. Past purchase orders stay on the books.</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Deactivating…"><span data-label>Deactivate</span></button>
            </div>
        </form>
    </div>
    <script>
        const closeMenus = () => {
            document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
            document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        const bindModal = (modal, openers) => {
            const open = () => { modal.hidden = false; };
            const close = () => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
            openers.forEach((button) => button.addEventListener('click', open));
            modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', close));
            return { open, close };
        };
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

        const createModal = document.querySelector('[data-create-modal]');
        const editModal = document.querySelector('[data-edit-modal]');
        const deleteModal = document.querySelector('[data-delete-modal]');
        bindModal(createModal, document.querySelectorAll('[data-supplier-open]'));
        const edit = bindModal(editModal, []);
        const remove = bindModal(deleteModal, []);
        const editForm = editModal.querySelector('[data-edit-form]');
        const deleteForm = deleteModal.querySelector('[data-delete-form]');

        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                editForm.action = button.dataset.url;
                editForm.querySelector('[name="supplier"]').value = button.dataset.id;
                editForm.querySelectorAll('[data-field]').forEach((field) => {
                    field.value = button.dataset[field.dataset.field] || '';
                });
                edit.open();
            });
        });
        document.querySelectorAll('[data-remove]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                deleteForm.action = button.dataset.url;
                deleteModal.querySelector('[data-delete-name]').textContent = button.dataset.name;
                remove.open();
            });
        });

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
            if (event.key !== 'Escape') return;
            if (document.querySelector('.more-menu:not([hidden])')) {
                closeMenus();
                return;
            }
            [createModal, editModal, deleteModal].forEach((modal) => {
                if (modal && !modal.hidden && !modal.querySelector('button.is-busy')) modal.hidden = true;
            });
        });
    </script>
@endsection
