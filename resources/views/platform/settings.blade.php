@extends('layouts.app')

@section('title', 'Settings | SalesDock')

@section('content')
    <div class="page">
        <div class="page-head">
            <div class="heading">
                <h1>Settings</h1>
            </div>
        </div>

        <section class="card">
            <p class="kicker band">Payments</p>
            <p class="muted" style="margin-bottom:0.75rem">Choose Flutterwave or Paystack, set SalesDock’s share of storefront card payments, and paste API keys here. Leave a secret blank to keep the saved value. Env keys are only a bootstrap fallback until you save here.</p>
            <form method="post" action="{{ route('admin.settings.payments') }}" data-busy class="contact-form">
                @csrf
                <div class="ui-field wide">
                    <span>Active provider</span>
                    <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:0.35rem">
                        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:500">
                            <input type="radio" name="provider" value="flutterwave" @checked(old('provider', $payment['provider']) === 'flutterwave')>
                            Flutterwave
                        </label>
                        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:500">
                            <input type="radio" name="provider" value="paystack" @checked(old('provider', $payment['provider']) === 'paystack')>
                            Paystack
                        </label>
                    </div>
                </div>
                <label class="ui-field">
                    <span>Platform fee (%)</span>
                    <input type="number" name="platform_split_percent" value="{{ old('platform_split_percent', $payment['platform_split_percent']) }}" min="0" max="50" step="0.1" required>
                </label>
                <p class="muted wide" style="margin:0">Taken from each online store card payment before the rest settles to the shop’s bank. Shops must re-save their payout account after you change this, so the new split reaches the gateway.</p>

                <p class="kicker wide" style="margin-top:0.75rem">Flutterwave</p>
                <label class="ui-field wide">
                    <span>Public key</span>
                    <input type="text" name="flutterwave_public_key" value="{{ old('flutterwave_public_key', $payment['flutterwave']['public_key']) }}" maxlength="255" placeholder="FLWPUBK_TEST-…-X" autocomplete="off">
                </label>
                <label class="ui-field wide">
                    <span>Secret key {{ $payment['flutterwave']['has_secret_key'] ? '(saved: '.$payment['flutterwave']['secret_key_hint'].')' : '' }}</span>
                    <input type="password" name="flutterwave_secret_key" value="" maxlength="255" placeholder="{{ $payment['flutterwave']['has_secret_key'] ? 'Leave blank to keep current' : 'FLWSECK_TEST-…-X' }}" autocomplete="new-password">
                </label>
                <label class="ui-field wide">
                    <span>Webhook secret hash {{ $payment['flutterwave']['has_secret_hash'] ? '(saved: '.$payment['flutterwave']['secret_hash_hint'].')' : '' }}</span>
                    <input type="password" name="flutterwave_secret_hash" value="" maxlength="255" placeholder="{{ $payment['flutterwave']['has_secret_hash'] ? 'Leave blank to keep current' : 'Same value as Flutterwave → Settings → Webhooks' }}" autocomplete="new-password">
                </label>
                <p class="muted wide" style="margin:0">Webhook URL: <code>{{ $flutterwaveWebhook }}</code></p>

                <p class="kicker wide" style="margin-top:0.75rem">Paystack</p>
                <label class="ui-field wide">
                    <span>Public key</span>
                    <input type="text" name="paystack_public_key" value="{{ old('paystack_public_key', $payment['paystack']['public_key']) }}" maxlength="255" placeholder="pk_test_…" autocomplete="off">
                </label>
                <label class="ui-field wide">
                    <span>Secret key {{ $payment['paystack']['has_secret_key'] ? '(saved: '.$payment['paystack']['secret_key_hint'].')' : '' }}</span>
                    <input type="password" name="paystack_secret_key" value="" maxlength="255" placeholder="{{ $payment['paystack']['has_secret_key'] ? 'Leave blank to keep current' : 'sk_test_…' }}" autocomplete="new-password">
                </label>
                <p class="muted wide" style="margin:0">Webhook URL: <code>{{ $paystackWebhook }}</code> (Paystack uses your secret key to sign webhooks.)</p>

                <div class="wide">
                    <button type="submit" data-loading="Saving…"><span data-label>Save payment settings</span></button>
                </div>
            </form>
        </section>

        <section class="card">
            <p class="kicker band">Delivery (Fez)</p>
            <p class="muted" style="margin-bottom:0.75rem">Each merchant connects their own Fez Business account under Settings → Delivery and funds Fez directly. SalesDock never collects delivery top-ups. Use this section only for the global switch, API endpoints, and support links. Connected merchant accounts: <strong>{{ $deliveryAccounts }}</strong>. Deliveries needing attention: <strong>{{ $deliveryAttention }}</strong>.</p>
            <form method="post" action="{{ route('admin.settings.delivery') }}" data-busy class="contact-form">
                @csrf
                <label class="ui-field wide" style="display:flex;align-items:center;gap:0.5rem">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $delivery['enabled']))>
                    <span>Enable Fez delivery platform-wide</span>
                </label>
                <label class="ui-field">
                    <span>Sandbox API base URL</span>
                    <input type="url" name="sandbox_base_url" value="{{ old('sandbox_base_url', $delivery['sandbox_base_url']) }}" placeholder="https://apisandbox.fezdelivery.co/v1">
                </label>
                <label class="ui-field">
                    <span>Production API base URL</span>
                    <input type="url" name="production_base_url" value="{{ old('production_base_url', $delivery['production_base_url']) }}" placeholder="Ask Fez to confirm">
                </label>
                <label class="ui-field">
                    <span>Fez Business Portal URL</span>
                    <input type="url" name="portal_url" value="{{ old('portal_url', $delivery['portal_url']) }}" placeholder="https://…">
                </label>
                <label class="ui-field">
                    <span>Fez support URL</span>
                    <input type="url" name="support_url" value="{{ old('support_url', $delivery['support_url']) }}" placeholder="https://…">
                </label>
                <p class="muted wide" style="margin:0">Webhook URL given to Fez for merchant organisations: <code>{{ $fezWebhook }}</code></p>
                <div class="wide">
                    <button type="submit" data-loading="Saving…"><span data-label>Save delivery settings</span></button>
                </div>
            </form>
        </section>

        <section class="card">
            <p class="kicker band">Google Analytics</p>
            <p class="muted" style="margin-bottom:0.75rem">Paste your GA4 measurement ID to track visits across the marketing site, auth pages, and storefronts. Leave blank to turn tracking off. Find it in Google Analytics → Admin → Data streams → your web stream.</p>
            <form method="post" action="{{ route('admin.settings.analytics') }}" data-busy class="contact-form">
                @csrf
                <label class="ui-field">
                    <span>Measurement ID</span>
                    <input type="text" name="measurement_id" value="{{ old('measurement_id', $googleAnalyticsId) }}" maxlength="32" placeholder="G-XXXXXXXXXX" autocomplete="off">
                </label>
                <div class="wide">
                    <button type="submit" data-loading="Saving…"><span data-label>Save analytics</span></button>
                </div>
            </form>
        </section>

        <section class="card">
            <p class="kicker band">WhatsApp (Zoho SalesIQ)</p>
            <p class="muted" style="margin-bottom:0.75rem">Powers signup OTP codes, low-stock alerts, and purchase-order WhatsApp sends. Create approved templates in Zoho/Meta first, then paste the IDs here. Leave secrets blank to keep saved values. Emails still send for every flow.</p>
            <form method="post" action="{{ route('admin.settings.whatsapp') }}" data-busy class="contact-form">
                @csrf
                <label class="ui-field wide" style="display:flex;align-items:center;gap:0.5rem">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $zoho['enabled']))>
                    <span>Enable Zoho WhatsApp outbound</span>
                </label>
                <label class="ui-field">
                    <span>Accounts URL</span>
                    <input type="url" name="accounts_url" value="{{ old('accounts_url', $zoho['accounts_url']) }}" placeholder="https://accounts.zoho.com">
                </label>
                <label class="ui-field">
                    <span>SalesIQ API base</span>
                    <input type="url" name="api_base" value="{{ old('api_base', $zoho['api_base']) }}" placeholder="https://salesiq.zoho.com">
                </label>
                <label class="ui-field wide">
                    <span>Client ID</span>
                    <input type="text" name="client_id" value="{{ old('client_id', $zoho['client_id']) }}" autocomplete="off">
                </label>
                <label class="ui-field wide">
                    <span>Client secret {{ $zoho['has_client_secret'] ? '(saved: '.$zoho['client_secret_hint'].')' : '' }}</span>
                    <input type="password" name="client_secret" value="" placeholder="{{ $zoho['has_client_secret'] ? 'Leave blank to keep current' : '' }}" autocomplete="new-password">
                </label>
                <label class="ui-field wide">
                    <span>Refresh token {{ $zoho['has_refresh_token'] ? '(saved: '.$zoho['refresh_token_hint'].')' : '' }}</span>
                    <input type="password" name="refresh_token" value="" placeholder="{{ $zoho['has_refresh_token'] ? 'Leave blank to keep current' : '' }}" autocomplete="new-password">
                </label>
                <label class="ui-field">
                    <span>Screen name</span>
                    <input type="text" name="screen_name" value="{{ old('screen_name', $zoho['screen_name']) }}">
                </label>
                <label class="ui-field">
                    <span>App ID</span>
                    <input type="text" name="app_id" value="{{ old('app_id', $zoho['app_id']) }}">
                </label>
                <label class="ui-field">
                    <span>Department ID</span>
                    <input type="text" name="department_id" value="{{ old('department_id', $zoho['department_id']) }}">
                </label>
                <label class="ui-field">
                    <span>OTP template ID</span>
                    <input type="text" name="otp_template_id" value="{{ old('otp_template_id', $zoho['otp_template_id']) }}">
                </label>
                <label class="ui-field">
                    <span>Restock alert template ID</span>
                    <input type="text" name="restock_template_id" value="{{ old('restock_template_id', $zoho['restock_template_id']) }}">
                </label>
                <label class="ui-field">
                    <span>Purchase order template ID</span>
                    <input type="text" name="purchase_order_template_id" value="{{ old('purchase_order_template_id', $zoho['purchase_order_template_id']) }}" placeholder="Optional — falls back to restock template">
                </label>
                <label class="ui-field wide">
                    <span>SalesIQ widget embed (optional)</span>
                    <textarea name="salesiq_widget_code" rows="4" placeholder="Paste Zoho SalesIQ website widget snippet for live chat">{{ old('salesiq_widget_code', $zoho['salesiq_widget_code']) }}</textarea>
                </label>
                <p class="muted wide" style="margin:0">Public support WhatsApp (click-to-chat beside Tawk) still uses the number under Help page contact details below.</p>
                <div class="wide">
                    <button type="submit" data-loading="Saving…"><span data-label>Save WhatsApp settings</span></button>
                </div>
            </form>
        </section>

        <section class="card">
            <p class="kicker band">Admin email copies</p>
            <p class="muted" style="margin-bottom:0.75rem">These addresses get a copy of every signup email sent to applicants (verification, welcome, approval and rejection). Use it when a customer says they did not get an email. One address per line, up to 10.</p>
            <form method="post" action="{{ route('admin.settings.update') }}" data-busy>
                @csrf
                <div class="ui-field">
                    <span>Recipient emails</span>
                    <textarea name="notifyEmails" rows="5" placeholder="admin@salesdock.com">{{ old('notifyEmails', implode("\n", $notifyEmails)) }}</textarea>
                </div>
                <div style="margin-top:0.65rem">
                    <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                </div>
            </form>
        </section>

        <section class="card">
            <p class="kicker band">Help page contact details</p>
            <p class="muted" style="margin-bottom:0.75rem">Shown to everyone on the public <a href="{{ route('help') }}" target="_blank" class="link">Help &amp; contact</a> page. Leave a field empty to hide it.</p>
            <form method="post" action="{{ route('admin.settings.contact') }}" data-busy class="contact-form">
                @csrf
                <label class="ui-field">
                    <span>Support email</span>
                    <input type="email" name="email" value="{{ old('email', $contact['email']) }}" required maxlength="255">
                </label>
                <label class="ui-field">
                    <span>Phone</span>
                    <input type="tel" name="phone" value="{{ old('phone', $contact['phone']) }}" maxlength="40" placeholder="+234 800 000 0000">
                </label>
                <label class="ui-field">
                    <span>WhatsApp number</span>
                    <input type="tel" name="whatsapp" value="{{ old('whatsapp', $contact['whatsapp']) }}" maxlength="40" placeholder="+234 800 000 0000">
                </label>
                <label class="ui-field">
                    <span>Opening hours</span>
                    <input type="text" name="hours" value="{{ old('hours', $contact['hours']) }}" maxlength="120">
                </label>
                <label class="ui-field wide">
                    <span>Office address</span>
                    <textarea name="address" rows="2" maxlength="500">{{ old('address', $contact['address']) }}</textarea>
                </label>
                <label class="ui-field wide">
                    <span>Directions</span>
                    <textarea name="directions" rows="3" maxlength="2000" placeholder="e.g. Second floor, opposite the filling station. Parking at the back.">{{ old('directions', $contact['directions']) }}</textarea>
                </label>
                <label class="ui-field wide">
                    <span>Google Maps link</span>
                    <input type="url" name="mapUrl" value="{{ old('mapUrl', $contact['mapUrl']) }}" maxlength="1000" placeholder="https://maps.app.goo.gl/…">
                </label>
                <div class="wide">
                    <button type="submit" data-loading="Saving…"><span data-label>Save contact details</span></button>
                </div>
            </form>
        </section>
    </div>
@endsection
