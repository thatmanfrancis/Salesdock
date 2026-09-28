@extends('layouts.app')

@section('title', 'Not eligible | SalesDock')

@section('content')
    <div class="page">
        <section class="gate">
            <span class="seal" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            </span>
            <h1>You're not eligible for this</h1>
            <p class="muted">This page is not included on your plan. Open billing to change it.</p>
            <a class="btn" href="{{ route('billing') }}">Open billing</a>
        </section>
    </div>
@endsection
