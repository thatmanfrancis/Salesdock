@extends('layouts.guest')

@section('title', 'Dashboard | SalesDock')

@section('content')
    <header>
        <h1>Dashboard</h1>
        <p>Signed in as {{ auth()->user()->name }}.</p>
    </header>
    <nav>
        @if (auth()->user()->isSuperAdmin || in_array(auth()->user()->role?->role, ['ADMIN', 'SUPER_ADMIN'], true))
            <a class="btn" href="{{ route('admin.registrations') }}">Review applications</a>
        @endif
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="ghost" type="submit">Sign out</button>
        </form>
    </nav>
@endsection
