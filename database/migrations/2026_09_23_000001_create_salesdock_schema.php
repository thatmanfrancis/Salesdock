<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full SalesDock schema ported from prisma/schema.prisma.
 * Every Prisma scalar column, enum, unique, index, and foreign key is included.
 * Columns use Prisma names (taxRate, createdAt). Table names match @@map.
 */
return new class extends Migration
{
    public function up(): void
    {
        // retailos (and any Prisma database) already has these tables.
        if (Schema::hasTable('tenants')) {
            return;
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('currency')->default('NGN');
            $table->decimal('taxRate', 5, 2)->default(7.5);
            $table->string('logoUrl')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('tin')->nullable();
            $table->string('rcNumber')->nullable();
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->enum('approvalStatus', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->timestamp('approvedAt')->nullable();
            $table->string('approvedBy')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('isMain')->default(DB::raw('false'));
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('name');
            $table->enum('role', ['CLIENT', 'CASHIER', 'SUPERVISOR', 'MANAGER', 'ADMIN', 'SUPER_ADMIN'])->default('CASHIER');
            $table->jsonb('permissions');
            $table->boolean('isDefault')->default(DB::raw('false'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->unique(['tenantId', 'name']);
            $table->index(['tenantId']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('branchId')->nullable();
            $table->string('roleId');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('passwordHash')->nullable();
            $table->string('pin')->nullable();
            $table->boolean('pinIsDefault')->default(DB::raw('true'));
            $table->boolean('isSuperAdmin')->default(DB::raw('false'));
            $table->string('phone')->nullable();
            $table->string('avatarUrl')->nullable();
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->boolean('twoFaEnabled')->default(DB::raw('false'));
            $table->string('twoFaSecret')->nullable();
            $table->timestamp('lastLoginAt')->nullable();
            $table->timestamp('shiftStartedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['pin']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('name');
            $table->string('contactEmail')->nullable();
            $table->string('contactPhone')->nullable();
            $table->string('contactWhatsapp')->nullable();
            $table->string('address')->nullable();
            $table->string('bankName')->nullable();
            $table->string('bankAccount')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->integer('totalOrders')->default(0);
            $table->decimal('totalSpend', 10, 2)->default(0);
            $table->integer('loyaltyPoints')->default(0);
            $table->enum('firstChannel', ['POS', 'ONLINE', 'WHATSAPP', 'INSTAGRAM', 'LINK'])->default('POS');
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->unique(['tenantId', 'phone']);
            $table->index(['tenantId']);
            $table->index(['email']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('supplierId')->nullable();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('category')->default('General');
            $table->string('subCategory')->nullable();
            $table->string('imageUrl')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('originalPrice', 10, 2)->nullable();
            $table->decimal('costPrice', 10, 2)->default(0);
            $table->decimal('wholesalePrice', 10, 2)->nullable();
            $table->decimal('vatRateOverride', 5, 2)->nullable();
            $table->integer('currentStock')->default(0);
            $table->integer('reservedQty')->default(0);
            $table->integer('minThreshold')->default(5);
            $table->integer('reorderQty')->default(0);
            $table->integer('leadTimeDays')->default(0);
            $table->enum('availabilityMode', ['POS_ONLY', 'ONLINE_ONLY', 'BOTH', 'DISABLED'])->default('BOTH');
            $table->boolean('isDiscounted')->default(DB::raw('false'));
            $table->string('activePromoId')->nullable();
            $table->timestamp('expiresAt')->nullable();
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->unique(['tenantId', 'sku']);
            $table->unique(['tenantId', 'barcode']);
            $table->index(['tenantId']);
            $table->index(['barcode']);
            $table->index(['sku']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('productId');
            $table->string('tenantId');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->jsonb('attributes');
            $table->decimal('price', 10, 2);
            $table->decimal('costPrice', 10, 2)->default(0);
            $table->integer('currentStock')->default(0);
            $table->integer('reservedQty')->default(0);
            $table->string('imageUrl')->nullable();
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->unique(['productId', 'sku']);
            $table->unique(['tenantId', 'barcode']);
            $table->index(['productId']);
            $table->index(['tenantId']);
            $table->index(['barcode']);
            $table->index(['sku']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('productId');
            $table->string('tenantId');
            $table->enum('type', ['SALE', 'RETURN', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT', 'HOLD', 'HOLD_RELEASE', 'PURCHASE_RECEIVED', 'DAMAGE', 'SHRINKAGE']);
            $table->integer('quantity');
            $table->integer('beforeQty');
            $table->integer('afterQty');
            $table->string('reference')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['productId']);
            $table->index(['tenantId']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('branchId')->nullable();
            $table->string('cashierId')->nullable();
            $table->enum('status', ['PENDING', 'RESERVED', 'COMPLETED', 'CANCELLED', 'RETURNED'])->default('PENDING');
            $table->enum('channel', ['POS', 'ONLINE', 'WHATSAPP', 'INSTAGRAM', 'LINK'])->default('POS');
            $table->decimal('totalAmount', 10, 2);
            $table->decimal('discountAmount', 10, 2)->default(0);
            $table->decimal('taxAmount', 10, 2)->default(0);
            $table->decimal('netAmount', 10, 2)->default(0);
            $table->string('customerId')->nullable();
            $table->string('customerName')->nullable();
            $table->string('customerEmail')->nullable();
            $table->string('customerPhone')->nullable();
            $table->string('customerAddress')->nullable();
            $table->string('cancelReason')->nullable();
            $table->timestamp('cancelledAt')->nullable();
            $table->boolean('cancelRequested')->default(DB::raw('false'));
            $table->string('returnReason')->nullable();
            $table->timestamp('returnedAt')->nullable();
            $table->string('paymentLinkUrl')->nullable();
            $table->string('paymentRef')->nullable()->unique();
            $table->string('notes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['cashierId']);
            $table->index(['status']);
            $table->index(['channel']);
            $table->index(['createdAt']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('productId');
            $table->string('variantId')->nullable();
            $table->integer('quantity');
            $table->decimal('unitPrice', 10, 2);
            $table->decimal('originalPrice', 10, 2);
            $table->decimal('discountAmount', 10, 2)->default(0);
            $table->decimal('lineTotal', 10, 2);
            $table->boolean('isPriceOverridden')->default(DB::raw('false'));
            $table->string('overrideReason')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['orderId']);
            $table->index(['productId']);
        });

        Schema::create('holds', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('orderId');
            $table->string('cashierId')->nullable();
            $table->string('reference')->nullable();
            $table->enum('status', ['ACTIVE', 'EXPIRED', 'RESTORED', 'CANCELLED'])->default('ACTIVE');
            $table->timestamp('expiresAt');
            $table->timestamp('expiredAt')->nullable();
            $table->timestamp('restoredAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['orderId']);
            $table->index(['status']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('tenantId');
            $table->enum('gateway', ['PAYSTACK', 'FLUTTERWAVE', 'STRIPE', 'CASH', 'TRANSFER', 'CARD', 'POS_TERMINAL', 'MONNIFY'])->default('CASH');
            $table->enum('method', ['CASH', 'TRANSFER', 'CARD', 'POS_TERMINAL', 'PAYMENT_LINK', 'VIRTUAL_ACCOUNT'])->default('CASH');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['SUCCESS', 'FAILED', 'PENDING', 'REFUNDED'])->default('PENDING');
            $table->string('reference')->nullable()->unique();
            $table->string('gatewayRef')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('completedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['orderId']);
            $table->index(['tenantId']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('orderId');
            $table->string('transactionId')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('reason');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'PROCESSED'])->default('PENDING');
            $table->string('approvedBy')->nullable();
            $table->timestamp('processedAt')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['orderId']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('supplierId');
            $table->enum('status', ['DRAFT', 'SENT', 'RECEIVED', 'CANCELLED'])->default('DRAFT');
            $table->enum('urgency', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL', 'OUT_OF_STOCK'])->default('LOW');
            $table->decimal('totalCost', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamp('sentAt')->nullable();
            $table->timestamp('receivedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['supplierId']);
            $table->index(['status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('purchaseOrderId');
            $table->string('productId');
            $table->integer('quantityOrdered');
            $table->integer('quantityReceived')->default(0);
            $table->decimal('unitCost', 10, 2);
            $table->decimal('lineTotal', 10, 2);
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['purchaseOrderId']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('productId')->nullable();
            $table->string('name');
            $table->enum('discountType', ['PERCENTAGE', 'FIXED']);
            $table->decimal('discountValue', 10, 2);
            $table->timestamp('startDatetime');
            $table->timestamp('endDatetime');
            $table->boolean('isActive')->default(DB::raw('false'));
            $table->string('createdBy')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['productId']);
            $table->index(['isActive']);
        });

        Schema::create('vat_settings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId')->unique();
            $table->decimal('globalVatRate', 5, 2)->default(7.5);
            $table->boolean('isInclusive')->default(DB::raw('false'));
            $table->timestamp('updatedAt');
        });

        Schema::create('tax_filings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('period');
            $table->decimal('grossSales', 10, 2);
            $table->decimal('totalVat', 10, 2);
            $table->decimal('netSales', 10, 2);
            $table->decimal('totalTaxPaid', 10, 2)->nullable();
            $table->string('submissionRef')->nullable();
            $table->string('attachmentUrl')->nullable();
            $table->enum('status', ['PENDING', 'FILED', 'OVERDUE'])->default('PENDING');
            $table->timestamp('filedAt')->nullable();
            $table->timestamp('dueDate');
            $table->string('notes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->unique(['tenantId', 'period']);
            $table->index(['tenantId']);
        });

        Schema::create('sales_ledger', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('orderId');
            $table->string('transactionId')->nullable();
            $table->enum('channel', ['POS', 'ONLINE', 'WHATSAPP', 'INSTAGRAM', 'LINK']);
            $table->string('cashierId')->nullable();
            $table->string('customerName')->nullable();
            $table->string('paymentMethod');
            $table->string('orderStatus');
            $table->decimal('grossRevenue', 10, 2);
            $table->decimal('netRevenue', 10, 2);
            $table->decimal('totalVat', 10, 2);
            $table->decimal('totalCogs', 10, 2);
            $table->decimal('grossProfit', 10, 2)->default(0);
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['tenantId']);
            $table->index(['orderId']);
            $table->index(['createdAt']);
        });

        Schema::create('sales_ledger_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('ledgerId');
            $table->string('productId')->nullable();
            $table->string('sku');
            $table->string('productName');
            $table->integer('quantity');
            $table->decimal('costPrice', 10, 2);
            $table->decimal('sellingPrice', 10, 2);
            $table->decimal('lineTotal', 10, 2);
            $table->decimal('vatPercentage', 5, 2);
            $table->decimal('vatAmount', 10, 2);
            $table->decimal('grossLineTotal', 10, 2);
            $table->decimal('netLineTotal', 10, 2);
            $table->decimal('grossProfit', 10, 2)->default(0);
            $table->index(['ledgerId']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('userId')->nullable();
            $table->string('supervisorId')->nullable();
            $table->enum('action', ['PRICE_OVERRIDE', 'DISCOUNT_APPLIED', 'LOGIN', 'LOGOUT', 'STOCK_ADJUST', 'PRODUCT_CREATED', 'PRODUCT_UPDATED', 'PRODUCT_DELETED', 'ORDER_CREATED', 'ORDER_CANCELLED', 'ORDER_RETURNED', 'POS_CHECKOUT_SUCCESS', 'POS_CART_RESERVED', 'CASHIER_VOID', 'REFUND_ISSUED', 'USER_CREATED', 'USER_UPDATED', 'USER_DELETED', 'ROLE_CHANGED', 'SETTINGS_UPDATED', 'PO_CREATED', 'PO_SENT', 'PO_RECEIVED', 'PROMO_CREATED', 'PROMO_EXPIRED', 'TAX_SETTINGS_UPDATED', 'BULK_UPLOAD', 'TWO_FA_ENABLED', 'TWO_FA_DISABLED', 'SUPERVISOR_PIN_USED']);
            $table->jsonb('details')->nullable();
            $table->string('ipAddress')->nullable();
            $table->string('userAgent')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->index(['tenantId']);
            $table->index(['userId']);
            $table->index(['action']);
            $table->index(['timestamp']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('userId')->nullable();
            $table->string('role')->nullable();
            $table->string('actionType');
            $table->enum('module', ['POS', 'INVENTORY', 'ORDERS', 'PROCUREMENT', 'FINANCIALS', 'STAFF', 'ANALYTICS', 'SETTINGS', 'AUTH', 'STOREFRONT']);
            $table->string('description');
            $table->string('affectedEntityId')->nullable();
            $table->string('ipAddress')->nullable();
            $table->string('deviceType')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->index(['tenantId']);
            $table->index(['userId']);
            $table->index(['module']);
            $table->index(['timestamp']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->enum('type', ['LOW_STOCK', 'OUT_OF_STOCK', 'NEW_ORDER', 'PAYMENT_RECEIVED', 'PO_APPROVED', 'TAX_REMINDER', 'AUDIT_ALERT', 'SYSTEM', 'SUBSCRIPTION_EXPIRING', 'SUBSCRIPTION_EXPIRED', 'SUBSCRIPTION_RENEWED', 'PAYMENT_FAILED']);
            $table->string('title');
            $table->string('message');
            $table->string('entityId')->nullable();
            $table->boolean('isRead')->default(DB::raw('false'));
            $table->timestamp('readAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['tenantId']);
            $table->index(['isRead']);
        });

        Schema::create('storefront_config', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId')->unique();
            $table->string('storeName');
            $table->string('tagline')->nullable();
            $table->string('bannerImageUrl')->nullable();
            $table->string('accentColor')->default('#16a34a');
            $table->boolean('isPublic')->default(DB::raw('true'));
            $table->boolean('allowGuestOrder')->default(DB::raw('true'));
            $table->string('customDomain')->nullable();
            $table->string('metaTitle')->nullable();
            $table->string('metaDescription')->nullable();
            $table->string('bankName')->nullable();
            $table->string('bankCode')->nullable();
            $table->string('bankAccount')->nullable();
            $table->string('accountName')->nullable();
            $table->string('flutterwaveSubaccountId')->nullable();
            $table->timestamp('updatedAt');
        });

        Schema::create('financial_goals', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('title');
            $table->enum('type', ['REVENUE', 'ORDERS', 'PROFIT', 'NEW_CUSTOMERS']);
            $table->enum('period', ['DAILY', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY', 'CUSTOM']);
            $table->decimal('targetValue', 14, 2);
            $table->timestamp('startDate');
            $table->timestamp('endDate');
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->string('notes')->nullable();
            $table->string('createdBy');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['isActive']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('title');
            $table->enum('category', ['RENT', 'UTILITIES', 'SALARIES', 'LOGISTICS', 'MARKETING', 'EQUIPMENT', 'SUPPLIES', 'TAXES', 'INSURANCE', 'PROFESSIONAL', 'OTHER']);
            $table->decimal('amount', 14, 2);
            $table->timestamp('date');
            $table->string('notes')->nullable();
            $table->string('receiptUrl')->nullable();
            $table->string('createdBy');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['date']);
            $table->index(['category']);
        });

        Schema::create('staff_payroll', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('userId');
            $table->string('period');
            $table->decimal('baseSalary', 10, 2);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('netPay', 10, 2);
            $table->boolean('isPaid')->default(DB::raw('false'));
            $table->timestamp('paidAt')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->unique(['userId', 'period']);
            $table->index(['tenantId']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique();
            $table->enum('tier', ['STARTER', 'PROFESSIONAL', 'ENTERPRISE']);
            $table->string('description')->nullable();
            $table->decimal('monthlyPrice', 10, 2);
            $table->decimal('quarterlyPrice', 10, 2);
            $table->decimal('annualPrice', 10, 2);
            $table->integer('maxBranches')->default(1);
            $table->integer('maxUsers')->default(5);
            $table->integer('maxProducts')->default(500);
            $table->jsonb('features');
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId')->unique();
            $table->string('planId');
            $table->enum('status', ['TRIALING', 'ACTIVE', 'PAST_DUE', 'SUSPENDED', 'CANCELLED', 'EXPIRED'])->default('TRIALING');
            $table->enum('billingCycle', ['MONTHLY', 'QUARTERLY', 'ANNUALLY'])->default('MONTHLY');
            $table->timestamp('currentPeriodStart');
            $table->timestamp('currentPeriodEnd');
            $table->timestamp('trialEndsAt')->nullable();
            $table->integer('gracePeriodDays')->default(7);
            $table->timestamp('gracePeriodEndsAt')->nullable();
            $table->string('paystackSubCode')->nullable();
            $table->string('stripeSubId')->nullable();
            $table->boolean('cancelAtPeriodEnd')->default(DB::raw('false'));
            $table->timestamp('cancelledAt')->nullable();
            $table->string('cancellationReason')->nullable();
            $table->timestamp('lastRenewedAt')->nullable();
            $table->timestamp('nextBillingDate')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['status']);
            $table->index(['currentPeriodEnd']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('subscriptionId')->nullable();
            $table->string('invoiceNumber')->unique();
            $table->enum('billingCycle', ['MONTHLY', 'QUARTERLY', 'ANNUALLY']);
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('NGN');
            $table->enum('status', ['PENDING', 'PAID', 'FAILED', 'REFUNDED', 'WAIVED'])->default('PENDING');
            $table->timestamp('periodStart');
            $table->timestamp('periodEnd');
            $table->enum('gateway', ['PAYSTACK', 'FLUTTERWAVE', 'STRIPE', 'CASH', 'TRANSFER', 'CARD', 'POS_TERMINAL', 'MONNIFY'])->nullable();
            $table->string('gatewayRef')->nullable();
            $table->timestamp('paidAt')->nullable();
            $table->timestamp('failedAt')->nullable();
            $table->string('failureReason')->nullable();
            $table->integer('retryCount')->default(0);
            $table->timestamp('nextRetryAt')->nullable();
            $table->string('receiptUrl')->nullable();
            $table->timestamp('dueDate');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['tenantId']);
            $table->index(['status']);
            $table->index(['dueDate']);
        });

        Schema::create('payment_links', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('orderId')->nullable();
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->string('gatewayUrl')->nullable();
            $table->boolean('isUsed')->default(DB::raw('false'));
            $table->timestamp('expiresAt')->nullable();
            $table->timestamp('usedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['tenantId']);
        });

        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenantId');
            $table->string('orderId')->nullable()->unique();
            $table->string('accountNumber');
            $table->string('bankName');
            $table->string('accountName');
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->boolean('isSettled')->default(DB::raw('false'));
            $table->timestamp('settledAt')->nullable();
            $table->timestamp('expiresAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['tenantId']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId');
            $table->string('type');
            $table->string('provider');
            $table->string('providerAccountId');
            $table->text('refresh_token')->nullable();
            $table->text('access_token')->nullable();
            $table->integer('expires_at')->nullable();
            $table->string('token_type')->nullable();
            $table->string('scope')->nullable();
            $table->text('id_token')->nullable();
            $table->string('session_state')->nullable();
            $table->unique(['provider', 'providerAccountId']);
            $table->index(['userId']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('sessionToken')->unique();
            $table->string('userId');
            $table->timestamp('expires');
            $table->index(['userId']);
        });

        Schema::create('verification_tokens', function (Blueprint $table) {
            $table->string('identifier');
            $table->string('token');
            $table->timestamp('expires');
            $table->unique(['identifier', 'token']);
        });

        Schema::create('authenticators', function (Blueprint $table) {
            $table->string('credentialID')->unique();
            $table->string('userId');
            $table->string('providerAccountId');
            $table->string('credentialPublicKey');
            $table->integer('counter');
            $table->string('credentialDeviceType');
            $table->boolean('credentialBackedUp');
            $table->string('transports')->nullable();
            $table->primary(['user_id', 'credential_id']);
        });

        Schema::create('tenant_registrations', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('businessName');
            $table->string('ownerName');
            $table->string('email')->unique();
            $table->string('passwordHash');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('businessType')->nullable();
            $table->string('city')->nullable();
            $table->string('message')->nullable();
            $table->string('tin')->nullable();
            $table->string('rcNumber')->nullable();
            $table->string('website')->nullable();
            $table->boolean('emailVerified')->default(DB::raw('false'));
            $table->timestamp('emailVerifiedAt')->nullable();
            $table->string('verifyToken')->nullable()->unique();
            $table->timestamp('verifyTokenExp')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->timestamp('reviewedAt')->nullable();
            $table->string('reviewedBy')->nullable();
            $table->string('rejectionReason')->nullable();
            $table->string('planToken')->nullable()->unique();
            $table->timestamp('planTokenExp')->nullable();
            $table->boolean('planSelected')->default(DB::raw('false'));
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId');
            $table->string('token')->unique();
            $table->timestamp('expiresAt');
            $table->timestamp('usedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['userId']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gateway');
            $table->string('eventType');
            $table->string('reference')->unique();
            $table->jsonb('payload');
            $table->boolean('processed')->default(DB::raw('false'));
            $table->timestamp('processedAt')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->index(['processed']);
        });

        Schema::create('catalogue_products', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->string('unit')->nullable();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('isActive')->default(DB::raw('true'));
            $table->string('createdBy');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt');
            $table->index(['name']);
            $table->index(['category']);
            $table->index(['isActive']);
        });

        // Foreign keys after all tables exist (products <-> promotions is circular).
        Schema::table('branches', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['branchId'])->references(['id'])->on('branches')->restrictOnDelete();
            $table->foreign(['roleId'])->references(['id'])->on('roles')->restrictOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['supplierId'])->references(['id'])->on('suppliers')->restrictOnDelete();
            $table->foreign(['activePromoId'])->references(['id'])->on('promotions')->restrictOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreign(['productId'])->references(['id'])->on('products')->cascadeOnDelete();
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign(['productId'])->references(['id'])->on('products')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['branchId'])->references(['id'])->on('branches')->restrictOnDelete();
            $table->foreign(['cashierId'])->references(['id'])->on('users')->restrictOnDelete();
            $table->foreign(['customerId'])->references(['id'])->on('customers')->restrictOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign(['orderId'])->references(['id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['productId'])->references(['id'])->on('products')->restrictOnDelete();
            $table->foreign(['variantId'])->references(['id'])->on('product_variants')->restrictOnDelete();
        });

        Schema::table('holds', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['orderId'])->references(['id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['cashierId'])->references(['id'])->on('users')->restrictOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign(['orderId'])->references(['id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreign(['orderId'])->references(['id'])->on('orders')->restrictOnDelete();
            $table->foreign(['transactionId'])->references(['id'])->on('transactions')->restrictOnDelete();
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['supplierId'])->references(['id'])->on('suppliers')->restrictOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreign(['purchaseOrderId'])->references(['id'])->on('purchase_orders')->cascadeOnDelete();
            $table->foreign(['productId'])->references(['id'])->on('products')->restrictOnDelete();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['productId'])->references(['id'])->on('products')->restrictOnDelete();
            $table->foreign(['createdBy'])->references(['id'])->on('users')->restrictOnDelete();
        });

        Schema::table('vat_settings', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('tax_filings', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('sales_ledger', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['orderId'])->references(['id'])->on('orders')->restrictOnDelete();
            $table->foreign(['transactionId'])->references(['id'])->on('transactions')->restrictOnDelete();
            $table->foreign(['cashierId'])->references(['id'])->on('users')->restrictOnDelete();
        });

        Schema::table('sales_ledger_items', function (Blueprint $table) {
            $table->foreign(['ledgerId'])->references(['id'])->on('sales_ledger')->cascadeOnDelete();
            $table->foreign(['productId'])->references(['id'])->on('products')->restrictOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['userId'])->references(['id'])->on('users')->restrictOnDelete();
            $table->foreign(['supervisorId'])->references(['id'])->on('users')->restrictOnDelete();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['userId'])->references(['id'])->on('users')->restrictOnDelete();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('storefront_config', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('financial_goals', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
        });

        Schema::table('staff_payroll', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['userId'])->references(['id'])->on('users')->restrictOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['planId'])->references(['id'])->on('plans')->restrictOnDelete();
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->foreign(['tenantId'])->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['subscriptionId'])->references(['id'])->on('subscriptions')->restrictOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign(['userId'])->references(['id'])->on('users')->cascadeOnDelete();
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign(['userId'])->references(['id'])->on('users')->cascadeOnDelete();
        });

        Schema::table('authenticators', function (Blueprint $table) {
            $table->foreign(['userId'])->references(['id'])->on('users')->cascadeOnDelete();
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->foreign(['userId'])->references(['id'])->on('users')->cascadeOnDelete();
        });

    }

    public function down(): void
    {
        // Never drop the shared Prisma tables from this migration.
    }
};
