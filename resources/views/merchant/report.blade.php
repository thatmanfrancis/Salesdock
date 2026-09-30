<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} | SalesDock</title>
    <link rel="icon" href="{{ asset('salesdockfavicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            font: 14px/1.5 "Space Grotesk", "Segoe UI", Arial, sans-serif;
            background: #fff;
        }
        .watermark {
            position: fixed;
            inset: 0;
            z-index: 0;
            display: grid;
            place-items: center;
            pointer-events: none;
            opacity: 0.07;
        }
        .watermark img {
            width: min(28rem, 70vw);
            height: auto;
            transform: rotate(-28deg);
        }
        main {
            position: relative;
            z-index: 1;
            max-width: 920px;
            margin: 0 auto;
            padding: 1.75rem;
        }
        .doc-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.25rem;
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
            border-bottom: 2px solid #16a34a;
        }
        .doc-brand {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            min-width: 8rem;
        }
        .doc-brand img {
            display: block;
            width: 2rem;
            height: 2rem;
            object-fit: contain;
        }
        .doc-brand strong {
            color: #16a34a;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
        }
        .doc-title { flex: 1; text-align: center; }
        .doc-title h1 { margin: 0; font-size: 1.35rem; font-weight: 700; }
        .doc-title p { margin: 0.2rem 0 0; color: #6b7280; font-size: 0.85rem; }
        .doc-stamp { text-align: right; min-width: 9rem; color: #6b7280; font-size: 0.8rem; }
        .doc-stamp strong { display: block; color: #374151; font-size: 0.8rem; }

        .identity {
            display: grid;
            gap: 0.2rem;
            margin: 0 0 1.35rem;
            padding: 0.9rem 1rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
        }
        .identity .biz {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #111827;
        }
        .identity p { margin: 0; color: #4b5563; font-size: 0.88rem; }
        .identity .regs { color: #6b7280; font-size: 0.8rem; }
        .identity .meta-line { margin-top: 0.35rem; color: #6b7280; font-size: 0.78rem; }

        h2 { margin: 1.35rem 0 0.65rem; font-size: 1rem; }
        p { margin: 0.15rem 0; }
        .muted { color: #6b7280; }
        .totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem; margin: 0.25rem 0 1.1rem; }
        .totals div { padding: 0.75rem; border: 1px solid #e5e7eb; border-radius: 0.75rem; background: #fff; }
        .totals span { display: block; color: #6b7280; font-size: 0.75rem; margin-bottom: 0.2rem; }
        .totals strong { font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.45rem 0.35rem; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 0.82rem; vertical-align: top; }
        th.num, td.num { text-align: right; white-space: nowrap; }
        .list { margin: 0; padding: 0; list-style: none; display: grid; gap: 0.45rem; }
        .list li { display: flex; justify-content: space-between; gap: 1rem; padding: 0.45rem 0; border-bottom: 1px solid #f3f4f6; }

        .doc-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        .doc-footer p { margin: 0; color: #9ca3af; font-size: 0.72rem; line-height: 1.55; max-width: 34rem; }
        .seal { text-align: right; }
        .seal strong { display: block; font-size: 0.75rem; color: #374151; }
        .seal code { font-size: 0.7rem; color: #9ca3af; word-break: break-all; }

        .actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        button, .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.55rem 0.9rem;
            color: #fff;
            background: #16a34a;
            border: 0;
            border-radius: 0.45rem;
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .btn.ghost { color: #111827; background: #fff; border: 1px solid #d1d5db; }

        @media print {
            .actions { display: none !important; }
            main { padding: 0; max-width: none; }
            .watermark { opacity: 0.08; }
        }
    </style>
</head>
<body>
    <div class="watermark" aria-hidden="true">
        <img src="{{ asset('SalesDock.svg') }}" alt="">
    </div>

    <main>
        <div class="doc-header">
            <div class="doc-brand">
                <img src="{{ asset('salesdockfavicon.png') }}" alt="SalesDock">
                <strong>SalesDock</strong>
            </div>
            <div class="doc-title">
                <h1>{{ $title }}</h1>
                <p>{{ $range }}</p>
            </div>
            <div class="doc-stamp">
                <strong>Official report</strong>
                <span>{{ $generatedAt }}</span>
            </div>
        </div>

        <section class="identity" aria-label="Business identity">
            <p class="biz">{{ $businessName }}</p>
            <p>Owner: {{ $ownerName }}</p>
            @if (! empty($businessAddress))
                <p>{{ $businessAddress }}</p>
            @endif
            @if (! empty($businessPhone) || ! empty($businessEmail))
                <p>
                    @if (! empty($businessPhone)){{ $businessPhone }}@endif
                    @if (! empty($businessPhone) && ! empty($businessEmail)) · @endif
                    @if (! empty($businessEmail)){{ $businessEmail }}@endif
                </p>
            @endif
            @if (! empty($businessTin) || ! empty($businessRc))
                <p class="regs">
                    @if (! empty($businessTin))TIN {{ $businessTin }}@endif
                    @if (! empty($businessTin) && ! empty($businessRc)) · @endif
                    @if (! empty($businessRc))RC {{ $businessRc }}@endif
                </p>
            @endif
            <p class="meta-line">Prepared by {{ $preparedBy }} · Generated {{ $generatedAt }}</p>
        </section>

        @yield('body')

        <div class="doc-footer">
            <p>
                Prepared by SalesDock from verified sales ledger data for {{ $businessName }}.<br>
                Figures reflect completed ledger sales in the selected range ({{ $range }}).<br>
                This is not a bank statement and is not issued by a tax authority.<br>
                Verify these figures inside SalesDock → Analytics / Financials for this shop and range.
            </p>
            <div class="seal">
                <strong>Generated {{ $generatedAt }}</strong>
                <code>{{ $docHash }}</code>
            </div>
        </div>

        <div class="actions">
            <button type="button" onclick="window.print()">Download / Print PDF</button>
            <a class="btn ghost" href="{{ $backUrl }}">Back</a>
        </div>
    </main>
    @if (request()->boolean('print'))
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
