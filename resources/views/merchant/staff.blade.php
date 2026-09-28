@extends('layouts.app')

@section('title', 'Staff | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            @if ($canWrite)
                <button type="button" data-staff-open @disabled($full || $assignable->isEmpty() || $branches->isEmpty()) @if ($full) title="Staff limit reached. Upgrade the plan to add more." @elseif ($assignable->isEmpty() || $branches->isEmpty()) title="Add a branch and a role before adding staff." @endif>Add staff</button>
            @endif
            <span class="plan-pill @if ($full) full @endif">{{ $slots }} / {{ $limit }} staff slots</span>
            <x-ui.filter :action="route('staff')" :active="$filtered" label="Filter staff">
                <div class="ui-field">
                    <span>Search</span>
                    <input type="search" name="q" value="{{ $q }}" placeholder="Name, email, or phone">
                </div>
                <x-ui.select name="role" label="Role" :value="$roleId" :options="['' => 'All roles'] + $roles->all()" />
                <x-ui.select name="branch" label="Branch" :value="$branchId" :options="['' => 'All branches', 'none' => 'Unassigned'] + $branches->all()" />
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Last login</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($people as $person)
                        <tr>
                            <td><a href="{{ route('staff.show', $person) }}">{{ $person->name }}</a></td>
                            <td>{{ $person->email }}</td>
                            <td>{{ $person->role?->name ?: '—' }}</td>
                            <td>{{ $person->branch?->name ?: '—' }}</td>
                            <td><span class="badge {{ $person->isActive ? 'active' : 'cancelled' }}">{{ $person->isActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $person->lastLoginAt ? $person->lastLoginAt->timezone('Africa/Lagos')->format('d M Y') : 'Never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No staff found.</td>
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

    @if ($canWrite)
        @php $creating = old('form') === 'create' && $errors->any(); @endphp
        <div class="ui-modal" data-create-modal @unless ($creating) hidden @endunless>
            <button type="button" class="ui-filter-backdrop" data-close aria-label="Close"></button>
            <form class="ui-modal-sheet" method="post" action="{{ route('staff.store') }}" data-busy>
                @csrf
                <input type="hidden" name="form" value="create">
                <div class="ui-filter-head">
                    <strong>Add staff</strong>
                    <button type="button" class="ghost" data-close aria-label="Close">✕</button>
                </div>
                <div class="ui-modal-grid">
                    <label class="wide">
                        <span>Full name <span class="req">*</span></span>
                        <input name="name" value="{{ $creating ? old('name') : '' }}" placeholder="Full name" required>
                    </label>
                    <label>
                        <span>Email <span class="req">*</span></span>
                        <input type="email" name="email" value="{{ $creating ? old('email') : '' }}" placeholder="name@email.com" required>
                    </label>
                    <label>
                        <span>Temporary password <span class="req">*</span></span>
                        <input type="password" name="password" placeholder="Temporary password" required minlength="8">
                    </label>
                    <label>
                        <span>Phone</span>
                        <input name="phone" value="{{ $creating ? old('phone') : '' }}" placeholder="0803 000 0000">
                    </label>
                    <x-ui.select name="roleId" label="Role" :required="true" :value="$creating ? old('roleId', '') : ''" :options="$assignable->all()" />
                    <x-ui.select name="branchId" label="Branch" :required="true" :value="$creating ? old('branchId', '') : ''" :options="$branches->all()" />
                </div>
                <div class="ui-modal-actions">
                    <button type="button" data-close>Cancel</button>
                    <button type="submit" data-loading="Creating…"><span data-label>Create</span></button>
                </div>
            </form>
        </div>
        <script>
            const modal = document.querySelector('[data-create-modal]');
            const close = () => { if (!modal.querySelector('button.is-busy')) modal.hidden = true; };
            document.querySelectorAll('[data-staff-open]').forEach((button) => {
                button.addEventListener('click', () => { if (!button.disabled) modal.hidden = false; });
            });
            modal.querySelectorAll('[data-close]').forEach((button) => button.addEventListener('click', close));
            modal.querySelector('form').addEventListener('submit', () => {
                const button = modal.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Creating…';
            });
        </script>
    @endif
@endsection
