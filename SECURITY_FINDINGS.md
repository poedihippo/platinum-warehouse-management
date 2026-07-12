# Security findings — logged, not fixed

Findings discovered during the loyalty/warehouse token-scoping work that are
intentionally **not** fixed here, because fixing them blind risks breaking a
live bejo workflow. Each entry says why it wasn't touched and what fixing it
would require.

## 1. `SalesOrderController` has zero permission gating

`app/Http/Controllers/Api/SalesOrderController.php` constructor — every
`$this->middleware('permission:sales_order_*', ...)` line is commented out:

```php
// $this->middleware('permission:sales_order_access', ['only' => ['index', 'show']]);
// $this->middleware('permission:sales_order_read', ['only' => ['index', 'show']]);
// $this->middleware('permission:sales_order_create', ['only' => 'store']);
// $this->middleware('permission:sales_order_edit', ['only' => 'update']);
// $this->middleware('permission:sales_order_delete', ['only' => 'destroy']);
// $this->middleware('permission:sales_order_print', ['only' => 'print']);
// $this->middleware('permission:sales_order_export_xml', ['only' => 'exportXml']);
```

Every action (`index`, `show`, `store`, `update`, `destroy`, `print`,
`exportXml`, plus the uncovered-by-design `productUnits`/`productUnitsNew`)
is reachable by any `auth:sanctum` token, gated only by `ability:warehouse`
as of this change (previously gated by nothing at all beyond authentication).
Someone commented this out on purpose — re-enabling it blind could break a
live bejo workflow that currently depends on broader-than-intended access.
**Do not re-enable without confirming with whoever disabled it.**

## 2. `resetVerifiedStock` / `'reset'` middleware name mismatch

`app/Http/Controllers/Api/DeliveryOrderDetailController.php`:
```php
$this->middleware('permission:delivery_order_delete', ['only' => ['destroy', 'reset']]);
```
The route is `PUT delivery-orders/{do}/details/{detail}/reset-verified-stock`
→ `DeliveryOrderDetailController@resetVerifiedStock`. Laravel's controller
middleware `only`/`except` match the exact action method name — `'reset'`
never matches `'resetVerifiedStock'`, so this permission check silently
never applies to that route. Looks like the method was renamed after this
line was written. **Fix is a one-word change** (`'reset'` →
`'resetVerifiedStock'`) but is left for whoever owns this controller to
verify against current DeliveryOrder workflows first.

## 3. `PersonalAccessToken` stores the plaintext token in the clear

`app/Models/PersonalAccessToken.php` extends Sanctum's model and adds a
`plain_text_token` column, populated on every `createToken()` call
(`app/Models/User.php:75-86`). Standard Sanctum stores only a SHA-256 hash
in `token` and never persists the plaintext anywhere. Here, the plaintext
sits in the database (and therefore in every DB backup, and any log or
export that dumps `personal_access_tokens`) for the lifetime of the token.
Anyone with read access to that table — or a backup, or a query log — can
authenticate as any user whose token is stored there, no password needed.

This is *why* the impersonation bug in finding 4 below was possible to
write in the first place: the column exists, so someone used it to look
up "does this bearer token exist" without stopping to ask whether it
should be queryable that way at all. Recommend: stop populating
`plain_text_token` (or at least stop reading it back anywhere), rely on
Sanctum's standard hash-only lookup, and rotate/expire all currently-issued
tokens once removed. Scheduled as separate work, not done here.

## 4. `SocialiteController` mints unscoped tokens, bypassing the new ability system

`app/Http/Controllers/Api/SocialiteController.php:38`:
```php
$token = $authUser->tokens()->latest()->first()->plain_text_token ?? $authUser->createToken('default')->plainTextToken;
```
This is the exact same reuse pattern that was just removed from
`AuthController::token()` — and worse for the new design: `createToken('default')`
with no second argument defaults to `['*']` abilities. Any user completing
Google/social OAuth login (`GET /api/auth/{provider}/callback`, itself
unauthenticated and unthrottled) gets a full `['*']` warehouse-capable
token regardless of their role — including a loyalty-only staff member, if
their email happens to match a social account. This is a silent side door
around the entire `ability:warehouse` scoping work in this task. Not part
of the requested scope, so not touched, but it should be fixed with the
same treatment `AuthController::token()` just got (mint via
`tokenAbilitiesFor()`-equivalent logic, never reuse).

## 5. Prior audit: ~30 warehouse endpoints had no permission check beyond `auth:sanctum`

Full endpoint-by-endpoint list is in the previous session's audit (not
reproduced in full here to keep this file short). Highlights, now all
additionally covered by `ability:warehouse` (so a loyalty-only token can no
longer reach any of them) but **still individually ungated for any
warehouse-ability token**:

- `DELETE /stocks/bulk`, `stocks/grouping-by-scan`, `stocks/record`,
  `stocks/verification-tempel`, `stocks/{id}/repack`, `stocks/set-to-printed`,
  `stocks/set-to-printing-queue`, `stocks/print-verification`,
  `stocks/add-to-stock` — all of `StockController`'s non-CRUD actions.
- All of `SalesOrderController` (see finding 1).
- `SalesOrderItemController`, `StockOpnameItemController`,
  `TemporaryStockController` — no constructor middleware at all.
- `product-units/{id}/create-relations`, `/update-relations/{id}`,
  `/change-product`, `/user-price/{user}`, `sample-import`, `import` on
  both `ProductUnitController` and `ProductController`.
- `delivery-orders/get-by-so-detail/{id}`, `/{id}/return`, `/{id}/attach`,
  `/{id}/verification/{id}`.
- `orders/{id}/convert-so`.
- `sales-orders/{id}/details/{id}` (update/destroy) and
  `orders/{id}/details/{id}` (update/destroy).
- `stock-histories/export`, `stock-opnames/.../scan`.

The token-scoping work in this task blocks loyalty-only tokens from all of
these uniformly. It does **not** fix the underlying per-endpoint gap for
warehouse-ability tokens — any bejo warehouse user can still reach all of
the above with no permission check, same as before.
