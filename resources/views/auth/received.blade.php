@extends('layouts.guest')

@section('title', 'Application received | SalesDock')

@section('content')
    <x-auth.shell title="Application Received!" lead="Your email has been successfully verified.">
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-5 text-left text-blue-900">
            <p class="mb-2 font-semibold">What happens next?</p>
            <p class="text-sm leading-relaxed">Our team is currently reviewing your registration. This process typically takes <strong>24-48 hours</strong>. Once your account is approved, you will receive another email with instructions on how to log in and get started.</p>
        </div>
        <p class="text-sm text-gray-500">Please keep an eye on your inbox (and spam folder) for further updates from the SalesDock team.</p>
    </x-auth.shell>
@endsection
