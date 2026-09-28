<form method="post" action="{{ $form['action'] }}">
    @csrf
    @isset($form['method']) @method($form['method']) @endisset
    @foreach ($form['fields'] as $field)
        @if (($field['type'] ?? '') === 'hidden')
            <input type="hidden" name="{{ $field['name'] }}" value="{{ old($field['name'], $field['value'] ?? '') }}">
            @continue
        @endif
        <label>
            {{ $field['label'] }}
            @if (($field['type'] ?? 'text') === 'textarea')
                <textarea name="{{ $field['name'] }}">{{ old($field['name'], $field['value'] ?? '') }}</textarea>
            @elseif (($field['type'] ?? '') === 'select')
                <select name="{{ $field['name'] }}">
                    @foreach ($field['options'] as $value => $label)
                        <option value="{{ $value }}" @selected((string) old($field['name'], $field['value'] ?? '') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @elseif (($field['type'] ?? '') === 'checks')
                <span class="checks">
                    @foreach ($field['options'] as $value => $label)
                        <label><input type="checkbox" name="{{ $field['name'] }}[]" value="{{ $value }}" @checked(in_array($value, old($field['name'], $field['value'] ?? []), true))> {{ $label }}</label>
                    @endforeach
                </span>
            @else
                <input type="{{ $field['type'] ?? 'text' }}" name="{{ $field['name'] }}" value="{{ ($field['type'] ?? '') === 'password' ? '' : old($field['name'], $field['value'] ?? '') }}" @if (!empty($field['step'])) step="{{ $field['step'] }}" @endif @required(!empty($field['required']))>
            @endif
        </label>
    @endforeach
    <button>{{ $form['submit'] ?? 'Save' }}</button>
</form>
