<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS &amp; Inventory Software for Nigerian Retailers | SalesDock</title>
    <meta name="description" content="POS, inventory, and online store software for Nigerian retailers. Works offline, logs every sale, and is priced in Naira. Start a 30-day free trial.">
    <!-- Open Graph -->
    <meta property="og:title" content="POS &amp; Inventory Software for Nigerian Retailers | SalesDock">
    <meta property="og:description" content="POS, inventory, and online store software for Nigerian retailers. Works offline, logs every sale, and is priced in Naira. Start a 30-day free trial.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://salesdock.ng">
    <meta property="og:site_name" content="SalesDock">
    <meta property="og:image" content="<?php echo e(url('/og-image.png')); ?>">
    <meta property="og:locale" content="en_NG">
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="POS &amp; Inventory Software for Nigerian Retailers | SalesDock">
    <meta name="twitter:description" content="POS, inventory, and online store software for Nigerian retailers. Works offline, logs every sale, and is priced in Naira. Start a 30-day free trial.">
    <meta name="twitter:image" content="<?php echo e(url('/og-image.png')); ?>">
    <!-- Canonical -->
    <link rel="canonical" href="https://salesdock.ng">
    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {"<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>":"https://schema.org","@type":"Organization","@id":"https://salesdock.ng/#organization","name":"SalesDock","legalName":"The Town Growers Hub Ltd","url":"https://salesdock.ng","logo":{"@type":"ImageObject","url":"https://salesdock.ng/SalesDock.svg"},"description":"POS, inventory management, and online store software for retail businesses in Nigeria.","address":{"@type":"PostalAddress","addressCountry":"NG"},"contactPoint":{"@type":"ContactPoint","contactType":"sales","email":"hello@salesdock.ng","areaServed":"NG","availableLanguage":["en"]}}
    </script>
    <script type="application/ld+json">
    {"<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>":"https://schema.org","@type":"SoftwareApplication","name":"SalesDock","operatingSystem":"Android, iOS, Windows, Web","applicationCategory":"BusinessApplication","applicationSubCategory":"Point of Sale Software","url":"https://salesdock.ng","description":"Retail POS, inventory management, and synced online storefront software for Nigerian businesses. Works offline and syncs on reconnect.","publisher":{"@id":"https://salesdock.ng/#organization"},"featureList":["Offline-capable point of sale","Real-time inventory tracking","Synced online storefront","Multi-branch support","Staff PIN-level access controls","Register audit logs"]}
    </script>
    <script type="application/ld+json">
    {"<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"Do I need to buy expensive, proprietary POS hardware?","acceptedAnswer":{"@type":"Answer","text":"No. SalesDock is designed to be highly compatible. You can turn any Android smartphone, tablet, iPad, or Windows laptop into a secure checkout till."}},{"@type":"Question","name":"How long does it take to set up my online store?","acceptedAnswer":{"@type":"Answer","text":"Less than five minutes. The moment you type in your product list and upload your inventory, your storefront link is active and ready to be shared with customers."}},{"@type":"Question","name":"Can I import my existing product lists?","acceptedAnswer":{"@type":"Answer","text":"Yes. If you have a large inventory list in Excel, you can bulk import your customers, suppliers, and products using a simple CSV file."}},{"@type":"Question","name":"How does SalesDock help prevent staff theft?","acceptedAnswer":{"@type":"Answer","text":"By creating individual login profiles for every employee. You control what permissions each staff member has, and track exactly who processed, discounted, or refunded any transaction."}},{"@type":"Question","name":"Does SalesDock work without internet?","acceptedAnswer":{"@type":"Answer","text":"Yes. SalesDock is built offline-first. Your registers keep processing sales when connectivity drops, and all transactions sync automatically the moment you are back online."}},{"@type":"Question","name":"Is SalesDock priced in Naira?","acceptedAnswer":{"@type":"Answer","text":"Yes. All SalesDock plans are priced in Naira with no dollar conversion. Your monthly bill does not change with exchange rate movements."}}]}
    </script>
    <?php echo $__env->make('partials.favicon', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset('css/salesdock.css')); ?>?v=<?php echo e(filemtime(public_path('css/salesdock.css'))); ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --green:   #16a34a;
            --green-l: #22c55e;
            --green-s: #f0fdf4;
            --dark:    #0f172a;
            --mid:     #374151;
            --muted:   #6b7280;
            --border:  #e5e7eb;
            --white:   #ffffff;
            --radius:  0.75rem;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'Space Grotesk', sans-serif; color: var(--dark); background: var(--white); line-height: 1.6; }
        img  { display: block; max-width: 100%; }
        a    { color: inherit; text-decoration: none; }

        /* ── Scroll animation base ── */
        .reveal { opacity: 0; transform: translateY(28px); transition: opacity .55s ease, transform .55s ease; }
        .reveal.in { opacity: 1; transform: none; }
        .reveal-delay-1 { transition-delay: .1s; }
        .reveal-delay-2 { transition-delay: .2s; }
        .reveal-delay-3 { transition-delay: .3s; }

        /* ── Utilities ── */
        .container { width: 100%; max-width: 72rem; margin-inline: auto; padding-inline: 1.5rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 0.5rem; font-weight: 700; font-size: 0.9375rem; cursor: pointer; transition: opacity .15s, transform .15s; border: 0; }
        .btn:hover { opacity: .88; transform: translateY(-1px); }
        .btn-primary { background: var(--green); color: #fff; }
        .btn-outline { background: transparent; color: var(--dark); border: 2px solid var(--dark); }
        .btn-outline:hover { background: var(--dark); color: #fff; }
        .btn-white  { background: #fff; color: var(--green); }
        .btn-lg     { padding: 1rem 2rem; font-size: 1.0625rem; }
        .tag { display: inline-block; padding: 0.25rem 0.75rem; background: var(--green-s); color: var(--green); border-radius: 999px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        .section-head { text-align: center; margin-bottom: 3.5rem; }
        .section-head h2 { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 700; line-height: 1.2; margin-top: 0.75rem; }
        .section-head p  { color: var(--muted); font-size: 1.0625rem; max-width: 42rem; margin: 0.75rem auto 0; }

        /* ── Nav ── */
        .land-nav { position: sticky; top: 0; z-index: 40; background: rgba(255,255,255,.95); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border); }
        .mobile-menu { display: none; position: absolute; top: 4rem; left: 0; right: 0; background: #fff; border-bottom: 1px solid var(--border); padding: 1rem 1.5rem 1.5rem; z-index: 50; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .mobile-menu.open { display: block; }
        .mobile-menu ul { list-style: none; display: grid; gap: 0.5rem; margin-bottom: 1rem; }
        .mobile-menu ul a { font-size: 1rem; font-weight: 600; color: var(--mid); display: block; padding: 0.4rem 0; }
        .land-nav .inner { display: flex; align-items: center; gap: 2rem; height: 4rem; }
        .land-logo { display: flex; align-items: center; flex-shrink: 0; }
        .land-logo img { height: 1.75rem; }
        .land-links { display: flex; gap: 1.75rem; list-style: none; margin-left: auto; }
        .land-links a { font-size: 0.9rem; font-weight: 500; color: var(--mid); transition: color .15s; }
        .land-links a:hover { color: var(--green); }
        .land-nav-actions { display: flex; gap: 0.75rem; align-items: center; flex-shrink: 0; margin-left: 1.5rem; }
        .nav-toggle { display: none; background: 0; border: 0; cursor: pointer; padding: 0.25rem; }

        /* ── Hero ── */
        .land-hero { padding: 5rem 0 4rem; text-align: center; background: linear-gradient(180deg, #f0fdf4 0%, #fff 100%); }
        .land-hero h1 { font-size: clamp(2rem, 5.5vw, 3.5rem); font-weight: 700; line-height: 1.15; max-width: 52rem; margin: 1rem auto 0; }
        .land-hero h1 span { color: var(--green); }
        .land-hero p { font-size: clamp(1rem, 2vw, 1.125rem); color: var(--muted); max-width: 42rem; margin: 1.25rem auto 0; }
        .hero-ctas { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; margin-top: 2rem; }
        .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 2rem; padding: 0.5rem 1rem; background: #fff; border: 1px solid var(--border); border-radius: 999px; font-size: 0.8125rem; color: var(--muted); }
        .hero-badge svg { color: var(--green); flex-shrink: 0; }
        .hero-preview { margin-top: 3.5rem; border-radius: 1rem; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.12); border: 1px solid var(--border); background: #f9fafb; }
        .hero-preview-bar { display: flex; align-items: center; gap: 0.4rem; padding: 0.75rem 1rem; background: #f3f4f6; border-bottom: 1px solid var(--border); }
        .hero-preview-bar span { width: 0.75rem; height: 0.75rem; border-radius: 50%; }
        .hero-preview-bar span:nth-child(1) { background: #f87171; }
        .hero-preview-bar span:nth-child(2) { background: #fbbf24; }
        .hero-preview-bar span:nth-child(3) { background: #4ade80; }
        .hero-preview-inner { padding: 2rem; display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; }
        .hero-stat { background: #fff; border-radius: 0.75rem; padding: 1.25rem; border: 1px solid var(--border); text-align: left; }
        .hero-stat small { font-size: 0.75rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .hero-stat strong { display: block; font-size: 1.75rem; font-weight: 700; color: var(--dark); margin-top: 0.25rem; }
        .hero-stat em { font-style: normal; font-size: 0.75rem; color: var(--green); font-weight: 600; }

        /* ── Before/After ── */
        .land-compare { padding: 5rem 0; }
        .compare-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2.5rem; }
        .compare-col { border-radius: var(--radius); overflow: hidden; }
        .compare-head { display: flex; align-items: center; gap: 0.6rem; padding: 1rem 1.5rem; font-weight: 700; font-size: 0.9375rem; }
        .compare-col.bad  .compare-head { background: #fef2f2; color: #b91c1c; }
        .compare-col.good .compare-head { background: var(--green-s); color: var(--green); }
        .compare-col.bad  { border: 1px solid #fee2e2; }
        .compare-col.good { border: 1px solid #bbf7d0; }
        .compare-row { display: grid; grid-template-columns: 1.75rem 1fr; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid; align-items: start; }
        .compare-col.bad  .compare-row { border-color: #fee2e2; }
        .compare-col.good .compare-row { border-color: #bbf7d0; }
        .compare-icon svg { display: block; margin-top: 0.15rem; }
        .compare-row strong { display: block; font-size: 0.875rem; font-weight: 700; margin-bottom: 0.2rem; }
        .compare-row p { font-size: 0.8125rem; color: var(--muted); margin: 0; }

        /* ── Features ── */
        .land-features { padding: 5rem 0; background: #f9fafb; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
        .feature-card { background: var(--white); border-radius: var(--radius); padding: 2rem; border: 1px solid var(--border); }
        .feature-num  { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; background: var(--green-s); color: var(--green); border-radius: 0.5rem; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.02em; margin-bottom: 1rem; }
        .feature-card h3 { font-size: 1.0625rem; font-weight: 700; margin-bottom: 0.5rem; }
        .feature-card p  { font-size: 0.875rem; color: var(--muted); margin-bottom: 1rem; }
        .feature-card ul { list-style: none; display: grid; gap: 0.5rem; }
        .feature-card ul li { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; color: var(--mid); }
        .feature-check { color: var(--green); flex-shrink: 0; margin-top: 0.1rem; }

        /* ── Segments ── */
        .land-segments { padding: 5rem 0; }
        .segments-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .segment-card { border-radius: var(--radius); padding: 2.5rem; border: 1px solid var(--border); }
        .segment-card.accent { background: var(--green); color: #fff; border-color: var(--green); }
        .segment-card.accent .seg-label { background: rgba(255,255,255,.2); color: #fff; }
        .segment-card.accent p, .segment-card.accent li { color: rgba(255,255,255,.85); }
        .seg-label { display: inline-block; padding: 0.25rem 0.75rem; background: var(--green-s); color: var(--green); border-radius: 999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 1rem; }
        .segment-card h3 { font-size: 1.375rem; font-weight: 700; margin-bottom: 0.75rem; }
        .segment-card p  { font-size: 0.9375rem; margin-bottom: 1rem; }
        .segment-card ul { list-style: none; display: grid; gap: 0.4rem; font-size: 0.875rem; }
        .segment-card ul li { display: flex; align-items: center; gap: 0.4rem; }

        /* ── Pricing ── */
        .land-pricing { padding: 5rem 0; background: #f9fafb; }
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: start; }
        .pricing-card { background: var(--white); border-radius: var(--radius); padding: 2rem; border: 1px solid var(--border); position: relative; }
        .pricing-card.popular { border-color: var(--green); box-shadow: 0 0 0 3px rgba(22,163,74,.12); }
        .popular-badge { position: absolute; top: -0.8rem; left: 50%; transform: translateX(-50%); background: var(--green); color: #fff; padding: 0.25rem 0.9rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
        .pricing-tier { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--green); }
        .pricing-card h3 { font-size: 1.125rem; font-weight: 700; margin: 0.4rem 0 0.75rem; }
        .pricing-price { font-size: 2.25rem; font-weight: 800; color: var(--dark); line-height: 1; }
        .pricing-price span { font-size: 1rem; font-weight: 500; color: var(--muted); }
        .pricing-desc { font-size: 0.875rem; color: var(--muted); margin: 0.75rem 0 1.5rem; min-height: 2.5rem; }
        .pricing-card ul { list-style: none; display: grid; gap: 0.6rem; margin-bottom: 2rem; }
        .pricing-card ul li { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; color: var(--mid); }
        .pricing-card .btn { width: 100%; justify-content: center; }

        /* ── Infrastructure ── */
        .land-infra { padding: 5rem 0; background: var(--dark); color: #fff; text-align: center; }
        .land-infra .section-head h2 { color: #fff; }
        .land-infra .section-head p  { color: #9ca3af; }
        .infra-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-top: 3rem; }
        .infra-stat { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); border-radius: var(--radius); padding: 2rem; }
        .infra-stat strong { display: block; font-size: 2.5rem; font-weight: 800; color: var(--green-l); }
        .infra-stat p { font-size: 0.875rem; color: #9ca3af; margin-top: 0.4rem; }

        /* ── FAQ ── */
        .land-faq { padding: 5rem 0; }
        .faq-list  { max-width: 48rem; margin: 0 auto; border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border); }
        .faq-item  { border-bottom: 1px solid var(--border); }
        .faq-item:last-child { border-bottom: 0; }
        .faq-q { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem; background: 0; border: 0; text-align: left; font-family: inherit; font-size: 0.9375rem; font-weight: 600; cursor: pointer; color: var(--dark); }
        .faq-q svg.faq-chevron { flex-shrink: 0; transition: transform .3s ease; }
        .faq-q[aria-expanded="true"] svg.faq-chevron { transform: rotate(180deg); }
        /* smooth expand via max-height */
        .faq-a { overflow: hidden; max-height: 0; transition: max-height .35s ease, padding .25s ease; padding: 0 1.5rem; }
        .faq-item.open .faq-a { max-height: 20rem; padding: 0 1.5rem 1.25rem; }
        .faq-a p { font-size: 0.9rem; color: var(--muted); line-height: 1.7; }

        /* ── Footer CTA ── */
        .land-cta { padding: 6rem 0; background: linear-gradient(135deg, #15803d 0%, #16a34a 60%, #22c55e 100%); text-align: center; color: #fff; }
        .land-cta h2 { font-size: clamp(1.75rem, 4vw, 2.75rem); font-weight: 700; }
        .land-cta p  { font-size: 1.0625rem; color: rgba(255,255,255,.85); margin: 0.75rem auto 0; max-width: 40rem; }
        .land-cta .cta-actions { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; margin-top: 2rem; }

        /* ── Footer ── */
        .land-footer { padding: 3rem 0 2rem; background: var(--dark); color: #9ca3af; }
        .footer-inner { display: flex; justify-content: space-between; align-items: center; gap: 2rem; flex-wrap: wrap; }
        .footer-logo  { display: flex; align-items: center; gap: 0.6rem; color: #fff; font-weight: 700; }
        .footer-logo img { height: 1.5rem; filter: brightness(0) invert(1); }
        .footer-links { display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.875rem; }
        .footer-links a:hover { color: #fff; }
        .footer-copy { width: 100%; text-align: center; font-size: 0.8125rem; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,.1); margin-top: 2rem; }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .land-links, .land-nav-actions .btn-outline { display: none; }
            .nav-toggle { display: block; margin-left: auto; }
            .compare-grid, .segments-grid, .pricing-grid, .infra-stats { grid-template-columns: 1fr; }
            .features-grid { grid-template-columns: 1fr; }
            .hero-preview-inner { grid-template-columns: 1fr 1fr; }
            .footer-inner { flex-direction: column; text-align: center; }
        }
        @media (max-width: 560px) {
            .hero-preview-inner { grid-template-columns: 1fr; }
            .pricing-card.popular { margin-top: 0; }
        }
    </style>
</head>
<body>


<header class="land-nav">
    <div class="container inner">
        <a href="/" class="land-logo">
            <img src="<?php echo e(asset('SalesDock.svg')); ?>" alt="SalesDock">
        </a>
        <ul class="land-links">
            <li><a href="#features">Features</a></li>
            <li><a href="#industries">Industries</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="#faq">FAQs</a></li>
        </ul>
        <div class="land-nav-actions">
            <a href="<?php echo e(route('login')); ?>" class="btn btn-outline" style="padding:.55rem 1.1rem;font-size:.875rem">Log In</a>
            <a href="<?php echo e(route('register')); ?>" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.875rem">Start Free Trial</a>
        </div>
        <button class="nav-toggle" id="nav-toggle" aria-label="Open menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    <div class="mobile-menu" id="mobile-menu">
        <ul>
            <li><a href="#features">Features</a></li>
            <li><a href="#industries">Industries</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="#faq">FAQs</a></li>
        </ul>
        <a href="<?php echo e(route('login')); ?>" class="btn btn-outline" style="margin-bottom:.5rem;width:100%;justify-content:center">Log In</a>
        <a href="<?php echo e(route('register')); ?>" class="btn btn-primary" style="width:100%;justify-content:center">Start Free Trial</a>
    </div>
</header>


<section class="land-hero">
    <div class="container">
        <span class="tag reveal in">Nigeria's #1 Retail OS</span>
        <h1 class="reveal in" style="transition-delay:.1s">Retail POS &amp; Inventory Software That <span>Stops the Bleed</span></h1>
        <p class="reveal in" style="transition-delay:.2s">Run your registers, track every item sold, and sell online from one platform. Offline-ready, audit-logged, and priced in Naira.</p>
        <div class="hero-ctas reveal in" style="transition-delay:.3s">
            <a href="<?php echo e(route('register')); ?>" class="btn btn-primary btn-lg">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                Start Your 30-Day Free Trial
            </a>
            <a href="#features" class="btn btn-outline btn-lg">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg>
                Explore Features
            </a>
        </div>
        <div class="hero-badge reveal in" style="transition-delay:.4s">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
            Military-grade cloud security. No hardware locks — runs on any phone, tablet, or PC.
        </div>
        <div class="hero-preview reveal in" style="transition-delay:.5s">
            <div class="hero-preview-bar">
                <span></span><span></span><span></span>
                <span style="flex:1;height:0.5rem;background:#e5e7eb;border-radius:999px;margin-left:.5rem"></span>
            </div>
            <div class="hero-preview-inner">
                <div class="hero-stat"><small>Today's Sales</small><strong>₦284,500</strong><em>↑ 12% vs yesterday</em></div>
                <div class="hero-stat"><small>Products</small><strong>1,247</strong><em>3 low stock alerts</em></div>
                <div class="hero-stat"><small>Online Orders</small><strong>38</strong><em>5 pending dispatch</em></div>
                <div class="hero-stat"><small>Active Staff</small><strong>12</strong><em>All clocked in</em></div>
            </div>
        </div>
    </div>
</section>


<section class="land-compare">
    <div class="container">
        <div class="section-head reveal">
            <span class="tag">The Retail Reality Check</span>
            <h2>Why Nigerian retail stores lose money every month</h2>
        </div>
        <div class="compare-grid">
            <div class="compare-col bad reveal">
                <div class="compare-head">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                    The Leaky, Fragmented Way
                </div>
                <?php $__currentLoopData = [
                    ['M2.5 2l19 19M9 4.5a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01', 'Inventory Shrinkage', 'Staff "forget" to record sales, and physical stock never matches your manual ledger.'],
                    ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', 'WhatsApp & DM Chaos', 'Spending hours copying customer delivery details and manually checking bank transfers.'],
                    ['M9 14s-4 0-4-4 4-4 4-4', 'Administrative Nightmare', 'Calculating staff payroll on paper and guessing your local tax liabilities.'],
                    ['M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', 'USD Subscription Anxiety', 'Watching your software bill skyrocket every month due to unstable exchange rates.'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$path, $title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="compare-row">
                    <span class="compare-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="<?php echo e($path); ?>"/></svg></span>
                    <div><strong><?php echo e($title); ?></strong><p><?php echo e($desc); ?></p></div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="compare-col good reveal reveal-delay-1">
                <div class="compare-head">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    The Protected SalesDock Way
                </div>
                <?php $__currentLoopData = [
                    ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10', 'Real-Time Audit Trails', 'Every sale, discount, and refund is instantly logged. Instant alerts for inventory discrepancies.'],
                    ['M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0', 'Instant Online Store', 'Every item on your POS auto-lists on your storefront. Share your link and let customers buy directly.'],
                    ['M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11', 'Unified Admin Engine', 'Run payroll with one click, track PAYE, and file taxes with built-in localized accounting.'],
                    ['M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', 'Naira-First Pricing', 'No dollar conversions, no unexpected currency spikes. Fair local pricing for local merchants.'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$path, $title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="compare-row">
                    <span class="compare-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="<?php echo e($path); ?>"/></svg></span>
                    <div><strong><?php echo e($title); ?></strong><p><?php echo e($desc); ?></p></div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </div>
</section>


<section class="land-features" id="features">
    <div class="container">
        <div class="section-head reveal">
            <span class="tag">Core Value Pillars</span>
            <h2>POS, inventory management, and online store in one system</h2>
            <p>Deep operational features explained as the business benefits they actually deliver.</p>
        </div>
        <div class="features-grid">
            <?php $__currentLoopData = [
                ['01', 'Smart POS with staff theft prevention', 'Turn any screen — your phone, tablet, or laptop — into an airtight checkout counter.', [
                    'Bulletproof register auditing — track cash balances down to the last Kobo',
                    'No more cash skimming — full remote control while staff run the floor',
                    'Offline-ready transactions — keep selling when internet goes down, auto-syncs on reconnect',
                ]],
                ['02', 'One-click online store, synced to your POS', 'Stop paying developers thousands to build an e-commerce website.', [
                    'Instant digital storefront — add products to POS, online store generates automatically',
                    'Perfect inventory mirror — online orders update physical POS stock in real-time',
                    'Click & Collect, in-store pickup, or local delivery — all from one dashboard',
                ]],
                ['03', 'Back office: suppliers, purchase orders and reporting', 'Most POS systems only track payments. SalesDock runs your entire business infrastructure.', [
                    'Stress-free tax compliance — automatic local tax filing report preparation',
                    'Supplier & stock control — manage vendor feeds, POs, and cost of goods',
                    'Automated payroll management — calculate salaries and disburse without Excel',
                ]],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$num, $title, $desc, $items]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="feature-card reveal" style="transition-delay:<?php echo e($i * 0.1); ?>s">
                <div class="feature-num"><?php echo e($num); ?></div>
                <h3><?php echo e($title); ?></h3>
                <p><?php echo e($desc); ?></p>
                <ul>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>
                        <svg class="feature-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php echo e($item); ?>

                    </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</section>


<section class="land-segments" id="industries">
    <div class="container">
        <div class="section-head reveal">
            <span class="tag">Built for Your Scale</span>
            <h2>Retail POS software for boutiques, pharmacies, supermarkets and chains</h2>
            <p>From a single boutique to a national retail chain — the same platform, the right features.</p>
        </div>
        <div class="segments-grid">
            <div class="segment-card reveal">
                <span class="seg-label">SMEs & Growing Retailers</span>
                <h3>Modernize, launch, and step away from the shop floor with confidence.</h3>
                <p>A secure register, an online brand, and a business you can run remotely.</p>
                <ul>
                    <?php $__currentLoopData = ['Fashion boutiques', 'Pharmacies', 'Beauty supply stores', 'Electronics shops']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <?php echo e($item); ?>

                    </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
            <div class="segment-card accent reveal reveal-delay-1">
                <span class="seg-label">Wholesalers & Multi-Store Ops</span>
                <h3>Centralize, consolidate, and track performance across every location.</h3>
                <p>Unified inventory, multi-location visibility, and one version of the truth.</p>
                <ul>
                    <?php $__currentLoopData = ['Supermarkets', 'Cosmetics distributors', 'Multi-branch retail chains', 'Wholesale merchants']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        <?php echo e($item); ?>

                    </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </div>
    </div>
</section>


<section class="land-pricing" id="pricing">
    <div class="container">
        <div class="section-head reveal">
            <span class="tag">Transparent Naira Pricing</span>
            <h2>POS software pricing in Naira — transparent monthly plans</h2>
            <p>Predictable local plans. No dollar-subscription anxiety.</p>
        </div>
        <?php if($plans->isNotEmpty()): ?>
        <div class="pricing-grid">
            <?php $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $features = is_array($plan->features) ? $plan->features : (json_decode($plan->features, true) ?? []);
                $isPopular = $i === 1;
                $isFree    = (float) $plan->monthlyPrice <= 0;
                $isCustom  = str_contains(strtolower((string) $plan->name), 'enterprise') || (float) $plan->monthlyPrice === 0.0 && $i > 0;
            ?>
            <div class="pricing-card <?php echo e($isPopular ? 'popular' : ''); ?> reveal" style="transition-delay:<?php echo e($i * 0.1); ?>s">
                <?php if($isPopular): ?>
                    <div class="popular-badge">Most Popular</div>
                <?php endif; ?>
                <div class="pricing-tier"><?php echo e($plan->tier); ?></div>
                <h3><?php echo e($plan->name); ?></h3>
                <div class="pricing-price">
                    <?php if($isFree): ?>
                        Free <span>forever</span>
                    <?php elseif($isCustom || (float)$plan->monthlyPrice >= 999999): ?>
                        <span style="font-size:1.5rem;font-weight:800">Let's Talk</span>
                    <?php else: ?>
                        ₦<?php echo e(number_format((float) $plan->monthlyPrice, 0)); ?> <span>/ month</span>
                    <?php endif; ?>
                </div>
                <p class="pricing-desc"><?php echo e($plan->description ?? ''); ?></p>
                <ul>
                    <?php if(is_array($features)): ?>
                        <?php $__currentLoopData = array_slice($features, 0, 8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li>
                            <svg class="feature-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php echo e($feat); ?>

                        </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php endif; ?>
                </ul>
                <?php if($isCustom || (float)$plan->monthlyPrice >= 999999): ?>
                    <a href="mailto:hello@salesdock.ng" class="btn btn-outline">Contact sales</a>
                <?php elseif($isPopular): ?>
                    <a href="<?php echo e(route('register')); ?>" class="btn btn-primary">Start free trial</a>
                <?php else: ?>
                    <a href="<?php echo e(route('register')); ?>" class="btn btn-outline">Get started</a>
                <?php endif; ?>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php else: ?>
        
        <div class="pricing-grid">
            <div class="pricing-card reveal">
                <div class="pricing-tier">STARTER</div>
                <h3>Growth</h3>
                <div class="pricing-price">₦4,500 <span>/ month</span></div>
                <p class="pricing-desc">Secure your store and eliminate manual paperwork.</p>
                <ul>
                    <?php $__currentLoopData = ['1 Smart POS Register', 'Real-Time Inventory & Stock Alerts', 'Staff Management & Register Audit Logs', 'Business Performance Reporting']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><svg class="feature-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo e($f); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
                <a href="<?php echo e(route('register')); ?>" class="btn btn-outline">Get started</a>
            </div>
            <div class="pricing-card popular reveal reveal-delay-1">
                <div class="popular-badge">Most Popular</div>
                <div class="pricing-tier">PROFESSIONAL</div>
                <h3>Omnichannel</h3>
                <div class="pricing-price">₦10,000 <span>/ month</span></div>
                <p class="pricing-desc">Sell seamlessly both in-store and online.</p>
                <ul>
                    <?php $__currentLoopData = ['Everything in Growth', 'Instant Custom Online Store', 'Real-Time In-Store & Online Stock Sync', 'Staff Payroll & Tax Filing Modules', 'Customer Loyalty Rewards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><svg class="feature-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo e($f); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
                <a href="<?php echo e(route('register')); ?>" class="btn btn-primary">Start free trial</a>
            </div>
            <div class="pricing-card reveal reveal-delay-2">
                <div class="pricing-tier">ENTERPRISE</div>
                <h3>Enterprise Scale</h3>
                <div class="pricing-price" style="font-size:1.5rem;font-weight:800">Let's Talk</div>
                <p class="pricing-desc">Custom local contract. Priority engineering support.</p>
                <ul>
                    <?php $__currentLoopData = ['Unlimited Stores & POS Registers', 'Centralized Warehouse Management', 'Dedicated API Access', '24/7 Priority Support & On-Site Migration']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><svg class="feature-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><?php echo e($f); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
                <a href="mailto:hello@salesdock.ng" class="btn btn-outline">Contact sales</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>


<section class="land-infra">
    <div class="container">
        <div class="section-head reveal">
            <span class="tag" style="background:rgba(255,255,255,.1);color:#4ade80">Trusted Infrastructure</span>
            <h2>Secure, NDPA-compliant retail software built for uptime</h2>
            <p>Powered by The Town Growers Hub Ltd. Your data is encrypted, backed up hourly, and protected under NDPA regulations.</p>
        </div>
        <div class="infra-stats">
            <?php $__currentLoopData = [
                ['99.9%', 'Uptime track record — stay online during peak hours'],
                ['Hourly', 'Automated backups — never lose a transaction'],
                ['NDPA', 'Compliant data protection — your customers\' data stays private'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$val, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="infra-stat reveal" style="transition-delay:<?php echo e($i * 0.1); ?>s">
                <strong><?php echo e($val); ?></strong>
                <p><?php echo e($desc); ?></p>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</section>


<section class="land-faq" id="faq">
    <div class="container">
        <div class="section-head reveal">
            <span class="tag">FAQs</span>
            <h2>Frequently Asked Questions</h2>
        </div>
        <div class="faq-list reveal">
            <?php $__currentLoopData = [
                ['Do I need to buy expensive, proprietary POS hardware?', 'No. SalesDock is designed to be highly compatible. You can turn any Android smartphone, tablet, iPad, or Windows laptop into a secure checkout till. No hardware locks, no vendor lock-in.'],
                ['How long does it take to set up my online store?', 'Less than five minutes. The moment you type in your product list and upload your inventory, your storefront link is active and ready to be shared with customers.'],
                ['Can I import my existing product lists?', 'Yes. If you have a large inventory list in Excel, you can bulk import using a simple CSV file. Our local support team is always on hand to assist with data integrity checks.'],
                ['How does SalesDock help prevent staff theft?', 'By creating individual login profiles for every employee. You control exactly what permissions each staff member has — and track who processed, discounted, or refunded any transaction, down to the timestamp.'],
                ['Is my data safe if I lose internet connection?', 'Yes. The SalesDock POS works offline and automatically queues your sales locally. The moment your connection is restored, all transactions sync to the cloud with full audit trails intact.'],
                ['What payment methods are supported?', 'SalesDock is Naira-first. We support cash, card terminals, and bank transfer at the POS. Online orders integrate with Flutterwave for card and bank payment processing.'],
                ['Does SalesDock work without internet?', 'Yes. SalesDock is built offline-first. Your registers keep processing sales when connectivity drops, and all transactions sync automatically the moment you are back online.'],
                ['Is SalesDock priced in Naira?', 'Yes. All SalesDock plans are priced in Naira with no dollar conversion. Your monthly bill does not change with exchange rate movements.'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$q, $a]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="faq-item">
                <button class="faq-q" aria-expanded="false">
                    <?php echo e($q); ?>

                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="faq-a"><p><?php echo e($a); ?></p></div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</section>


<section class="land-cta">
    <div class="container reveal">
        <h2>Ready to stop the leaks and scale your profits?</h2>
        <p>Take back absolute control of your business operations. No dollar subscriptions. No hardware locks.</p>
        <div class="cta-actions">
            <a href="<?php echo e(route('register')); ?>" class="btn btn-white btn-lg">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Start Your 30-Day Free Trial
            </a>
        </div>
    </div>
</section>


<footer class="land-footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-logo">
                <img src="<?php echo e(asset('SalesDock.svg')); ?>" alt="SalesDock">
                <span>SalesDock</span>
            </div>
            <div class="footer-links">
                <a href="#features">Features</a>
                <a href="#industries">Industries</a>
                <a href="#pricing">Pricing</a>
                <a href="#faq">FAQs</a>
                <a href="<?php echo e(route('login')); ?>">Log In</a>
                <a href="<?php echo e(route('register')); ?>">Register</a>
            </div>
        </div>
        <div class="footer-copy">
            © <?php echo e(date('Y')); ?> SalesDock by The Town Growers Hub Ltd. All rights reserved.
        </div>
    </div>
</footer>

<script src="<?php echo e(asset('js/home.js')); ?>?v=<?php echo e(filemtime(public_path('js/home.js'))); ?>"></script>
</body>
</html>
