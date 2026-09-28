@extends('layouts.app')

@section('title', $title.' | SalesDock')

@section('content')
    <div class="page">
        @if (! empty($intro) || ! empty($action))
            <div class="page-head">
                <div>
                    @isset($intro)<p class="muted">{{ $intro }}</p>@endisset
                </div>
                @isset($action)<a class="btn" href="{{ $action['href'] }}">{{ $action['label'] }}</a>@endisset
            </div>
        @endif

        @isset($stats)
            <div class="stats">
                @foreach ($stats as $stat)
                    <article>
                        <span>{{ $stat['label'] }}</span>
                        <strong>{{ $stat['value'] }}</strong>
                    </article>
                @endforeach
            </div>
        @endisset

        @isset($chart)
            <section class="card bars">
                <h2>Last 14 days</h2>
                @foreach ($chart as $bar)
                    <div>
                        <span>{{ $bar['label'] }} · {{ $bar['value'] }}</span>
                        <i style="--w: {{ $bar['width'] }}%"></i>
                    </div>
                @endforeach
            </section>
        @endisset

        @if (is_array($form ?? null))
            <section class="card">@include('merchant.form', ['form' => $form])</section>
        @endif
        @if (is_array($sideForm ?? null))
            <section class="card">@include('merchant.form', ['form' => $sideForm])</section>
        @endif

        @isset($columns)
            <section class="card">
                <table>
                    <thead>
                        <tr>@foreach ($columns as $column)<th>{{ $column }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>@foreach ($row as $cell)<td>{!! $cell !!}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endisset

        @isset($second)
            <section class="card">
                <h2>{{ $second['title'] }}</h2>
                <table>
                    <thead><tr>@foreach ($second['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($second['rows'] as $row)
                            <tr>@foreach ($row as $cell)<td>{!! $cell !!}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($second['columns']) }}">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        @endisset
    </div>
@endsection
