@extends('layouts.site')

@section('title', 'Terms of Service | SalesDock')
@section('description', 'The terms that apply when you use SalesDock.')
@section('heading', 'Terms of Service')
@section('lead', 'The agreement between your business and SalesDock. Please read it before you sign up.')
@section('width', 'narrow')

@php
    $sections = [
        'agreement' => 'The agreement',
        'account' => 'Your account',
        'trial' => 'Free trial',
        'billing' => 'Plans and billing',
        'ended' => 'When a plan ends',
        'use' => 'Acceptable use',
        'data' => 'Your data',
        'storefront' => 'Storefront and payments',
        'availability' => 'Availability',
        'liability' => 'Liability',
        'closing' => 'Closing your account',
        'general' => 'General',
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

        <h2 id="agreement">1. The agreement</h2>
        <p>These terms are an agreement between your business and The Town Growers Hub Ltd ("SalesDock", "we", "us"). By creating an account or using SalesDock you accept these terms and our <a href="{{ route('privacy') }}">Privacy Policy</a>. If you sign up on behalf of a business, you confirm you are allowed to bind that business.</p>

        <h2 id="account">2. Your account</h2>
        <ul>
            <li>Give accurate business and contact details and keep them up to date.</li>
            <li>Keep passwords and PINs private. You are responsible for everything done by you and the staff you add.</li>
            <li>Tell us straight away at <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a> if you think your account has been misused.</li>
        </ul>

        <h2 id="trial">3. Free trial</h2>
        <p>New shops get a 30-day free trial with every feature unlocked. No card is needed to start. When the trial ends you must choose a paid plan to keep using the dashboard. There is no free plan.</p>

        <h2 id="billing">4. Plans and billing</h2>
        <ul>
            <li>Plans are priced in Naira and billed monthly, quarterly or annually, in advance, through our payment partner Flutterwave.</li>
            <li>The features and limits of each plan are shown on our website and on your Billing page.</li>
            <li>At the end of each billing period you renew by paying from your Billing page. We will remind you by email and in the dashboard when payment is due.</li>
            <li>We may change prices with at least 30 days' notice. New prices apply from your next billing period.</li>
            <li>Payments are non-refundable except where the law requires otherwise or we agree in writing.</li>
        </ul>

        <h2 id="ended">5. When a plan ends or payment is missed</h2>
        <p>If a renewal is not paid, your account enters a 7-day grace period and keeps working. If payment is still not received after the grace period, the subscription ends and the dashboard is locked until the shop owner chooses a plan. Your data is kept while the account exists, so everything is still there when you pay.</p>

        <h2 id="use">6. Acceptable use</h2>
        <p>You must not use SalesDock to:</p>
        <ul>
            <li>break any law, including tax, consumer protection and data protection laws;</li>
            <li>sell goods or services that are illegal in Nigeria;</li>
            <li>send spam or misleading messages to customers or suppliers;</li>
            <li>try to access other shops' data, test or bypass our security, or overload the service;</li>
            <li>copy, resell or reverse engineer SalesDock.</li>
        </ul>
        <p>We may suspend an account that breaks these rules. Where we can, we will tell you first and give you a chance to fix the problem.</p>

        <h2 id="data">7. Your data</h2>
        <p>The products, sales, customers and other records you enter belong to your business. You give us permission to store and process them only to provide SalesDock to you. Some records, such as expenses, can be exported from the dashboard, and you can ask us for a copy of your other records at any time. How we handle personal data is explained in our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

        <h2 id="storefront">8. Storefront and payments</h2>
        <p>If you use the online storefront you are the seller. You are responsible for your prices, product descriptions, delivery, returns and customer service. Payments taken through the storefront are handled by Flutterwave and are also subject to Flutterwave's terms.</p>

        <h2 id="availability">9. Availability and changes</h2>
        <p>We work hard to keep SalesDock available and the point of sale keeps recording sales offline when your internet drops. We may still need short maintenance windows, and we cannot promise the service will never be interrupted. We may improve, change or remove features over time; we will give notice before removing a feature that is part of a paid plan.</p>

        <h2 id="liability">10. Liability</h2>
        <p>SalesDock is a tool to help you run your shop; you remain responsible for your business decisions, records and tax filings. To the extent the law allows, we are not liable for indirect losses such as lost profits or lost business, and our total liability for any claim is limited to the fees you paid us in the 3 months before the claim. Nothing in these terms limits liability that cannot be limited by law.</p>

        <h2 id="closing">11. Closing your account</h2>
        <p>You can stop using SalesDock at any time by not renewing your plan, or email us to delete your shop. A deleted shop can be restored within 30 days; after that it is permanently erased. We may close accounts that seriously or repeatedly break these terms.</p>

        <h2 id="general">12. General</h2>
        <p>These terms are governed by the laws of the Federal Republic of Nigeria, and the courts of Nigeria have jurisdiction over any dispute. We may update these terms; if the changes are significant we will tell shop owners at least 14 days before they take effect. Continuing to use SalesDock after that means you accept the new terms.</p>
        <p class="callout">Questions about these terms? Email <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a> or visit our <a href="{{ route('help') }}">help and contact page</a>.</p>
    </article>
@endsection
