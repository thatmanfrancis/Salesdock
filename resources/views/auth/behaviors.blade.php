<script>
    document.addEventListener('focusin', (event) => {
        if (event.target.matches('[data-no-fill]')) event.target.removeAttribute('readonly');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-eye]');
        if (!button) return;
        const input = button.parentElement.querySelector('input');
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.querySelector('[data-eye-open]').classList.toggle('hidden', reveal);
        button.querySelector('[data-eye-shut]').classList.toggle('hidden', !reveal);
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const button = event.submitter || event.target.querySelector('[type="submit"]');
        if (!button || button.disabled) return;
        button.disabled = true;
        button.textContent = button.dataset.loading || 'Please wait…';
    });

    window.salesdockGate = function (form) {
        const fields = [...form.querySelectorAll('input, textarea')].filter((field) => {
            if (field.disabled || field.type === 'hidden' || field.type === 'checkbox' || field.type === 'radio') return false;
            if ((field.name || '').startsWith('fake-')) return false;
            return field.required || field.getAttribute('minlength');
        });
        let ready = fields.every((field) => {
            const value = field.value.trim();
            if (!value) return false;
            if (field.name === 'email' || field.getAttribute('inputmode') === 'email') {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            }
            const min = Number(field.getAttribute('minlength') || 0);
            return value.length >= min;
        });
        const confirm = [...form.querySelectorAll('input')].find((field) => !field.disabled && field.name.endsWith('_confirmation'));
        if (confirm) {
            const other = form.querySelector('[name="' + confirm.name.replace('_confirmation', '') + '"]');
            if (!other || confirm.value === '' || confirm.value !== other.value) ready = false;
        }
        const names = new Set([...form.querySelectorAll('input[type="radio"][required]')].map((field) => field.name));
        names.forEach((name) => {
            if (![...form.querySelectorAll('[name="' + name + '"]')].some((field) => field.checked)) ready = false;
        });
        form.querySelectorAll('[type="submit"]').forEach((button) => {
            const panel = button.closest('.password-panel, .pin-panel');
            if (panel && panel.classList.contains('hidden')) return;
            button.disabled = !ready;
        });
    };

    document.addEventListener('input', (event) => {
        const form = event.target.closest('form[data-gate]');
        if (form) window.salesdockGate(form);
    });
    document.addEventListener('change', (event) => {
        const form = event.target.closest('form[data-gate]');
        if (form) window.salesdockGate(form);
    });
    document.querySelectorAll('form[data-gate]').forEach((form) => window.salesdockGate(form));
</script>
