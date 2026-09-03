gw mau bikin kolom baru aja di table sales_orders total_voucher, itu di isi ketika create/update invoice

terus ini jawaban opencode big pickle di session laptop lain

You're weighing two options for giving the frontend the total voucher discount per sales order:

1. Column total_voucher on sales_orders — written on create/update invoice.
2. Append/accessor on the model — computed on the fly.
   Let me give you a clear recommendation with the tradeoffs, then confirm before planning the exact implementation.
   Comparison
   Aspect Column total_voucher (persisted)
   Consistency Server-side single source of truth. Stored once, always correct & fast to read (no join needed for list/detail).
   List performance No extra query — plain column.
   Preview (before save) Column empty until saved → can't show during preview.
   Extra migration/backfill Needs new column + backfill 26 legacy rows.
   Consistency with price price is already a persisted column, so total_voucher fits the same pattern and can be validated together.
   My recommendation
   Column total_voucher — for these reasons:

- It's consistent with existing design: price, shipment_fee, additional_discount, auto_discount are all persisted columns on sales_orders. total_voucher fits naturally beside them.
- List & detail read it with zero extra cost (no need to eager-load vouchers just to sum discount). Frontend can show total voucher in the grid without loading relations.
- It's a single authoritative number computed once at create/update by the pipeline and stored — same as price.
- It composes with price validation (like CheckExpectedOrderPrice).
  The append approach is simpler (no migration) but:
- Forces eager-loading vouchers everywhere the total is read, or triggers N+1.
- For preview it still needs raw_source source anyway.
  So: persist total_voucher as a column, set in CalculateVoucher (the pipe already computes $totalVoucherNominal), and backfill the 26 legacy rows from their recomputed pivot sums.
  Notes / caveats to confirm
- Duplicate source: raw_source['voucher_total_nominal'] already stores this snapshot. We'd add a real column too. Two places — worth confirming we're OK keeping the raw_source snapshot alongside (I'd recommend keeping it for audit; the column is the canonical one the frontend reads).
- For preview: if frontend needs the total before save, neither the column nor the relation will have it. Pipeline could put the preview total somewhere — but if preview responses serialize the model, the column won't be set unless the preview saves. Do you need preview total, or just the displayed total for saved invoices/list/detail? This affects whether we also need to expose raw_source's total in the preview response.
  Let me confirm the key decision points before I write the plan:

# Questions

Untuk memberi frontend total nominal voucher, pendekatan mana yang kamu pilih?
Kolom persisted total_voucher (Recommended)
Apakah frontend perlu total voucher saat PREVIEW harga (sebelum order tersimpan), atau hanya untuk order yang sudah tersimpan (list/detail)?
Butuh juga di preview
Decision confirmed:

- Kolom persisted total_voucher di sales_orders, diisi pipeline saat create/update.
- Perlu juga di preview — jadi total voucher harus bisa keluar di response preview sebelum tersimpan.
  The preview need changes things slightly. Let me examine the preview flow to plan correctly. The preview path runs the pipeline with $isPreview = true, so SaveOrder/UpdateOrder are skipped. Let me check how the preview response is serialized and whether total_voucher (a real column) would be present on an unsaved preview model.
Let me look at the actual save order flow and how the model is returned for preview.
→Read app/Pipes/Order/SaveOrder.php 
$ cd /home/nikko/Development/laravel/platinum-warehouse-management && git --no-pager diff app/Pipes/Order/SaveOrder.php app/Pipes/Order/UpdateOrder.php app/Pipes/Order/CalculateVoucher.php
  diff --git a/app/Pipes/Order/CalculateVoucher.php b/app/Pipes/Order/CalculateVoucher.php
  index 0e775eb..6bfa159 100644
  --- a/app/Pipes/Order/CalculateVoucher.php
  +++ b/app/Pipes/Order/CalculateVoucher.php
  @@ -28,7 +28,7 @@ public function handle(SalesOrder $salesOrder, \Closure $next)
  }
  $totalVoucherNominal = 0;
-        $perVoucherNominal = [];

*        $discountAmountByVoucherId = [];
    …
    Click to expand
    Now I understand the full picture. Let me think through how the preview exposes the total.
    Key insight about preview: In preview mode, SaveOrder/UpdateOrder are skipped (pipes list omits them). So $salesOrder returned from the pipeline is not persisted — no id, no pivot rows, and a total_voucher column would be null/0 because it's not filled. The raw_source['voucher_total_nominal'] is set by CalculateVoucher and would be present.
    So to expose the total for both saved and preview, the cleanest approach is an append accessor that reads:

- If a total_voucher column is filled → use it (saved orders).
- Else (preview) → fall back to raw_source['voucher_total_nominal'].
  But that's a hybrid. Let me reconsider — the user picked "kolom persisted" but also chose "butuh juga di preview". These two combined suggest we need the accessor to handle preview. Let me design this cleanly.
  Actually, the simplest robust solution that satisfies both requirements:
  Add a total_voucher column, AND set it in CalculateVoucher (so both saved and the in-memory preview model carry it). Since CalculateVoucher runs in both preview and save pipelines, setting $salesOrder->total_voucher = $totalVoucherNominal; there means:
- Preview: model returned from pipeline has total_voucher attribute set in-memory → serialized in response.
- Save: $salesOrder->save() persists it to the DB column.
But wait — for preview, SaveOrder is skipped so save() doesn't run, but the attribute is still set on the model object and will be included in the JSON serialization (it's a real column attribute, not an append, so it shows up in toArray()). Let me verify this works — non-persisted models who have attributes set DO serialize them.
Actually for a non-saved Eloquent model, attributes set via $salesOrder->total_voucher = x ARE included in toArray() regardless of DB presence. So setting it in CalculateVoucher covers preview naturally. And on save it persists. This is the cleanest — no fallback logic needed, single source.
But there's a subtlety: CalculateVoucher sets it on the model, but does SaveOrder's $salesOrder->save() persist total_voucher? Yes, because it's a fillable attribute set on the model. I'll add total_voucher to $fillable and the migration column.
Let me also double check: the JSON response for preview — is it serialized via SalesOrderResource which exposes price etc.? Since total_voucher is a plain column attribute, it'll appear automatically in the resource's generic fields. But I should confirm the resource doesn't whitelist fields. Let me check the resource to be sure total_voucher will flow through, and confirm the fillable/column addition.
Let me look at SalesOrderResource and how preview is returned (to confirm serialization).
→Read app/Http/Resources/SalesOrderResource.php 
The resource calls parent::toArray($request) which merges all model attributes including raw_source (which is hidden) and any plain columns. Since total_voucher would be a plain column attribute, it will automatically be included in parent::toArray(). And for preview, setting the attribute in-memory means it's included too.
  I now have a complete plan. Let me verify one more thing about preview serialization — whether the preview response actually uses SalesOrderResource. Let me check the is_preview path in the controllers.
  ✱Grep "is_preview|isPerview|isPreview" (27 matches)
  Let me look at how the preview response is returned from the SalesOrderController and OrderController to confirm whether it gets wrapped in SalesOrderResource (which would include the attribute) or returned raw.
  →Read app/Http/Controllers/Api/SalesOrderController.php [limit=75, offset=38]
  Confirmed: SalesOrderController::store (line 45-47) returns new SalesOrderResource($salesOrder) for both preview and non-preview. And SalesOrderResource uses parent::toArray() which includes all model attributes. So if CalculateVoucher sets $salesOrder->total_voucher, it will be serialized in the response for both preview and saved orders — exactly what we need.
  I now have everything to finalize the plan.
  Final Plan: Add persisted total_voucher column (set in pipeline, available in preview too)
  Design
- Add a total_voucher integer column to sales_orders.
- Set it in CalculateVoucher (the pipe runs in both preview and save pipelines). Because it's a plain attribute on the model:
- Preview (is_preview=true, no save): the in-memory model has total_voucher set → included in SalesOrderResource via parent::toArray().
- Save (is_preview=false): $salesOrder->save() in SaveOrder/UpdateOrder persists it to the DB column.
- No fallback/duplicate logic needed — single source set at compute time.
- Backfill the 26 legacy rows from their pivot discount_amount sums (recomputed earlier: e.g. SO214 = 90487, SO2911 = 50000, etc.).
  Why this satisfies both answers
- Persisted column → cheap reads in list/detail, consistent with price/shipment_fee design.
- Preview → because CalculateVoucher runs in the preview pipeline and sets the attribute on the returned model, the resource exposes it even before save.
  Changes

1. New migration — ...\_add_total_voucher_to_sales_orders_table.php (new file, don't touch existing):

- Add total_voucher unsigned integer, default 0, after price (or near voucher-related fields).
- Backfill: UPDATE sales_orders s SET s.total_voucher = (SELECT COALESCE(SUM(sov.discount_amount),0) FROM sales_order_vouchers sov WHERE sov.sales_order_id = s.id) — idempotent, guarded.
- down(): drop column.

2. app/Models/SalesOrder.php:

- Add 'total_voucher' to $fillable and $casts (integer).
- (Optional) Add to $appends? No — it's a real column, parent::toArray() already exposes it. No append needed.

3. app/Pipes/Order/CalculateVoucher.php:

- Set $salesOrder->total_voucher = $totalVoucherNominal; after computing the total. (Runs in preview + save.)
  Note: SaveOrder/UpdateOrder already do $salesOrder->save() which will persist total_voucher since it's fillable — no change needed there. voucherPivot logic stays as-is.

4. Frontend PRD — note the new total_voucher field in the response (saved + preview).
   Verification (local DB)

- php -l changed files.
- Run migration → column added + 26 backfilled (sums match pivot).
- Tinker: a saved SO reads total_voucher; simulate preview model (set attribute) → appears in SalesOrderResource::toArray().
- Grep: no stale refs.
  Things to confirm before I build

1. Backfill for 26 legacy from pivot sum — matches earlier recompute values (each legacy row has a single voucher, so total_voucher = that row's discount_amount). Good.
2. Should total_voucher also feed into any server-side validation (like CheckExpectedOrderPrice)? Currently voucher discount is part of price calc; total_voucher is just a display/derived-per-order figure. Confirm you don't need it in validation (I'll keep it display-only).
3. Confirm total_voucher placement in $fillable/$casts is fine (I'll add it).
   Want me to proceed with this plan?

terus gw bilang gini di mode build

ok great, make it clean code, effisien, and no errors.
kalo butuh buat migration, buat file baru aja ya, jangan edit yang lama biar ga ada error, terus test dulu aja di database local

terus dia ngerjain ini, tapi keburu limit opencode nya, jadi gw minta tolong lu buat lanjutin ya

Let me set up the todo list and start building.

# Todos

[•] Create new migration: add total_voucher column + backfill 26 legacy rows from pivot sum
[ ] Update SalesOrder model: add total_voucher to $fillable and $casts
[ ] Update CalculateVoucher pipe: set $salesOrder->total_voucher
[ ] Update frontend PRD: document total_voucher field
[ ] php -l changed files
[ ] Test migration on local DB + verify backfill + preview/saved serialization
Let me create the new migration. I'll place total_voucher after price for logical grouping.

# Wrote database/migrations/2026_09_03_200001_add_total_voucher_to_sales_orders_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_orders', 'total_voucher')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->unsignedInteger('total_voucher')->default(0)->after('price');
            });
        }

        DB::statement(
            'UPDATE sales_orders s
                SET s.total_voucher = (
                    SELECT COALESCE(SUM(sov.discount_amount), 0)
                    FROM sales_order_vouchers sov
                    WHERE sov.sales_order_id = s.id
                )
                WHERE s.total_voucher = 0'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_orders', 'total_voucher')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('total_voucher');
            });
        }
    }
};
Now update the SalesOrder model — add total_voucher to $fillable and $casts.
← Edit app/Models/SalesOrder.php
    'shipment_fee',
    'additional_discount',
    'auto_discount',
    'price',
    'total_voucher',
    'description',
    'is_invoice',
    'type',
];
← Edit app/Models/SalesOrder.php
    'shipment_fee' => 'integer',
    'additional_discount' => 'integer',
    'auto_discount' => 'float',
    'price' => 'integer',
    'total_voucher' => 'integer',
    'is_invoice' => 'boolean',
    'type' => SalesOrderType::class,
    'company' => CompanyEnum::class,
];
