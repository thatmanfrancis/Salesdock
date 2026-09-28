@props([
    'name',
    'label',
    'value' => '',
    'placeholder' => 'Any date',
    'required' => false,
])

<div class="ui-field" data-date>
    <span>{{ $label }} @if ($required)<span class="req">*</span>@endif</span>
    <button type="button" class="ui-trigger" data-date-open aria-haspopup="dialog" aria-expanded="false">
        <span data-date-label data-empty="{{ $placeholder }}">{{ $value }}</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/></svg>
    </button>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-date-value>
    <div class="ui-calendar" role="dialog" hidden data-calendar>
        <div class="ui-calendar-nav">
            <button type="button" data-cal-prev aria-label="Previous month">‹</button>
            <strong data-cal-title></strong>
            <button type="button" data-cal-next aria-label="Next month">›</button>
        </div>
        <div class="ui-calendar-days" aria-hidden="true">
            <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
        </div>
        <div class="ui-calendar-grid" data-cal-grid></div>
    </div>
</div>
@include('components.ui.script')
