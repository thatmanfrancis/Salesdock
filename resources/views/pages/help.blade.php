@extends('layouts.site')

@section('title', 'Help & contact | SalesDock')
@section('description', 'Get help with SalesDock. Email, call or WhatsApp our team, or visit our office.')
@section('heading', 'How can we help?')
@section('lead', 'Talk to a real person on our team. We help with setup, billing, staff access and anything else in your shop.')

@php
    $digits = fn (?string $number) => preg_replace('/\D+/', '', (string) $number);
    $whatsapp = $digits($contact['whatsapp']);
    if (str_starts_with($whatsapp, '0')) {
        $whatsapp = '234'.substr($whatsapp, 1);
    }
    $mapLink = $contact['mapUrl']
        ?: ($contact['address'] ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($contact['address']) : null);
@endphp

@push('styles')
    <style>
        .contact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem; }
        .contact-card { display: grid; gap: 0.35rem; align-content: start; padding: 1.25rem; border: 1px solid var(--border); border-radius: 8px; background: #fff; transition: border-color .15s, box-shadow .15s; }
        a.contact-card:hover { border-color: var(--green); box-shadow: 0 6px 18px rgba(22,163,74,.08); }
        .contact-card .icon { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; margin-bottom: 0.4rem; border-radius: 6px; background: var(--green-s); color: var(--green); }
        .contact-card small { color: var(--muted); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .contact-card strong { font-size: 1rem; word-break: break-word; }
        .contact-card span { color: var(--muted); font-size: 0.85rem; }
        .visit { display: grid; grid-template-columns: 1.1fr 1fr; gap: 1.5rem; margin-top: 2.5rem; padding: 1.5rem; border: 1px solid var(--border); border-radius: 8px; background: #f9fafb; }
        .visit h2, .help-topics h2 { font-size: 1.35rem; margin-bottom: 0.5rem; }
        .visit address { font-style: normal; font-weight: 600; white-space: pre-line; }
        .visit .directions { margin-top: 0.75rem; color: var(--mid); white-space: pre-line; }
        .visit .map { display: grid; place-items: center; gap: 0.75rem; padding: 1.5rem; text-align: center; border: 1px dashed #cbd5e1; border-radius: 8px; background: #fff; color: var(--muted); }
        .help-topics { margin-top: 2.5rem; }
        .help-topics .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr)); gap: 1rem; margin-top: 1rem; }
        .help-topics article { padding: 1.25rem; border-left: 4px solid var(--green); background: var(--green-s); border-radius: 0 8px 8px 0; }
        .help-topics h3 { font-size: 1rem; margin-bottom: 0.35rem; }
        .help-topics p { color: var(--mid); font-size: 0.92rem; }
        .help-topics p a { color: var(--green-d); font-weight: 700; text-decoration: underline; }
        .safety { margin-top: 2.5rem; padding: 1rem 1.25rem; border: 1px solid #fde68a; background: #fffbeb; border-radius: 8px; color: #92400e; font-size: 0.9rem; }
        @media (max-width: 760px) { .visit { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="contact-grid">
        <a class="contact-card" href="mailto:{{ $contact['email'] }}">
            <div class="icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div>
            <small>Email</small>
            <strong>{{ $contact['email'] }}</strong>
            <span>We reply within one working day.</span>
        </a>
        @if ($contact['phone'])
            <a class="contact-card" href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}">
                <div class="icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg></div>
                <small>Phone</small>
                <strong>{{ $contact['phone'] }}</strong>
                <span>Call us during opening hours.</span>
            </a>
        @endif
        @if ($whatsapp)
            <a class="contact-card" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">
                <div class="icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.4A8.4 8.4 0 1 1 21 11.5z"/></svg></div>
                <small>WhatsApp</small>
                <strong>{{ $contact['whatsapp'] }}</strong>
                <span>Chat with us on WhatsApp.</span>
            </a>
        @endif
        @if ($contact['hours'])
            <div class="contact-card">
                <div class="icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
                <small>Opening hours</small>
                <strong>{{ $contact['hours'] }}</strong>
                <span>Closed on public holidays.</span>
            </div>
        @endif
    </div>

    @if ($contact['address'] || $contact['directions'])
        <section class="visit">
            <div>
                <h2>Visit our office</h2>
                @if ($contact['address'])
                    <address>{{ $contact['address'] }}</address>
                @endif
                @if ($contact['directions'])
                    <p class="directions">{{ $contact['directions'] }}</p>
                @endif
            </div>
            @if ($mapLink)
                <div class="map">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <p>Get turn-by-turn directions to our office.</p>
                    <a href="{{ $mapLink }}" target="_blank" rel="noopener" class="btn btn-primary">Open in Google Maps</a>
                </div>
            @endif
        </section>
    @endif

    <section class="help-topics">
        <h2>Quick answers</h2>
        <div class="grid">
            <article>
                <h3>I forgot my password</h3>
                <p>Reset it from the <a href="{{ route('password.request') }}">forgot password page</a>. Cashiers who sign in with a PIN should ask the shop owner to reset it.</p>
            </article>
            <article>
                <h3>Already a customer?</h3>
                <p>Sign in and open <strong>Support</strong> in your dashboard to raise a ticket. Our team can see your shop and help faster.</p>
            </article>
            <article>
                <h3>My trial or plan has ended</h3>
                <p>Your data is safe. The shop owner can sign in and choose a plan on the Billing page to unlock the dashboard again.</p>
            </article>
            <article>
                <h3>Didn't get our email?</h3>
                <p>Check your spam or promotions folder. If it is not there, email <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a> from the address you signed up with.</p>
            </article>
        </div>
    </section>

    <p class="safety"><strong>Stay safe:</strong> SalesDock staff will never ask for your password, PIN or card details by email, phone, WhatsApp or chat.</p>
@endsection
