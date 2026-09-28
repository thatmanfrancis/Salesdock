@extends('errors::minimal')

@section('title', "You can't open this")
@section('code', '403')
@section('message', "You can't open this")
@section('hint', in_array($exception->getMessage(), ['', 'Forbidden', 'This action is unauthorized.'], true) ? "You don't have access to this page." : $exception->getMessage())
