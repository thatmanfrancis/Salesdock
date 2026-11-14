<dialog class="bill-renew-dialog" data-subscription-cancelled aria-labelledby="subscription-cancelled-title">
    <h2 id="subscription-cancelled-title">{{ session('subscription_cancelled.merchantName') }}'s subscription has been cancelled</h2>
    <p>The merchant is now on Basic free access. POS, basic inventory, customers, sales history and refunds remain available so they can keep selling. Advanced paid features and new online storefront sales are unavailable. Automatic renewal is off, and existing data and invoices are retained.</p>
    <p>To subscribe again, the merchant can choose a plan in Billing and complete a new card payment. This restores the selected paid features. Automatic renewal requires fresh approval.</p>
    <p>The cancellation was recorded in the audit log and a notification was created for the merchant. No refund was issued automatically. Any payment already submitted may still complete and need review.</p>
    <div class="ui-modal-actions">
        <button type="button" data-cancel-result-close>Got it</button>
    </div>
</dialog>
