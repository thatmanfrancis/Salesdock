@extends('layouts.app')

@section('title', 'Settings | SalesDock')

@php
    $sections = [
        'business' => 'Business',
        'vat' => 'VAT',
        'subscription' => 'Subscription',
    ];
    if ($opensStorefront ?? false) {
        $sections = [
            'business' => 'Business',
            'vat' => 'VAT',
            'storefront' => 'Storefront',
            'subscription' => 'Subscription',
        ];
    }
    $rate = old('globalVatRate', $vat->globalVatRate ?? $tenant->taxRate ?? '7.5');
    $inclusive = old('section') === 'vat' ? (bool) old('isInclusive') : (bool) $vat->isInclusive;
    $public = old('section') === 'storefront' ? (bool) old('isPublic') : ($store->exists ? (bool) $store->isPublic : true);
    $guests = old('section') === 'storefront' ? (bool) old('allowGuestOrder') : ($store->exists ? (bool) $store->allowGuestOrder : true);
    $accent = old('accentColor', $store->accentColor ?: '#16a34a');
    $soon = $days !== null && $days > 0 && $days <= 7;
    $ended = $days !== null && $days <= 0;
    $words = preg_split('/\s+/', trim((string) $tenant->name)) ?: [];
    $mark = strtoupper(mb_substr($words[0] ?? 'S', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    $vatLabel = rtrim(rtrim(number_format((float) ($vat->globalVatRate ?? $tenant->taxRate ?? 7.5), 2), '0'), '.').'%';
@endphp

@section('content')
    <div class="page">
        <div class="settings">
            <nav class="settings-nav" aria-label="Settings">
                @foreach ($sections as $key => $label)
                    <a href="{{ route('settings', ['section' => $key]) }}" @class(['on' => $section === $key]) @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>

            <div class="settings-main">
                @if ($section === 'business')
                    <section class="card profile-card">
                        <form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="business">
                            <div class="profile-layout">
                                <div class="profile-id">
                                    @if ($tenant->logoUrl)
                                        <img src="{{ $tenant->logoUrl }}" alt="">
                                    @else
                                        <span class="face">{{ $mark }}</span>
                                    @endif
                                    <strong>{{ $tenant->name }}</strong>
                                    @if ($tenant->email)
                                        <em>{{ $tenant->email }}</em>
                                    @endif
                                    <div class="pills">
                                        <span class="pill">VAT {{ $vatLabel }}</span>
                                        <span class="pill">{{ ($store->exists ? $store->isPublic : true) ? 'Public' : 'Private' }}</span>
                                    </div>
                                    <div class="csv-file" data-file>
                                        <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-file-input>
                                        <button type="button" class="tool" data-file-open>Change logo</button>
                                        <span data-file-name>JPEG, PNG, or WebP, up to 2 MB.</span>
                                    </div>
                                </div>
                                <div class="profile-fields">
                                    <label>
                                        <span>Business name <span class="req">*</span></span>
                                        <input name="name" value="{{ old('name', $tenant->name) }}" placeholder="Shop name" required>
                                    </label>
                                    <label>
                                        <span>Email</span>
                                        <input type="email" name="email" value="{{ old('email', $tenant->email) }}" placeholder="shop@email.com">
                                    </label>
                                    <label>
                                        <span>Phone</span>
                                        <input name="phone" value="{{ old('phone', $tenant->phone) }}" placeholder="0803 000 0000">
                                    </label>
                                    <label>
                                        <span>Website</span>
                                        <input name="website" value="{{ old('website', $tenant->website) }}" placeholder="https://">
                                    </label>
                                    <label>
                                        <span>Address</span>
                                        <input name="address" value="{{ old('address', $tenant->address) }}" placeholder="Street, city">
                                    </label>
                                    <label>
                                        <span>TIN</span>
                                        <input name="tin" value="{{ old('tin', $tenant->tin) }}" placeholder="Tax identification number">
                                    </label>
                                    <label>
                                        <span>RC number</span>
                                        <input name="rcNumber" value="{{ old('rcNumber', $tenant->rcNumber) }}" placeholder="Company registration number">
                                    </label>
                                    <div class="ui-modal-actions">
                                        <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </section>
                @elseif ($section === 'vat')
                    <section class="card profile-narrow">
                        <form method="post" action="{{ route('settings.update') }}" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="vat">
                            <p class="kicker">This rate is the one the till and the tax filings use.</p>
                            <label>
                                <span>VAT rate (%)</span>
                                <input type="number" name="globalVatRate" min="0" max="100" step="0.01" value="{{ $rate }}" required>
                            </label>
                            <label class="switch">
                                <span>Prices include VAT</span>
                                <input type="checkbox" name="isInclusive" value="1" @checked($inclusive)>
                            </label>
                            <div class="ui-modal-actions">
                                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                            </div>
                        </form>
                    </section>
                @elseif ($section === 'storefront')
                    <section class="card settings-sheet">
                        <form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="storefront">
                            @if ($storeUrl)
                                <div class="link-row">
                                    <span>Store link</span>
                                    <code data-store-url>{{ $storeUrl }}</code>
                                    <button type="button" data-copy>Copy</button>
                                </div>
                            @endif
                            <div class="settings-grid">
                                <label>
                                    <span>Store name</span>
                                    <input name="storeName" value="{{ old('storeName', $store->storeName) }}" placeholder="{{ $tenant->name }}">
                                </label>
                                <label>
                                    <span>Tagline</span>
                                    <input name="tagline" value="{{ old('tagline', $store->tagline) }}" placeholder="A short line under the name">
                                </label>
                                <div class="wide">
                                    <span class="field-label">Banner</span>
                                    <div class="csv-file" data-file>
                                        @if ($store->bannerImageUrl)
                                            <img class="banner" src="{{ $store->bannerImageUrl }}" alt="">
                                        @endif
                                        <input type="file" name="banner" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-file-input>
                                        <button type="button" data-file-open>Choose image</button>
                                        <span data-file-name>Recommended 1600 × 400. JPEG, PNG, or WebP, up to 2 MB.</span>
                                    </div>
                                </div>
                                <label>
                                    <span>Accent colour</span>
                                    <span class="colour">
                                        <input type="color" value="{{ $accent }}" data-colour>
                                        <input name="accentColor" value="{{ $accent }}" maxlength="7" data-colour-text>
                                    </span>
                                </label>
                                <label>
                                    <span>Custom domain</span>
                                    <input name="customDomain" value="{{ old('customDomain', $store->customDomain) }}" placeholder="shop.example.com">
                                </label>
                                <label class="wide">
                                    <span>Page title</span>
                                    <input name="metaTitle" value="{{ old('metaTitle', $store->metaTitle) }}" placeholder="Title for search">
                                </label>
                                <label class="wide">
                                    <span>Page description</span>
                                    <textarea name="metaDescription" maxlength="500" placeholder="A short description for search">{{ old('metaDescription', $store->metaDescription) }}</textarea>
                                </label>
                            </div>
                            <label class="switch">
                                <span>Store is public</span>
                                <input type="checkbox" name="isPublic" value="1" @checked($public)>
                            </label>
                            <label class="switch">
                                <span>Guests can order without an account</span>
                                <input type="checkbox" name="allowGuestOrder" value="1" @checked($guests)>
                            </label>
                            <p class="kicker">Bank transfer</p>
                            <div class="settings-grid">
                                <label>
                                    <span>Bank</span>
                                    <input name="bankName" value="{{ old('bankName', $store->bankName) }}" placeholder="Bank name">
                                </label>
                                <label>
                                    <span>Account number</span>
                                    <input name="bankAccount" value="{{ old('bankAccount', $store->bankAccount) }}" placeholder="0123456789" inputmode="numeric">
                                </label>
                                <label class="wide">
                                    <span>Account name</span>
                                    <input name="accountName" value="{{ old('accountName', $store->accountName) }}" placeholder="Name on the account">
                                </label>
                            </div>
                            <div class="ui-modal-actions">
                                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                            </div>
                        </form>
                    </section>
                @else
                    <section class="card profile-narrow">
                        @if (! $subscription)
                            <p class="empty">No subscription on this shop.</p>
                        @else
                            @if ($soon)
                                <p class="note soon">Your subscription ends in {{ $days }} {{ $days === 1 ? 'day' : 'days' }}. Renew to keep access.</p>
                            @elseif ($ended)
                                <p class="note ended">Your subscription has ended. Renew to keep access.</p>
                            @endif
                            <div class="supplier-facts">
                                <div>
                                    <span>Plan</span>
                                    <strong>{{ $subscription->plan?->name ?: '—' }}</strong>
                                </div>
                                <div>
                                    <span>Tier</span>
                                    <strong>{{ $subscription->plan?->tier ? ucfirst(strtolower($subscription->plan->tier)) : '—' }}</strong>
                                </div>
                                <div>
                                    <span>Status</span>
                                    <strong><span class="badge {{ $ended ? 'overdue' : ($soon ? 'pending' : 'active') }}">{{ ucfirst(strtolower($subscription->status)) }}</span></strong>
                                </div>
                                <div>
                                    <span>Started</span>
                                    <strong>{{ $subscription->currentPeriodStart?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</strong>
                                </div>
                                <div>
                                    <span>Ends</span>
                                    <strong>{{ $subscription->currentPeriodEnd?->timezone('Africa/Lagos')->format('d M Y') ?: '—' }}</strong>
                                </div>
                                <div>
                                    <span>Days left</span>
                                    <strong>{{ $days === null ? '—' : ($days <= 0 ? 'Ended' : $days) }}</strong>
                                </div>
                            </div>
                            <a class="btn" href="{{ route('billing') }}">Change plan</a>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-file]').forEach((box) => {
            const input = box.querySelector('[data-file-input]');
            const name = box.querySelector('[data-file-name]');
            const hint = name.textContent;
            box.querySelector('[data-file-open]').addEventListener('click', () => input.click());
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                name.textContent = file ? file.name : hint;
            });
        });
        document.querySelectorAll('[data-colour]').forEach((picker) => {
            const text = picker.parentElement.querySelector('[data-colour-text]');
            picker.addEventListener('input', () => { text.value = picker.value; });
            text.addEventListener('input', () => {
                if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
            });
        });
        const copy = document.querySelector('[data-copy]');
        if (copy) {
            copy.addEventListener('click', async () => {
                const url = document.querySelector('[data-store-url]').textContent;
                try {
                    await navigator.clipboard.writeText(url);
                    copy.textContent = 'Copied';
                    setTimeout(() => { copy.textContent = 'Copy'; }, 1500);
                } catch (error) {
                    copy.textContent = 'Copy the link';
                }
            });
        }
        document.querySelectorAll('form[data-busy]').forEach((box) => {
            box.addEventListener('submit', () => {
                const button = box.querySelector('[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('is-busy');
                const label = button.querySelector('[data-label]');
                if (label) label.textContent = button.dataset.loading || 'Saving…';
            });
        });
    </script>
@endsection
