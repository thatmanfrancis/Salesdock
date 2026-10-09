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
