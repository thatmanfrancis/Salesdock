@extends('layouts.site')

@section('title', 'Privacy Policy | SalesDock')
@section('description', 'How SalesDock collects, uses and protects your data and your customers\' data.')
@section('heading', 'Privacy Policy')
@section('lead', 'What we collect, why we collect it, and the choices you have.')
@section('width', 'narrow')

@php
    $sections = [
        'who' => 'Who we are',
        'collect' => 'What we collect',
        'use' => 'How we use it',
        'customers' => 'Your customers\' data',
        'sharing' => 'Who we share it with',
        'storage' => 'Storage and security',
        'retention' => 'How long we keep it',
        'rights' => 'Your rights',
        'cookies' => 'Cookies',
        'changes' => 'Changes and contact',
    ];
@endphp

@section('content')
    <article class="legal">
        <p class="updated">Last updated: 8 October 2026</p>

        <nav class="toc">
            <strong>On this page</strong>
            <ol>
                @foreach ($sections as $id => $label)
                    <li><a href="#{{ $id }}">{{ $label }}</a></li>
                @endforeach
            </ol>
        </nav>

        <h2 id="who">1. Who we are</h2>
        <p>SalesDock is a point of sale, inventory and online store service run by The Town Growers Hub Ltd, a company registered in Nigeria ("SalesDock", "we", "us"). This policy explains how we handle personal data when you visit our website, sign up, or use SalesDock to run your shop. We process personal data in line with the Nigeria Data Protection Act 2023.</p>

        <h2 id="collect">2. What we collect</h2>
        <ul>
            <li><strong>Account details:</strong> business name, owner name, email address, phone number, business address, and optional business registration details such as TIN and RC number.</li>
            <li><strong>Staff details:</strong> names, emails and roles of the staff you add, and the times they sign in and out.</li>
            <li><strong>Shop records:</strong> products, stock levels, sales, refunds, expenses, suppliers, purchase orders and customer records that you enter.</li>
            <li><strong>Billing details:</strong> your plan, invoices and payment status. Card and bank details are entered on our payment partner's page; we do not store full card numbers.</li>
            <li><strong>Technical data:</strong> IP address, browser and device type, and activity logs we use to keep accounts secure and to fix problems.</li>
            <li><strong>Support messages:</strong> anything you send us through support tickets, email, phone or chat.</li>
        </ul>

        <h2 id="use">3. How we use it</h2>
        <ul>
            <li>To create and run your account and give you the features of your plan.</li>
            <li>To process subscription payments and send receipts, reminders and account notices.</li>
            <li>To keep SalesDock secure, for example detecting suspicious sign-ins and keeping audit logs of sensitive actions.</li>
            <li>To answer support requests and, with your permission, to help you inside your shop.</li>
            <li>To improve SalesDock using aggregated usage information that does not identify you.</li>
        </ul>
        <p>We do not sell your data, and we do not use your shop records to advertise to you or your customers.</p>

        <h2 id="customers">4. Your customers' data</h2>
        <p>When you record customers, take online orders or email suppliers through SalesDock, you decide what data is collected and why. For that data you are the data controller and SalesDock processes it on your behalf. You are responsible for telling your customers how you use their information and for having a lawful reason to collect it.</p>

        <h2 id="sharing">5. Who we share it with</h2>
        <p>We only share data with service providers that help us run SalesDock, under agreements that require them to protect it:</p>
        <ul>
            <li><strong>Payments:</strong> Flutterwave, to process subscription and storefront payments.</li>
            <li><strong>Email:</strong> Zoho ZeptoMail, to deliver account, billing and supplier emails.</li>
            <li><strong>Live chat:</strong> tawk.to, if you use the chat window on our website.</li>
            <li><strong>Security and bot protection:</strong> Cloudflare, which serves our website and runs the Turnstile check on sign-in, sign-up, password reset and checkout forms. It processes your IP address and basic browser signals to tell people from automated attacks.</li>
            <li><strong>Hosting:</strong> our cloud hosting and database providers.</li>
        </ul>
        <p>We may also disclose data where the law requires it, or to protect the rights and safety of SalesDock, our customers or the public.</p>

        <h2 id="storage">6. Storage and security</h2>
        <p>Data is sent over encrypted connections and passwords and PINs are stored as one-way hashes. Each shop's records are kept separate from other shops, and access to your data by our staff is limited to support and maintenance. Some of our providers may store data outside Nigeria; where they do, we rely on safeguards allowed under the Nigeria Data Protection Act.</p>

        <h2 id="retention">7. How long we keep it</h2>
        <ul>
            <li>We keep your shop data for as long as your account exists, including after a trial or plan ends, so you can pick up where you left off.</li>
            <li>When a shop is deleted, it can be restored for 30 days. After that it is permanently erased with everything tied to it.</li>
            <li>Signups that never verify their email are removed after 7 days.</li>
            <li>We may keep billing records for longer where tax or accounting law requires it.</li>
        </ul>

        <h2 id="rights">8. Your rights</h2>
        <p>You can ask to access, correct, export or delete your personal data, object to or restrict how we use it, or withdraw consent you have given. Shop owners can update most details directly in Settings. For anything else, email <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a> and we will respond within 30 days. You may also complain to the Nigeria Data Protection Commission.</p>

        <h2 id="cookies">9. Cookies</h2>
        <p>We use essential cookies to keep you signed in, protect forms from misuse and remember your settings. The live chat widget may set its own cookies when it loads. We do not use advertising cookies.</p>

        <h2 id="changes">10. Changes and contact</h2>
        <p>We may update this policy as SalesDock changes. If the changes are significant we will tell shop owners by email or in the dashboard before they take effect.</p>
        <p class="callout">Questions about privacy? Email <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a> or see our <a href="{{ route('help') }}">help and contact page</a>.</p>
    </article>
@endsection
