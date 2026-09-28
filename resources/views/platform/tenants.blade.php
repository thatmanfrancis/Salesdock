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
                <x-ui.select name="status" label="Status" :value="$status" :options="['' => 'All', 'active' => 'Active', 'inactive' => 'Inactive']" />
            </x-ui.filter>
        </div>

        <section class="card orders">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Email</th>
                        <th>Approval</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr>
                            <td><a href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a></td>
                            <td>{{ $tenant->slug }}</td>
                            <td>{{ $tenant->email ?: '—' }}</td>
                            <td><span class="badge {{ $tone[$tenant->approvalStatus] ?? 'draft' }}">{{ $approvalLabel[$tenant->approvalStatus] ?? $tenant->approvalStatus }}</span></td>
                            <td><span class="badge {{ $tenant->isActive ? 'active' : 'cancelled' }}">{{ $tenant->isActive ? 'Active' : 'Inactive' }}</span></td>
                            <td>{{ $tenant->createdAt?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="empty" colspan="6">No tenants.</td>
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
@endsection
