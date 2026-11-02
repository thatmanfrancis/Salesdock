<label>
    <span>Name <span class="req">*</span></span>
    <input name="name" value="{{ $name }}" required maxlength="120">
</label>
<x-ui.select name="tier" label="Tier" :required="true" :value="$tier" :options="$tiers" />
<label>
    <span>Description</span>
    <textarea name="description" rows="3" maxlength="500">{{ $description }}</textarea>
</label>
<div class="ui-modal-grid">
    <label>
        <span>Monthly price <span class="req">*</span></span>
        <input type="number" name="monthlyPrice" value="{{ $monthly }}" min="0" max="99999999.99" step="0.01" required data-monthly>
    </label>
    <label>
        <span>Quarterly price <span class="req">*</span></span>
        <input type="number" name="quarterlyPrice" value="{{ $quarterly }}" min="0" max="99999999.99" step="0.01" required data-quarterly>
    </label>
    <label>
        <span>Annual price <span class="req">*</span></span>
        <input type="number" name="annualPrice" value="{{ $annual }}" min="0" max="99999999.99" step="0.01" required data-annual>
    </label>
    <label>
        <span>Branches <span class="req">*</span></span>
        <input type="number" name="maxBranches" value="{{ $branches }}" min="0" max="999999" step="1" required>
    </label>
    <label>
        <span>Users <span class="req">*</span></span>
        <input type="number" name="maxUsers" value="{{ $users }}" min="0" max="999999" step="1" required>
    </label>
    <label>
        <span>Products <span class="req">*</span></span>
        <input type="number" name="maxProducts" value="{{ $products }}" min="0" max="999999" step="1" required>
    </label>
</div>
<fieldset>
    <legend>Introductory pricing (optional)</legend>
    <p>For shops making their first paid subscription purchase. Each price applies for the specified number of paid billing periods, then the regular price applies. Leave a price blank to disable its offer. Existing agreed schedules remain unchanged.</p>
    @foreach (['MONTHLY' => 'Monthly', 'QUARTERLY' => 'Quarterly', 'ANNUALLY' => 'Annual'] as $offerCycle => $offerLabel)
        <div class="ui-modal-grid">
            <label>
                <span>{{ $offerLabel }} introductory price (NGN)</span>
                <input type="number" name="introductoryPricing[{{ $offerCycle }}][amount]" value="{{ $introductoryPricing[$offerCycle]['amount'] ?? '' }}" min="0.01" max="99999999.99" step="0.01">
            </label>
            <label>
                <span>{{ $offerLabel }} discounted billing periods</span>
                <input type="number" name="introductoryPricing[{{ $offerCycle }}][periods]" value="{{ $introductoryPricing[$offerCycle]['periods'] ?? 1 }}" min="1" max="12" step="1">
            </label>
        </div>
    @endforeach
</fieldset>
<div class="feature-picks">
    @foreach ($choices as $feature)
        <label>
            <input type="checkbox" name="features[]" value="{{ $feature }}" @checked(in_array($feature, $picked, true))>
            <span>{{ $feature }}</span>
        </label>
    @endforeach
</div>
