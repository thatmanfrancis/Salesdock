@extends('layouts.app')

@section('title', 'Platform | SalesDock')

@section('content')
    <div class="page">
        <div class="page-head">
            <div>
                <h1>Platform</h1>
                <p class="muted">SalesDock admin</p>
            </div>
            <x-ui.filter
                :action="route('admin.home')"
                :period="$period"
                :from="$from"
                :to="$to"
                :active="$period !== '30'"
                label="Filter sales"
                :options="[
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    '7' => 'Last 7 days',
                    '30' => 'Last 30 days',
                    'custom' => 'Custom range',
                ]"
            />
        </div>

        <div class="stats even">
            @foreach ($stats as $stat)
                <article>
                    <span>{{ $stat['label'] }}</span>
                    <strong>{{ $stat['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <section class="card">
            <h2>Sales · {{ $periodLabel }}</h2>
            <div @class(['day-chart', 'platform', 'wide' => $wide])>
                @foreach ($chart as $bar)
                    <div>
                        <i style="height: {{ $bar['height'] > 0 ? max($bar['height'], 8) : 0 }}%" title="{{ $bar['title'] }}"></i>
                        <span>{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
