<script>
    (() => {
        let refreshing = false;
        setInterval(async () => {
            if (document.hidden || !navigator.onLine || refreshing
                || document.querySelector('.ui-modal:not([hidden]), [data-filter-panel]:not([hidden])')
                || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
            refreshing = true;
            try {
                const response = await fetch(location.href, {headers: {Accept: 'text/html'}, cache: 'no-store'});
                if (!response.ok || response.redirected) return;
                const next = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (next.title !== document.title) return;
                document.querySelectorAll('[data-live-region]').forEach((region) => {
                    const replacement = next.querySelector('[data-live-region="' + CSS.escape(region.dataset.liveRegion) + '"]');
                    if (replacement) region.replaceChildren(...Array.from(replacement.childNodes));
                });
            } catch {} finally { refreshing = false; }
        }, 15000);
    })();
</script>
