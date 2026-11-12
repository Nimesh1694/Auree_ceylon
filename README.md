# Aura Luxe Botanical Cosmetics — E-Commerce & Multi-Level Agent Network

A production-ready cosmetic brand e-commerce platform featuring a multi-level agent network, real-time inventory locking, Cash on Delivery (COD) order management, courier tracking, and an authorized agent portal.

Built with **Laravel 12 (PHP 8.2+)**, **React 18**, **TypeScript**, **Inertia.js**, and **Tailwind CSS**. Designed specifically for shared hosting (cPanel) compatibility without requiring Docker or Redis.

---

## Current Status: Phase 6 Complete

| Phase | Description | Status | Test Coverage |
| :--- | :--- | :--- | :--- |
| **Phase 1** | Foundation Architecture, Auth, Roles, Closure Table Hierarchy | **Audited & Passed** | 40 tests, 112 assertions |
| **Phase 2** | Customer Storefront, Cart, COD Checkout, Guest Tracking | **Audited & Passed** | 62 tests, 287 assertions |
| **Phase 3** | Inventory & Order Management, Courier & COD Reconciliation | **Audited & Passed** | 97 tests, 490 assertions |
| **Phase 4** | Multi-Level Agent Network, Agent Portal & Administration | **Audited & Passed** | 133 tests, 737 assertions |
| **Phase 5** | Multi-Tier Commission Calculation Engine & Immutable Ledger | **Audited & Passed** | 168 tests, 848 assertions |
| **Phase 6** | Agent Wallet, Commission Payouts & Statements | **Implemented & Verified** | **191 tests, 1030 assertions** |
| **Phase 7** | Reports, Analytics & cPanel Deployment Package | *Pending Approval* | — |

---

## Phase 6 Features Implemented

### 1. Agent Wallet & Balance Tracking
- **Decoupled Wallet Service**: `App\Services\WalletService`
- **Gross Wallet vs Available Balance**:
  - `wallet_balance`: Gross balance of all approved commissions less completed payouts and reversals.
  - `reserved_balance`: Balance held in escrow for active pending, approved, or processing payout requests.
  - `available_balance`: Dynamically computed as `bcsub(wallet_balance, reserved_balance, 2)`.
  - Available balance is strictly spendable and withdrawable. Pending commissions awaiting customer delivery/COD confirmation are excluded.
- **Dedicated Wallet Journal**: `wallet_transactions` table logs all credits, reversals, reservations, releases, and completed transfers with signed amounts and `balance_after`.

### 2. Commission Payout Request System
- **Decoupled Payout Service**: `App\Services\PayoutService`
- **Immediate Escrow Reservation**: Submitting a payout request locks the agent row via `lockForUpdate()` and increments `reserved_balance`, instantly preventing duplicate or concurrent overdrawing.
- **Immutable Bank Snapshot**: Captures the exact bank name, branch, account number, and account name at submission time.
- **Pre-flight Validations**: Payouts are restricted unless the agent has verified KYC and valid bank account details configured.
- **Agent Cancellation**: Agents can cancel their own pending requests anytime to immediately release reserved funds.

### 3. Owner-Exclusive Approval Workflow
- **Executive Authorization**: Payout approval and rejection are strictly restricted to the Executive Company Owner (`isOwner()` server-side enforcement; `403 Forbidden` for non-owners).
- **Mandatory Rejection Reasons**: Rejections require an audited reason and automatically release reserved funds back to the agent's available balance.
- **Dual Management Remittance**: Marking as processing and recording bank remittance payments is accessible to the `owner` and `accountant`.

### 4. Bank Transfer Settlement & Payment Records
- **Bank Transfer Tracking**: Records unique bank transfer reference numbers, notes, and uploaded PDF/image transfer receipts into storage.
- **Atomic Balance Settlement**: Deducts BOTH `wallet_balance` and `reserved_balance`, increments `total_withdrawn`, and transitions the request to `paid`.
- **Duplicate Prevention**: Bank transfer references must be unique.
- **Notifications**: Automated database notifications alert agents when payouts are requested, approved, rejected, processing, paid, or cancelled.

### 5. Official Monthly Financial Statements
- **Dedicated Statements Engine**: Computes certified monthly statements with opening balance, chronological transaction ledger, categorized totals, and closing balance using exact BCMath string arithmetic.
- **Print-Friendly View**: CSS print layout for physical printing or PDF export with brand headers and verification footers.
- **Privacy Enforcement**: Statements are strictly isolated to the authenticated agent (`403 Forbidden` on unauthorized access).

### 6. Management Financial Dashboard & Clearing Queue
- **Executive Dashboard (`/admin/payouts/dashboard`)**: Aggregates company-wide agent wallet liabilities, pending owner reviews, processing funds, and completed payouts.
- **Payout Queue (`/admin/payouts`)**: Tabbed status views, text search, date filtering, and quick modal actions for approval, rejection, and payment recording.
- **Detailed Audit Inspector (`/admin/payouts/{id}`)**: Complete timeline, immutable bank snapshot, reviewer/payer metadata, and linked journal entries.

---

---

## Phase 5 Features Implemented

### 1. Multi-Tier Commission Calculation Engine
- **Decoupled Service**: `App\Services\CommissionService`
- **Rule A (High-Level Agent Direct Sale)**: 25% direct commission on `orders.subtotal`.
- **Rule B (Low-Level Agent Sale)**: 25% direct commission on `subtotal` + 10% upline override for their immediate active high-level ancestor in the closure tree.
- **Rule C (Direct Customer Sale)**: 0% commission.
- **Rule D (Recruitment)**: 0% commission on recruiting/onboarding agents.
- **Rule E (Holding Period)**: Pending commissions require delivery confirmation, verified COD collection (`matched`), and return window (default 7 days) to elapse before auto-approval.
- **Rule F (Management Roles)**: 0% per-sale commission for management accounts (`owner`, `marketing_manager`, `it_manager`, `accountant`).
- **Precision**: Uses BCMath / string decimal calculations avoiding binary floating-point roundoff errors.

### 2. Append-Only Immutable Ledger (`commission_ledger_entries`)
- Each commission event records an immutable unique transaction `entry_number` (`COMM-YYYYMMDD-XXXXXX`).
- Preserves complete rate snapshot (`tier`, `rate_percentage`, `base_amount`, `rule`, `timestamp`) in JSON format.
- **Strict Immutability**: Historical entries are never deleted or mutated.
- **Reversal Ledger Entries**: Order cancellations, doorstep refusals, and returns append an offsetting reversal record (`commission_type = 'reversal'`) with negative calculated amount linked to `original_entry_id`.
- Post-approval returns safely decrement `agent_profiles.wallet_balance` and `total_earned`.

### 3. Executive Governance & Rule Adjustment Workflow
- Critical commission parameters (`direct_agent_commission_percent`, `upline_agent_commission_percent`, `max_commission_percent_per_order`, `commission_return_period_days`) stored in `settings`.
- **Two-Step Approval**: Proposed changes are tracked in `commission_rule_changes`.
- **Executive Owner Only**: Non-owner management roles cannot activate rule changes (`403 Forbidden`). Only the company Owner can review, approve, and activate changes.
- All actions logged to the centralized `audit_logs` table.

### 4. Frontends & Portals
- **Agent Commission Ledger (`/agent/commissions`)**: Filterable commission statements, transaction details, and earnings breakdown.
- **Admin Commission Ledger (`/admin/commissions/ledger`)**: Company-wide financial ledger with metrics, filters, and on-demand eligibility processing trigger.
- **Admin Commission Governance (`/admin/commissions/rules`)**: Active rule definitions, adjustment proposal form, and Owner review queue.

---

## Phase 4 Features Implemented

### 1. Agent Registration & Mandatory Sponsor Validation
- Public registration endpoint: `/agent/register`
- Requires valid sponsor referral code from an active agent in the network.
- Automatically generates unique 8-character uppercase referral code.
- New accounts enter in `pending` status with `low_level_agent` role and unverified KYC.
- Direct management email and database notifications dispatched upon registration.

### 2. Referral Cookie & Attribution Engine
- Global `CaptureReferralCode` middleware detects `?ref=CODE` in URL.
- Sets 30-day encrypted `referral_code` cookie and session attribution.
- Restores active referral code on return visits even after session expiration.
- Shares `referralCode` prop with all Inertia views.

### 3. Agent Portal
- Accessible at `/agent/*` for active agents with role `high_level_agent` or `low_level_agent`.
- **Dashboard (`/agent/dashboard`)**:
  - Independent metrics distinguishing own physically placed orders, customer referral orders, and downline team orders.
  - One-click personal referral link copy.
  - Active KYC verification banner and warning prompts.
- **Assisted Customer Order Placement (`/agent/orders/create`)**:
  - Agents can place COD orders on behalf of offline clients.
  - Sets `orders.agent_id` (placing agent) and `orders.referral_code` (credited sponsor).
  - Triggers server-side stock validation, pessimistic locking, and inventory deduction.
- **My Placed Orders (`/agent/orders`)**:
  - List of orders physically placed by the agent with real-time delivery and payment status.
- **Downline Network Explorer (`/agent/team`)**:
  - Visual upline sponsorship path to brand root.
  - Multi-level downline table with depth filters (Level 1 direct, Level 2+, etc.).
  - **Downline Privacy Protection**: Sensitive records (NIC numbers, personal bank accounts, and wallet balances) of downline agents are strictly masked from peer access.
- **Profile & KYC Management (`/agent/profile`)**:
  - Update personal contact details, business/salon name, and bank account for payouts.
  - Tamper-proof: Agents cannot self-modify their role, account status, referral code, or KYC verification status.

### 4. Admin Agent Network Management
- Accessible at `/admin/agents/*` for authorized management roles.
- **Directory & Filters (`/admin/agents`)**:
  - Filter by account status (`pending`, `active`, `suspended`) and KYC status (`pending`, `verified`, `rejected`).
  - Search by name, phone, email, or referral code.
  - Quick action buttons to Approve or Reject pending applications.
- **Agent Detail & Compliance (`/admin/agents/{agent}`)**:
  - Identity & KYC review card (approve or reject with reviewer notes).
  - Account status control (suspend or re-activate with audit logging).
  - Upline & downline hierarchy visualization.
  - Complete audit trail of all management modifications.
- **Hierarchy Movement with Cycle Detection**:
  - Submit hierarchy change requests to re-assign an agent's sponsor or promote to top-level.
  - Cycle detection in `AgentHierarchyService` prevents circular loops (an agent cannot become a child of their own descendant).
  - **Owner-Only Authorization**: Only the executive company Owner can approve and execute hierarchy tree moves (`403 Forbidden` enforced for other management roles).

---

## Role Permissions Matrix

| Permission / Function | Owner | IT Manager | Marketing Manager | Accountant | High-Level Agent | Low-Level Agent | Customer |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Storefront & COD Checkout | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Agent Portal Access | No | No | No | No | Yes | Yes | No |
| Place Order on Behalf of Customer | No | No | No | No | Yes | Yes | No |
| View Own Downline Network | No | No | No | No | Yes | Yes | No |
| Admin Dashboard & Orders | Yes | Yes | Yes | Yes | No | No | No |
| Inventory Movements & Restock | Yes | Yes | No | No | No | No | No |
| Update Order Delivery & Courier | Yes | Yes | No | No | No | No | No |
| COD Financial Reconciliation | Yes | Yes | No | Yes | No | No | No |
| View Agent Directory | Yes | Yes | Yes | Yes | No | No | No |
| Approve / Reject Agent Application | Yes | Yes | No | No | No | No | No |
| Verify Agent KYC Documents | Yes | Yes | No | Yes | No | No | No |
| Request Hierarchy Movement | Yes | Yes | No | No | No | No | No |
| **Execute Hierarchy Movement** | **Yes** | **No** | **No** | **No** | **No** | **No** | **No** |

---

## Verification & Automated Testing

```bash
# Run PHPUnit test suite (168 tests, 848 assertions)
php .\vendor\bin\phpunit

# Run TypeScript strict type-check (0 errors)
npx tsc --noEmit

# Run Vite production build (0 warnings/errors)
npm run build

# Validate Composer configuration (valid)
composer validate
```

---

## Production Deployment & cPanel Shared Hosting Configuration

### 1. PHP Requirements
- **PHP Version**: `^8.2`
- **Required Extension**: `ext-bcmath` (Enabled by default in modern cPanel installations via "Select PHP Version" / EA-PHP). Declared in `composer.json`.

### 2. Automated Daily Commission Maturation (cPanel Cron)
Add the following cron entry via cPanel > **Cron Jobs**:
```cron
* * * * * cd /home/username/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```
Alternatively, schedule the commission maturation command directly once per day:
```cron
0 2 * * * cd /home/username/public_html && /usr/local/bin/php artisan commissions:process-eligible >> /dev/null 2>&1
```
The command runs idempotently with atomic database status checks, ensuring that scheduled cron runs and manual management clicks on the admin portal can never conflict or duplicate wallet credits.
