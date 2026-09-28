<div class="bell" data-bell>
    <button type="button" class="bell-open" data-bell-open aria-expanded="false" aria-haspopup="dialog" aria-label="Notifications">
        @include('partials.icon', ['name' => '/notifications'])
        @if (($bell['unread'] ?? 0) > 0)
            <span class="bell-count">{{ $bell['unread'] > 9 ? '9+' : $bell['unread'] }}</span>
        @endif
    </button>
    <div class="bell-menu" data-bell-menu role="dialog" aria-label="Notifications" hidden>
        <div class="bell-head">
            <strong>Notifications</strong>
            @if (($bell['unread'] ?? 0) > 0)
                <form method="post" action="{{ route('notifications.read') }}">
                    @csrf
                    <button type="submit">Mark all read</button>
                </form>
            @endif
        </div>
        <ul>
            @forelse ($bell['items'] as $note)
                <li @class(['unread' => ! $note->isRead])>
                    <div class="meta">
                        <span class="pill">{{ $bell['labels'][$note->type] ?? \Illuminate\Support\Str::headline((string) $note->type) }}</span>
                        @unless ($note->isRead)
                            <i class="dot" aria-label="Unread"></i>
                        @endunless
                        <time>{{ $note->createdAt?->timezone('Africa/Lagos')->format('d M, H:i') ?: '—' }}</time>
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
        <a href="{{ route('notifications') }}">View all</a>
    </div>
</div>
<script>
    const bell = document.querySelector('[data-bell]');
    if (bell) {
        const button = bell.querySelector('[data-bell-open]');
        const menu = bell.querySelector('[data-bell-menu]');
        const closeBell = () => {
            menu.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        };
        button.addEventListener('click', () => {
            menu.hidden = !menu.hidden;
            button.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', (event) => {
            if (!bell.contains(event.target)) closeBell();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeBell();
        });

        // Poll for unread count every 30 s and update badge in-place
        function pollBellUnread() {
            fetch(@json(route('notifications.unread')), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then((data) => {
                    const count = data.unread || 0;
                    let badge = button.querySelector('.bell-count');
                    if (count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'bell-count';
                            button.appendChild(badge);
                        }
                        badge.textContent = count > 9 ? '9+' : String(count);
                    } else if (badge) {
                        badge.remove();
                    }
                })
                .catch(() => {});
        }
        setInterval(pollBellUnread, 30000);
    }
</script>
