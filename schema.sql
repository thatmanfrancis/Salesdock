--
-- PostgreSQL database dump
--

\restrict kkwHYvaYdWXXrUPr7uXeukFVd8DNDohLupDeCLtIJbWt5ypsL28xKZbEyATFhSL

-- Dumped from database version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: public; Type: SCHEMA; Schema: -; Owner: salesdock_user
--

-- *not* creating schema, since initdb creates it


ALTER SCHEMA public OWNER TO salesdock_user;

--
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: salesdock_user
--

COMMENT ON SCHEMA public IS '';


--
-- Name: ActivityModule; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."ActivityModule" AS ENUM (
    'POS',
    'INVENTORY',
    'ORDERS',
    'PROCUREMENT',
    'FINANCIALS',
    'STAFF',
    'ANALYTICS',
    'SETTINGS',
    'AUTH',
    'STOREFRONT'
);


ALTER TYPE public."ActivityModule" OWNER TO salesdock_user;

--
-- Name: AuditAction; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."AuditAction" AS ENUM (
    'PRICE_OVERRIDE',
    'DISCOUNT_APPLIED',
    'LOGIN',
    'LOGOUT',
    'STOCK_ADJUST',
    'PRODUCT_CREATED',
    'PRODUCT_UPDATED',
    'PRODUCT_DELETED',
    'ORDER_CREATED',
    'ORDER_CANCELLED',
    'ORDER_RETURNED',
    'POS_CHECKOUT_SUCCESS',
    'POS_CART_RESERVED',
    'CASHIER_VOID',
    'REFUND_ISSUED',
    'USER_CREATED',
    'USER_UPDATED',
    'USER_DELETED',
    'ROLE_CHANGED',
    'SETTINGS_UPDATED',
    'PO_CREATED',
    'PO_SENT',
    'PO_RECEIVED',
    'PROMO_CREATED',
    'PROMO_EXPIRED',
    'TAX_SETTINGS_UPDATED',
    'BULK_UPLOAD',
    'TWO_FA_ENABLED',
    'TWO_FA_DISABLED',
    'SUPERVISOR_PIN_USED'
);


ALTER TYPE public."AuditAction" OWNER TO salesdock_user;

--
-- Name: AvailabilityMode; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."AvailabilityMode" AS ENUM (
    'POS_ONLY',
    'ONLINE_ONLY',
    'BOTH',
    'DISABLED'
);


ALTER TYPE public."AvailabilityMode" OWNER TO salesdock_user;

--
-- Name: BillingCycle; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."BillingCycle" AS ENUM (
    'MONTHLY',
    'QUARTERLY',
    'ANNUALLY'
);


ALTER TYPE public."BillingCycle" OWNER TO salesdock_user;

--
-- Name: BillingPaymentStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."BillingPaymentStatus" AS ENUM (
    'PENDING',
    'PAID',
    'FAILED',
    'REFUNDED',
    'WAIVED'
);


ALTER TYPE public."BillingPaymentStatus" OWNER TO salesdock_user;

--
-- Name: DiscountType; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."DiscountType" AS ENUM (
    'PERCENTAGE',
    'FIXED'
);


ALTER TYPE public."DiscountType" OWNER TO salesdock_user;

--
-- Name: ExpenseCategory; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."ExpenseCategory" AS ENUM (
    'RENT',
    'UTILITIES',
    'SALARIES',
    'LOGISTICS',
    'MARKETING',
    'EQUIPMENT',
    'SUPPLIES',
    'TAXES',
    'INSURANCE',
    'PROFESSIONAL',
    'OTHER'
);


ALTER TYPE public."ExpenseCategory" OWNER TO salesdock_user;

--
-- Name: GoalPeriod; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."GoalPeriod" AS ENUM (
    'DAILY',
    'WEEKLY',
    'MONTHLY',
    'QUARTERLY',
    'YEARLY',
    'CUSTOM'
);


ALTER TYPE public."GoalPeriod" OWNER TO salesdock_user;

--
-- Name: GoalType; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."GoalType" AS ENUM (
    'REVENUE',
    'ORDERS',
    'PROFIT',
    'NEW_CUSTOMERS'
);


ALTER TYPE public."GoalType" OWNER TO salesdock_user;

--
-- Name: HoldStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."HoldStatus" AS ENUM (
    'ACTIVE',
    'EXPIRED',
    'RESTORED',
    'CANCELLED'
);


ALTER TYPE public."HoldStatus" OWNER TO salesdock_user;

--
-- Name: NotificationType; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."NotificationType" AS ENUM (
    'LOW_STOCK',
    'OUT_OF_STOCK',
    'NEW_ORDER',
    'PAYMENT_RECEIVED',
    'PO_APPROVED',
    'TAX_REMINDER',
    'AUDIT_ALERT',
    'SYSTEM',
    'SUBSCRIPTION_EXPIRING',
    'SUBSCRIPTION_EXPIRED',
    'SUBSCRIPTION_RENEWED',
    'PAYMENT_FAILED'
);


ALTER TYPE public."NotificationType" OWNER TO salesdock_user;

--
-- Name: OrderChannel; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."OrderChannel" AS ENUM (
    'POS',
    'ONLINE',
    'WHATSAPP',
    'INSTAGRAM',
    'LINK'
);


ALTER TYPE public."OrderChannel" OWNER TO salesdock_user;

--
-- Name: OrderStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."OrderStatus" AS ENUM (
    'PENDING',
    'RESERVED',
    'COMPLETED',
    'CANCELLED',
    'RETURNED'
);


ALTER TYPE public."OrderStatus" OWNER TO salesdock_user;

--
-- Name: PaymentGateway; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."PaymentGateway" AS ENUM (
    'PAYSTACK',
    'FLUTTERWAVE',
    'STRIPE',
    'CASH',
    'TRANSFER',
    'CARD',
    'POS_TERMINAL',
    'MONNIFY'
);


ALTER TYPE public."PaymentGateway" OWNER TO salesdock_user;

--
-- Name: PaymentMethod; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."PaymentMethod" AS ENUM (
    'CASH',
    'TRANSFER',
    'CARD',
    'POS_TERMINAL',
    'PAYMENT_LINK',
    'VIRTUAL_ACCOUNT'
);


ALTER TYPE public."PaymentMethod" OWNER TO salesdock_user;

--
-- Name: PlanTier; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."PlanTier" AS ENUM (
    'STARTER',
    'PROFESSIONAL',
    'ENTERPRISE'
);


ALTER TYPE public."PlanTier" OWNER TO salesdock_user;

--
-- Name: PurchaseOrderStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."PurchaseOrderStatus" AS ENUM (
    'DRAFT',
    'SENT',
    'RECEIVED',
    'CANCELLED'
);


ALTER TYPE public."PurchaseOrderStatus" OWNER TO salesdock_user;

--
-- Name: PurchaseOrderUrgency; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."PurchaseOrderUrgency" AS ENUM (
    'LOW',
    'MEDIUM',
    'HIGH',
    'CRITICAL',
    'OUT_OF_STOCK'
);


ALTER TYPE public."PurchaseOrderUrgency" OWNER TO salesdock_user;

--
-- Name: RefundStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."RefundStatus" AS ENUM (
    'PENDING',
    'APPROVED',
    'REJECTED',
    'PROCESSED'
);


ALTER TYPE public."RefundStatus" OWNER TO salesdock_user;

--
-- Name: StockMovementType; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."StockMovementType" AS ENUM (
    'SALE',
    'RETURN',
    'ADJUSTMENT_IN',
    'ADJUSTMENT_OUT',
    'HOLD',
    'HOLD_RELEASE',
    'PURCHASE_RECEIVED',
    'DAMAGE',
    'SHRINKAGE'
);


ALTER TYPE public."StockMovementType" OWNER TO salesdock_user;

--
-- Name: SubscriptionStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."SubscriptionStatus" AS ENUM (
    'TRIALING',
    'ACTIVE',
    'PAST_DUE',
    'SUSPENDED',
    'CANCELLED',
    'EXPIRED'
);


ALTER TYPE public."SubscriptionStatus" OWNER TO salesdock_user;

--
-- Name: TaxFilingStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."TaxFilingStatus" AS ENUM (
    'PENDING',
    'FILED',
    'OVERDUE'
);


ALTER TYPE public."TaxFilingStatus" OWNER TO salesdock_user;

--
-- Name: TenantApprovalStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."TenantApprovalStatus" AS ENUM (
    'PENDING',
    'APPROVED',
    'REJECTED'
);


ALTER TYPE public."TenantApprovalStatus" OWNER TO salesdock_user;

--
-- Name: TransactionStatus; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."TransactionStatus" AS ENUM (
    'SUCCESS',
    'FAILED',
    'PENDING',
    'REFUNDED'
);


ALTER TYPE public."TransactionStatus" OWNER TO salesdock_user;

--
-- Name: UserRole; Type: TYPE; Schema: public; Owner: salesdock_user
--

CREATE TYPE public."UserRole" AS ENUM (
    'CASHIER',
    'SUPERVISOR',
    'MANAGER',
    'ADMIN',
    'CLIENT',
    'SUPER_ADMIN'
);


ALTER TYPE public."UserRole" OWNER TO salesdock_user;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: _prisma_migrations; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public._prisma_migrations (
    id character varying(36) NOT NULL,
    checksum character varying(64) NOT NULL,
    finished_at timestamp with time zone,
    migration_name character varying(255) NOT NULL,
    logs text,
    rolled_back_at timestamp with time zone,
    started_at timestamp with time zone DEFAULT now() NOT NULL,
    applied_steps_count integer DEFAULT 0 NOT NULL
);


ALTER TABLE public._prisma_migrations OWNER TO salesdock_user;

--
-- Name: accounts; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.accounts (
    id text NOT NULL,
    "userId" text NOT NULL,
    type text NOT NULL,
    provider text NOT NULL,
    "providerAccountId" text NOT NULL,
    refresh_token text,
    access_token text,
    expires_at integer,
    token_type text,
    scope text,
    id_token text,
    session_state text
);


ALTER TABLE public.accounts OWNER TO salesdock_user;

--
-- Name: activity_logs; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.activity_logs (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "userId" text,
    role text,
    "actionType" text NOT NULL,
    module public."ActivityModule" NOT NULL,
    description text NOT NULL,
    "affectedEntityId" text,
    "ipAddress" text,
    "deviceType" text,
    "timestamp" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.activity_logs OWNER TO salesdock_user;

--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.audit_logs (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "userId" text,
    "supervisorId" text,
    action public."AuditAction" NOT NULL,
    details jsonb,
    "ipAddress" text,
    "userAgent" text,
    "timestamp" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.audit_logs OWNER TO salesdock_user;

--
-- Name: authenticators; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.authenticators (
    "credentialID" text NOT NULL,
    "userId" text NOT NULL,
    "providerAccountId" text NOT NULL,
    "credentialPublicKey" text NOT NULL,
    counter integer NOT NULL,
    "credentialDeviceType" text NOT NULL,
    "credentialBackedUp" boolean NOT NULL,
    transports text
);


ALTER TABLE public.authenticators OWNER TO salesdock_user;

--
-- Name: billing_invoices; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.billing_invoices (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "subscriptionId" text,
    "invoiceNumber" text NOT NULL,
    "billingCycle" public."BillingCycle" NOT NULL,
    amount numeric(10,2) NOT NULL,
    currency text DEFAULT 'NGN'::text NOT NULL,
    status public."BillingPaymentStatus" DEFAULT 'PENDING'::public."BillingPaymentStatus" NOT NULL,
    "periodStart" timestamp(3) without time zone NOT NULL,
    "periodEnd" timestamp(3) without time zone NOT NULL,
    gateway public."PaymentGateway",
    "gatewayRef" text,
    "paidAt" timestamp(3) without time zone,
    "failedAt" timestamp(3) without time zone,
    "failureReason" text,
    "retryCount" integer DEFAULT 0 NOT NULL,
    "nextRetryAt" timestamp(3) without time zone,
    "receiptUrl" text,
    "dueDate" timestamp(3) without time zone NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.billing_invoices OWNER TO salesdock_user;

--
-- Name: branches; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.branches (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    name text NOT NULL,
    address text,
    phone text,
    "isMain" boolean DEFAULT false NOT NULL,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.branches OWNER TO salesdock_user;

--
-- Name: catalogue_products; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.catalogue_products (
    id text NOT NULL,
    name text NOT NULL,
    category text,
    description text,
    unit text,
    barcode text,
    brand text,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdBy" text NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.catalogue_products OWNER TO salesdock_user;

--
-- Name: customers; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.customers (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    name text NOT NULL,
    email text,
    phone text,
    whatsapp text,
    address text,
    notes text,
    "totalOrders" integer DEFAULT 0 NOT NULL,
    "totalSpend" numeric(10,2) DEFAULT 0 NOT NULL,
    "loyaltyPoints" integer DEFAULT 0 NOT NULL,
    "firstChannel" public."OrderChannel" DEFAULT 'POS'::public."OrderChannel" NOT NULL,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.customers OWNER TO salesdock_user;

--
-- Name: expenses; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.expenses (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    title text NOT NULL,
    category public."ExpenseCategory" NOT NULL,
    amount numeric(14,2) NOT NULL,
    date timestamp(3) without time zone NOT NULL,
    notes text,
    "receiptUrl" text,
    "createdBy" text NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.expenses OWNER TO salesdock_user;

--
-- Name: financial_goals; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.financial_goals (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    title text NOT NULL,
    type public."GoalType" NOT NULL,
    period public."GoalPeriod" NOT NULL,
    "targetValue" numeric(14,2) NOT NULL,
    "startDate" timestamp(3) without time zone NOT NULL,
    "endDate" timestamp(3) without time zone NOT NULL,
    "isActive" boolean DEFAULT true NOT NULL,
    notes text,
    "createdBy" text NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.financial_goals OWNER TO salesdock_user;

--
-- Name: holds; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.holds (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "orderId" text NOT NULL,
    "cashierId" text,
    reference text,
    status public."HoldStatus" DEFAULT 'ACTIVE'::public."HoldStatus" NOT NULL,
    "expiresAt" timestamp(3) without time zone NOT NULL,
    "expiredAt" timestamp(3) without time zone,
    "restoredAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.holds OWNER TO salesdock_user;

--
-- Name: notifications; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.notifications (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    type public."NotificationType" NOT NULL,
    title text NOT NULL,
    message text NOT NULL,
    "entityId" text,
    "isRead" boolean DEFAULT false NOT NULL,
    "readAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.notifications OWNER TO salesdock_user;

--
-- Name: order_items; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.order_items (
    id text NOT NULL,
    "orderId" text NOT NULL,
    "productId" text NOT NULL,
    "variantId" text,
    quantity integer NOT NULL,
    "unitPrice" numeric(10,2) NOT NULL,
    "originalPrice" numeric(10,2) NOT NULL,
    "discountAmount" numeric(10,2) DEFAULT 0 NOT NULL,
    "lineTotal" numeric(10,2) NOT NULL,
    "isPriceOverridden" boolean DEFAULT false NOT NULL,
    "overrideReason" text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.order_items OWNER TO salesdock_user;

--
-- Name: orders; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.orders (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "branchId" text,
    "cashierId" text,
    status public."OrderStatus" DEFAULT 'PENDING'::public."OrderStatus" NOT NULL,
    channel public."OrderChannel" DEFAULT 'POS'::public."OrderChannel" NOT NULL,
    "totalAmount" numeric(10,2) NOT NULL,
    "discountAmount" numeric(10,2) DEFAULT 0 NOT NULL,
    "taxAmount" numeric(10,2) DEFAULT 0 NOT NULL,
    "netAmount" numeric(10,2) DEFAULT 0 NOT NULL,
    "customerId" text,
    "customerName" text,
    "customerEmail" text,
    "customerPhone" text,
    "customerAddress" text,
    "cancelReason" text,
    "cancelledAt" timestamp(3) without time zone,
    "cancelRequested" boolean DEFAULT false NOT NULL,
    "returnReason" text,
    "returnedAt" timestamp(3) without time zone,
    "paymentLinkUrl" text,
    "paymentRef" text,
    notes text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.orders OWNER TO salesdock_user;

--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.password_reset_tokens (
    id text NOT NULL,
    "userId" text NOT NULL,
    token text NOT NULL,
    "expiresAt" timestamp(3) without time zone NOT NULL,
    "usedAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.password_reset_tokens OWNER TO salesdock_user;

--
-- Name: payment_links; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.payment_links (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "orderId" text,
    reference text NOT NULL,
    amount numeric(10,2) NOT NULL,
    description text,
    "gatewayUrl" text,
    "isUsed" boolean DEFAULT false NOT NULL,
    "expiresAt" timestamp(3) without time zone,
    "usedAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.payment_links OWNER TO salesdock_user;

--
-- Name: plans; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.plans (
    id text NOT NULL,
    name text NOT NULL,
    tier public."PlanTier" NOT NULL,
    description text,
    "monthlyPrice" numeric(10,2) NOT NULL,
    "quarterlyPrice" numeric(10,2) NOT NULL,
    "annualPrice" numeric(10,2) NOT NULL,
    "maxBranches" integer DEFAULT 1 NOT NULL,
    "maxUsers" integer DEFAULT 5 NOT NULL,
    "maxProducts" integer DEFAULT 500 NOT NULL,
    features jsonb NOT NULL,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.plans OWNER TO salesdock_user;

--
-- Name: product_variants; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.product_variants (
    id text NOT NULL,
    "productId" text NOT NULL,
    sku text NOT NULL,
    barcode text,
    name text NOT NULL,
    attributes jsonb NOT NULL,
    price numeric(10,2) NOT NULL,
    "costPrice" numeric(10,2) DEFAULT 0 NOT NULL,
    "currentStock" integer DEFAULT 0 NOT NULL,
    "reservedQty" integer DEFAULT 0 NOT NULL,
    "imageUrl" text,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "tenantId" text NOT NULL
);


ALTER TABLE public.product_variants OWNER TO salesdock_user;

--
-- Name: products; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.products (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "supplierId" text,
    sku text NOT NULL,
    barcode text,
    name text NOT NULL,
    description text,
    category text DEFAULT 'General'::text NOT NULL,
    "subCategory" text,
    "imageUrl" text,
    price numeric(10,2) NOT NULL,
    "originalPrice" numeric(10,2),
    "costPrice" numeric(10,2) DEFAULT 0 NOT NULL,
    "wholesalePrice" numeric(10,2),
    "vatRateOverride" numeric(5,2),
    "currentStock" integer DEFAULT 0 NOT NULL,
    "reservedQty" integer DEFAULT 0 NOT NULL,
    "minThreshold" integer DEFAULT 5 NOT NULL,
    "reorderQty" integer DEFAULT 0 NOT NULL,
    "leadTimeDays" integer DEFAULT 0 NOT NULL,
    "availabilityMode" public."AvailabilityMode" DEFAULT 'BOTH'::public."AvailabilityMode" NOT NULL,
    "isDiscounted" boolean DEFAULT false NOT NULL,
    "activePromoId" text,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "expiresAt" timestamp(3) without time zone
);


ALTER TABLE public.products OWNER TO salesdock_user;

--
-- Name: promotions; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.promotions (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "productId" text,
    name text NOT NULL,
    "discountType" public."DiscountType" NOT NULL,
    "discountValue" numeric(10,2) NOT NULL,
    "startDatetime" timestamp(3) without time zone NOT NULL,
    "endDatetime" timestamp(3) without time zone NOT NULL,
    "isActive" boolean DEFAULT false NOT NULL,
    "createdBy" text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.promotions OWNER TO salesdock_user;

--
-- Name: purchase_order_items; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.purchase_order_items (
    id text NOT NULL,
    "purchaseOrderId" text NOT NULL,
    "productId" text NOT NULL,
    "quantityOrdered" integer NOT NULL,
    "quantityReceived" integer DEFAULT 0 NOT NULL,
    "unitCost" numeric(10,2) NOT NULL,
    "lineTotal" numeric(10,2) NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.purchase_order_items OWNER TO salesdock_user;

--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.purchase_orders (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "supplierId" text NOT NULL,
    status public."PurchaseOrderStatus" DEFAULT 'DRAFT'::public."PurchaseOrderStatus" NOT NULL,
    urgency public."PurchaseOrderUrgency" DEFAULT 'LOW'::public."PurchaseOrderUrgency" NOT NULL,
    "totalCost" numeric(10,2) DEFAULT 0 NOT NULL,
    notes text,
    "sentAt" timestamp(3) without time zone,
    "receivedAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.purchase_orders OWNER TO salesdock_user;

--
-- Name: refunds; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.refunds (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "orderId" text NOT NULL,
    "transactionId" text,
    amount numeric(10,2) NOT NULL,
    reason text NOT NULL,
    status public."RefundStatus" DEFAULT 'PENDING'::public."RefundStatus" NOT NULL,
    "approvedBy" text,
    "processedAt" timestamp(3) without time zone,
    notes text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.refunds OWNER TO salesdock_user;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.roles (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    name text NOT NULL,
    role public."UserRole" DEFAULT 'CASHIER'::public."UserRole" NOT NULL,
    permissions jsonb NOT NULL,
    "isDefault" boolean DEFAULT false NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.roles OWNER TO salesdock_user;

--
-- Name: sales_ledger; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.sales_ledger (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "orderId" text NOT NULL,
    "transactionId" text,
    channel public."OrderChannel" NOT NULL,
    "cashierId" text,
    "customerName" text,
    "paymentMethod" text NOT NULL,
    "orderStatus" text NOT NULL,
    "grossRevenue" numeric(10,2) NOT NULL,
    "netRevenue" numeric(10,2) NOT NULL,
    "totalVat" numeric(10,2) NOT NULL,
    "totalCogs" numeric(10,2) NOT NULL,
    "grossProfit" numeric(10,2) DEFAULT 0 NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.sales_ledger OWNER TO salesdock_user;

--
-- Name: sales_ledger_items; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.sales_ledger_items (
    id text NOT NULL,
    "ledgerId" text NOT NULL,
    "productId" text,
    sku text NOT NULL,
    "productName" text NOT NULL,
    quantity integer NOT NULL,
    "costPrice" numeric(10,2) NOT NULL,
    "sellingPrice" numeric(10,2) NOT NULL,
    "lineTotal" numeric(10,2) NOT NULL,
    "vatPercentage" numeric(5,2) NOT NULL,
    "vatAmount" numeric(10,2) NOT NULL,
    "grossLineTotal" numeric(10,2) NOT NULL,
    "netLineTotal" numeric(10,2) NOT NULL,
    "grossProfit" numeric(10,2) DEFAULT 0 NOT NULL
);


ALTER TABLE public.sales_ledger_items OWNER TO salesdock_user;

--
-- Name: sessions; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.sessions (
    id text NOT NULL,
    "sessionToken" text NOT NULL,
    "userId" text NOT NULL,
    expires timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.sessions OWNER TO salesdock_user;

--
-- Name: staff_payroll; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.staff_payroll (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "userId" text NOT NULL,
    period text NOT NULL,
    "baseSalary" numeric(10,2) NOT NULL,
    bonus numeric(10,2) DEFAULT 0 NOT NULL,
    deductions numeric(10,2) DEFAULT 0 NOT NULL,
    "netPay" numeric(10,2) NOT NULL,
    "isPaid" boolean DEFAULT false NOT NULL,
    "paidAt" timestamp(3) without time zone,
    notes text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.staff_payroll OWNER TO salesdock_user;

--
-- Name: stock_movements; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.stock_movements (
    id text NOT NULL,
    "productId" text NOT NULL,
    "tenantId" text NOT NULL,
    type public."StockMovementType" NOT NULL,
    quantity integer NOT NULL,
    "beforeQty" integer NOT NULL,
    "afterQty" integer NOT NULL,
    reference text,
    notes text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.stock_movements OWNER TO salesdock_user;

--
-- Name: storefront_config; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.storefront_config (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "storeName" text NOT NULL,
    tagline text,
    "bannerImageUrl" text,
    "accentColor" text DEFAULT '#16a34a'::text NOT NULL,
    "isPublic" boolean DEFAULT true NOT NULL,
    "allowGuestOrder" boolean DEFAULT true NOT NULL,
    "customDomain" text,
    "metaTitle" text,
    "metaDescription" text,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "bankName" text,
    "bankAccount" text,
    "accountName" text,
    "bankCode" text,
    "flutterwaveSubaccountId" text
);


ALTER TABLE public.storefront_config OWNER TO salesdock_user;

--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.subscriptions (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "planId" text NOT NULL,
    status public."SubscriptionStatus" DEFAULT 'TRIALING'::public."SubscriptionStatus" NOT NULL,
    "billingCycle" public."BillingCycle" DEFAULT 'MONTHLY'::public."BillingCycle" NOT NULL,
    "currentPeriodStart" timestamp(3) without time zone NOT NULL,
    "currentPeriodEnd" timestamp(3) without time zone NOT NULL,
    "trialEndsAt" timestamp(3) without time zone,
    "gracePeriodDays" integer DEFAULT 7 NOT NULL,
    "gracePeriodEndsAt" timestamp(3) without time zone,
    "paystackSubCode" text,
    "stripeSubId" text,
    "cancelAtPeriodEnd" boolean DEFAULT false NOT NULL,
    "cancelledAt" timestamp(3) without time zone,
    "cancellationReason" text,
    "lastRenewedAt" timestamp(3) without time zone,
    "nextBillingDate" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.subscriptions OWNER TO salesdock_user;

--
-- Name: suppliers; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.suppliers (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    name text NOT NULL,
    "contactEmail" text,
    "contactPhone" text,
    "contactWhatsapp" text,
    address text,
    "bankName" text,
    "bankAccount" text,
    notes text,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.suppliers OWNER TO salesdock_user;

--
-- Name: tax_filings; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.tax_filings (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    period text NOT NULL,
    "grossSales" numeric(10,2) NOT NULL,
    "totalVat" numeric(10,2) NOT NULL,
    "netSales" numeric(10,2) NOT NULL,
    status public."TaxFilingStatus" DEFAULT 'PENDING'::public."TaxFilingStatus" NOT NULL,
    "filedAt" timestamp(3) without time zone,
    "dueDate" timestamp(3) without time zone NOT NULL,
    notes text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "attachmentUrl" text,
    "submissionRef" text,
    "totalTaxPaid" numeric(10,2)
);


ALTER TABLE public.tax_filings OWNER TO salesdock_user;

--
-- Name: tenant_registrations; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.tenant_registrations (
    id text NOT NULL,
    "businessName" text NOT NULL,
    "ownerName" text NOT NULL,
    email text NOT NULL,
    "passwordHash" text NOT NULL,
    phone text,
    address text,
    "businessType" text,
    city text,
    message text,
    "emailVerified" boolean DEFAULT false NOT NULL,
    "emailVerifiedAt" timestamp(3) without time zone,
    "verifyToken" text,
    "verifyTokenExp" timestamp(3) without time zone,
    status public."TenantApprovalStatus" DEFAULT 'PENDING'::public."TenantApprovalStatus" NOT NULL,
    "reviewedAt" timestamp(3) without time zone,
    "reviewedBy" text,
    "rejectionReason" text,
    "planToken" text,
    "planTokenExp" timestamp(3) without time zone,
    "planSelected" boolean DEFAULT false NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "rcNumber" text,
    tin text,
    website text
);


ALTER TABLE public.tenant_registrations OWNER TO salesdock_user;

--
-- Name: tenants; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.tenants (
    id text NOT NULL,
    name text NOT NULL,
    slug text NOT NULL,
    currency text DEFAULT 'NGN'::text NOT NULL,
    "taxRate" numeric(5,2) DEFAULT 7.5 NOT NULL,
    "logoUrl" text,
    address text,
    phone text,
    email text,
    website text,
    tin text,
    "rcNumber" text,
    "isActive" boolean DEFAULT true NOT NULL,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "approvalStatus" public."TenantApprovalStatus" DEFAULT 'PENDING'::public."TenantApprovalStatus" NOT NULL,
    "approvedAt" timestamp(3) without time zone,
    "approvedBy" text
);


ALTER TABLE public.tenants OWNER TO salesdock_user;

--
-- Name: transactions; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.transactions (
    id text NOT NULL,
    "orderId" text NOT NULL,
    "tenantId" text NOT NULL,
    gateway public."PaymentGateway" DEFAULT 'CASH'::public."PaymentGateway" NOT NULL,
    method public."PaymentMethod" DEFAULT 'CASH'::public."PaymentMethod" NOT NULL,
    amount numeric(10,2) NOT NULL,
    status public."TransactionStatus" DEFAULT 'PENDING'::public."TransactionStatus" NOT NULL,
    reference text,
    "gatewayRef" text,
    metadata jsonb,
    "completedAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.transactions OWNER TO salesdock_user;

--
-- Name: users; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.users (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "branchId" text,
    "roleId" text NOT NULL,
    name text NOT NULL,
    email text NOT NULL,
    "passwordHash" text,
    pin text,
    phone text,
    "avatarUrl" text,
    "isActive" boolean DEFAULT true NOT NULL,
    "twoFaEnabled" boolean DEFAULT false NOT NULL,
    "twoFaSecret" text,
    "lastLoginAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL,
    "pinIsDefault" boolean DEFAULT true NOT NULL,
    "isSuperAdmin" boolean DEFAULT false NOT NULL,
    "shiftStartedAt" timestamp(3) without time zone
);


ALTER TABLE public.users OWNER TO salesdock_user;

--
-- Name: vat_settings; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.vat_settings (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "globalVatRate" numeric(5,2) DEFAULT 7.5 NOT NULL,
    "isInclusive" boolean DEFAULT false NOT NULL,
    "updatedAt" timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.vat_settings OWNER TO salesdock_user;

--
-- Name: verification_tokens; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.verification_tokens (
    identifier text NOT NULL,
    token text NOT NULL,
    expires timestamp(3) without time zone NOT NULL
);


ALTER TABLE public.verification_tokens OWNER TO salesdock_user;

--
-- Name: virtual_accounts; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.virtual_accounts (
    id text NOT NULL,
    "tenantId" text NOT NULL,
    "orderId" text,
    "accountNumber" text NOT NULL,
    "bankName" text NOT NULL,
    "accountName" text NOT NULL,
    reference text NOT NULL,
    amount numeric(10,2) NOT NULL,
    "isSettled" boolean DEFAULT false NOT NULL,
    "settledAt" timestamp(3) without time zone,
    "expiresAt" timestamp(3) without time zone,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.virtual_accounts OWNER TO salesdock_user;

--
-- Name: webhook_events; Type: TABLE; Schema: public; Owner: salesdock_user
--

CREATE TABLE public.webhook_events (
    id text NOT NULL,
    gateway text NOT NULL,
    "eventType" text NOT NULL,
    reference text NOT NULL,
    payload jsonb NOT NULL,
    processed boolean DEFAULT false NOT NULL,
    "processedAt" timestamp(3) without time zone,
    error text,
    "createdAt" timestamp(3) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.webhook_events OWNER TO salesdock_user;

--
-- Name: _prisma_migrations _prisma_migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public._prisma_migrations
    ADD CONSTRAINT _prisma_migrations_pkey PRIMARY KEY (id);


--
-- Name: accounts accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_pkey PRIMARY KEY (id);


--
-- Name: activity_logs activity_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT activity_logs_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: authenticators authenticators_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.authenticators
    ADD CONSTRAINT authenticators_pkey PRIMARY KEY ("userId", "credentialID");


--
-- Name: billing_invoices billing_invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.billing_invoices
    ADD CONSTRAINT billing_invoices_pkey PRIMARY KEY (id);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: catalogue_products catalogue_products_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.catalogue_products
    ADD CONSTRAINT catalogue_products_pkey PRIMARY KEY (id);


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: expenses expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_pkey PRIMARY KEY (id);


--
-- Name: financial_goals financial_goals_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.financial_goals
    ADD CONSTRAINT financial_goals_pkey PRIMARY KEY (id);


--
-- Name: holds holds_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.holds
    ADD CONSTRAINT holds_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: order_items order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT order_items_pkey PRIMARY KEY (id);


--
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (id);


--
-- Name: payment_links payment_links_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.payment_links
    ADD CONSTRAINT payment_links_pkey PRIMARY KEY (id);


--
-- Name: plans plans_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);


--
-- Name: product_variants product_variants_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT product_variants_pkey PRIMARY KEY (id);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: promotions promotions_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.promotions
    ADD CONSTRAINT promotions_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_items purchase_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: refunds refunds_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT refunds_pkey PRIMARY KEY (id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sales_ledger_items sales_ledger_items_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger_items
    ADD CONSTRAINT sales_ledger_items_pkey PRIMARY KEY (id);


--
-- Name: sales_ledger sales_ledger_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger
    ADD CONSTRAINT sales_ledger_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: staff_payroll staff_payroll_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.staff_payroll
    ADD CONSTRAINT staff_payroll_pkey PRIMARY KEY (id);


--
-- Name: stock_movements stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_pkey PRIMARY KEY (id);


--
-- Name: storefront_config storefront_config_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.storefront_config
    ADD CONSTRAINT storefront_config_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: suppliers suppliers_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_pkey PRIMARY KEY (id);


--
-- Name: tax_filings tax_filings_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.tax_filings
    ADD CONSTRAINT tax_filings_pkey PRIMARY KEY (id);


--
-- Name: tenant_registrations tenant_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.tenant_registrations
    ADD CONSTRAINT tenant_registrations_pkey PRIMARY KEY (id);


--
-- Name: tenants tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.tenants
    ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);


--
-- Name: transactions transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_pkey PRIMARY KEY (id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vat_settings vat_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.vat_settings
    ADD CONSTRAINT vat_settings_pkey PRIMARY KEY (id);


--
-- Name: virtual_accounts virtual_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.virtual_accounts
    ADD CONSTRAINT virtual_accounts_pkey PRIMARY KEY (id);


--
-- Name: webhook_events webhook_events_pkey; Type: CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.webhook_events
    ADD CONSTRAINT webhook_events_pkey PRIMARY KEY (id);


--
-- Name: accounts_provider_providerAccountId_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "accounts_provider_providerAccountId_key" ON public.accounts USING btree (provider, "providerAccountId");


--
-- Name: accounts_userId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "accounts_userId_idx" ON public.accounts USING btree ("userId");


--
-- Name: activity_logs_module_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX activity_logs_module_idx ON public.activity_logs USING btree (module);


--
-- Name: activity_logs_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "activity_logs_tenantId_idx" ON public.activity_logs USING btree ("tenantId");


--
-- Name: activity_logs_timestamp_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX activity_logs_timestamp_idx ON public.activity_logs USING btree ("timestamp");


--
-- Name: activity_logs_userId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "activity_logs_userId_idx" ON public.activity_logs USING btree ("userId");


--
-- Name: audit_logs_action_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX audit_logs_action_idx ON public.audit_logs USING btree (action);


--
-- Name: audit_logs_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "audit_logs_tenantId_idx" ON public.audit_logs USING btree ("tenantId");


--
-- Name: audit_logs_timestamp_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX audit_logs_timestamp_idx ON public.audit_logs USING btree ("timestamp");


--
-- Name: audit_logs_userId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "audit_logs_userId_idx" ON public.audit_logs USING btree ("userId");


--
-- Name: authenticators_credentialID_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "authenticators_credentialID_key" ON public.authenticators USING btree ("credentialID");


--
-- Name: billing_invoices_dueDate_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "billing_invoices_dueDate_idx" ON public.billing_invoices USING btree ("dueDate");


--
-- Name: billing_invoices_invoiceNumber_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "billing_invoices_invoiceNumber_key" ON public.billing_invoices USING btree ("invoiceNumber");


--
-- Name: billing_invoices_status_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX billing_invoices_status_idx ON public.billing_invoices USING btree (status);


--
-- Name: billing_invoices_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "billing_invoices_tenantId_idx" ON public.billing_invoices USING btree ("tenantId");


--
-- Name: branches_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "branches_tenantId_idx" ON public.branches USING btree ("tenantId");


--
-- Name: catalogue_products_category_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX catalogue_products_category_idx ON public.catalogue_products USING btree (category);


--
-- Name: catalogue_products_isActive_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "catalogue_products_isActive_idx" ON public.catalogue_products USING btree ("isActive");


--
-- Name: catalogue_products_name_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX catalogue_products_name_idx ON public.catalogue_products USING btree (name);


--
-- Name: customers_email_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX customers_email_idx ON public.customers USING btree (email);


--
-- Name: customers_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "customers_tenantId_idx" ON public.customers USING btree ("tenantId");


--
-- Name: customers_tenantId_phone_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "customers_tenantId_phone_key" ON public.customers USING btree ("tenantId", phone);


--
-- Name: expenses_category_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX expenses_category_idx ON public.expenses USING btree (category);


--
-- Name: expenses_date_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX expenses_date_idx ON public.expenses USING btree (date);


--
-- Name: expenses_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "expenses_tenantId_idx" ON public.expenses USING btree ("tenantId");


--
-- Name: financial_goals_isActive_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "financial_goals_isActive_idx" ON public.financial_goals USING btree ("isActive");


--
-- Name: financial_goals_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "financial_goals_tenantId_idx" ON public.financial_goals USING btree ("tenantId");


--
-- Name: holds_orderId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "holds_orderId_idx" ON public.holds USING btree ("orderId");


--
-- Name: holds_status_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX holds_status_idx ON public.holds USING btree (status);


--
-- Name: holds_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "holds_tenantId_idx" ON public.holds USING btree ("tenantId");


--
-- Name: notifications_isRead_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "notifications_isRead_idx" ON public.notifications USING btree ("isRead");


--
-- Name: notifications_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "notifications_tenantId_idx" ON public.notifications USING btree ("tenantId");


--
-- Name: order_items_orderId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "order_items_orderId_idx" ON public.order_items USING btree ("orderId");


--
-- Name: order_items_productId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "order_items_productId_idx" ON public.order_items USING btree ("productId");


--
-- Name: orders_cashierId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "orders_cashierId_idx" ON public.orders USING btree ("cashierId");


--
-- Name: orders_channel_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX orders_channel_idx ON public.orders USING btree (channel);


--
-- Name: orders_createdAt_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "orders_createdAt_idx" ON public.orders USING btree ("createdAt");


--
-- Name: orders_paymentRef_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "orders_paymentRef_key" ON public.orders USING btree ("paymentRef");


--
-- Name: orders_status_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX orders_status_idx ON public.orders USING btree (status);


--
-- Name: orders_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "orders_tenantId_idx" ON public.orders USING btree ("tenantId");


--
-- Name: password_reset_tokens_token_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX password_reset_tokens_token_idx ON public.password_reset_tokens USING btree (token);


--
-- Name: password_reset_tokens_token_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX password_reset_tokens_token_key ON public.password_reset_tokens USING btree (token);


--
-- Name: password_reset_tokens_userId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "password_reset_tokens_userId_idx" ON public.password_reset_tokens USING btree ("userId");


--
-- Name: payment_links_reference_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX payment_links_reference_idx ON public.payment_links USING btree (reference);


--
-- Name: payment_links_reference_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX payment_links_reference_key ON public.payment_links USING btree (reference);


--
-- Name: payment_links_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "payment_links_tenantId_idx" ON public.payment_links USING btree ("tenantId");


--
-- Name: plans_name_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX plans_name_key ON public.plans USING btree (name);


--
-- Name: product_variants_barcode_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX product_variants_barcode_idx ON public.product_variants USING btree (barcode);


--
-- Name: product_variants_productId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "product_variants_productId_idx" ON public.product_variants USING btree ("productId");


--
-- Name: product_variants_productId_sku_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "product_variants_productId_sku_key" ON public.product_variants USING btree ("productId", sku);


--
-- Name: product_variants_sku_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX product_variants_sku_idx ON public.product_variants USING btree (sku);


--
-- Name: product_variants_tenantId_barcode_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "product_variants_tenantId_barcode_key" ON public.product_variants USING btree ("tenantId", barcode);


--
-- Name: product_variants_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "product_variants_tenantId_idx" ON public.product_variants USING btree ("tenantId");


--
-- Name: products_barcode_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX products_barcode_idx ON public.products USING btree (barcode);


--
-- Name: products_sku_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX products_sku_idx ON public.products USING btree (sku);


--
-- Name: products_tenantId_barcode_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "products_tenantId_barcode_key" ON public.products USING btree ("tenantId", barcode);


--
-- Name: products_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "products_tenantId_idx" ON public.products USING btree ("tenantId");


--
-- Name: products_tenantId_sku_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "products_tenantId_sku_key" ON public.products USING btree ("tenantId", sku);


--
-- Name: promotions_isActive_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "promotions_isActive_idx" ON public.promotions USING btree ("isActive");


--
-- Name: promotions_productId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "promotions_productId_idx" ON public.promotions USING btree ("productId");


--
-- Name: promotions_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "promotions_tenantId_idx" ON public.promotions USING btree ("tenantId");


--
-- Name: purchase_order_items_purchaseOrderId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "purchase_order_items_purchaseOrderId_idx" ON public.purchase_order_items USING btree ("purchaseOrderId");


--
-- Name: purchase_orders_status_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX purchase_orders_status_idx ON public.purchase_orders USING btree (status);


--
-- Name: purchase_orders_supplierId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "purchase_orders_supplierId_idx" ON public.purchase_orders USING btree ("supplierId");


--
-- Name: purchase_orders_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "purchase_orders_tenantId_idx" ON public.purchase_orders USING btree ("tenantId");


--
-- Name: refunds_orderId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "refunds_orderId_idx" ON public.refunds USING btree ("orderId");


--
-- Name: refunds_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "refunds_tenantId_idx" ON public.refunds USING btree ("tenantId");


--
-- Name: roles_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "roles_tenantId_idx" ON public.roles USING btree ("tenantId");


--
-- Name: roles_tenantId_name_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "roles_tenantId_name_key" ON public.roles USING btree ("tenantId", name);


--
-- Name: sales_ledger_createdAt_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "sales_ledger_createdAt_idx" ON public.sales_ledger USING btree ("createdAt");


--
-- Name: sales_ledger_items_ledgerId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "sales_ledger_items_ledgerId_idx" ON public.sales_ledger_items USING btree ("ledgerId");


--
-- Name: sales_ledger_orderId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "sales_ledger_orderId_idx" ON public.sales_ledger USING btree ("orderId");


--
-- Name: sales_ledger_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "sales_ledger_tenantId_idx" ON public.sales_ledger USING btree ("tenantId");


--
-- Name: sessions_sessionToken_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "sessions_sessionToken_key" ON public.sessions USING btree ("sessionToken");


--
-- Name: sessions_userId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "sessions_userId_idx" ON public.sessions USING btree ("userId");


--
-- Name: staff_payroll_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "staff_payroll_tenantId_idx" ON public.staff_payroll USING btree ("tenantId");


--
-- Name: staff_payroll_userId_period_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "staff_payroll_userId_period_key" ON public.staff_payroll USING btree ("userId", period);


--
-- Name: stock_movements_productId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "stock_movements_productId_idx" ON public.stock_movements USING btree ("productId");


--
-- Name: stock_movements_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "stock_movements_tenantId_idx" ON public.stock_movements USING btree ("tenantId");


--
-- Name: storefront_config_tenantId_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "storefront_config_tenantId_key" ON public.storefront_config USING btree ("tenantId");


--
-- Name: subscriptions_currentPeriodEnd_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "subscriptions_currentPeriodEnd_idx" ON public.subscriptions USING btree ("currentPeriodEnd");


--
-- Name: subscriptions_status_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX subscriptions_status_idx ON public.subscriptions USING btree (status);


--
-- Name: subscriptions_tenantId_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "subscriptions_tenantId_key" ON public.subscriptions USING btree ("tenantId");


--
-- Name: suppliers_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "suppliers_tenantId_idx" ON public.suppliers USING btree ("tenantId");


--
-- Name: tax_filings_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "tax_filings_tenantId_idx" ON public.tax_filings USING btree ("tenantId");


--
-- Name: tax_filings_tenantId_period_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "tax_filings_tenantId_period_key" ON public.tax_filings USING btree ("tenantId", period);


--
-- Name: tenant_registrations_email_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX tenant_registrations_email_idx ON public.tenant_registrations USING btree (email);


--
-- Name: tenant_registrations_email_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX tenant_registrations_email_key ON public.tenant_registrations USING btree (email);


--
-- Name: tenant_registrations_planToken_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "tenant_registrations_planToken_key" ON public.tenant_registrations USING btree ("planToken");


--
-- Name: tenant_registrations_status_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX tenant_registrations_status_idx ON public.tenant_registrations USING btree (status);


--
-- Name: tenant_registrations_verifyToken_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "tenant_registrations_verifyToken_key" ON public.tenant_registrations USING btree ("verifyToken");


--
-- Name: tenants_slug_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX tenants_slug_key ON public.tenants USING btree (slug);


--
-- Name: transactions_orderId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "transactions_orderId_idx" ON public.transactions USING btree ("orderId");


--
-- Name: transactions_reference_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX transactions_reference_idx ON public.transactions USING btree (reference);


--
-- Name: transactions_reference_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX transactions_reference_key ON public.transactions USING btree (reference);


--
-- Name: transactions_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "transactions_tenantId_idx" ON public.transactions USING btree ("tenantId");


--
-- Name: users_email_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX users_email_idx ON public.users USING btree (email);


--
-- Name: users_email_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX users_email_key ON public.users USING btree (email);


--
-- Name: users_pin_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX users_pin_idx ON public.users USING btree (pin);


--
-- Name: users_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "users_tenantId_idx" ON public.users USING btree ("tenantId");


--
-- Name: vat_settings_tenantId_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "vat_settings_tenantId_key" ON public.vat_settings USING btree ("tenantId");


--
-- Name: verification_tokens_identifier_token_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX verification_tokens_identifier_token_key ON public.verification_tokens USING btree (identifier, token);


--
-- Name: virtual_accounts_orderId_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX "virtual_accounts_orderId_key" ON public.virtual_accounts USING btree ("orderId");


--
-- Name: virtual_accounts_reference_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX virtual_accounts_reference_idx ON public.virtual_accounts USING btree (reference);


--
-- Name: virtual_accounts_reference_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX virtual_accounts_reference_key ON public.virtual_accounts USING btree (reference);


--
-- Name: virtual_accounts_tenantId_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX "virtual_accounts_tenantId_idx" ON public.virtual_accounts USING btree ("tenantId");


--
-- Name: webhook_events_processed_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX webhook_events_processed_idx ON public.webhook_events USING btree (processed);


--
-- Name: webhook_events_reference_idx; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE INDEX webhook_events_reference_idx ON public.webhook_events USING btree (reference);


--
-- Name: webhook_events_reference_key; Type: INDEX; Schema: public; Owner: salesdock_user
--

CREATE UNIQUE INDEX webhook_events_reference_key ON public.webhook_events USING btree (reference);


--
-- Name: accounts accounts_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT "accounts_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: activity_logs activity_logs_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT "activity_logs_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: activity_logs activity_logs_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.activity_logs
    ADD CONSTRAINT "activity_logs_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_supervisorId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT "audit_logs_supervisorId_fkey" FOREIGN KEY ("supervisorId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: audit_logs audit_logs_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT "audit_logs_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: audit_logs audit_logs_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT "audit_logs_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: authenticators authenticators_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.authenticators
    ADD CONSTRAINT "authenticators_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: billing_invoices billing_invoices_subscriptionId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.billing_invoices
    ADD CONSTRAINT "billing_invoices_subscriptionId_fkey" FOREIGN KEY ("subscriptionId") REFERENCES public.subscriptions(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: billing_invoices billing_invoices_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.billing_invoices
    ADD CONSTRAINT "billing_invoices_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: branches branches_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT "branches_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: customers customers_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT "customers_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: expenses expenses_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT "expenses_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: financial_goals financial_goals_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.financial_goals
    ADD CONSTRAINT "financial_goals_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: holds holds_cashierId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.holds
    ADD CONSTRAINT "holds_cashierId_fkey" FOREIGN KEY ("cashierId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: holds holds_orderId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.holds
    ADD CONSTRAINT "holds_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES public.orders(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: holds holds_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.holds
    ADD CONSTRAINT "holds_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: notifications notifications_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT "notifications_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_items order_items_orderId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT "order_items_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES public.orders(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: order_items order_items_productId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT "order_items_productId_fkey" FOREIGN KEY ("productId") REFERENCES public.products(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: order_items order_items_variantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.order_items
    ADD CONSTRAINT "order_items_variantId_fkey" FOREIGN KEY ("variantId") REFERENCES public.product_variants(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: orders orders_branchId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT "orders_branchId_fkey" FOREIGN KEY ("branchId") REFERENCES public.branches(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: orders orders_cashierId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT "orders_cashierId_fkey" FOREIGN KEY ("cashierId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: orders orders_customerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT "orders_customerId_fkey" FOREIGN KEY ("customerId") REFERENCES public.customers(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: orders orders_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT "orders_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: password_reset_tokens password_reset_tokens_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT "password_reset_tokens_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_variants product_variants_productId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT "product_variants_productId_fkey" FOREIGN KEY ("productId") REFERENCES public.products(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: product_variants product_variants_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.product_variants
    ADD CONSTRAINT "product_variants_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: products products_activePromoId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT "products_activePromoId_fkey" FOREIGN KEY ("activePromoId") REFERENCES public.promotions(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: products products_supplierId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT "products_supplierId_fkey" FOREIGN KEY ("supplierId") REFERENCES public.suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: products products_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT "products_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: promotions promotions_createdBy_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.promotions
    ADD CONSTRAINT "promotions_createdBy_fkey" FOREIGN KEY ("createdBy") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: promotions promotions_productId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.promotions
    ADD CONSTRAINT "promotions_productId_fkey" FOREIGN KEY ("productId") REFERENCES public.products(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: promotions promotions_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.promotions
    ADD CONSTRAINT "promotions_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: purchase_order_items purchase_order_items_productId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT "purchase_order_items_productId_fkey" FOREIGN KEY ("productId") REFERENCES public.products(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: purchase_order_items purchase_order_items_purchaseOrderId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT "purchase_order_items_purchaseOrderId_fkey" FOREIGN KEY ("purchaseOrderId") REFERENCES public.purchase_orders(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: purchase_orders purchase_orders_supplierId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT "purchase_orders_supplierId_fkey" FOREIGN KEY ("supplierId") REFERENCES public.suppliers(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: purchase_orders purchase_orders_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT "purchase_orders_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: refunds refunds_orderId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT "refunds_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES public.orders(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: refunds refunds_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT "refunds_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: refunds refunds_transactionId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.refunds
    ADD CONSTRAINT "refunds_transactionId_fkey" FOREIGN KEY ("transactionId") REFERENCES public.transactions(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: roles roles_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT "roles_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_ledger sales_ledger_cashierId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger
    ADD CONSTRAINT "sales_ledger_cashierId_fkey" FOREIGN KEY ("cashierId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: sales_ledger_items sales_ledger_items_ledgerId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger_items
    ADD CONSTRAINT "sales_ledger_items_ledgerId_fkey" FOREIGN KEY ("ledgerId") REFERENCES public.sales_ledger(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_ledger_items sales_ledger_items_productId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger_items
    ADD CONSTRAINT "sales_ledger_items_productId_fkey" FOREIGN KEY ("productId") REFERENCES public.products(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: sales_ledger sales_ledger_orderId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger
    ADD CONSTRAINT "sales_ledger_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES public.orders(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sales_ledger sales_ledger_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger
    ADD CONSTRAINT "sales_ledger_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: sales_ledger sales_ledger_transactionId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sales_ledger
    ADD CONSTRAINT "sales_ledger_transactionId_fkey" FOREIGN KEY ("transactionId") REFERENCES public.transactions(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: sessions sessions_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT "sessions_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: staff_payroll staff_payroll_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.staff_payroll
    ADD CONSTRAINT "staff_payroll_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: staff_payroll staff_payroll_userId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.staff_payroll
    ADD CONSTRAINT "staff_payroll_userId_fkey" FOREIGN KEY ("userId") REFERENCES public.users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: stock_movements stock_movements_productId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT "stock_movements_productId_fkey" FOREIGN KEY ("productId") REFERENCES public.products(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: storefront_config storefront_config_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.storefront_config
    ADD CONSTRAINT "storefront_config_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: subscriptions subscriptions_planId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT "subscriptions_planId_fkey" FOREIGN KEY ("planId") REFERENCES public.plans(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: subscriptions subscriptions_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT "subscriptions_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: suppliers suppliers_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT "suppliers_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: tax_filings tax_filings_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.tax_filings
    ADD CONSTRAINT "tax_filings_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: transactions transactions_orderId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT "transactions_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES public.orders(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: transactions transactions_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT "transactions_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: users users_branchId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT "users_branchId_fkey" FOREIGN KEY ("branchId") REFERENCES public.branches(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: users users_roleId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT "users_roleId_fkey" FOREIGN KEY ("roleId") REFERENCES public.roles(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: users users_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT "users_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: vat_settings vat_settings_tenantId_fkey; Type: FK CONSTRAINT; Schema: public; Owner: salesdock_user
--

ALTER TABLE ONLY public.vat_settings
    ADD CONSTRAINT "vat_settings_tenantId_fkey" FOREIGN KEY ("tenantId") REFERENCES public.tenants(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: salesdock_user
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;


--
-- PostgreSQL database dump complete
--

\unrestrict kkwHYvaYdWXXrUPr7uXeukFVd8DNDohLupDeCLtIJbWt5ypsL28xKZbEyATFhSL

