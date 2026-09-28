<?php

use App\Http\Controllers\Admin\RegistrationReviewController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Merchant\SupportController;
use App\Models\User;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SelectPlanController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Merchant\DeskController;
use App\Http\Controllers\Merchant\OfficeController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Controllers\Storefront\StorefrontController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = Auth::user();
    if ($user instanceof User) {
        return redirect($user->homePath());
    }
    $plans = \App\Models\Plan::query()->where('isActive', true)->orderBy('monthlyPrice')->get();
    return view('home', ['plans' => $plans]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'send'])->name('password.email');
    Route::get('/reset-password', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');
});

Route::get('/verify-email', VerifyEmailController::class)->name('verify-email');
Route::view('/registration-received', 'auth.received')->name('registration-received');

Route::get('/select-plan', [SelectPlanController::class, 'create'])->name('select-plan');
Route::post('/select-plan', [SelectPlanController::class, 'store'])->name('select-plan.store');
Route::get('/pay/{invoice}', [PaymentController::class, 'checkout'])->name('billing.checkout');
Route::get('/billing/verify', [PaymentController::class, 'verify'])->name('billing.verify');
Route::post('/billing/webhook', [PaymentController::class, 'webhook'])->name('billing.webhook');

Route::prefix('store/{slug}')->group(function () {
    Route::get('/', [StorefrontController::class, 'show'])->name('store.show');
    Route::get('/products/{product}', [StorefrontController::class, 'product'])->name('store.product');
    Route::post('/checkout', [StorefrontController::class, 'checkout'])->name('store.checkout');
    Route::get('/pay/{ref}', [StorefrontController::class, 'pay'])->name('store.pay');
    Route::get('/orders/{ref}', [StorefrontController::class, 'order'])->name('store.order');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('workspace')->group(function () {
        Route::view('/suspended', 'suspended')->name('suspended');
        Route::view('/unauthorized', 'unauthorized')->name('unauthorized');
        Route::view('/not-eligible', 'not-eligible')->name('not-eligible');

        Route::get('/dashboard', [DeskController::class, 'dashboard'])->name('dashboard');
        Route::get('/pos', [DeskController::class, 'pos'])->name('pos');
        Route::post('/pos', [DeskController::class, 'sell'])->name('pos.store');
        Route::post('/pos/void', [DeskController::class, 'voidRegister'])->name('pos.void');
        Route::post('/pos/holds/{hold}/restore', [DeskController::class, 'restoreHold'])->name('pos.holds.restore');
        Route::post('/pos/holds/{hold}/drop', [DeskController::class, 'dropHold'])->name('pos.holds.drop');
        Route::get('/products', [DeskController::class, 'products'])->name('products');
        Route::get('/products/sku', [DeskController::class, 'suggestSku'])->name('products.sku');
        Route::get('/products/starter', [DeskController::class, 'starterCatalogue'])->name('products.starter');
        Route::post('/products/starter', [DeskController::class, 'importStarter'])->name('products.starter.import');
        Route::post('/products', [DeskController::class, 'storeProduct'])->name('products.store');
        Route::post('/products/bulk', [DeskController::class, 'bulkProducts'])->name('products.bulk');
        Route::post('/products/csv', [DeskController::class, 'importProducts'])->name('products.csv');
        Route::get('/products/template', [DeskController::class, 'productTemplate'])->name('products.template');
        Route::post('/products/catalogue', [DeskController::class, 'importCatalogue'])->name('products.catalogue');
        Route::get('/products/{product}', [DeskController::class, 'showProduct'])->name('products.show');
        Route::put('/products/{product}', [DeskController::class, 'updateProduct'])->name('products.update');
        Route::post('/products/{product}/deactivate', [DeskController::class, 'deactivateProduct'])->name('products.deactivate');
        Route::post('/products/{product}/stock', [DeskController::class, 'adjustProductStock'])->name('products.stock');
        Route::post('/products/{product}/promo', [DeskController::class, 'attachProductPromo'])->name('products.promo');
        Route::post('/products/{product}/variants', [DeskController::class, 'storeVariant'])->name('products.variants.store');
        Route::get('/orders', [DeskController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [DeskController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/complete', [DeskController::class, 'completeOrder'])->name('orders.complete');
        Route::post('/orders/{order}/status', [DeskController::class, 'orderStatus'])->name('orders.status');
        Route::post('/orders/{order}/void', [DeskController::class, 'voidOrder'])->name('orders.void');
        Route::get('/customers', [DeskController::class, 'customers'])->name('customers');
        Route::post('/customers', [DeskController::class, 'storeCustomer'])->name('customers.store');
        Route::get('/customers/{customer}', [DeskController::class, 'showCustomer'])->name('customers.show');
        Route::put('/customers/{customer}', [DeskController::class, 'updateCustomer'])->name('customers.update');
        Route::delete('/customers/{customer}', [DeskController::class, 'destroyCustomer'])->name('customers.destroy');
        Route::get('/refunds', [DeskController::class, 'refunds'])->name('refunds');
        Route::post('/refunds', [DeskController::class, 'storeRefund'])->name('refunds.store');
        Route::post('/refunds/{refund}/approve', [DeskController::class, 'approveRefund'])->name('refunds.approve');
        Route::post('/refunds/{refund}/reject', [DeskController::class, 'rejectRefund'])->name('refunds.reject');
        Route::post('/refunds/{refund}/process', [DeskController::class, 'processRefund'])->name('refunds.process');
        Route::get('/inventory', [DeskController::class, 'inventory'])->name('inventory');
        Route::post('/inventory/adjust', [DeskController::class, 'adjustStock'])->name('inventory.adjust');
        Route::get('/inventory/purchase-orders', [DeskController::class, 'purchaseOrders'])->name('inventory.orders');
        Route::post('/inventory/purchase-orders', [DeskController::class, 'storePurchaseOrder'])->name('inventory.orders.store');
        Route::get('/inventory/purchase-orders/{purchaseOrder}', [DeskController::class, 'showPurchaseOrder'])->name('inventory.orders.show');
        Route::post('/inventory/purchase-orders/{purchaseOrder}/send', [DeskController::class, 'sendPurchaseOrder'])->name('inventory.orders.send');
        Route::post('/inventory/purchase-orders/{purchaseOrder}/cancel', [DeskController::class, 'cancelPurchaseOrder'])->name('inventory.orders.cancel');
        Route::post('/inventory/purchase-orders/{purchaseOrder}/receive', [DeskController::class, 'receivePurchaseOrder'])->name('inventory.orders.receive');
        Route::get('/suppliers', [DeskController::class, 'suppliers'])->name('suppliers');
        Route::post('/suppliers', [DeskController::class, 'storeSupplier'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [DeskController::class, 'showSupplier'])->name('suppliers.show');
        Route::put('/suppliers/{supplier}', [DeskController::class, 'updateSupplier'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [DeskController::class, 'destroySupplier'])->name('suppliers.destroy');
        Route::get('/promotions', [DeskController::class, 'promotions'])->name('promotions');
        Route::post('/promotions', [DeskController::class, 'storePromotion'])->name('promotions.store');
        Route::put('/promotions/{promotion}', [DeskController::class, 'updatePromotion'])->name('promotions.update');
        Route::post('/promotions/{promotion}/toggle', [DeskController::class, 'togglePromotion'])->name('promotions.toggle');
        Route::delete('/promotions/{promotion}', [DeskController::class, 'destroyPromotion'])->name('promotions.destroy');

        Route::get('/financials/ledger', [OfficeController::class, 'ledger'])->name('financials');
        Route::get('/financials/ledger/{ledger}', [OfficeController::class, 'showLedger'])->name('financials.show');
        Route::post('/financials/goals', [OfficeController::class, 'storeGoal'])->name('financials.goals.store');
        Route::get('/financials/tax-filings', [OfficeController::class, 'taxFilings'])->name('tax-filings');
        Route::get('/financials/tax-filings/{filing}/pdf', [OfficeController::class, 'taxFilingPdf'])->name('tax-filings.pdf');
        Route::get('/financials/tax-filings/{filing}', [OfficeController::class, 'showTaxFiling'])->name('tax-filings.show');
        Route::post('/financials/tax-filings/{filing}/file', [OfficeController::class, 'fileTaxFiling'])->name('tax-filings.file');
        Route::get('/expenses', [OfficeController::class, 'expenses'])->name('expenses');
        Route::post('/expenses', [OfficeController::class, 'storeExpense'])->name('expenses.store');
        Route::put('/expenses/{expense}', [OfficeController::class, 'updateExpense'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [OfficeController::class, 'destroyExpense'])->name('expenses.destroy');
        Route::get('/analytics', [OfficeController::class, 'analytics'])->name('analytics');
        Route::get('/staff', [OfficeController::class, 'staff'])->name('staff');
        Route::post('/staff', [OfficeController::class, 'storeStaff'])->name('staff.store');
        Route::get('/staff/{user}', [OfficeController::class, 'showStaff'])->name('staff.show');
        Route::put('/staff/{user}', [OfficeController::class, 'updateStaff'])->name('staff.update');
        Route::post('/staff/{user}/status', [OfficeController::class, 'toggleStaff'])->name('staff.status');
        Route::post('/staff/{user}/pin', [OfficeController::class, 'resetStaffPin'])->name('staff.pin');
        Route::get('/payroll', [OfficeController::class, 'payroll'])->name('payroll');
        Route::post('/payroll', [OfficeController::class, 'storePayroll'])->name('payroll.store');
        Route::put('/payroll/{payroll}', [OfficeController::class, 'updatePayroll'])->name('payroll.update');
        Route::get('/payroll/{payroll}/print', [OfficeController::class, 'printPayroll'])->name('payroll.print');
        Route::post('/payroll/{payroll}/pay', [OfficeController::class, 'payPayroll'])->name('payroll.pay');
        Route::get('/branches', [OfficeController::class, 'branches'])->name('branches');
        Route::post('/branches', [OfficeController::class, 'storeBranch'])->name('branches.store');
        Route::put('/branches/{branch}', [OfficeController::class, 'updateBranch'])->name('branches.update');
        Route::post('/branches/{branch}/status', [OfficeController::class, 'toggleBranch'])->name('branches.status');
        Route::get('/settings', [OfficeController::class, 'settings'])->name('settings');
        Route::post('/settings', [OfficeController::class, 'updateSettings'])->name('settings.update');
        Route::get('/roles', [OfficeController::class, 'roles'])->name('roles');
        Route::post('/roles', [OfficeController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [OfficeController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [OfficeController::class, 'destroyRole'])->name('roles.destroy');
        Route::get('/audit-logs', [OfficeController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/activity-logs', [OfficeController::class, 'activityLogs'])->name('activity-logs');
        Route::get('/notifications', [OfficeController::class, 'notifications'])->name('notifications');
        Route::get('/notifications/unread', [OfficeController::class, 'notificationsUnread'])->name('notifications.unread');
        Route::post('/notifications/read', [OfficeController::class, 'readNotifications'])->name('notifications.read');
        Route::post('/notifications/{notification}/read', [OfficeController::class, 'readNotification'])->name('notifications.read-one');

        Route::get('/support', [SupportController::class, 'index'])->name('support');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');
        Route::get('/support/unread', [SupportController::class, 'unread'])->name('support.unread');
        Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
        Route::get('/support/{ticket}/stream', [SupportController::class, 'stream'])->name('support.stream');
        Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::post('/support/{ticket}/close', [SupportController::class, 'close'])->name('support.close');
        Route::get('/profile', [OfficeController::class, 'profile'])->name('profile');
        Route::post('/profile', [OfficeController::class, 'updateProfile'])->name('profile.update');
        Route::get('/billing', [OfficeController::class, 'billing'])->name('billing');
        Route::post('/billing', [OfficeController::class, 'choosePlan'])->name('billing.choose');
        Route::get('/billing/invoices/{invoice}/view', [OfficeController::class, 'invoice'])->name('billing.invoice');
        Route::get('/billing/invoices/{invoice}', [PaymentController::class, 'pay'])->name('billing.pay');
        Route::get('/account', [OfficeController::class, 'account'])->name('account');
        Route::get('/account/loyalty', [OfficeController::class, 'loyalty'])->name('account.loyalty');

        Route::middleware('reviewer')->group(function () {
            Route::get('/admin/registrations', [RegistrationReviewController::class, 'index'])->name('admin.registrations');
            Route::get('/admin/registrations/{registration}', [RegistrationReviewController::class, 'show'])->name('admin.registrations.show');
            Route::post('/admin/registrations/{registration}/approve', [RegistrationReviewController::class, 'approve'])->name('admin.registrations.approve');
            Route::post('/admin/registrations/{registration}/reject', [RegistrationReviewController::class, 'reject'])->name('admin.registrations.reject');
            Route::post('/admin/registrations/{registration}/resend', [RegistrationReviewController::class, 'resend'])->name('admin.registrations.resend');
        });

        Route::middleware('super')->prefix('admin')->group(function () {
            Route::get('/', [PlatformController::class, 'home'])->name('admin.home');
            Route::get('/tenants', [PlatformController::class, 'tenants'])->name('admin.tenants');
            Route::get('/tenants/{tenant}', [PlatformController::class, 'tenant'])->name('admin.tenants.show');
            Route::post('/tenants/{tenant}', [PlatformController::class, 'updateTenant'])->name('admin.tenants.update');
            Route::delete('/tenants/{tenant}', [PlatformController::class, 'destroyTenant'])->name('admin.tenants.destroy');
            Route::delete('/tenants/{tenant}/users/{user}', [PlatformController::class, 'destroyUser'])->name('admin.tenants.users.destroy');
            Route::get('/subscriptions', [PlatformController::class, 'subscriptions'])->name('admin.subscriptions');
            Route::get('/billing', [PlatformController::class, 'billing'])->name('admin.billing');
            Route::post('/plans', [PlatformController::class, 'storePlan'])->name('admin.plans.store');
            Route::put('/plans/{plan}', [PlatformController::class, 'updatePlan'])->name('admin.plans.update');
            Route::post('/plans/{plan}/active', [PlatformController::class, 'setPlanActive'])->name('admin.plans.active');
            Route::get('/plans', [PlatformController::class, 'plans'])->name('admin.plans');
            Route::get('/revenue', [PlatformController::class, 'revenue'])->name('admin.revenue');
            Route::get('/analytics', [PlatformController::class, 'analytics'])->name('admin.analytics');
            Route::get('/tax-filings', [PlatformController::class, 'taxFilings'])->name('admin.tax-filings');
            Route::get('/users', [PlatformController::class, 'users'])->name('admin.users');
            Route::post('/users/{user}', [PlatformController::class, 'updateUser'])->name('admin.users.update');
            Route::delete('/users/{user}', [PlatformController::class, 'destroyPlatformUser'])->name('admin.users.destroy');
            Route::get('/customers', [PlatformController::class, 'customers'])->name('admin.customers');
            Route::get('/catalogue', [PlatformController::class, 'catalogue'])->name('admin.catalogue');
            Route::post('/catalogue/categories', [PlatformController::class, 'storeCategory'])->name('admin.catalogue.categories.store');
            Route::put('/catalogue/categories', [PlatformController::class, 'updateCategory'])->name('admin.catalogue.categories.update');
            Route::delete('/catalogue/categories', [PlatformController::class, 'destroyCategory'])->name('admin.catalogue.categories.destroy');
            Route::post('/catalogue', [PlatformController::class, 'storeCatalogue'])->name('admin.catalogue.store');
            Route::put('/catalogue/{item}', [PlatformController::class, 'updateCatalogue'])->name('admin.catalogue.update');
            Route::delete('/catalogue/{item}', [PlatformController::class, 'destroyCatalogue'])->name('admin.catalogue.destroy');
            Route::get('/audit-logs', [PlatformController::class, 'auditLogs'])->name('admin.audit-logs');
            Route::get('/activity-logs', [PlatformController::class, 'activityLogs'])->name('admin.activity-logs');
            Route::get('/webhooks', [PlatformController::class, 'webhooks'])->name('admin.webhooks');
            Route::get('/support', [AdminSupportController::class, 'index'])->name('admin.support');
            Route::get('/support/{ticket}', [AdminSupportController::class, 'show'])->name('admin.support.show');
            Route::post('/support/{ticket}/claim', [AdminSupportController::class, 'claim'])->name('admin.support.claim');
            Route::post('/support/{ticket}/reply', [AdminSupportController::class, 'reply'])->name('admin.support.reply');
            Route::post('/support/{ticket}/resolve', [AdminSupportController::class, 'resolve'])->name('admin.support.resolve');
        });
    });
});
