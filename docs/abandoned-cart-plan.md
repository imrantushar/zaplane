# Abandoned Cart Feature — Zaplane (dependent on GemCRM)

## Overview

WooCommerce stores lose revenue when customers add items to cart but don't complete checkout. This module tracks abandoned carts programmatically, creates **GemCRM** contacts from cart data, applies configurable tags/lists to trigger email automations, and marks carts as recovered or lost based on configurable time windows.

**Requirement:** GemCRM plugin (`gemcrm/gemcrm.php`) must be active.  
**Reference implementation:** `fluentcampaign-pro/app/Modules/AbandonCart/` (studied for architecture patterns).

### Two Types of Abandoned Carts in WooCommerce

| Type | Description |
|------|-------------|
| **Active carts** | Customer added items but never started checkout — captured when they reach the checkout page and enter their email via AJAX |
| **Unpaid orders** | Checkout was started (order created) but payment was never completed — captured via the `zaplane_ab_cart_token` cookie that links the cart record to the order |

---

## Architecture

The feature lives entirely inside **Zaplane** as a self-contained module:

- DB table: `wp_zaplane_abandonned_cart` (Zaplane Schema prepends `wp_zaplane_`)
- REST namespace: `zaplane/v1`
- Module class implements `Zaplane\Framework\Core\ModuleInterface`
- GemCRM integrated via its PHP models directly (not via HTTP)
- Scheduling via **Action Scheduler** (already vendored in both Zaplane and GemCRM)

---

## Cart Lifecycle — State Machine

```
[Customer on checkout]
        │
        ▼ AJAX capture (on email input)
    ┌─────────┐
    │  draft  │ ─── user completes order immediately ──→ DELETE record
    └────┬────┘     (was never abandoned; order linked via cookie)
         │
         │ cart_off_time minutes pass with no order
         ▼
    ┌──────────┐
    │ skipped  │ ◄── no automation configured (no tags/lists set)
    └──────────┘
         │
         │ automation IS configured
         ▼
    ┌────────────┐
    │ processing │ ◄── GemCRM contact created, abandoned tags/lists applied
    └─────┬──────┘     do_action('zaplane/abandoned_cart/started')
          │
     ┌────┴────┐
     │         │
     ▼         ▼
┌─────────┐  ┌──────┐
│recovered│  │ lost │
└─────────┘  └──────┘
  Order          mark_as_lost_after_minutes
  completed      passes without recovery
  → remove       → apply lost tags/lists
  abandoned      → remove abandoned tags/lists
  tags/lists     do_action('zaplane/abandoned_cart/lost')
  do_action(
  'zaplane/
  abandoned_cart/
  recovered')

Special states (set immediately, never transition further):
  opt_out  — customer rejected GDPR consent checkbox
```

### Status Reference

| Status | When set | Notes |
|--------|----------|-------|
| `draft` | AJAX capture on checkout page | Deleted if user completes order before `cart_off_time` |
| `skipped` | `cart_off_time` elapsed, but no tags/lists configured | Automation has nothing to do |
| `opt_out` | Customer clicked opt-out on GDPR checkbox | `is_optout=1`, cookie set |
| `processing` | Automation started — contact created, tags/lists applied | Recovery email sequence can now fire |
| `recovered` | WC order placed after recovery link + order status changed to recovery status | Abandoned tags/lists removed from contact |
| `lost` | `mark_as_lost_after_minutes` elapsed without recovery | Lost tags/lists applied, abandoned tags/lists removed |

---

## Files to Create

### PHP / Backend

```
zaplane/
├── assets/js/
│   └── abandoned-cart-woo.js              # AJAX cart capture on WC checkout
└── includes/
    └── modules/
        └── abandoned-cart/
            ├── abandoned-cart-module.php       # ModuleInterface — boots everything
            ├── abandoned-cart-model.php        # ORM model for zaplane_abandonned_cart
            ├── abandoned-cart-migration.php    # Schema::create() migration
            ├── abandoned-cart-runner.php       # Cron: draft→processing / processing→lost
            ├── abandoned-cart-helper.php       # Settings r/w, GDPR checks, role checks
            ├── woo/
            │   └── woo-cart-tracking-init.php # WC hooks, AJAX handlers, recovery link
            └── api/
                ├── settings-controller.php        # GET/POST /abandoned-cart/settings
                └── abandoned-cart-controller.php  # GET /abandoned-carts, report, bulk-delete
```

### Frontend / React

```
zaplane/
└── dev_zaplane/
    ├── containers/
    │   └── BackendDashboard/
    │       └── pages/
    │           └── abandoned-carts/
    │               ├── index.js                   # Tab router: List vs Settings
    │               ├── CartList/
    │               │   ├── index.js               # Full list page with report cards
    │               │   ├── ReportCards.js         # 5 stat cards (recovered/processing/lost/draft/optout)
    │               │   ├── CartTable.js           # ZAPTable with status badge, actions
    │               │   └── CartFilters.js         # Status dropdown, search, date range
    │               └── CartSettings/
    │                   ├── index.js               # Settings form (Formik)
    │                   ├── GeneralSettings.js     # Enable toggle + time fields
    │                   ├── GDPRSettings.js        # GDPR toggle + message textarea
    │                   ├── TaggingSettings.js     # Abandoned/lost tags and lists selects
    │                   └── RecoverySettings.js    # Recovery status multi-select + role exclusion
    └── redux/
        └── Slices/
            └── abandonedCartSlice/
                ├── index.js                       # slice + reducers
                └── actions.js                     # async thunks (fetchCarts, fetchReport, saveSettings…)
```

---

## Database Table

**Migration class:** `Zaplane\Modules\AbandonedCart\AbandonedCartMigration`  
**Table:** `wp_zaplane_abandonned_cart` (Schema prepends `wp_zaplane_` automatically)

```php
Schema::create('abandonned_cart', function (Blueprint $table) {
    $table->id();
    $table->string('checkout_key', 192)->nullable();
    $table->string('cart_hash', 192)->nullable();
    $table->tinyInteger('is_optout')->default(0);
    $table->string('full_name', 192)->nullable();
    $table->string('email', 192)->nullable();
    $table->string('provider', 100)->default('woo');
    $table->unsignedBigInteger('user_id')->nullable();
    $table->unsignedBigInteger('click_counts')->default(0);
    $table->unsignedBigInteger('contact_id')->nullable();
    $table->unsignedBigInteger('order_id')->nullable();
    $table->unsignedBigInteger('automation_id')->nullable();
    $table->unsignedBigInteger('checkout_page_id')->nullable();
    $table->string('status', 30)->default('draft');
    $table->decimal('subtotal', 10, 2)->nullable();
    $table->decimal('shipping', 10, 2)->nullable();
    $table->decimal('tax', 10, 2)->nullable();
    $table->decimal('discounts', 10, 2)->nullable();
    $table->decimal('fees', 10, 2)->nullable();
    $table->decimal('total', 10, 2)->nullable();
    $table->string('currency', 50)->nullable();
    $table->longText('cart')->nullable();
    $table->text('note')->nullable();
    $table->timestamp('abandoned_at')->nullable();
    $table->timestamp('recovered_at')->nullable();
    $table->timestamps();

    $table->index('status');
    $table->index('checkout_key');
    $table->index('email');
});
```

**`cart` column JSON structure:**
```json
{
  "customer_data": {
    "billing_email": "john@example.com",
    "billingAddress": { "first_name":"","last_name":"","company":"","address_1":"","address_2":"","city":"","state":"","postcode":"","country":"","phone":"" },
    "shippingAddress": { "...same..." },
    "differentShipping": "no",
    "order_comments": ""
  },
  "cart_contents": [
    { "product_id":42, "variation_id":0, "quantity":2, "line_total":800.00, "line_tax":40.00, "title":"Product Name", "variation":{} }
  ],
  "coupons": ["SAVE10"],
  "fees": []
}
```

Register migration by adding `AbandonedCartMigration::class` to **`zaplane/includes/installer.php`**.

---

## Settings

**Option key:** `zaplane_abandoned_cart_settings`

| Field key | Label | Type | Default | Description |
|-----------|-------|------|---------|-------------|
| `status` | Enable Abandoned Cart | `bool` | `false` | Master on/off switch |
| `cart_off_time` | Cart Abandoned Cut-off Time (Minutes) | `int` | `30` | Inactivity before draft → abandoned |
| `mark_as_lost_after_minutes` | Mark as Lost after (Minutes) | `int` | `10080` | Minutes after `abandoned_at` before → lost (default 7 days) |
| `cool_off_period` | Cool-Off Period (Minutes) | `int` | `10080` | Skip tracking if customer placed paid order within this window |
| `status_of_new_contact` | Status for New Contacts | `string` | `transactional` | GemCRM contact status for new contacts |
| `mark_as_recovered_when_order_status_changed_to` | Recover on Order Status | `array` | `['processing','completed']` | WC statuses that trigger recovery |
| `gdpr_consent_in_woo_checkout_page` | Show GDPR Consent | `bool` | `false` | Show opt-in message on checkout |
| `gdpr_msg` | GDPR Message | `string` | `'By continuing, you agree...'` | Text displayed on checkout |
| `disabled_user_roles` | Disable Tracking For User Roles | `array` | `[]` | WP user roles excluded from tracking |
| `abandoned_list` | Add Lists on Cart Abandoned | `array` | `[]` | GemCRM list IDs — applied on processing, removed on recovery |
| `abandoned_tags` | Add Tags on Cart Abandoned | `array` | `[]` | GemCRM tag IDs — applied on processing, removed on recovery |
| `lost_list` | Add Lists on Cart Lost | `array` | `[]` | GemCRM list IDs applied when lost |
| `lost_tags` | Add Tags on Cart Lost | `array` | `[]` | GemCRM tag IDs applied when lost |

---

## REST API Endpoints

All routes under `zaplane/v1`. Registered in `zaplane/includes/api.php`. Controllers extend `WP_REST_Controller`.

### Settings

| Method | Route | Handler |
|--------|-------|---------|
| `GET` | `/abandoned-cart/settings` | `SettingsController::get_settings()` |
| `POST` | `/abandoned-cart/settings` | `SettingsController::save_settings()` |

Response includes: `settings`, `woo_order_statuses`, `gemcrm_tags`, `gemcrm_lists`, `wp_roles`.

### Cart Management

| Method | Route | Handler |
|--------|-------|---------|
| `GET` | `/abandoned-carts` | `AbandonedCartController::get_carts()` |
| `POST` | `/abandoned-carts/bulk-delete` | `AbandonedCartController::bulk_delete()` |
| `GET` | `/abandoned-carts/report` | `AbandonedCartController::get_report()` |

**`GET /abandoned-carts` query params:** `status`, `search`, `date_from`, `date_to`, `per_page`, `page`

**Report response:**
```json
{
  "report_name": "Abandon Carts - Reports",
  "currency": "BDT",
  "date_range": { "from": "2026-05-01", "to": "2026-05-10" },
  "summary": {
    "recovered_revenue":  { "orders": 12, "amount": 45800.00, "formatted": "45,800.00৳" },
    "processing_revenue": { "orders": 5,  "amount": 12250.00, "formatted": "12,250.00৳" },
    "lost_revenue":       { "orders": 34, "amount": 98700.00, "formatted": "98,700.00৳" },
    "draft_revenue":      { "orders": 7,  "amount": 15400.00, "formatted": "15,400.00৳" },
    "optout_revenue":     { "orders": 3,  "amount": 6200.00,  "formatted": "6,200.00৳"  },
    "recovery_rate":      { "percentage": 26.09, "formatted": "26.09%" }
  },
  "totals": {
    "total_abandoned_carts": 46,
    "total_recovered_carts": 12,
    "total_revenue": 178350.00,
    "currency_symbol": "৳"
  }
}
```

---

## WooCommerce Cart Tracking (`WooCartTrackingInit`)

### JS Injection

Hooks: `woocommerce_after_checkout_form` (classic) + `woocommerce_blocks_enqueue_checkout_block_scripts_after` (blocks)  
Script: `zaplane/assets/js/abandoned-cart-woo.js`  
Localized as `zaplane_ab_cart`: `{ ajax_url, nonce, is_gdpr_enabled, gdpr_message }`

### AJAX: Cart Capture — `wc_ajax_zaplane_ab_cart_update`

```
POST data: billing_email, billingAddress{}, shippingAddress{}, differentShipping, order_comments
Server calculates: subtotal, shipping, tax, discounts, fees, total, currency, cart_contents[], cart_hash, coupons[]

Checks (skip if any fail):
  - is_enabled()
  - opt-out cookie zaplane_ab_cart_skip absent
  - email not empty
  - user role not in disabled_user_roles

Record logic:
  - Read zaplane_ab_cart_token cookie
  - Cookie exists → UPDATE existing draft
  - No cookie → INSERT new row (status=draft, checkout_key=wp_generate_uuid4())
  - Set HttpOnly cookie zaplane_ab_cart_token = checkout_key (30 days)
```

### AJAX: GDPR Opt-Out — `wc_ajax_zaplane_ab_cart_optout`

```
1. Find draft by zaplane_ab_cart_token cookie
2. UPDATE: is_optout=1, status='opt_out'
3. Set zaplane_ab_cart_skip cookie (7 days), delete zaplane_ab_cart_token cookie
```

### Order Created — `woocommerce_checkout_order_processed` + `woocommerce_store_api_checkout_order_processed`

```
1. Read zaplane_ab_cart_token cookie
2. Find record by checkout_key:
   - status = 'draft'      → DELETE record (not abandoned — normal purchase)
   - status = 'processing' → UPDATE order_id = $order_id
                             SET cookie zaplane_ab_cart_token = checkout_key (keep alive)
3. If no cookie / no record → do nothing
```

> **Important:** When a `processing` cart's order is placed via the recovery link, the cookie is kept alive so the subsequent order status change can find the record and mark it recovered.

### Order Status Change — `woocommerce_order_status_changed` (priority 10, 4 args)

**Recovery** — new status ∈ `mark_as_recovered_when_order_status_changed_to`:
```
1. Find record WHERE order_id = $order_id AND status = 'processing'
2. UPDATE: status='recovered', recovered_at=now()
3. GemCRM: Tag::attach($contact_id, [])          — clears abandoned tags
4. GemCRM: ListModel::attach($contact_id, [])    — clears abandoned lists
5. do_action('zaplane/abandoned_cart/recovered', $cart, $order)
6. Clear zaplane_ab_cart_token cookie
```

**Loss** — new status ∈ `['cancelled','refunded','failed']`:
```
1. Find record WHERE order_id = $order_id AND status = 'processing'
2. UPDATE: status='lost'
3. GemCRM: apply lost_tags and lost_list
4. GemCRM: clear abandoned_tags and abandoned_list
5. do_action('zaplane/abandoned_cart/lost', $cart, $order)
```

### Recovery Link Handler — `template_redirect`

URL: `/?zaplane=1&route=abandoned-cart&checkout_key={token}`

```
1. Validate: GET params present, checkout_key sanitised
2. Find record: checkout_key matches AND status = 'processing'
3. If not found → redirect to wc_get_cart_url()
4. WC()->cart->empty_cart()
5. Re-add each cart_contents item via WC()->cart->add_to_cart(product_id, qty, variation_id, variation)
6. Restore coupons: WC()->cart->apply_coupon($code)
7. Store customer_data in WC session (for checkout field prefill)
8. UPDATE: click_counts += 1
9. SET cookie zaplane_ab_cart_token = checkout_key (30 days, HttpOnly)
   ← this cookie ensures the subsequent order placement links back to this record
10. Hook woocommerce_checkout_get_value → prefill billing/shipping fields from session
11. redirect to wc_get_checkout_url()
```

> **Recovery auto-detection:** Step 9 re-sets the `zaplane_ab_cart_token` cookie with the cart's `checkout_key`. When the customer then places the order at checkout, `woocommerce_checkout_order_processed` finds the cookie, links `order_id` to the `processing` record. When WooCommerce transitions the order to a recovery status (`processing`, `completed`, etc.), `woocommerce_order_status_changed` fires and marks the cart as `recovered` automatically — no manual intervention needed.

---

## Cron / Runner Logic (`AbandonedCartRunner`)

Uses **Action Scheduler** (vendored). Two recurring actions registered on `zaplane_init`:

```php
public static function schedule_recurring(): void {
    if (!as_next_scheduled_action('zaplane_ab_cart_check_abandoned')) {
        as_schedule_recurring_action(time(), 300, 'zaplane_ab_cart_check_abandoned', [], 'zaplane_abandoned_cart');
    }
    if (!as_next_scheduled_action('zaplane_ab_cart_check_lost')) {
        as_schedule_recurring_action(time(), DAY_IN_SECONDS, 'zaplane_ab_cart_check_lost', [], 'zaplane_abandoned_cart');
    }
}
```

### Every 5 min — `run_abandoned()` — Draft → Processing or Skipped

```
1. Fetch WHERE status='draft' AND updated_at <= (now - cart_off_time minutes)
2. For each:
   a. Skip if cool_off_period check fails (recent paid order by this email)
   b. Skip + mark 'skipped' if user role in disabled_user_roles
   c. If abandoned_tags empty AND abandoned_list empty → status='skipped', continue
   d. Create/update GemCRM contact (Contact::email_exists → update or create)
   e. Tag::attach($contact_id, $settings['abandoned_tags'])
   f. ListModel::attach($contact_id, $settings['abandoned_list'])
   g. UPDATE: status='processing', contact_id=$id, abandoned_at=now()
   h. do_action('zaplane/abandoned_cart/started', $cart_record)
```

### Daily — `run_lost()` — Processing → Lost

```
1. Fetch WHERE status='processing' AND abandoned_at <= (now - mark_as_lost_after_minutes minutes)
2. For each:
   a. Tag::attach($contact_id, $settings['lost_tags'])
   b. ListModel::attach($contact_id, $settings['lost_list'])
   c. Clear abandoned tags/lists
   d. UPDATE: status='lost'
   e. do_action('zaplane/abandoned_cart/lost', $cart_record)
```

---

## GemCRM API Integration

| File | Class | Methods used |
|------|-------|-------------|
| `gemcrm/includes/database/models/contact.php` | `Contact` | `email_exists()`, `create()`, `ins()->qb()->where()->first()` |
| `gemcrm/includes/database/models/label-base.php` | `LabelBase` | `attach(int $contact_id, array $ids)` — full replacement |
| `gemcrm/includes/database/models/tag.php` | `Tag` | extends `LabelBase` |
| `gemcrm/includes/database/models/list-model.php` | `ListModel` | extends `LabelBase` |

---

## Module Registration

Add to `module-manager.php` `$module_classes`:
```php
\Zaplane\Modules\AbandonedCart\AbandonedCartModule::class,
```

Add to `api.php` `register_route()`:
```php
(new \Zaplane\Modules\AbandonedCart\API\SettingsController())->register_routes();
(new \Zaplane\Modules\AbandonedCart\API\AbandonedCartController())->register_routes();
```

Add to `installer.php` migrator: `AbandonedCartMigration::class`

---

## Files to Modify (Backend)

| File | Change |
|------|--------|
| `zaplane/includes/framework/core/module-manager.php` | Add `AbandonedCartModule::class` to `$module_classes` |
| `zaplane/includes/api.php` | Register both controllers |
| `zaplane/includes/installer.php` | Add migration |

---

---

# Frontend — UI/UX

## Tech Stack (matches Zaplane exactly)

| Concern | Solution |
|---------|----------|
| Framework | React 18 |
| Build | Webpack via `@wordpress/scripts` |
| UI library | **Chakra UI v3** |
| CSS vars | `--zaplane-*` (primary-color, background, border-color, shadow, font-color) |
| State | **Redux Toolkit** |
| Routing | Query params — `?page=zaplane-abandoned-carts` + `?tab=settings` |
| Forms | **Formik** |
| Icons | `react-icons` |
| Entry point | `dev_zaplane/app.js` (already exists, extend it) |

---

## PHP — Admin Menu Registration

Add to `zaplane/includes/modules/admin/menu.php` inside `Helper::get_admin_menu_list()`:

```php
'zaplane-abandoned-carts' => [
    'parent_slug' => ZAPLANE_PLUGIN_SLUG,
    'title'       => __('Abandoned Carts', 'zaplane'),
    'capability'  => 'manage_options',
],
```

The existing `load_main_template()` renders `<div id="zaplane-app">` which already mounts the React app — no extra PHP needed.

---

## PHP — Asset Enqueuing

The existing `assets.php` already enqueues on `_page_zaplane*` hooks, which covers `zaplane-abandoned-carts` automatically. No changes needed.

Pass extra data to `ZaplaneGlobal` in the existing `wp_localize_script` call (or use a separate `wp_add_inline_script`):

```php
// already in ZaplaneGlobal — ensure these exist:
'rest_url'        => rest_url('zaplane/v1/'),
'nonce'           => wp_create_nonce('wp_rest'),
'route_path'      => admin_url('admin.php?page='),
```

---

## React Router — Add Route

**File:** `dev_zaplane/containers/BackendDashboard/index.js`

```js
case 'zaplane-abandoned-carts':
    return <AbandonedCarts />;
```

Import:
```js
import AbandonedCarts from './pages/abandoned-carts';
```

---

## Redux Slice — `abandonedCartSlice`

**File:** `dev_zaplane/redux/Slices/abandonedCartSlice/index.js`

```js
const abandonedCartSlice = createSlice({
    name: 'abandonedCart',
    initialState: {
        carts: [],
        total: 0,
        currentPage: 1,
        perPage: 20,
        filters: { status: '', search: '', date_from: '', date_to: '' },
        report: null,
        settings: null,
        loading: false,
        reportLoading: false,
        settingsLoading: false,
        selectedIds: [],
    },
    reducers: {
        setFilters(state, { payload }) { state.filters = { ...state.filters, ...payload }; state.currentPage = 1; },
        setPage(state, { payload }) { state.currentPage = payload; },
        setSelectedIds(state, { payload }) { state.selectedIds = payload; },
    },
    extraReducers: (builder) => { /* fulfilled/pending/rejected for each thunk */ }
});
```

**File:** `dev_zaplane/redux/Slices/abandonedCartSlice/actions.js`

```js
const API_BASE = () => `${ZaplaneGlobal.rest_url}abandoned-carts`;

export const fetchCarts = createAsyncThunk('abandonedCart/fetchCarts', async (params) => {
    const qs = new URLSearchParams(params).toString();
    const res = await apiFetch({ path: `/zaplane/v1/abandoned-carts?${qs}` });
    return res;
});

export const fetchReport = createAsyncThunk('abandonedCart/fetchReport', async (params) => {
    const qs = new URLSearchParams(params).toString();
    return await apiFetch({ path: `/zaplane/v1/abandoned-carts/report?${qs}` });
});

export const fetchSettings = createAsyncThunk('abandonedCart/fetchSettings', async () => {
    return await apiFetch({ path: '/zaplane/v1/abandoned-cart/settings' });
});

export const saveSettings = createAsyncThunk('abandonedCart/saveSettings', async (data) => {
    return await apiFetch({ path: '/zaplane/v1/abandoned-cart/settings', method: 'POST', data });
});

export const bulkDelete = createAsyncThunk('abandonedCart/bulkDelete', async (ids) => {
    return await apiFetch({ path: '/zaplane/v1/abandoned-carts/bulk-delete', method: 'POST', data: { ids } });
});
```

Register slice in `dev_zaplane/redux/store.js`:
```js
import abandonedCartReducer from './Slices/abandonedCartSlice';
// add to combineReducers:
abandonedCart: abandonedCartReducer,
```

---

## Page Router — `abandoned-carts/index.js`

Reads `?tab=` param to switch between List and Settings tab.

```jsx
import { useSearchParams } from 'react-router-dom';
import { Tabs } from '@chakra-ui/react';
import CartList from './CartList';
import CartSettings from './CartSettings';
import TopBar from '../../../components/TopBar';

const TABS = [
    { value: 'carts',    label: 'Abandoned Carts' },
    { value: 'settings', label: 'Settings' },
];

export default function AbandonedCarts() {
    const [params, setParams] = useSearchParams();
    const tab = params.get('tab') || 'carts';

    return (
        <>
            <TopBar
                title="Abandoned Carts"
                leftContent={
                    <Tabs.Root value={tab} variant="enclosed" size="sm">
                        <Tabs.List>
                            {TABS.map(t => (
                                <Tabs.Trigger
                                    key={t.value}
                                    value={t.value}
                                    onClick={() => setParams({ page: 'zaplane-abandoned-carts', tab: t.value })}
                                >
                                    {t.label}
                                </Tabs.Trigger>
                            ))}
                        </Tabs.List>
                    </Tabs.Root>
                }
            />
            {tab === 'carts'    && <CartList />}
            {tab === 'settings' && <CartSettings />}
        </>
    );
}
```

---

## Carts List Page — `CartList/index.js`

```
┌─────────────────────────────────────────────────────────────────────┐
│  TopBar:  [Abandoned Carts tab] [Settings tab]          [date range]│
├─────────────────────────────────────────────────────────────────────┤
│  REPORT CARDS (5 cards in a row)                                    │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ │
│  │Recovered │ │Processing│ │  Lost    │ │  Draft   │ │ Opt Out  │ │
│  │  12 carts│ │  5 carts │ │ 34 carts │ │  7 carts │ │  3 carts │ │
│  │  45,800৳ │ │ 12,250৳  │ │ 98,700৳  │ │ 15,400৳  │ │  6,200৳  │ │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘ │
│  Recovery Rate: 26.09%                                              │
├──────────────────────┬──────────────────────┬───────────────────────┤
│  [Status ▾]          │  [🔍 Search name/email│  [Bulk Delete]       │
├──────────────────────┴──────────────────────┴───────────────────────┤
│  TABLE                                                              │
│  ☐ │ Customer       │ Cart Total │ Status      │ Date      │ Actions │
│  ──┼────────────────┼────────────┼─────────────┼───────────┼─────────│
│  ☐ │ John Doe       │ 1,500.00৳  │ ● Abandoned │ 2h ago    │ [···]   │
│     │ john@ex.com   │            │             │ 2 clicks  │         │
│     ↳ actions menu: Copy Recovery Link · View GemCRM Contact · Delete│
│  ☐ │ Jane Smith     │   800.00৳  │ ✓ Recovered │ 1d ago    │ [···]   │
│     │ jane@ex.com   │            │             │           │         │
│     ↳ actions menu: View GemCRM Contact · View Order · Delete        │
├─────────────────────────────────────────────────────────────────────┤
│  Showing 1–20 of 46     [< 1 2 3 >]                per page [20 ▾] │
└─────────────────────────────────────────────────────────────────────┘
```

### `ReportCards.js`

```jsx
const STATUS_CARD_CONFIG = [
    { key: 'recovered_revenue',  label: 'Recovered',  color: 'green.500', icon: FiCheckCircle },
    { key: 'processing_revenue', label: 'Processing', color: 'blue.500',  icon: FiClock },
    { key: 'lost_revenue',       label: 'Lost',       color: 'red.500',   icon: FiXCircle },
    { key: 'draft_revenue',      label: 'Draft',      color: 'gray.400',  icon: FiEdit },
    { key: 'optout_revenue',     label: 'Opted Out',  color: 'orange.400',icon: FiSlash },
];

export default function ReportCards({ report, dateRange, onDateChange, loading }) {
    return (
        <Box mb={6}>
            {/* Date range filter row */}
            <Flex justify="flex-end" mb={4} gap={2}>
                <Input type="date" size="sm" value={dateRange.from} onChange={...} w="160px" />
                <Input type="date" size="sm" value={dateRange.to}   onChange={...} w="160px" />
            </Flex>

            {/* Stat cards */}
            <SimpleGrid columns={5} gap={4} mb={3}>
                {STATUS_CARD_CONFIG.map(card => (
                    <Box key={card.key}
                        bg="var(--zaplane-background)"
                        border="1px solid var(--zaplane-border-color)"
                        borderRadius="md" p={4}
                        boxShadow="var(--zaplane-shadow)"
                    >
                        <Flex align="center" gap={2} mb={2}>
                            <Icon as={card.icon} color={card.color} boxSize={4} />
                            <Text fontSize="12px" color="var(--zaplane-font-color)" fontWeight="500">
                                {card.label}
                            </Text>
                        </Flex>
                        <Text fontSize="22px" fontWeight="700" color="var(--zaplane-font-color)">
                            {loading ? '—' : report?.summary?.[card.key]?.formatted ?? '0'}
                        </Text>
                        <Text fontSize="11px" color="gray.400">
                            {loading ? '' : `${report?.summary?.[card.key]?.orders ?? 0} orders`}
                        </Text>
                    </Box>
                ))}
            </SimpleGrid>

            {/* Recovery rate pill */}
            <Flex align="center" gap={2}>
                <Text fontSize="13px" color="var(--zaplane-font-color)">Recovery Rate:</Text>
                <Badge colorPalette="green" fontSize="13px" px={3} py={1} borderRadius="full">
                    {report?.summary?.recovery_rate?.formatted ?? '0%'}
                </Badge>
            </Flex>
        </Box>
    );
}
```

### `CartFilters.js`

```jsx
const STATUS_OPTIONS = [
    { value: '',           label: 'All Statuses' },
    { value: 'draft',      label: 'Draft' },
    { value: 'processing', label: 'Abandoned (Active)' },
    { value: 'recovered',  label: 'Recovered' },
    { value: 'lost',       label: 'Lost' },
    { value: 'skipped',    label: 'Skipped' },
    { value: 'opt_out',    label: 'Opted Out' },
];

export default function CartFilters({ filters, onChange, selectedIds, onBulkDelete }) {
    return (
        <Flex gap={3} mb={4} align="center" flexWrap="wrap">
            <Select size="sm" w="180px" value={filters.status}
                onChange={e => onChange({ status: e.target.value })}>
                {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </Select>

            <InputGroup size="sm" w="240px">
                <InputLeftElement><Icon as={FiSearch} color="gray.400" /></InputLeftElement>
                <Input
                    placeholder="Search name or email…"
                    value={filters.search}
                    onChange={e => onChange({ search: e.target.value })}
                />
            </InputGroup>

            {selectedIds.length > 0 && (
                <Button size="sm" colorScheme="red" variant="outline" onClick={onBulkDelete}>
                    Delete ({selectedIds.length})
                </Button>
            )}
        </Flex>
    );
}
```

### `CartTable.js`

```jsx
const STATUS_BADGE = {
    draft:      { color: 'gray',   dot: '○', label: 'Draft' },
    processing: { color: 'blue',   dot: '●', label: 'Abandoned' },
    recovered:  { color: 'green',  dot: '✓', label: 'Recovered' },
    lost:       { color: 'red',    dot: '✕', label: 'Lost' },
    skipped:    { color: 'orange', dot: '—', label: 'Skipped' },
    opt_out:    { color: 'purple', dot: '⊘', label: 'Opted Out' },
};

const columns = [
    {
        label: 'Customer',
        render: row => (
            <Box>
                <Text fontWeight="500" fontSize="13px">{row.full_name || '—'}</Text>
                <Text fontSize="11px" color="gray.400">{row.email}</Text>
            </Box>
        ),
    },
    {
        label: 'Cart Total',
        render: row => (
            <Text fontWeight="600" fontSize="13px">{row.formatted_total}</Text>
        ),
    },
    {
        label: 'Status',
        render: row => {
            const s = STATUS_BADGE[row.status] ?? STATUS_BADGE.draft;
            return (
                <Badge colorPalette={s.color} px={2} py={0.5} borderRadius="full" fontSize="11px">
                    {s.dot} {s.label}
                </Badge>
            );
        },
    },
    {
        label: 'Date',
        render: row => (
            <Box>
                <Text fontSize="12px">{timeAgo(row.created_at)}</Text>
                {row.click_counts > 0 && (
                    <Text fontSize="11px" color="blue.400">{row.click_counts} link click(s)</Text>
                )}
            </Box>
        ),
    },
];

export default function CartTable({ data, loading, selectedIds, onSelect, onDelete }) {
    return (
        <Box
            bg="var(--zaplane-background)"
            border="1px solid var(--zaplane-border-color)"
            borderRadius="md"
            boxShadow="var(--zaplane-shadow)"
            overflow="hidden"
        >
            <ZAPTable
                data={data}
                columns={columns}
                rowKey="id"
                loading={loading}
                selectedIds={selectedIds}
                onSelectChange={onSelect}
                showCheckbox
                actionsRenderer={row => (
                    <Menu.Root>
                        <Menu.Trigger asChild>
                            <IconButton size="xs" variant="ghost" aria-label="Actions">
                                <Icon as={FiMoreVertical} />
                            </IconButton>
                        </Menu.Trigger>
                        <Menu.Positioner>
                            <Menu.Content>
                                {/* Recovery link — only shown for processing carts */}
                                {row.status === 'processing' && (
                                    <Menu.Item value="copy-link"
                                        onClick={() => {
                                            const link = `${siteUrl}/?zaplane=1&route=abandoned-cart&checkout_key=${row.checkout_key}`;
                                            navigator.clipboard.writeText(link);
                                            dispatch(showNotification({ type: 'success', message: 'Recovery link copied!' }));
                                        }}>
                                        <Icon as={FiLink} mr={2} /> Copy Recovery Link
                                    </Menu.Item>
                                )}
                                {row.contact_id && (
                                    <Menu.Item value="view-contact"
                                        onClick={() => window.open(`${routePath}gemcrm-contacts&id=${row.contact_id}`)}>
                                        <Icon as={FiUser} mr={2} /> View GemCRM Contact
                                    </Menu.Item>
                                )}
                                {row.order_id && (
                                    <Menu.Item value="view-order"
                                        onClick={() => window.open(`${adminUrl}post.php?post=${row.order_id}&action=edit`)}>
                                        <Icon as={FiShoppingBag} mr={2} /> View Order
                                    </Menu.Item>
                                )}
                                <Menu.Separator />
                                <Menu.Item value="delete" color="red.500" onClick={() => onDelete(row.id)}>
                                    <Icon as={FiTrash2} mr={2} /> Delete
                                </Menu.Item>
                            </Menu.Content>
                        </Menu.Positioner>
                    </Menu.Root>
                )}
            />
        </Box>
    );
}
```

### Full `CartList/index.js`

```jsx
export default function CartList() {
    const dispatch = useDispatch();
    const { carts, total, currentPage, perPage, filters, report, loading, reportLoading, selectedIds } =
        useSelector(s => s.abandonedCart);
    const [dateRange, setDateRange] = useState({ from: '', to: '' });

    useEffect(() => {
        dispatch(fetchCarts({ ...filters, page: currentPage, per_page: perPage }));
    }, [filters, currentPage, perPage]);

    useEffect(() => {
        dispatch(fetchReport(dateRange));
    }, [dateRange]);

    const handleBulkDelete = async () => {
        await dispatch(bulkDelete(selectedIds));
        dispatch(fetchCarts({ ...filters, page: 1, per_page: perPage }));
        dispatch(fetchReport(dateRange));
    };

    return (
        <Box p={6}>
            <ReportCards
                report={report}
                loading={reportLoading}
                dateRange={dateRange}
                onDateChange={setDateRange}
            />
            <CartFilters
                filters={filters}
                onChange={f => dispatch(setFilters(f))}
                selectedIds={selectedIds}
                onBulkDelete={handleBulkDelete}
            />
            <CartTable
                data={carts}
                loading={loading}
                selectedIds={selectedIds}
                onSelect={ids => dispatch(setSelectedIds(ids))}
                onDelete={id => dispatch(bulkDelete([id])).then(() =>
                    dispatch(fetchCarts({ ...filters, page: currentPage, per_page: perPage }))
                )}
            />
            <Pagination
                total={total}
                perPage={perPage}
                currentPage={currentPage}
                onChange={page => dispatch(setPage(page))}
            />
        </Box>
    );
}
```

---

## Settings Page — `CartSettings/index.js`

```
┌─────────────────────────────────────────────────────────────────────┐
│  TopBar: [Abandoned Carts] [Settings tab]           [Save Settings] │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─ General ──────────────────────────────────────────────────────┐ │
│  │  Enable Abandoned Cart Tracking          [ Toggle ON/OFF ]     │ │
│  │  Cart Abandoned Cut-off Time (min)       [________30_______]   │ │
│  │  Mark as Lost after (min)                [______10080_______]  │ │
│  │  Cool-Off Period (min)                   [______10080_______]  │ │
│  │  Status for New Contacts                 [ transactional ▾ ]   │ │
│  │  Mark Recovered when WC Status →         [ processing ✓ ] [ completed ✓ ] │
│  └────────────────────────────────────────────────────────────────┘ │
│  ┌─ GDPR ─────────────────────────────────────────────────────────┐ │
│  │  Show GDPR Consent on Checkout           [ Toggle ON/OFF ]     │ │
│  │  GDPR Message                                                   │ │
│  │  ┌──────────────────────────────────────────────────────────┐  │ │
│  │  │ By continuing, you agree that we may save your email…    │  │ │
│  │  └──────────────────────────────────────────────────────────┘  │ │
│  │  Disable Tracking For User Roles         [ admin ✕ ] [ + Add ] │ │
│  └────────────────────────────────────────────────────────────────┘ │
│  ┌─ Contact Tagging — Cart Abandoned ─────────────────────────────┐ │
│  │  Add Lists on Cart Abandoned             [ Select lists… ▾ ]   │ │
│  │  Add Tags on Cart Abandoned              [ Select tags… ▾ ]    │ │
│  └────────────────────────────────────────────────────────────────┘ │
│  ┌─ Contact Tagging — Cart Lost ──────────────────────────────────┐ │
│  │  Add Lists on Cart Lost                  [ Select lists… ▾ ]   │ │
│  │  Add Tags on Cart Lost                   [ Select tags… ▾ ]    │ │
│  └────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

```jsx
export default function CartSettings() {
    const dispatch = useDispatch();
    const { settings, settingsLoading } = useSelector(s => s.abandonedCart);

    useEffect(() => { dispatch(fetchSettings()); }, []);

    const onSubmit = async (values) => {
        await dispatch(saveSettings(values));
        dispatch(showNotification({ type: 'success', message: 'Settings saved.' }));
    };

    if (!settings) return <Loading />;

    return (
        <Formik initialValues={settings} onSubmit={onSubmit} enableReinitialize>
            {({ values, setFieldValue, handleSubmit }) => (
                <form onSubmit={handleSubmit}>
                    <TopBar
                        title="Abandoned Cart Settings"
                        rightContent={
                            <Button type="submit" size="sm" isLoading={settingsLoading}
                                bg="var(--zaplane-primary-color)" color="white">
                                Save Settings
                            </Button>
                        }
                    />
                    <Box p={6} maxW="860px">
                        <GeneralSettings values={values} setFieldValue={setFieldValue} settings={settings} />
                        <GDPRSettings    values={values} setFieldValue={setFieldValue} />
                        <TaggingSettings values={values} setFieldValue={setFieldValue}
                            tags={settings.gemcrm_tags} lists={settings.gemcrm_lists}
                            section="abandoned" title="Cart Abandoned" />
                        <TaggingSettings values={values} setFieldValue={setFieldValue}
                            tags={settings.gemcrm_tags} lists={settings.gemcrm_lists}
                            section="lost" title="Cart Lost" />
                    </Box>
                </form>
            )}
        </Formik>
    );
}
```

### `GeneralSettings.js`

```jsx
export default function GeneralSettings({ values, setFieldValue, settings }) {
    return (
        <SettingsCard title="General">
            <SettingRow label="Enable Abandoned Cart Tracking"
                hint="Track abandoned carts and run recovery automations.">
                <Switch
                    checked={values.status}
                    onChange={e => setFieldValue('status', e.target.checked)}
                />
            </SettingRow>

            <SettingRow label="Cart Abandoned Cut-off Time (Minutes)"
                hint="How many minutes of inactivity before a cart is marked as abandoned.">
                <NumberInput size="sm" value={values.cart_off_time} min={1} w="120px"
                    onChange={val => setFieldValue('cart_off_time', Number(val))}>
                    <NumberInputField />
                </NumberInput>
            </SettingRow>

            <SettingRow label="Mark as Lost after (Minutes)"
                hint="Mark the cart as Lost if the order is not placed within this time after being abandoned.">
                <NumberInput size="sm" value={values.mark_as_lost_after_minutes} min={1} w="120px"
                    onChange={val => setFieldValue('mark_as_lost_after_minutes', Number(val))}>
                    <NumberInputField />
                </NumberInput>
            </SettingRow>

            <SettingRow label="Cool-Off Period (Minutes)"
                hint="Skip tracking if the customer placed a paid order within this many minutes.">
                <NumberInput size="sm" value={values.cool_off_period} min={0} w="120px"
                    onChange={val => setFieldValue('cool_off_period', Number(val))}>
                    <NumberInputField />
                </NumberInput>
            </SettingRow>

            <SettingRow label="Status for New Contacts"
                hint="GemCRM contact status assigned to newly created contacts from abandoned carts.">
                <Select size="sm" w="200px" value={values.status_of_new_contact}
                    onChange={e => setFieldValue('status_of_new_contact', e.target.value)}>
                    <option value="transactional">Transactional</option>
                    <option value="subscribed">Subscribed</option>
                    <option value="pending">Pending</option>
                </Select>
            </SettingRow>

            <SettingRow label="Mark Cart as Recovered when Order Status →"
                hint="Select which WooCommerce order statuses should trigger cart recovery.">
                <CheckboxGroup
                    value={values.mark_as_recovered_when_order_status_changed_to}
                    onChange={val => setFieldValue('mark_as_recovered_when_order_status_changed_to', val)}>
                    <HStack gap={4} flexWrap="wrap">
                        {Object.entries(settings.woo_order_statuses).map(([val, label]) => (
                            <Checkbox key={val} value={val}>{label}</Checkbox>
                        ))}
                    </HStack>
                </CheckboxGroup>
            </SettingRow>
        </SettingsCard>
    );
}
```

### `GDPRSettings.js`

```jsx
export default function GDPRSettings({ values, setFieldValue }) {
    return (
        <SettingsCard title="GDPR & Tracking Exclusions">
            <SettingRow label="Show GDPR Consent on Checkout"
                hint="Inform customers that their email and cart data are saved for abandonment reminders.">
                <Switch
                    checked={values.gdpr_consent_in_woo_checkout_page}
                    onChange={e => setFieldValue('gdpr_consent_in_woo_checkout_page', e.target.checked)}
                />
            </SettingRow>

            {values.gdpr_consent_in_woo_checkout_page && (
                <SettingRow label="GDPR Message" hint="Text shown to customers on the checkout page.">
                    <Textarea size="sm" rows={3} value={values.gdpr_msg}
                        onChange={e => setFieldValue('gdpr_msg', e.target.value)} />
                </SettingRow>
            )}

            <SettingRow label="Disable Tracking For User Roles"
                hint="Carts from these user roles will not be tracked.">
                <CheckboxGroup
                    value={values.disabled_user_roles}
                    onChange={val => setFieldValue('disabled_user_roles', val)}>
                    <HStack gap={4} flexWrap="wrap">
                        {Object.entries(settings?.wp_roles ?? {}).map(([val, label]) => (
                            <Checkbox key={val} value={val}>{label}</Checkbox>
                        ))}
                    </HStack>
                </CheckboxGroup>
            </SettingRow>
        </SettingsCard>
    );
}
```

### `TaggingSettings.js` (shared for abandoned + lost sections)

```jsx
export default function TaggingSettings({ values, setFieldValue, tags, lists, section, title }) {
    const listKey = `${section}_list`;
    const tagsKey = `${section}_tags`;

    return (
        <SettingsCard title={`Contact Tagging — Cart ${title}`}>
            <SettingRow
                label={`Add Lists on Cart ${title}`}
                hint={`Selected list(s) will be added when cart is marked as ${section.toLowerCase()}.${section === 'abandoned' ? ' Removed on successful order.' : ''}`}>
                <Select
                    isMulti
                    size="sm"
                    options={lists.map(l => ({ value: l.id, label: l.title }))}
                    value={lists.filter(l => values[listKey].includes(l.id)).map(l => ({ value: l.id, label: l.title }))}
                    onChange={sel => setFieldValue(listKey, sel.map(s => s.value))}
                    placeholder="Select lists…"
                />
            </SettingRow>

            <SettingRow
                label={`Add Tags on Cart ${title}`}
                hint={`Selected tag(s) will be added when cart is marked as ${section.toLowerCase()}.${section === 'abandoned' ? ' Removed on successful order.' : ''}`}>
                <Select
                    isMulti
                    size="sm"
                    options={tags.map(t => ({ value: t.id, label: t.title }))}
                    value={tags.filter(t => values[tagsKey].includes(t.id)).map(t => ({ value: t.id, label: t.title }))}
                    onChange={sel => setFieldValue(tagsKey, sel.map(s => s.value))}
                    placeholder="Select tags…"
                />
            </SettingRow>
        </SettingsCard>
    );
}
```

### Shared `SettingsCard` + `SettingRow` components

These are small layout primitives used across settings sections:

```jsx
// SettingsCard — white box with title + divider
export function SettingsCard({ title, children }) {
    return (
        <Box
            bg="var(--zaplane-background)"
            border="1px solid var(--zaplane-border-color)"
            borderRadius="md"
            boxShadow="var(--zaplane-shadow)"
            mb={6}
            overflow="hidden"
        >
            <Box px={5} py={3} borderBottomWidth="1px" borderColor="var(--zaplane-border-color)">
                <Text fontWeight="600" fontSize="14px" color="var(--zaplane-font-color)">{title}</Text>
            </Box>
            <Box px={5} py={4}>
                <Stack gap={5}>{children}</Stack>
            </Box>
        </Box>
    );
}

// SettingRow — label left, control right, hint below label
export function SettingRow({ label, hint, children }) {
    return (
        <Grid templateColumns="1fr 1fr" gap={4} alignItems="start">
            <Box>
                <Text fontSize="13px" fontWeight="500" color="var(--zaplane-font-color)">{label}</Text>
                {hint && <Text fontSize="11px" color="gray.400" mt={1}>{hint}</Text>}
            </Box>
            <Box>{children}</Box>
        </Grid>
    );
}
```

---

## Files to Modify (Frontend)

| File | Change |
|------|--------|
| `dev_zaplane/containers/BackendDashboard/index.js` | Add `case 'zaplane-abandoned-carts'` route |
| `dev_zaplane/redux/store.js` | Register `abandonedCart` reducer |
| `includes/modules/admin/menu.php` | Add `zaplane-abandoned-carts` submenu entry |

---

## Autoload Mapping (Backend)

| Class | File |
|-------|------|
| `Zaplane\Modules\AbandonedCart\AbandonedCartModule` | `includes/modules/abandoned-cart/abandoned-cart-module.php` |
| `Zaplane\Modules\AbandonedCart\AbandonedCartModel` | `includes/modules/abandoned-cart/abandoned-cart-model.php` |
| `Zaplane\Modules\AbandonedCart\AbandonedCartMigration` | `includes/modules/abandoned-cart/abandoned-cart-migration.php` |
| `Zaplane\Modules\AbandonedCart\AbandonedCartRunner` | `includes/modules/abandoned-cart/abandoned-cart-runner.php` |
| `Zaplane\Modules\AbandonedCart\AbandonedCartHelper` | `includes/modules/abandoned-cart/abandoned-cart-helper.php` |
| `Zaplane\Modules\AbandonedCart\Woo\WooCartTrackingInit` | `includes/modules/abandoned-cart/woo/woo-cart-tracking-init.php` |
| `Zaplane\Modules\AbandonedCart\API\SettingsController` | `includes/modules/abandoned-cart/api/settings-controller.php` |
| `Zaplane\Modules\AbandonedCart\API\AbandonedCartController` | `includes/modules/abandoned-cart/api/abandoned-cart-controller.php` |

---

## Custom Hooks Fired (Backend)

| Hook | Args | Purpose |
|------|------|---------|
| `zaplane/abandoned_cart/started` | `$cart_record` | Cart → processing. Wire Zaplane workflows here to send recovery emails |
| `zaplane/abandoned_cart/recovered` | `$cart_record, $order` | Cart → recovered. Order was placed via recovery link and paid |
| `zaplane/abandoned_cart/lost` | `$cart_record` | Cart → lost. Time window expired with no purchase |

---

## Verification Checklist

| # | Test | Expected |
|---|------|----------|
| 1 | Activate/reactivate Zaplane | `wp_zaplane_abandonned_cart` table exists |
| 2 | Admin sidebar | "Abandoned Carts" submenu visible under Zaplane |
| 3 | Navigate to Abandoned Carts page | Report cards show (all zeros), empty table |
| 4 | Navigate to Settings tab | All form fields populated with defaults |
| 5 | Save settings | Toast success; re-open settings shows saved values |
| 6 | Enable feature + add product → checkout → type email | DB `draft` row appears |
| 7 | Enable GDPR → open checkout | Consent message visible |
| 8 | Click opt-out | `status=opt_out`, opt-out cookie set, table reflects status |
| 9 | Complete order immediately (before `cart_off_time`) | Draft record deleted — not shown in table |
| 10 | Let draft age past `cart_off_time` → `run_abandoned()` | GemCRM contact created, `status=processing`, report card updates |
| 11 | Visit recovery link `?zaplane=1&route=abandoned-cart&checkout_key=TOKEN` | Cart restored in WC, `click_counts` incremented, `zaplane_ab_cart_token` cookie set |
| 12 | After step 11 — place order from restored cart | `order_id` linked to processing record |
| 13 | WooCommerce changes order to `processing` status | `status=recovered`, `recovered_at` set, abandoned tags cleared — **auto-detected, no manual step** |
| 14 | Age processing cart past `mark_as_lost_after_minutes` → `run_lost()` | `status=lost`, lost tags applied |
| 15 | Filter table by `status=processing` + search `john` | Filtered, paginated results |
| 16 | Report API with date range | Correct revenue totals, recovery rate |
| 17 | Select rows → Bulk Delete | Rows removed, table refreshes |
| 18 | Status badges in table | Correct colour + label per status |
