<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SalesDock')</title>
    <meta name="description" content="@yield('description', 'POS, inventory and online store software for Nigerian retailers.')">
    <link rel="canonical" href="{{ url()->current() }}">
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --green: #16a34a; --green-d: #15803d; --green-s: #f0fdf4; --dark: #0f172a; --mid: #374151; --muted: #6b7280; --border: #e5e7eb; }
        body { font-family: 'Space Grotesk', sans-serif; color: var(--dark); background: #fff; line-height: 1.65; }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        .container { width: 100%; max-width: 72rem; margin-inline: auto; padding-inline: 1.5rem; }
        .narrow { max-width: 48rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.65rem 1.2rem; border-radius: 6px; font-weight: 700; font-size: 0.9rem; border: 2px solid transparent; cursor: pointer; transition: background .15s, color .15s; }
        .btn-primary { background: var(--green); color: #fff; }
        .btn-primary:hover { background: var(--green-d); }
        .btn-outline { border-color: var(--dark); color: var(--dark); }
        .btn-outline:hover { background: var(--dark); color: #fff; }

        .site-nav { position: sticky; top: 0; z-index: 40; background: rgba(255,255,255,.96); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border); }
        .site-nav .inner { display: flex; align-items: center; gap: 1.5rem; height: 4rem; }
        .site-nav .logo img { height: 1.75rem; }
        .site-nav ul { display: flex; gap: 1.5rem; list-style: none; margin-left: auto; }
        .site-nav ul a { font-size: 0.9rem; font-weight: 500; color: var(--mid); }
        .site-nav ul a:hover, .site-nav ul a[aria-current="page"] { color: var(--green); }
        .site-nav .actions { display: flex; gap: 0.6rem; }

        .site-hero { padding: 3.5rem 0 2.5rem; background: linear-gradient(180deg, var(--green-s) 0%, #fff 100%); }
        .site-hero .back { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.75rem 0.35rem 0.55rem; color: var(--green-d); font-size: 0.875rem; font-weight: 700; background: #fff; border: 1px solid #bbf7d0; border-radius: 6px; transition: background .15s, border-color .15s; }
        .site-hero .back:hover { background: #dcfce7; border-color: #86efac; }
        .site-hero h1 { font-size: clamp(1.9rem, 4.5vw, 2.75rem); line-height: 1.2; margin-top: 0.75rem; }
        .site-hero p { color: var(--muted); font-size: 1.05rem; margin-top: 0.6rem; max-width: 40rem; }
        .site-main { padding: 2.5rem 0 4rem; }

        .legal { color: var(--mid); }
        .legal .updated { color: var(--muted); font-size: 0.875rem; margin-bottom: 1.5rem; }
        .legal .toc { padding: 1rem 1.25rem; margin-bottom: 2rem; border: 1px solid var(--border); border-radius: 8px; background: #f9fafb; }
        .legal .toc strong { display: block; color: var(--dark); margin-bottom: 0.4rem; }
        .legal .toc ol { padding-left: 1.25rem; columns: 2; column-gap: 2rem; font-size: 0.9rem; }
        .legal .toc a { color: var(--green-d); }
        .legal h2 { color: var(--dark); font-size: 1.25rem; margin: 2rem 0 0.6rem; scroll-margin-top: 5rem; }
        .legal p, .legal ul { margin-bottom: 0.85rem; }
        .legal ul { padding-left: 1.25rem; }
        .legal li { margin-bottom: 0.35rem; }
        .legal a { color: var(--green-d); font-weight: 600; text-decoration: underline; }
        .legal .callout { padding: 1rem 1.25rem; border-left: 4px solid var(--green); background: var(--green-s); border-radius: 0 8px 8px 0; }
        @media (max-width: 640px) { .legal .toc ol { columns: 1; } }

        .site-footer { padding: 2.5rem 0 2rem; background: var(--dark); color: #9ca3af; font-size: 0.875rem; }
        .site-footer .inner { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .site-footer .brand { display: flex; align-items: center; gap: 0.6rem; color: #fff; font-weight: 700; }
        .site-footer .brand img { height: 1.5rem; filter: brightness(0) invert(1); }
        .site-footer nav { display: flex; gap: 1.25rem; flex-wrap: wrap; }
        .site-footer nav a:hover { color: #fff; }
        .site-footer .copy { width: 100%; text-align: center; font-size: 0.8125rem; padding-top: 1.5rem; margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,.1); }

        @media (max-width: 760px) {
            .site-nav .inner { flex-wrap: wrap; height: auto; gap: 0 1rem; padding-block: 0.6rem 0; }
            .site-nav ul { order: 3; width: 100%; margin: 0.5rem 0 0; padding: 0.55rem 0; gap: 1.25rem; overflow-x: auto; white-space: nowrap; border-top: 1px solid var(--border); scrollbar-width: none; }
            .site-nav ul::-webkit-scrollbar { display: none; }
            .site-nav .actions { margin-left: auto; }
            .site-nav .btn { padding: 0.5rem 0.9rem; font-size: 0.85rem; }
            .site-hero { padding: 2.25rem 0 1.75rem; }
            .site-main { padding: 1.75rem 0 3rem; }
            .site-nav .actions .btn-outline { display: none; }
            .site-footer .inner { flex-direction: column; text-align: center; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="site-nav">
        <div class="container inner">
            <a href="{{ route('home') }}" class="logo"><img src="{{ asset('SalesDock.svg') }}" alt="SalesDock"></a>
            <ul>
                <li><a href="{{ route('home') }}">Home</a></li>
                <li><a href="{{ route('help') }}" @if (request()->routeIs('help')) aria-current="page" @endif>Help &amp; contact</a></li>
                <li><a href="{{ route('privacy') }}" @if (request()->routeIs('privacy')) aria-current="page" @endif>Privacy</a></li>
                <li><a href="{{ route('terms') }}" @if (request()->routeIs('terms')) aria-current="page" @endif>Terms</a></li>
            </ul>
            <div class="actions">
                @auth
                    <a href="{{ auth()->user()->homePath() }}" class="btn btn-primary">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Start free trial</a>
                @endauth
            </div>
        </div>
    </header>

    <section class="site-hero">
        <div class="container @yield('width')">
            <a href="{{ route('home') }}" class="back" data-back>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Go back
            </a>
            <h1>@yield('heading')</h1>
            @hasSection('lead')
                <p>@yield('lead')</p>
            @endif
        </div>
    </section>

    <main class="site-main">
        <div class="container @yield('width')">
            @yield('content')
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="inner">
                <div class="brand"><img src="{{ asset('SalesDock.svg') }}" alt="SalesDock"><span>SalesDock</span></div>
                <nav>
                    <a href="{{ route('home') }}">Home</a>
                    <a href="{{ route('help') }}">Help &amp; contact</a>
                    <a href="{{ route('privacy') }}">Privacy</a>
                    <a href="{{ route('terms') }}">Terms</a>
                    <a href="{{ route('login') }}">Log in</a>
                </nav>
            </div>
            <div class="copy">© {{ date('Y') }} SalesDock by The Town Growers Hub Ltd. All rights reserved.</div>
        </div>
    </footer>
    <script>
        document.querySelector('[data-back]')?.addEventListener('click', (event) => {
            let cameFromHere = false;
            try { cameFromHere = document.referrer !== '' && new URL(document.referrer).origin === location.origin; } catch (error) {}
            if (cameFromHere && history.length > 1) {
                event.preventDefault();
                history.back();
            }
        });
    </script>
</body>
</html>
