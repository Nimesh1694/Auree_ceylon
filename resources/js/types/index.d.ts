export type UserRole =
    | 'owner'
    | 'super_admin'
    | 'marketing_manager'
    | 'it_manager'
    | 'accountant'
    | 'high_level_agent'
    | 'low_level_agent'
    | 'customer'
    | string;

export type UserStatus = 'active' | 'pending' | 'suspended';

export interface AgentProfile {
    id: number;
    user_id: number;
    business_name?: string | null;
    nic?: string | null;
    address?: string | null;
    bank_name?: string | null;
    bank_branch?: string | null;
    bank_account_number?: string | null;
    bank_account_name?: string | null;
    kyc_status: 'pending' | 'verified' | 'rejected';
    kyc_notes?: string | null;
    wallet_balance: string | number;
    reserved_balance?: string | number;
    available_balance?: string | number;
    total_earned: string | number;
    total_withdrawn: string | number;
}

export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role: UserRole;
    role_id?: number | null;
    protected_authority?: 'business' | 'technical' | null;
    status: UserStatus;
    referral_code?: string | null;
    parent_id?: number | null;
    email_verified_at?: string | null;
    agent_profile?: AgentProfile | null;
    parent?: User | null;
    children?: User[];
    created_at?: string;
}

export interface HierarchyChangeRequest {
    id: number;
    agent_id: number;
    current_parent_id?: number | null;
    requested_parent_id?: number | null;
    requested_by: number;
    reason?: string | null;
    status: 'pending' | 'approved' | 'rejected';
    review_notes?: string | null;
    reviewed_by?: number | null;
    reviewed_at?: string | null;
    created_at: string;
    agent?: User;
    requested_parent?: User | null;
    requested_by_user?: User | null;
    reviewed_by_user?: User | null;
}

export interface CommissionLedgerEntry {
    id: number;
    entry_number: string;
    order_id: number;
    order_item_id?: number | null;
    agent_id: number;
    selling_agent_id: number;
    commission_type: 'direct_high_level' | 'direct_low_level' | 'upline_override' | 'reversal';
    calculation_base: string;
    base_amount: string | number;
    rate_percentage: string | number;
    calculated_amount: string | number;
    currency: string;
    status: 'pending' | 'approved' | 'reversed';
    rate_snapshot?: Record<string, any> | null;
    original_entry_id?: number | null;
    reversal_reason?: string | null;
    approved_at?: string | null;
    reversed_at?: string | null;
    notes?: string | null;
    created_at: string;
    order?: Order;
    agent?: User;
    selling_agent?: User;
}

export interface CommissionRuleChange {
    id: number;
    rule_key: string;
    old_value?: string | null;
    new_value: string;
    requested_by: number;
    reviewed_by?: number | null;
    status: 'pending' | 'approved' | 'rejected';
    reason: string;
    review_notes?: string | null;
    reviewed_at?: string | null;
    created_at: string;
    requested_by_user?: User;
    reviewed_by_user?: User;
    requester?: User;
    reviewer?: User;
    requestedBy?: User;
    reviewedBy?: User;
}

export type PayoutStatus =
    | 'pending'
    | 'approved'
    | 'processing'
    | 'paid'
    | 'rejected'
    | 'cancelled'
    | 'failed';

export interface PayoutRequest {
    id: number;
    reference_number: string;
    agent_id: number;
    amount: string | number;
    currency: string;
    status: PayoutStatus;
    bank_name: string;
    bank_branch?: string | null;
    bank_account_number: string;
    bank_account_name: string;
    bank_snapshot?: {
        bank_name: string;
        bank_branch?: string | null;
        bank_account_number: string;
        bank_account_name: string;
        captured_at: string;
    } | null;
    agent_notes?: string | null;
    rejection_reason?: string | null;
    review_notes?: string | null;
    reviewed_by?: number | null;
    reviewed_at?: string | null;
    paid_by?: number | null;
    paid_at?: string | null;
    failed_by?: number | null;
    failed_at?: string | null;
    failure_reason?: string | null;
    bank_transfer_reference?: string | null;
    bank_transfer_receipt_path?: string | null;
    bank_transfer_notes?: string | null;
    created_at: string;
    updated_at: string;
    agent?: User;
    reviewer?: User | null;
    payer?: User | null;
    failed_by_user?: User | null;
    wallet_transactions?: WalletTransaction[];
}

export type WalletTransactionType =
    | 'commission_credit'
    | 'commission_reversal'
    | 'payout_reserved'
    | 'payout_released'
    | 'payout_completed';

export interface WalletTransaction {
    id: number;
    transaction_number: string;
    agent_id: number;
    transaction_type: WalletTransactionType;
    amount: string | number;
    balance_after: string | number;
    available_balance_after?: string | number;
    currency: string;
    source_type?: string | null;
    source_id?: number | null;
    description: string;
    metadata?: Record<string, any> | null;
    created_at: string;
    agent?: User;
}

export interface Category {
    id: number;
    name_en: string;
    name_si: string;
    slug: string;
    description_en?: string | null;
    description_si?: string | null;
    image?: string | null;
    sort_order?: number;
    is_active: boolean;
    products_count?: number;
}

export interface ProductImage {
    id: number;
    product_id: number;
    path: string;
    alt_text?: string | null;
    sort_order: number;
    is_primary: boolean;
}

export interface Product {
    id: number;
    category_id?: number | null;
    name_en: string;
    name_si: string;
    slug: string;
    sku?: string | null;
    short_description_en?: string | null;
    short_description_si?: string | null;
    description_en?: string | null;
    description_si?: string | null;
    ingredients_en?: string | null;
    ingredients_si?: string | null;
    directions_en?: string | null;
    directions_si?: string | null;
    price: string | number;
    compare_at_price?: string | number | null;
    cost_price?: string | number | null;
    stock_quantity: number;
    low_stock_threshold: number;
    expiry_date?: string | null;
    is_active: boolean;
    is_featured: boolean;
    is_best_seller: boolean;
    is_new_arrival: boolean;
    category?: Category | null;
    images?: ProductImage[];
    primary_image?: ProductImage | null;
}

export interface OrderItem {
    id: number;
    order_id: number;
    product_id?: number | null;
    product_name: string;
    product_sku?: string | null;
    unit_price: string | number;
    quantity: number;
    subtotal: string | number;
    product?: Product | null;
}

export interface Delivery {
    id: number;
    order_id: number;
    courier_name?: string | null;
    tracking_number?: string | null;
    status: string;
    actual_courier_fee?: string | number | null;
    courier_fee_recorded_by?: number | null;
    courier_fee_recorded_at?: string | null;
    courier_invoice_reference?: string | null;
    recorded_by?: User | null;
    dispatched_at?: string | null;
    delivered_at?: string | null;
    notes?: string | null;
}

export interface CodCollection {
    id: number;
    order_id: number;
    expected_amount: string | number;
    collected_amount: string | number;
    courier_name?: string | null;
    courier_reference?: string | null;
    reconciled_by?: number | null;
    reconciliation_status: 'pending' | 'matched' | 'mismatched_underpaid' | 'mismatched_overpaid';
    collected_at?: string | null;
    notes?: string | null;
    reconciled_by_user?: User | null;
}

export interface Order {
    id: number;
    order_number: string;
    customer_id?: number | null;
    customer_name: string;
    customer_phone: string;
    customer_email?: string | null;
    shipping_address: string;
    district: string;
    city: string;
    postal_code?: string | null;
    subtotal: string | number;
    delivery_charge: string | number;
    total_amount: string | number;
    payment_method: string;
    status: string;
    agent_id?: number | null;
    referral_code?: string | null;
    tracking_token?: string;
    notes?: string | null;
    placed_at: string;
    items?: OrderItem[];
    delivery?: Delivery | null;
    cod_collection?: CodCollection | null;
    customer?: User | null;
    agent?: User | null;
}

export interface InventoryMovement {
    id: number;
    product_id: number;
    movement_type: string;
    quantity: number;
    previous_stock: number;
    new_stock: number;
    reason?: string | null;
    order_id?: number | null;
    user_id?: number | null;
    created_at: string;
    product?: Product | null;
    order?: Order | null;
    user?: User | null;
}

export interface Courier {
    id: number;
    name: string;
    code: string;
    phone?: string | null;
    website?: string | null;
    is_active: boolean;
}

export interface AuditLog {
    id: number;
    user_id?: number | null;
    action: string;
    auditable_type?: string | null;
    auditable_id?: number | null;
    old_values?: Record<string, unknown> | null;
    new_values?: Record<string, unknown> | null;
    ip_address?: string | null;
    user_agent?: string | null;
    created_at: string;
    user?: User | null;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface Permission {
    id: number;
    name: string;
    display_name: string;
    module: string;
    description?: string | null;
    is_financial_restricted: boolean;
}

export interface Role {
    id: number;
    name: string;
    display_name: string;
    description?: string | null;
    is_system: boolean;
    is_protected: boolean;
    created_by?: number | null;
    created_at?: string;
    permissions?: Permission[];
    users_count?: number;
    assigned_users_count?: number;
}

export interface RolePermissionRequest {
    id: number;
    role_id: number;
    permission_id: number;
    requested_by: number;
    reviewed_by?: number | null;
    status: 'pending' | 'approved' | 'rejected';
    reason: string;
    review_notes?: string | null;
    reviewed_at?: string | null;
    created_at: string;
    role?: Role;
    permission?: Permission;
    requester?: User;
    reviewer?: User | null;
}

export interface AgentInvitation {
    id: number;
    token_hash: string;
    invitation_type: 'high_level_agent' | 'low_level_agent';
    created_by: number;
    parent_agent_id?: number | null;
    candidate_name?: string | null;
    candidate_email?: string | null;
    candidate_phone?: string | null;
    status: 'pending' | 'accepted' | 'expired' | 'revoked';
    expires_at: string;
    used_at?: string | null;
    used_by?: number | null;
    created_at: string;
    updated_at: string;
    creator?: {
        id: number;
        name: string;
        role: string;
    } | null;
    parent_agent?: {
        id: number;
        name: string;
        referral_code?: string;
    } | null;
    parentAgent?: {
        id: number;
        name: string;
        referral_code?: string;
    } | null;
    used_by_user?: {
        id: number;
        name: string;
        email?: string;
    } | null;
    usedBy?: {
        id: number;
        name: string;
        email?: string;
    } | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions?: string[];
        is_owner?: boolean;
        is_super_admin?: boolean;
        is_business_authority?: boolean;
        is_technical_authority?: boolean;
    };
    locale: 'en' | 'si';
    translations: Record<string, string>;
    flash: {
        success?: string | null;
        error?: string | null;
        generated_link?: string | null;
        token?: string | null;
        candidate_name?: string | null;
    };
    cartCount: number;
};
