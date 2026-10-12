<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoiceNumber }} | SalesDock</title>
    <link rel="icon" href="{{ asset('salesdockfavicon.png') }}?v={{ filemtime(public_path('salesdockfavicon.png')) }}" type="image/png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1.75rem; background: #f3f4f6; font-family: "Space Grotesk", "Segoe UI", Arial, sans-serif; }
        .actions { display: flex; justify-content: center; gap: 0.75rem; margin: 1.25rem auto 0; }
        .actions button, .actions a { display: inline-flex; align-items: center; padding: 0.55rem 0.9rem; color: #fff; background: #16a34a; border: 0; border-radius: 6px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .actions a { color: #111827; background: #fff; border: 1px solid #d1d5db; }
        @page { margin: 14mm; }
        @media print {
            body { padding: 0; background: #fff; }
            .actions { display: none !important; }
        }
    </style>
</head>
<body>
    @include('merchant.partials.invoice-sheet')

    <div class="actions">
        <button type="button" onclick="window.print()">Download / Print PDF</button>
        <a href="{{ route('billing.invoice', $invoice) }}">Back</a>
    </div>
    @if (request()->boolean('print'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
