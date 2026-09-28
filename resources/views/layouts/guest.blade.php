<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SalesDock')</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-white font-sans text-gray-900 antialiased">
    @yield('content')
    @include('auth.behaviors')
    @if (config('services.tawk.property_id') && config('services.tawk.widget_id'))
    <script>
        var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
        (function(){
            var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
            s1.async=true;
            s1.src='https://embed.tawk.to/{{ config('services.tawk.property_id') }}/{{ config('services.tawk.widget_id') }}';
            s1.charset='UTF-8';
            s1.setAttribute('crossorigin','*');
            s0.parentNode.insertBefore(s1,s0);
        })();
    </script>
    @endif
</body>
</html>
