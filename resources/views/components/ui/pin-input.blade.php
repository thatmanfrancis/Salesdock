@props(['name', 'label'])

<div class="ui-field" data-pin-input>
    <span id="{{ $name }}-label">{{ $label }} <span class="req">*</span></span>
    <div class="pin-digits" role="group" aria-labelledby="{{ $name }}-label">
        @for ($digit = 1; $digit <= 4; $digit++)
            <input type="password" inputmode="numeric" pattern="[0-9]" maxlength="1" required autocomplete="off" aria-label="{{ $label }} digit {{ $digit }} of 4" data-pin-digit>
        @endfor
    </div>
    <input type="hidden" name="{{ $name }}" value="" data-pin-value>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-pin-input]').forEach((field) => {
                const digits = Array.from(field.querySelectorAll('[data-pin-digit]'));
                const value = field.querySelector('[data-pin-value]');
                const sync = () => { value.value = digits.map((digit) => digit.value).join(''); };
                digits.forEach((digit, index) => {
                    digit.addEventListener('focus', () => digit.select());
                    digit.addEventListener('input', () => {
                        digit.value = digit.value.replace(/[^0-9]/g, '').slice(-1);
                        sync();
                        if (digit.value) digits[index + 1]?.focus();
                    });
                    digit.addEventListener('keydown', (event) => {
                        if (event.key === 'Backspace' && !digit.value) {
                            event.preventDefault();
                            if (index > 0) {
                                digits[index - 1].value = '';
                                digits[index - 1].focus();
                                sync();
                            }
                        } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                            event.preventDefault();
                            digits[index + (event.key === 'ArrowLeft' ? -1 : 1)]?.focus();
                        }
                    });
                    digit.addEventListener('paste', (event) => {
                        event.preventDefault();
                        const pasted = event.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 4 - index);
                        if (!pasted) return;
                        Array.from(pasted).forEach((character, offset) => { digits[index + offset].value = character; });
                        sync();
                        digits[Math.min(index + pasted.length, 3)].focus();
                    });
                });
                field.closest('form')?.addEventListener('reset', () => { digits.forEach((digit) => { digit.value = ''; }); value.value = ''; });
            });
        });
    </script>
@endonce
