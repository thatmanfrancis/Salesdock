@extends('layouts.app')

@section('title', 'Customers | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            <button type="button" data-customer-open>Add customer</button>
            <x-ui.filter :action="route('customers')" :active="$filtered" label="Filter customers">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, phone, email, or WhatsApp">
                </div>
            </x-ui.filter>
        </div>

        <section class="card orders customers">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="num">Orders</th>
                        <th class="num">Spend</th>
                        <th class="num">Points</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a></td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>{{ $customer->email ?: '—' }}</td>
                            <td class="num">{{ $customer->totalOrders }}</td>
                            <td class="num">₦{{ number_format((float) $customer->totalSpend, 2) }}</td>
                            <td class="num">{{ $customer->loyaltyPoints }}</td>
                            <td>
                                <div class="more">
                                    <button type="button" data-more aria-label="Actions for {{ $customer->name }}" aria-expanded="false">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div class="more-menu" hidden>
                                        <button type="button" data-edit data-url="{{ route('customers.update', $customer) }}" data-id="{{ $customer->id }}" data-name="{{ $customer->name }}" data-phone="{{ $customer->phone }}" data-email="{{ $customer->email }}" data-whatsapp="{{ $customer->whatsapp }}" data-address="{{ $customer->address }}" data-notes="{{ $customer->notes }}">Edit</button>
                                        <button type="button" class="danger" data-remove data-url="{{ route('customers.destroy', $customer) }}" data-name="{{ $customer->name }}">Delete</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="7">No customers found.</td>
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

    @php $editing = old('form') === 'edit' && $errors->any(); @endphp

    <div class="ui-modal" data-create-modal @unless (old('form') === 'create' && $errors->any()) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('customers.store') }}" data-busy>
            @csrf
            <input type="hidden" name="form" value="create">
            <div class="ui-filter-head">
                <strong>New customer</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-modal-grid">
                <label class="wide">
                    <span>Name <span class="req">*</span></span>
                    <input name="name" value="{{ old('form') === 'create' ? old('name') : '' }}" placeholder="Customer name" required>
                </label>
                <label>
                    <span>Phone</span>
                    <input name="phone" value="{{ old('form') === 'create' ? old('phone') : '' }}" placeholder="0803 000 0000">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('form') === 'create' ? old('email') : '' }}" placeholder="name@email.com">
                </label>
                <label>
                    <span>WhatsApp</span>
                    <input name="whatsapp" value="{{ old('form') === 'create' ? old('whatsapp') : '' }}" placeholder="0803 000 0000">
                </label>
                <label>
                    <span>Address</span>
                    <input name="address" value="{{ old('form') === 'create' ? old('address') : '' }}" placeholder="Street, city">
                </label>
                <label class="wide">
                    <span>Notes</span>
                    <textarea name="notes" placeholder="Anything the shop should remember">{{ old('form') === 'create' ? old('notes') : '' }}</textarea>
                </label>
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Creating…"><span data-label>Create</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-edit-modal @unless ($editing) hidden @endunless>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ $editing ? route('customers.update', old('customer')) : route('customers.store') }}" data-busy data-edit-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="form" value="edit">
            <input type="hidden" name="customer" value="{{ old('customer') }}" data-edit-id>
            <div class="ui-filter-head">
                <strong>Edit customer</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <div class="ui-modal-grid">
                <label class="wide">
                    <span>Name <span class="req">*</span></span>
                    <input name="name" value="{{ $editing ? old('name') : '' }}" placeholder="Customer name" required data-field="name">
                </label>
                <label>
                    <span>Phone</span>
                    <input name="phone" value="{{ $editing ? old('phone') : '' }}" placeholder="0803 000 0000" data-field="phone">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ $editing ? old('email') : '' }}" placeholder="name@email.com" data-field="email">
                </label>
                <label>
                    <span>WhatsApp</span>
                    <input name="whatsapp" value="{{ $editing ? old('whatsapp') : '' }}" placeholder="0803 000 0000" data-field="whatsapp">
                </label>
                <label>
                    <span>Address</span>
                    <input name="address" value="{{ $editing ? old('address') : '' }}" placeholder="Street, city" data-field="address">
                </label>
                <label class="wide">
                    <span>Notes</span>
                    <textarea name="notes" placeholder="Anything the shop should remember" data-field="notes">{{ $editing ? old('notes') : '' }}</textarea>
                </label>
            </div>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
            </div>
        </form>
    </div>

    <div class="ui-modal" data-delete-modal hidden>
        <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
        <form class="ui-modal-sheet" method="post" action="{{ route('customers.store') }}" data-busy data-delete-form>
            @csrf
            @method('DELETE')
            <div class="ui-filter-head">
                <strong>Delete customer</strong>
                <button type="button" class="ghost" data-close aria-label="Close">✕</button>
            </div>
            <p class="ask">Are you sure you want to delete <strong data-delete-name>this customer</strong>? Past sales stay on the books.</p>
            <div class="ui-modal-actions">
                <button type="button" data-close>Cancel</button>
                <button type="submit" class="danger" data-loading="Deleting…"><span data-label>Delete</span></button>
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
        bindModal(createModal, document.querySelectorAll('[data-customer-open]'));
        const edit = bindModal(editModal, []);
        const remove = bindModal(deleteModal, []);
        const editForm = editModal.querySelector('[data-edit-form]');
        const deleteForm = deleteModal.querySelector('[data-delete-form]');

        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                closeMenus();
                editForm.action = button.dataset.url;
                editForm.querySelector('[data-edit-id]').value = button.dataset.id;
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
