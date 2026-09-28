<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SalesDock')</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/salesdock.css') }}?v={{ filemtime(public_path('css/salesdock.css')) }}">
    @if (request()->routeIs('pos'))
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#16a34a">
    @endif
</head>
<body class="app @if (!empty($paywallLocked)) locked @endif">
    <input id="nav-open" type="checkbox" aria-label="Open menu">
    <label class="backdrop" for="nav-open"></label>
    <aside>
        <div class="brand">
            <a href="{{ url('/dashboard') }}"><img src="{{ asset('SalesDock.svg') }}" alt="SalesDock"></a>
            <label class="collapse" title="Collapse">
                <input id="nav-collapse" type="checkbox">
                ‹
            </label>
        </div>
        <nav>
            @foreach ($nav ?? [] as $item)
                @php
                    $path = ltrim($item['path'], '/');
                    $active = $item['path'] === '/admin' || $item['path'] === '/dashboard'
                        ? request()->is($path)
                        : request()->is($path) || request()->is($path.'/*');
                @endphp
                <a href="{{ !empty($paywallLocked) ? route('billing') : $item['href'] }}" @class(['active' => $active && empty($paywallLocked)])>
                    @include('partials.icon', ['name' => $item['icon']])
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>
    <div class="main">
        <header class="top">
            <label class="menu" for="nav-open" aria-label="Open menu">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </label>
            <div class="who">
                <strong>{{ auth()->user()->name }}</strong>
                <span class="bar">|</span>
                <span>{{ $roleName ?? '' }}</span>
            </div>
            <div class="actions">
                @if (!empty($bell))
                    @include('partials.bell')
                @else
                    <a href="{{ route('notifications') }}" aria-label="Notifications">@include('partials.icon', ['name' => '/notifications'])</a>
                @endif
                <a href="{{ route('profile') }}">Profile</a>
                <form method="post" action="{{ route('logout') }}" id="signout-form">
                    @csrf
                    <button class="signout" type="button" id="signout-btn">Sign out</button>
                </form>
            </div>
        </header>
        @include('partials.crumbs')
        @if (!empty($paywallLocked))
            <div class="banner">
                <span>Your workspace is in preview. Choose a plan to unlock selling, inventory, and staff tools.</span>
                <a href="{{ route('billing') }}">Choose a plan</a>
            </div>
        @elseif (($subscription ?? null) && $subscription->status === 'PAST_DUE')
            @php
                $graceEnds = $subscription->gracePeriodEndsAt;
                $daysLeft  = $graceEnds ? max(0, (int) now()->diffInDays($graceEnds, false)) : 0;
            @endphp
            <div class="banner banner-warn">
                <span>
                    Your subscription has expired.
                    @if ($daysLeft > 0)
                        You have <strong>{{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}</strong> before your account moves to the free plan.
                    @else
                        Your account will be moved to the free plan shortly.
                    @endif
                </span>
                <a href="{{ route('billing') }}">Renew now</a>
            </div>
        @endif
        @if (session('status'))
            <p class="flash ok">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="flash error">{{ $errors->first() }}</p>
        @endif
        <div class="workspace">
            @if (!empty($paywallLocked))
                <a class="unlock" href="{{ route('billing') }}">
                    <strong>Unlock SalesDock</strong>
                    <p class="muted">Pick a free or paid plan to start using POS, products, and the rest of your dashboard.</p>
                    <span class="btn">View plans</span>
                </a>
            @endif
            @yield('content')
        </div>
    </div>
    <script>
        const nav = document.querySelector('.app > aside nav');
        const workspace = document.querySelector('.workspace');
        if (nav) {
            nav.scrollTop = Number(sessionStorage.getItem('salesdock-nav-scroll') || 0);
            nav.addEventListener('scroll', () => sessionStorage.setItem('salesdock-nav-scroll', String(nav.scrollTop)), { passive: true });
        }
        if (workspace) {
            const key = 'salesdock-workspace:' + location.pathname;
            workspace.scrollTop = Number(sessionStorage.getItem(key) || 0);
            workspace.addEventListener('scroll', () => sessionStorage.setItem(key, String(workspace.scrollTop)), { passive: true });
        }
        const placeMenus = () => {
            document.querySelectorAll('.more-menu:not([hidden])').forEach((menu) => {
                const button = menu.parentElement.querySelector('[data-more]');
                if (!button || window.innerWidth > 800) {
                    menu.style.position = '';
                    menu.style.top = '';
                    menu.style.left = '';
                    menu.style.right = '';
                    return;
                }
                const box = button.getBoundingClientRect();
                const width = menu.getBoundingClientRect().width || 140;
                const left = Math.min(Math.max(8, box.right - width), window.innerWidth - width - 8);
                menu.style.position = 'fixed';
                menu.style.top = (box.bottom + 6) + 'px';
                menu.style.left = left + 'px';
                menu.style.right = 'auto';
                menu.style.zIndex = '30';
            });
        };
        document.addEventListener('click', () => requestAnimationFrame(placeMenus), true);
        document.querySelectorAll('.card.orders').forEach((card) => {
            card.addEventListener('scroll', () => {
                document.querySelectorAll('.more-menu').forEach((menu) => { menu.hidden = true; });
                document.querySelectorAll('[data-more]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
            }, { passive: true });
        });
    </script>
    {{-- Sign out confirmation modal --}}
    <div class="ui-modal" id="signout-modal" hidden>
        <button type="button" class="ui-filter-backdrop" id="signout-backdrop" aria-label="Close"></button>
        <div class="ui-modal-sheet">
            <div class="ui-filter-head">
                <strong>Sign out</strong>
                <button type="button" class="ghost" id="signout-cancel-x" aria-label="Close">✕</button>
            </div>
            <p class="ask">Are you sure you want to sign out?</p>
            <div class="ui-modal-actions">
                <button type="button" id="signout-cancel">Cancel</button>
                <button type="button" id="signout-confirm" class="danger">Sign out</button>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const modal   = document.getElementById('signout-modal');
            const form    = document.getElementById('signout-form');
            const open    = document.getElementById('signout-btn');
            const confirm = document.getElementById('signout-confirm');
            const close   = () => { modal.hidden = true; };
            if (!modal || !form || !open) return;
            open.addEventListener('click', () => { modal.hidden = false; });
            confirm.addEventListener('click', () => { form.submit(); });
            document.getElementById('signout-cancel').addEventListener('click', close);
            document.getElementById('signout-cancel-x').addEventListener('click', close);
            document.getElementById('signout-backdrop').addEventListener('click', close);
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) close(); });
        })();
    </script>

    {{-- Support float button (hidden on the support pages themselves) --}}
    @unless (request()->routeIs('support', 'support.show') || ($role ?? '') === 'SUPER_ADMIN')
    <div id="support-float" class="support-float">
        <button type="button" id="support-float-btn" aria-label="Open support" title="Support">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <span class="support-float-badge" id="support-float-badge" hidden>0</span>
        </button>
        <div id="support-float-panel" class="support-float-panel" hidden>
            <div class="support-float-head">
                <strong>Support</strong>
                <button type="button" id="support-float-close" aria-label="Close">✕</button>
            </div>
            <div class="support-float-body">
                <p>Need help? Submit a ticket and our team will respond as soon as possible.</p>
                <form method="post" action="{{ route('support.store') }}" data-float-form>
                    @csrf
                    <input type="hidden" name="form" value="float">
                    <div class="ui-field">
                        <span>Subject <span class="req">*</span></span>
                        <input type="text" name="subject" required maxlength="255" placeholder="What do you need help with?">
                    </div>
                    <div class="ui-field">
                        <span>Message <span class="req">*</span></span>
                        <textarea name="message" rows="4" required maxlength="5000" placeholder="Describe your issue…"></textarea>
                    </div>
                    <div class="support-float-foot">
                        <button type="submit" data-loading="Submitting…"><span data-label>Submit ticket</span></button>
                        <a href="{{ route('support') }}">View all tickets →</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const btn    = document.getElementById('support-float-btn');
            const panel  = document.getElementById('support-float-panel');
            const close  = document.getElementById('support-float-close');
            const badge  = document.getElementById('support-float-badge');
            const form   = document.querySelector('[data-float-form]');

            btn.addEventListener('click', () => {
                panel.hidden = !panel.hidden;
                // Clear badge when user opens the panel
                if (!panel.hidden) {
                    badge.hidden = true;
                    badge.textContent = '0';
                }
            });
            close.addEventListener('click', () => { panel.hidden = true; });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !panel.hidden) panel.hidden = true;
            });
            form?.addEventListener('submit', () => {
                const submit = form.querySelector('[type="submit"]');
                const label  = submit?.querySelector('[data-label]');
                if (!submit || submit.disabled) return;
                submit.disabled = true;
                submit.classList.add('is-busy');
                if (label) label.textContent = submit.dataset.loading || 'Submitting…';
            });

            // Poll for unread admin replies every 30 s
            function pollUnread() {
                fetch(@json(route('support.unread')), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((r) => r.json())
                    .then((data) => {
                        const count = data.count || 0;
                        badge.hidden  = count < 1;
                        badge.textContent = count > 9 ? '9+' : String(count);
                    })
                    .catch(() => {});
            }

            // Also exposed globally so the ticket SSE handler can nudge it
            window.updateFloatBadge = function (delta) {
                const current = parseInt(badge.textContent, 10) || 0;
                const next    = current + delta;
                badge.hidden  = next < 1;
                badge.textContent = next > 9 ? '9+' : String(next);
            };

            pollUnread();
            setInterval(pollUnread, 30000);
        })();
    </script>
    @endunless
</body>
</html>
