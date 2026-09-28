@extends('errors::minimal')

@section('title', 'Page not found')
@section('code', '404')
@section('message', 'Page not found')
@section('hint', "The page you're looking for doesn't exist or has been moved.")
@section('actions')
    <button type="button" class="btn" onclick="history.back()">Go back</button>
@endsection
