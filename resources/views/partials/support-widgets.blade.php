@php
    use App\Models\PlatformSetting;
    use App\Support\PhoneNumber;

    $contact = PlatformSetting::supportContact();
    $wa = PhoneNumber::waMe($contact['whatsapp'] ?? null);
    $zohoWidget = PlatformSetting::zohoWhatsApp()['salesiq_widget_code'] ?? '';
@endphp

@if (config('services.tawk.property_id') && config('services.tawk.widget_id'))
<script>
    var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
    (function(){
        var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
        s1.async=true;
        s1.src='https://embed.tawk.to/{{ config('services.tawk.property_id') }}/{{ config('services.tawk.widget_id') }}';
        s1.charset='UTF-8';
        s1.setAttribute('crossorigin','*');
        s0.parentNode.insertBefore(s1,s0);
    })();
</script>
@endif

@if ($zohoWidget !== '')
    {!! $zohoWidget !!}
@endif

@if ($wa)
<a href="https://wa.me/{{ $wa }}" class="sd-wa-fab" target="_blank" rel="noopener" aria-label="Chat on WhatsApp" title="Chat on WhatsApp">
    <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M16.02 3C9.4 3 4 8.36 4 14.92c0 2.1.56 4.15 1.63 5.96L4 29l8.33-1.58A12.1 12.1 0 0 0 16.02 27C22.64 27 28 21.64 28 15.08S22.64 3 16.02 3zm0 21.9c-1.8 0-3.56-.48-5.1-1.39l-.37-.22-4.94.94.95-4.78-.24-.38a9.7 9.7 0 0 1-1.5-5.17c0-5.35 4.4-9.7 9.8-9.7s9.8 4.35 9.8 9.7-4.4 9.7-9.8 9.7zm5.37-7.27c-.29-.15-1.73-.85-2-.95-.27-.1-.46-.15-.66.15-.2.29-.76.95-.93 1.14-.17.2-.34.22-.63.07-.29-.15-1.23-.45-2.35-1.43-.87-.77-1.45-1.72-1.62-2.01-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.2-.29.29-.49.1-.2.05-.37-.02-.52-.07-.15-.66-1.58-.9-2.16-.24-.58-.48-.5-.66-.51h-.56c-.2 0-.52.07-.79.37-.27.29-1.04 1.01-1.04 2.46s1.07 2.85 1.22 3.05c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.73-.7 1.97-1.38.24-.68.24-1.26.17-1.38-.07-.12-.27-.2-.56-.34z"/></svg>
</a>
<style>
    .sd-wa-fab {
        position: fixed;
        right: 1.1rem;
        bottom: 1.1rem;
        z-index: 2147483000;
        width: 3.4rem;
        height: 3.4rem;
        border-radius: 999px;
        background: #25d366;
        color: #fff;
        display: grid;
        place-items: center;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.25);
        text-decoration: none;
    }
    .sd-wa-fab:hover { filter: brightness(1.05); }
    @media (max-width: 640px) {
        .sd-wa-fab { right: 0.85rem; bottom: 0.85rem; }
    }
</style>
@endif
