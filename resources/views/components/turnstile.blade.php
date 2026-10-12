@props(['action'])

@if (\App\Support\Turnstile::needsChallenge())
    <div {{ $attributes->merge(['class' => 'cf-turnstile']) }}
        data-sitekey="{{ \App\Support\Turnstile::siteKey() }}"
        data-action="{{ $action }}"
        data-theme="light"
        data-size="flexible"
        data-callback="salesdockCaptcha"
        data-expired-callback="salesdockCaptcha"
        data-error-callback="salesdockCaptcha"
        data-timeout-callback="salesdockCaptcha"></div>
    @once
        <script>
            window.salesdockCaptcha = function () {
                document.querySelectorAll('.cf-turnstile').forEach((widget) => {
                    const form = widget.closest('form');
                    if (!form) return;
                    if (form.hasAttribute('data-gate')) {
                        window.salesdockGate?.(form);
                        return;
                    }
                    const passed = !!form.querySelector('[name="cf-turnstile-response"]')?.value;
                    form.querySelectorAll('[data-captcha-submit]').forEach((button) => { button.disabled = !passed; });
                });
            };
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
