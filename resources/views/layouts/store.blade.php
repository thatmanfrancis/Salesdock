<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Store')</title>
    @if ($config->metaDescription)
        <meta name="description" content="{{ $config->metaDescription }}">
    @endif
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/salesdock.css') }}?v={{ filemtime(public_path('css/salesdock.css')) }}">
</head>
<body class="storefront" style="--green: {{ $config->accentColor ?? '#16a34a' }}">
    <div class="sf-platform">
        <a href="{{ url('/') }}" class="sf-platform-brand">
            <img src="{{ asset('SalesDock.svg') }}" alt="SalesDock" width="40" height="40">
            <span>SalesDock</span>
        </a>
        <span class="sf-platform-note">Secure checkout · Powered by SalesDock</span>
    </div>

    <header class="sf-header">
        <div class="sf-header-inner">
            <a class="sf-store-brand" href="{{ route('store.show', $tenant->slug) }}">
                <strong>{{ $config->storeName }}</strong>
                @if ($config->tagline)
                    <span>{{ $config->tagline }}</span>
                @endif
            </a>

            <form class="sf-search" method="get" action="{{ route('store.show', $tenant->slug) }}" role="search">
                <input
                    type="search"
                    name="q"
                    value="{{ $search ?? request('q') }}"
                    placeholder="Search products"
                    aria-label="Search products"
                >
                <button type="submit">Search</button>
            </form>

            <a class="sf-cart-link" href="{{ route('store.cart', $tenant->slug) }}" aria-label="Cart" data-sf-cart-link>
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 6h15l-1.5 9h-12z"/>
                    <path d="M6 6 5 3H2"/>
                    <circle cx="9" cy="20" r="1.5"/>
                    <circle cx="18" cy="20" r="1.5"/>
                </svg>
                <span>Cart</span>
                <i data-sf-cart-count @if (($cartCount ?? 0) < 1) hidden @endif>{{ $cartCount ?? 0 }}</i>
            </a>
        </div>
    </header>

    <main class="sf-main">
        <p class="sf-flash ok" data-sf-toast hidden></p>
        @if (session('status'))
            <p class="sf-flash ok">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="sf-flash error">{{ $errors->first() }}</p>
        @endif
        @yield('content')
    </main>

    <footer class="sf-footer">
        <p>{{ $config->storeName }} storefront</p>
        <p><a href="{{ url('/') }}">Built with SalesDock</a></p>
    </footer>

    <script>
        (function () {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const toast = document.querySelector('[data-sf-toast]');
            let toastTimer;

            function showToast(message, isError) {
                if (!toast) return;
                toast.hidden = false;
                toast.textContent = message;
                toast.classList.toggle('ok', !isError);
                toast.classList.toggle('error', !!isError);
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function () { toast.hidden = true; }, 2800);
            }

            function updateCartBadge(count) {
                const badge = document.querySelector('[data-sf-cart-count]');
                if (!badge) return;
                badge.textContent = String(count);
                badge.hidden = count < 1;
            }

            function markButton(button, qty) {
                if (!button) return;
                button.dataset.qty = String(qty);
                if (qty > 0) {
                    button.classList.add('in-cart');
                    button.textContent = 'In cart · ' + qty;
                } else {
                    button.classList.remove('in-cart');
                    button.textContent = 'Add to cart';
                }
            }

            document.addEventListener('submit', async function (event) {
                const form = event.target.closest('[data-sf-cart-add]');
                if (!form) return;

                event.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                if (button) button.disabled = true;

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token || '',
                        },
                        body: new FormData(form),
                        credentials: 'same-origin',
                    });

                    const data = await response.json().catch(function () { return {}; });

                    if (!response.ok) {
                        const message = data.message
                            || Object.values(data.errors || {})[0]?.[0]
                            || 'Could not add to cart.';
                        showToast(message, true);
                        return;
                    }

                    updateCartBadge(data.cartCount || 0);
                    markButton(button, data.productQty || 0);
                    document.querySelectorAll('[data-sf-cart-add] button[type="submit"]').forEach(function (btn) {
                        const id = btn.closest('form')?.querySelector('[name="productId"]')?.value;
                        if (id && id === data.productId) markButton(btn, data.productQty || 0);
                    });
                    showToast(data.message || 'Added to cart.');
                } catch (error) {
                    showToast('Could not add to cart.', true);
                } finally {
                    if (button) button.disabled = false;
                }
            });
        })();
    </script>
</body>
</html>
