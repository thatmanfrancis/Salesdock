@php
    $here = '/'.ltrim(request()->path(), '/');
    $items = collect($nav ?? []);
    $rootPath = str_starts_with($here, '/admin') ? '/admin' : '/dashboard';
    $root = $items->firstWhere('path', $rootPath);
    $section = $items
        ->filter(fn ($item) => $item['path'] !== $rootPath && ($here === $item['path'] || str_starts_with($here, $item['path'].'/')))
        ->sortByDesc(fn ($item) => strlen($item['path']))
        ->first();
    $trail = [];
    if ($root) {
        $trail[] = ['label' => $root['label'], 'href' => $root['href']];
    }
    if ($section) {
        $trail[] = ['label' => $section['label'], 'href' => $section['href']];
        $rest = trim(substr($here, strlen($section['path'])), '/');
        if ($rest !== '') {
            $piece = str($rest)->before('/')->toString();
            $trail[] = [
                'label' => strlen($piece) > 18 ? 'Details' : str($piece)->headline()->toString(),
                'href' => request()->fullUrl(),
            ];
        }
    }
    if ($trail === []) {
        $trail[] = ['label' => 'Home', 'href' => url('/dashboard')];
    }
@endphp
<nav class="crumbs" aria-label="Breadcrumb">
    @foreach ($trail as $crumb)
        @if (! $loop->first)
            <span class="sep" aria-hidden="true">/</span>
        @endif
        <a href="{{ $crumb['href'] }}" @class(['current' => $loop->last])>{{ $crumb['label'] }}</a>
    @endforeach
</nav>
