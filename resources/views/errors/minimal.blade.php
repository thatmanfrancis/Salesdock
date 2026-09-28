<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | SalesDock</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/salesdock.css') }}">
</head>
<body class="miss">
    <main>
        <div class="code">@yield('code')</div>
        <h1>@yield('message')</h1>
        @hasSection('hint')
            <p>@yield('hint')</p>
        @endif
        <div class="actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a class="btn" href="{{ url('/dashboard') }}">Go to dashboard</a>
                <a class="ghost" href="{{ url('/') }}">Home</a>
            @endif
        </div>
    </main>
</body>
</html>
