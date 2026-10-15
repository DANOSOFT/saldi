# Feature capability fragments

20261001 CDX/LH — T2-B0.

`GET /auth/me.php` loads `*.php` here in filename order. Each definition-only file returns an associative map, for example `return ['open_posts' => 'v2'];`. Values are booleans or `"v2"`; keys are lowercase snake_case. A fragment must not emit output, change connections, perform migrations or call a provider. Output is discarded defensively. Invalid/duplicate flags fail closed. Existing Tier 1 flags cannot be overridden.

The feature slice adds its fragment after its backend gate passes. A missing flag means unavailable; the foundation advertises no unbuilt T2 feature. This applies to account, tenantless registry and exchanged registry `/auth/me.php`. Capabilities describe installed code; permissions and tenant/provider configuration still decide whether a user may perform an action.

Reserved feature keys and owners: `open_posts` (B1), `customers`, `cvr` (B3), `dunning` (B2), `catalogue` (B4), `sales` (B6a), `invoicing` (B6b), `payments` (B7), `payment_link` (B8b). B5 delivery is used under the owning sales/dunning flow; it may add `documents` after its gate.

20261001 CDX/LH — T4-B0 reservations: `stock_count` (T4-B1b), `goods_receipt` (T4-B2b), `time_entries` (T4-B4b), `bookings` (T4-B5), `claims` (T4-B6a, statements completed by B6b). Missing flags remain unavailable; B0 activates none. Employee features require an account principal and the current `employee_link`/case permissions. Claims also require the tenant's configured payable account; statement rendering requires the T1 renderer. Fragments describe installed code and must remain definition-only: tenant configuration checks belong to the feature handler and its summary projection. Translation ids 5490–5499 are reserved for T4-B6a's web settings; B0 uses no web labels.
