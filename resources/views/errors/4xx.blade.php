@extends('errors::minimal')

@section('title', "This page isn't available")
@section('code', $exception->getStatusCode())
@section('message', "This page isn't available")
@section('hint', 'The request could not be completed.')
