@extends('layouts.site')
@section('title', 'Recurring Card Payment Authorisation | SalesDock')
@section('description', 'How optional automatic software subscription renewals work, what you authorise, and how to cancel.')
@section('heading', 'Recurring Card Payment Authorisation')
@section('lead', 'An optional authorisation for software subscription renewals. Keep a copy for your business records.')
@section('width', 'narrow')
@section('content')
    <article class="legal">
        <p class="updated">Version 2026-11-02.2 · Last updated: 2 November 2026</p>
        <h2>1. Who you authorise</h2>
        <p>When you select automatic renewal at checkout, you authorise The Town Growers Hub Ltd (SalesDock) to instruct the payment provider identified at checkout to charge your authorised card for your business's software subscription. Only a signed-in shop owner can give this authorisation. You confirm that you are authorised to use the card.</p>
        <h2>2. Amount and schedule</h2>
        <p>The exact Naira amount, monthly, quarterly or annual interval, and first renewal date are shown before you continue to the provider. Subsequent renewals follow the paid subscription period, not a universal month-end date. For a date absent in a shorter month, the renewal falls on that month's last day. Automatic renewal does not start a free trial charge without your separate opt-in and a successful card payment.</p>
        <p>If an introductory offer applies, checkout discloses the discounted price, the number of paid billing periods it lasts (including the first payment), and the regular price afterwards. By selecting automatic renewal, you approve that exact schedule, including the disclosed return to the regular price. Introductory offers are for a shop's first paid subscription purchase; switching plans does not restart an offer. Unused discounted periods continue on manual renewals of the same plan and interval while its agreed regular price remains unchanged.</p>
        <p>Other price, plan, interval or provider changes require a new checkout and fresh approval. Opening checkout for a different plan or interval pauses the previous automatic renewal. A successful payment activates the selected plan, starts a new paid period and replaces the previous renewal schedule. It does not automatically prorate or credit unused time. Your Billing page shows the current renewal status, next payment amount and date.</p>
        <h2>3. Your choice</h2>
        <p>Available intervals depend on the provider. Flutterwave automatic renewal supports monthly and quarterly subscriptions; annual subscriptions use a one-time card payment because Flutterwave card tokens expire after one year. Expired cards or tokens require a fresh card payment and authorisation.</p>
        <p>The automatic renewal box is unchecked by default. Leaving it unchecked makes the payment a one-time card payment. Declining or withdrawing automatic renewal does not remove access you have already paid for. If you opt in but the provider does not return a reusable authorisation, your successful payment still activates the subscription and we tell you to renew manually.</p>
        <h2>4. Scope and card security</h2>
        <p>This authorisation covers SalesDock software subscription fees only. It does not authorise us to debit the collection bank account where your shop receives sales proceeds, and it does not authorise additional stock purchases, storefront customer payments or delivery funding. Gateway fees or other charges, if applicable, must be disclosed by the provider before authorisation.</p>
        <p>Your card details are entered on the provider's hosted checkout. SalesDock stores an encrypted provider authorisation token and the email linked to it, plus limited card identifiers and your approval record. We do not store full card numbers, card PINs or CVV codes.</p>
        <h2>5. Reminders, failures and uncertainty</h2>
        <p>We send a renewal reminder through the dashboard and attempt email delivery before the renewal date. Email delivery depends on your address and our messaging service. One automatic charge submission is made for each billing period. A declined or unsupported renewal pauses automatic renewal and you can pay manually.</p>
        <p>If the provider's result is uncertain, we check the same payment reference rather than submitting another charge. Do not make a second payment while Billing says a renewal is awaiting confirmation. Contact support if it remains pending. We cannot promise that every bank or provider request will succeed. A missed paid-plan renewal normally has a seven-day grace period before access is restricted; free trials end on their stated date. Your records remain subject to the account retention rules in our Privacy Policy.</p>
        <h2>6. Cancellation</h2>
        <p>You can withdraw this authorisation at any time by signing in as the shop owner and selecting “Turn off automatic renewal” in Billing, or by contacting us at <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>. Cancellation stops future automatic submissions and does not cancel the remainder of your paid period. A payment already submitted before cancellation may still complete; contact support to review it. Cancellation is not itself a refund of a completed payment. Statutory rights are unaffected.</p>
        <h2>7. Your record and support</h2>
        <p>Your Billing page links to a printable record of the exact text, amount, interval, version and electronic acceptance time associated with your authorisation. We keep evidence of approval, withdrawal and payments for account administration, disputes and applicable legal obligations, as described in our <a href="{{ route('privacy') }}">Privacy Policy</a>. Our <a href="{{ route('terms') }}">Terms of Service</a> also apply.</p>
        <p class="callout">For billing questions, cancellation assistance or a disputed renewal, contact <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>. Do not send card numbers, PINs, CVVs or secret keys.</p>
    </article>
@endsection
