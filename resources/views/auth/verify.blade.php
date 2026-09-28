@extends('layouts.guest')

@section('title', ($status === 'success' ? 'Email verified' : 'Verification failed').' | SalesDock')

@section('content')
    <x-auth.shell :title="$status === 'success' ? 'Email Verified!' : 'Verification Failed'" :lead="$status === 'success' ? 'Thanks for verifying your email. We\'ve received your registration and our team is reviewing it.' : $message">
        @if ($status === 'success')
            <div class="space-y-1 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-left">
                <p class="text-xs font-semibold text-green-700">What happens next?</p>
                <p class="text-xs text-green-600">We'll send you an email once your account is approved — usually within 24 hours.</p>
            </div>
            <a href="{{ route('login') }}" class="text-sm font-medium text-green-600">← Back to login</a>
        @else
            <a href="{{ route('register') }}" class="text-sm font-medium text-green-600">Try registering again</a>
        @endif
    </x-auth.shell>
@endsection
