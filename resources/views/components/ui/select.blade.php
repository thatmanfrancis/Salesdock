@props([
    'name',
    'label',
    'value' => '',
    'options' => [],
    'required' => false,
])

<div class="ui-field" data-select>
    <span>{{ $label }} @if ($required)<span class="req">*</span>@endif</span>
    <button type="button" class="ui-trigger" data-select-open aria-haspopup="listbox" aria-expanded="false">
        <span data-select-label>{{ $options[$value] ?? 'Choose' }}</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-select-value>
    <ul class="ui-menu" role="listbox" hidden data-select-menu>
        @foreach ($options as $key => $text)
            <li>
                <button type="button" role="option" data-value="{{ $key }}" @if ((string) $key === (string) $value) aria-selected="true" @endif>{{ $text }}</button>
            </li>
        @endforeach
    </ul>
</div>
@include('components.ui.script')
