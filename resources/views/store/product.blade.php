@extends('layouts.store')

@section('title', $product->name.' | '.$config->storeName)

@section('content')
    <section>
        <h2>{{ $product->name }}</h2>
        <p>{{ $product->description }}</p>
        <p>₦{{ number_format($product->price, 2) }} · {{ $product->currentStock }} in stock</p>
    </section>
    <form method="post" action="{{ route('store.checkout', $tenant->slug) }}">
        @csrf
        <input type="hidden" name="qty[{{ $product->id }}]" value="1">
        <label>Name<input name="customerName" required></label>
        <label>Email<input type="email" name="customerEmail" required></label>
        <label>Phone<input name="customerPhone" required></label>
        <label>Address<textarea name="customerAddress"></textarea></label>
        <label>Payment
            <select name="method">
                <option value="CARD">Card</option>
                <option value="TRANSFER">Bank transfer</option>
            </select>
        </label>
        <button>Buy</button>
    </form>
@endsection
