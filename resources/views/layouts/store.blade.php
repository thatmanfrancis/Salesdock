<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Store')</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/salesdock.css') }}">
</head>
<body>
    <main class="store" style="--green: {{ $config->accentColor ?? '#16a34a' }}">
        <header>
            <h1>{{ $config->storeName }}</h1>
            @if ($config->tagline)<p class="muted">{{ $config->tagline }}</p>@endif
            <nav class="row"><a href="{{ route('store.show', $tenant->slug) }}">All products</a></nav>
        </header>
        @if (session('status'))<p class="ok">{{ session('status') }}</p>@endif
        @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
        @yield('content')
    </main>
</body>
</html>
