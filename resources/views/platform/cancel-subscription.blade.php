<div data-subscription-cancellation>
<button type="button" class="quiet" data-cancel-open>Cancel subscription</button>
<dialog class="bill-renew-dialog" data-cancel-credentials aria-labelledby="cancel-subscription-{{ $tenant->id }}">
    <form method="post" action="{{ route('admin.tenants.subscription.cancel', $tenant) }}" data-cancel-form data-busy>
        @csrf
        @method('DELETE')
        <input type="hidden" name="final_confirmation" value="0">
        <h2 id="cancel-subscription-{{ $tenant->id }}">Cancel {{ $tenant->name }}'s subscription?</h2>
        <p>Enter the phrase and your admin password, then review what cancellation means before making your final decision.</p>
        <label>
            <span>Type <strong>DELETE {{ $tenant->name }}</strong> exactly to confirm</span>
            <input name="confirmation" required autocomplete="off" spellcheck="false" data-expected-confirmation="DELETE {{ $tenant->name }}">
        </label>
        <label>
            <span>Your admin login password</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <div class="ui-modal-actions">
            <button type="button" data-cancel-dismiss>Keep subscription</button>
            <button type="submit">Review cancellation</button>
        </div>
    </form>
</dialog>
<dialog class="bill-renew-dialog" data-cancel-review aria-labelledby="cancel-review-{{ $tenant->id }}">
    <h2 id="cancel-review-{{ $tenant->id }}">Are you sure you want to cancel {{ $tenant->name }}'s subscription?</h2>
    <p>No cancellation has happened yet. Confirming will:</p>
    <ul>
        <li>Move the merchant to Basic free access: POS, basic inventory, customers, sales history and refunds stay available.</li>
        <li>Remove advanced paid features and pause new online storefront sales.</li>
        <li>Stop future automatic renewal charges and remove the saved renewal authorisation.</li>
        <li>Keep the merchant's shop, staff, data and invoices. Billing and support remain accessible.</li>
    </ul>
    <p><strong>The merchant can keep selling and subscribe again later.</strong> A new paid plan restores its advanced features. Automatic renewal requires fresh approval.</p>
    <p>No refund is issued automatically. A payment already submitted may still complete and will be flagged for review.</p>
    <div class="ui-modal-actions">
        <button type="button" data-cancel-back>Go back</button>
        <button type="button" data-cancel-dismiss>Keep subscription</button>
        <button type="button" class="danger" data-cancel-confirm>Confirm cancellation</button>
    </div>
</dialog>
</div>
