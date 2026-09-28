@extends('layouts.guest')

@section('title', ($title ?? 'SalesDock').' | SalesDock')

@section('content')
    <x-auth.shell :title="$title" :lead="$body">
        <a href="{{ route('login') }}" class="text-sm font-medium text-green-600">← Back to login</a>
    </x-auth.shell>
@endsection
