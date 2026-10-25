@extends('layouts.app')

@section('title', 'Settings | SalesDock')

@php
    $sections = [
        'business' => 'Business',
        'vat' => 'VAT',
        'delivery' => 'Delivery',
        'subscription' => 'Subscription',
    ];
    if ($opensStorefront ?? false) {
        $sections = [
            'business' => 'Business',
            'vat' => 'VAT',
            'storefront' => 'Storefront',
            'delivery' => 'Delivery',
            'subscription' => 'Subscription',
        ];
    }
    if ($managesSuppliers ?? false) {
        $sections = array_slice($sections, 0, -1, true) + ['restock' => 'Restock'] + ['subscription' => 'Subscription'];
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
                            <p class="kicker">Payout account</p>
                            <p class="hint">Online store card payments settle to this account via {{ $gatewayLabel ?? 'the payment gateway' }}. SalesDock keeps a {{ number_format((float) ($platformSplitPercent ?? 1.5), 1) }}% service fee. Low-stock WhatsApp alerts use your shop phone and each supplier’s WhatsApp number.</p>
                            @if ($payoutConnected ?? false)
                                <p class="muted">Payouts are connected.</p>
                            @endif
                            <div class="settings-grid">
                                <div class="wide">
                                    <x-ui.select name="bankCode" label="Bank" :value="old('bankCode', $store->bankCode)" :options="['' => 'Choose your bank'] + ($banks ?? [])" required />
                                </div>
                                <label>
                                    <span>Account number <span class="req">*</span></span>
                                    <input name="bankAccount" value="{{ old('bankAccount', $store->bankAccount) }}" placeholder="0123456789" inputmode="numeric" required maxlength="20">
                                </label>
                                <label>
                                    <span>Account name</span>
                                    <input name="accountName" value="{{ old('accountName', $store->accountName) }}" placeholder="Confirmed from your bank when possible" maxlength="255">
                                </label>
                            </div>
                            <div class="ui-modal-actions">
                                <button type="submit" data-loading="Saving…"><span data-label>Save</span></button>
                            </div>
                        </form>
                    </section>
                @elseif ($section === 'delivery')
                    <section class="card profile-narrow">
                        <form method="post" action="{{ route('settings.delivery') }}" data-busy>
                            @csrf
                            <p class="kicker">Fez Delivery</p>
                            <p class="hint">Connect <strong>your own Fez Business account</strong>. You fund Fez directly in the Fez app or Business Portal — SalesDock never collects delivery top-ups, and refunds for eligible cancellations return to your Fez wallet.</p>
                            @if (!($deliveryConfig['enabled'] ?? true))
                                <p class="note ended">Fez delivery is currently disabled by SalesDock admin.</p>
                            @endif
                            @if (($deliveryAttention ?? 0) > 0)
                                <p class="note soon">{{ $deliveryAttention }} deliver{{ $deliveryAttention === 1 ? 'y needs' : 'ies need' }} attention (waiting for Fez wallet funds, failed, or uncertain). Open the affected orders to refresh or retry.</p>
                            @endif
                            @if ($deliveryAccount?->exists)
                                <div class="supplier-facts">
                                    <div><span>Status</span><strong>{{ str_replace('_', ' ', $deliveryAccount->status) }}</strong></div>
                                    <div><span>Organisation</span><strong>{{ $deliveryAccount->orgName ?: '—' }}</strong></div>
                                    <div><span>Last tested</span><strong>{{ $deliveryAccount->lastTestedAt?->format('d M Y H:i') ?: 'Not tested' }}</strong></div>
                                </div>
                                @if ($deliveryAccount->lastError)
                                    <p class="note ended">{{ $deliveryAccount->lastError }}</p>
                                @endif
                            @endif
                            <div class="settings-grid">
                                <label><span>Environment</span>
                                    <select name="environment">
                                        <option value="sandbox" @selected(old('environment', $deliveryAccount->environment ?? 'sandbox') === 'sandbox')>Sandbox</option>
                                        <option value="production" @selected(old('environment', $deliveryAccount->environment ?? 'sandbox') === 'production')>Production</option>
                                    </select>
                                </label>
                                <label><span>Custom API base URL (only if Fez gives you one)</span><input name="baseUrl" value="{{ old('baseUrl', $deliveryAccount->baseUrl) }}" placeholder="Leave blank to use SalesDock's Fez endpoint"></label>
                                <label><span>Fez user ID</span><input name="fezUserId" value="{{ old('fezUserId') }}" placeholder="{{ $deliveryAccount?->exists ? 'Saved — leave blank to keep' : 'Your Fez Business user ID' }}" autocomplete="off"></label>
                                <label><span>Fez password</span><input type="password" name="fezPassword" placeholder="{{ $deliveryAccount?->exists ? 'Saved — leave blank to keep' : 'Your Fez Business password' }}" autocomplete="new-password"></label>
                                <label><span>Fez secret key (if Fez gave you one)</span><input type="password" name="fezSecretKey" placeholder="{{ $deliveryAccount?->secretKey ? 'Saved — leave blank to keep' : 'Optional until Fez confirms' }}" autocomplete="off"></label>
                                <label><span>Pickup contact name</span><input name="pickupName" value="{{ old('pickupName', $deliveryAccount->pickupName ?: $tenant->name) }}"></label>
                                <label><span>Pickup phone</span><input name="pickupPhone" value="{{ old('pickupPhone', $deliveryAccount->pickupPhone ?: $tenant->phone) }}"></label>
                                <label class="wide"><span>Pickup address</span><textarea name="pickupAddress" rows="2">{{ old('pickupAddress', $deliveryAccount->pickupAddress ?: $tenant->address) }}</textarea></label>
                                <label><span>Pickup state</span>
                                    <select name="pickupState">
                                        <option value="">Choose state…</option>
                                        @foreach ($deliveryStates as $state)
                                            <option value="{{ $state }}" @selected(old('pickupState', $deliveryAccount->pickupState) === $state)>{{ $state }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label><span>Default parcel weight (kg)</span><input type="number" step="0.01" min="0.1" name="defaultWeightKg" value="{{ old('defaultWeightKg', $deliveryAccount->defaultWeightKg ?? 1) }}"></label>
                                <label><span>Packaging weight (kg)</span><input type="number" step="0.01" min="0" name="packagingWeightKg" value="{{ old('packagingWeightKg', $deliveryAccount->packagingWeightKg ?? 0) }}"></label>
                            </div>
                            <label class="switch"><span>Enable Fez delivery at checkout</span><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $deliveryAccount->enabled ?? false))></label>
                            <label class="switch"><span>Automatically book Fez after payment is confirmed</span><input type="checkbox" name="autoBook" value="1" @checked(old('autoBook', $deliveryAccount->autoBook ?? false))></label>
                            <p class="hint">Add each product's weight on its product page. If a product has no weight, SalesDock uses the default parcel weight above. Keep enough funds in your Fez wallet; if Fez reports “Pending Payment”, top up in Fez and refresh the order here.</p>
                            @if (($deliveryConfig['portal_url'] ?? '') !== '')
                                <p><a href="{{ $deliveryConfig['portal_url'] }}" target="_blank" rel="noreferrer">Open Fez Business Portal</a></p>
                            @endif
                            <div class="ui-modal-actions">
                                <button type="submit" data-loading="Saving…"><span data-label>Save Fez settings</span></button>
                            </div>
                        </form>
                        @if ($deliveryAccount?->exists)
                            <form method="post" action="{{ route('settings.delivery.test') }}">
                                @csrf
                                <button class="tool" type="submit">Test connection</button>
                            </form>
                        @endif
                    </section>
                @elseif ($section === 'restock')
                    <section class="card profile-narrow">
                        <form method="post" action="{{ route('settings.update') }}" data-busy>
                            @csrf
                            <input type="hidden" name="section" value="restock">
                            <p class="kicker">Low-stock restock drafts</p>
                            <p class="hint restock-intro">When a product drops to its low-stock threshold, SalesDock drafts a purchase order to that product's supplier. You review it, change anything you like, then send it. Nothing reaches a supplier without your confirmation.</p>
                            <label class="switch">
                                <span>Draft purchase orders when stock runs low</span>
                                <input type="checkbox" name="restockDrafts" value="1" @checked($tenant->restockDrafts)>
                            </label>
                            <div class="supplier-facts">
                                <div><span>Suppliers switched on</span><strong>{{ $restock['suppliersOn'] }} of {{ $restock['suppliers'] }}</strong></div>
                                <div><span>Products with a supplier</span><strong>{{ $restock['linkedProducts'] }}</strong></div>
                            </div>
                            @if ($restock['unlinkedProducts'] > 0)
                                <p class="hint">{{ $restock['unlinkedProducts'] }} {{ $restock['unlinkedProducts'] === 1 ? 'product has' : 'products have' }} no supplier, so {{ $restock['unlinkedProducts'] === 1 ? 'it' : 'they' }} will not be drafted. Pick a supplier and a low-stock threshold on each product.</p>
                            @endif
                            <p class="hint">You can switch individual suppliers on or off from <a href="{{ route('suppliers') }}">Suppliers</a>. When this switch is off, no supplier gets a draft.</p>
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
                                    <strong>{{ $subscription->status === 'TRIALING' ? 'Free trial (all features)' : ($subscription->plan?->name ?: '—') }}</strong>
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
