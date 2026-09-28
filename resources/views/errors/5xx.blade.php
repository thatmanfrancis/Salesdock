@extends('errors::minimal')

@section('title', 'Something went wrong')
@section('code', $exception->getStatusCode())
@section('message', 'Something went wrong')
@section('hint', 'We hit a problem on our side. Try again in a moment.')
