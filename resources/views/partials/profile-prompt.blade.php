@php
    $profile = \App\Http\Controllers\Auth\CompleteProfileController::prefill(auth()->user());
    $profileErrors = $errors->getBag('profile');
    $openNow = $profileErrors->any() || session('profile_prompt_now');
    $types = collect(\App\Http\Controllers\Auth\CompleteProfileController::BUSINESS_TYPES)->mapWithKeys(fn ($type) => [$type => $type])->all();
@endphp

<div class="ui-modal onboard" data-onboard-welcome hidden>
    <div class="ui-filter-backdrop"></div>
    <div class="ui-modal-sheet onboard-card" role="dialog" aria-modal="true" aria-labelledby="onboard-title">
        <img src="{{ asset('salesdockfavicon.png') }}" alt="" class="onboard-mark">
        <h2 id="onboard-title">Welcome to SalesDock</h2>
        <p>Let's get <strong>{{ $profile['businessName'] }}</strong> set up. Add a few business details so your receipts, store and reports carry the right information. It takes less than a minute.</p>
        <button type="button" class="onboard-go" data-onboard-continue>Continue</button>
    </div>
</div>

<div class="ui-modal onboard" data-onboard-form hidden>
    <div class="ui-filter-backdrop"></div>
    <form class="ui-modal-sheet wide onboard-form" method="post" action="{{ route('complete-profile.store') }}" role="dialog" aria-modal="true" aria-labelledby="onboard-form-title">
        @csrf
        <div class="ui-filter-head">
            <strong id="onboard-form-title">Your business details</strong>
        </div>
        <p class="muted">Fields marked <span class="req">*</span> are required.</p>
        @if ($profileErrors->any())
            <p class="flash error">{{ $profileErrors->first() }}</p>
        @endif
        <div class="ui-modal-grid">
            <label class="ui-field">
                <span>Owner name <span class="req">*</span></span>
                <input name="ownerName" value="{{ old('ownerName', $profile['ownerName']) }}" placeholder="John Doe" maxlength="255" required autocomplete="name">
            </label>
            <label class="ui-field">
                <span>Phone <span class="req">*</span></span>
                <input name="phone" value="{{ old('phone', $profile['phone']) }}" placeholder="+234..." maxlength="50" inputmode="tel" required autocomplete="tel">
            </label>
            <div class="wide">
                <x-ui.select name="businessType" label="Business type" :value="old('businessType', $profile['businessType'] ?? '')" :options="$types" required />
            </div>
            <label class="ui-field wide">
                <span>Business address <span class="req">*</span></span>
                <input name="address" value="{{ old('address', $profile['address']) }}" placeholder="12 Allen Avenue, Ikeja" maxlength="500" required autocomplete="street-address">
            </label>
            <label class="ui-field">
                <span>City <span class="req">*</span></span>
                <input name="city" value="{{ old('city', $profile['city']) }}" placeholder="Lagos" maxlength="120" required autocomplete="address-level2">
            </label>
            <label class="ui-field">
                <span>Website</span>
                <input name="website" value="{{ old('website', $profile['website']) }}" placeholder="www.yourbusiness.com" maxlength="255">
            </label>
            <label class="ui-field">
                <span>TIN</span>
                <input name="tin" value="{{ old('tin', $profile['tin']) }}" placeholder="Tax ID" maxlength="50">
            </label>
            <label class="ui-field">
                <span>RC number</span>
                <input name="rcNumber" value="{{ old('rcNumber', $profile['rcNumber']) }}" placeholder="RC123456" maxlength="50">
            </label>
        </div>
        <p class="muted onboard-note">These details appear on your receipts, online store and official reports.</p>
        <div class="ui-modal-actions">
            <button type="submit" class="onboard-go" data-loading="Saving…"><span data-label>Save and continue</span></button>
        </div>
    </form>
</div>

<script>
    (function () {
        const welcome = document.querySelector('[data-onboard-welcome]');
        const form = document.querySelector('[data-onboard-form]');
        if (!welcome || !form) return;
        const sheet = form.querySelector('form');
        const showForm = () => {
            welcome.hidden = true;
            form.hidden = false;
            form.querySelector('input[name="ownerName"]')?.focus();
        };

        welcome.querySelector('[data-onboard-continue]').addEventListener('click', showForm);

        sheet.addEventListener('submit', (event) => {
            const type = sheet.querySelector('[name="businessType"]');
            if (type && !type.value) {
                event.preventDefault();
                sheet.querySelector('[data-select-open]')?.focus();
                sheet.querySelector('[data-select-open]')?.click();
                return;
            }
            const submit = sheet.querySelector('[type="submit"]');
            if (!submit || submit.disabled) return;
            submit.disabled = true;
            submit.classList.add('is-busy');
            const label = submit.querySelector('[data-label]');
            if (label) label.textContent = submit.dataset.loading || 'Saving…';
        });

        @if ($openNow)
            showForm();
        @else
            setTimeout(() => { welcome.hidden = false; }, 3000);
        @endif
    })();
</script>
