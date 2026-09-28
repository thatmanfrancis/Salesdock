@php
    $picked = old('form') === $form;
    $val = function (string $key) use ($picked, $supplier) {
        if ($picked) {
            return old($key);
        }

        return $supplier?->{$key} ?? '';
    };
@endphp
<div class="ui-modal-grid">
    <label class="wide">
        <span>Name <span class="req">*</span></span>
        <input name="name" value="{{ $val('name') }}" placeholder="Supplier name" required data-field="name">
    </label>
    <label>
        <span>Email</span>
        <input type="email" name="contactEmail" value="{{ $val('contactEmail') }}" placeholder="name@email.com" data-field="email">
    </label>
    <label>
        <span>Phone</span>
        <input name="contactPhone" value="{{ $val('contactPhone') }}" placeholder="0803 000 0000" data-field="phone">
    </label>
    <label>
        <span>WhatsApp</span>
        <input name="contactWhatsapp" value="{{ $val('contactWhatsapp') }}" placeholder="0803 000 0000" data-field="whatsapp">
    </label>
    <label>
        <span>Bank</span>
        <input name="bankName" value="{{ $val('bankName') }}" placeholder="Bank name" data-field="bank">
    </label>
    <label class="wide">
        <span>Address</span>
        <input name="address" value="{{ $val('address') }}" placeholder="Street, city" data-field="address">
    </label>
    <label class="wide">
        <span>Account number</span>
        <input name="bankAccount" value="{{ $val('bankAccount') }}" placeholder="0123456789" data-field="account">
    </label>
    <label class="wide">
        <span>Notes</span>
        <textarea name="notes" placeholder="Anything the shop should remember" data-field="notes">{{ $val('notes') }}</textarea>
    </label>
</div>
