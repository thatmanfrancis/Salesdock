@extends('layouts.app')

@section('title', 'Notifications | SalesDock')

@section('content')
    <div class="page">
        <div class="page-tools">
            @if ($unread > 0)
                <span class="unread-count">{{ $unread }} unread</span>
                <form method="post" action="{{ route('notifications.read') }}">
                    @csrf
                    <button type="submit">Mark all read</button>
                </form>
            @endif
            <x-ui.filter :action="route('notifications')" :active="$filtered" label="Filter notifications">
                <x-ui.select name="type" label="Type" :value="$type" :options="$types" />
            </x-ui.filter>
        </div>

        <ul class="notice-list">
            @forelse ($notes as $note)
                <li @class(['unread' => ! $note->isRead])>
                    <div class="meta">
                        <span class="pill">{{ $labels[$note->type] ?? \Illuminate\Support\Str::headline((string) $note->type) }}</span>
                        @unless ($note->isRead)
                            <i class="dot" aria-label="Unread"></i>
                        @endunless
                        <time datetime="{{ $note->createdAt?->toIso8601String() }}">{{ $note->createdAt?->timezone('Africa/Lagos')->format('d M Y, H:i') ?: '—' }}</time>
                    </div>
                    @if ($note->title)
                        <strong>{{ $note->title }}</strong>
                    @endif
                    <p>{{ $note->message }}</p>
                    @unless ($note->isRead)
                        <form method="post" action="{{ route('notifications.read-one', $note) }}">
                            @csrf
                            <button type="submit">Mark read</button>
                        </form>
                    @endunless
                </li>
            @empty
                <li class="empty">No notifications.</li>
            @endforelse
        </ul>
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
    </div>
@endsection
