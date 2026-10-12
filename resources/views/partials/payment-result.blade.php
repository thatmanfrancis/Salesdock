@php
    $result = \App\Support\PlanTour::pull(auth()->user()?->tenantId, $nav ?? [], (bool) ($opensStorefront ?? false));
@endphp

@if ($result)
    @php
        $success = $result['state'] === 'success';
        $amount = $result['invoice'] ? '₦'.number_format((float) $result['invoice']->amount, 2) : null;
        $until = $result['invoice']?->periodEnd?->timezone('Africa/Lagos')->format('d M Y');
        $steps = $result['steps'];
    @endphp
    <div class="ui-modal pay-result" id="pay-result" role="dialog" aria-modal="true" aria-labelledby="pay-result-title">
        <button type="button" class="ui-filter-backdrop" data-pay-close aria-label="Close"></button>
        <div class="ui-modal-sheet">
            <section class="pay-panel" data-pay-step="0">
                <div @class(['pay-icon', 'ok' => $success, 'bad' => ! $success])>
                    @if ($success)
                        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17"/></svg>
                    @endif
                </div>
                <h2 id="pay-result-title">
                    @if ($success)
                        Payment successful
                    @elseif ($result['state'] === 'cancelled')
                        Payment cancelled
                    @else
                        Payment failed
                    @endif
                </h2>
                @if ($success)
                    <p class="muted">
                        {{ $amount ? $amount.' received' : 'Payment received' }}{{ $result['plan'] ? ' · '.$result['plan']->name.' plan' : '' }}{{ $until ? ' · active until '.$until : '' }}.
                    </p>
                    @if ($result['limits'])
                        <ul class="pay-limits">
                            @foreach ($result['limits'] as $limit)
                                <li>{{ $limit }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($steps)
                        <p>Let's take a quick look at what your plan unlocks.</p>
                    @endif
                @else
                    <p class="muted">{{ $result['message'] ?: 'Your card was not charged. You can try again.' }}</p>
                @endif
                <div class="ui-modal-actions">
                    @if ($success)
                        <button type="button" data-pay-close>{{ $steps ? 'Skip tour' : 'Done' }}</button>
                        @if ($steps)
                            <button type="button" class="primary" data-pay-next>Show me around</button>
                        @endif
                    @else
                        <a class="btn ghost" href="{{ route('support') }}">Contact support</a>
                        @if ($result['retryUrl'])
                            <a class="btn" href="{{ $result['retryUrl'] }}">Try again</a>
                        @else
                            <button type="button" data-pay-close>Close</button>
                        @endif
                    @endif
                </div>
            </section>

            @foreach ($steps as $index => $step)
                <section class="pay-panel" data-pay-step="{{ $index + 1 }}" hidden>
                    <span class="pay-count">Step {{ $index + 1 }} of {{ count($steps) }}</span>
                    <div class="pay-icon tour">@include('partials.icon', ['name' => $step['icon']])</div>
                    <h2>{{ $step['title'] }}</h2>
                    <p class="muted">{{ $step['body'] }}</p>
                    <a class="pay-open" href="{{ $step['href'] }}">{{ $step['cta'] }} →</a>
                    <div class="pay-dots" aria-hidden="true">
                        @foreach ($steps as $dot => $unused)
                            <span @class(['on' => $dot === $index])></span>
                        @endforeach
                    </div>
                    <div class="ui-modal-actions">
                        <button type="button" data-pay-back>Back</button>
                        @if ($index + 1 < count($steps))
                            <button type="button" class="primary" data-pay-next>Next</button>
                        @else
                            <button type="button" class="primary" data-pay-close>Finish</button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
    <script>
        (function () {
            const modal = document.getElementById('pay-result');
            if (!modal) return;
            const panels = [...modal.querySelectorAll('[data-pay-step]')];
            let current = 0;
            const show = (index) => {
                current = Math.max(0, Math.min(index, panels.length - 1));
                panels.forEach((panel, i) => { panel.hidden = i !== current; });
            };
            modal.addEventListener('click', (event) => {
                if (event.target.closest('[data-pay-next]')) show(current + 1);
                if (event.target.closest('[data-pay-back]')) show(current - 1);
                if (event.target.closest('[data-pay-close]')) modal.hidden = true;
            });
            document.addEventListener('keydown', (event) => {
                if (modal.hidden) return;
                if (event.key === 'Escape') modal.hidden = true;
                if (event.key === 'ArrowRight' && current < panels.length - 1) show(current + 1);
                if (event.key === 'ArrowLeft' && current > 0) show(current - 1);
            });
        })();
    </script>
@endif
