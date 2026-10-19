@php
    $v = static function (string $relative): string {
        $path = public_path($relative);

        return file_exists($path) ? (string) filemtime($path) : (string) time();
    };
@endphp
{{-- Browsers still request /favicon.ico by default; keep that file in public/. --}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $v('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ $v('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v={{ $v('favicon-16x16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v={{ $v('apple-touch-icon.png') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('SalesDock.svg') }}?v={{ $v('SalesDock.svg') }}">
