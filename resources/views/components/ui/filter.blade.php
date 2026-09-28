@props([
    'action',
    'period' => 'today',
    'from' => '',
    'to' => '',
    'options' => [],
    'label' => 'Filter',
    'active' => null,
])

<div class="ui-filter" data-filter>
    <button type="button" class="ghost ui-filter-open @if ($active ?? ($period !== '' && $period !== 'today')) on @endif" data-filter-open aria-haspopup="dialog" aria-expanded="false" aria-label="{{ $label }}">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/></svg>
    </button>
    <div class="ui-filter-overlay" role="dialog" aria-modal="true" aria-label="{{ $label }}" hidden data-filter-panel>
        <button type="button" class="ui-filter-backdrop" data-filter-close aria-label="Close"></button>
        <form class="ui-filter-sheet" method="get" action="{{ $action }}">
            <div class="ui-filter-head">
                <strong>{{ $label }}</strong>
                <button type="button" class="ghost" data-filter-close aria-label="Close">✕</button>
            </div>
            {{ $slot }}
            @if ($options !== [])
                <x-ui.select name="period" label="Period" :value="$period" :options="$options" />
                <div class="ui-filter-dates">
                    <x-ui.date name="from" label="From" :value="$from" />
                    <x-ui.date name="to" label="To" :value="$to" />
                </div>
            @endif
            <div class="ui-filter-actions">
                <a class="ghost" href="{{ $action }}">Clear</a>
                <button type="submit">Apply</button>
            </div>
        </form>
    </div>
</div>
@include('components.ui.script')
