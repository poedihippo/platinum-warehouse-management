---
name: db-schema
description: Use when inspecting the database schema, table structures, column types, indexes, foreign keys, or data in this BEJO project (MySQL). Covers the mysql MCP server, artisan schema commands, and a key-tables reference.
---

# Database Schema Inspection (BEJO)

## 1. MySQL MCP (preferred)

The project `.mcp.json` defines a `mysql` server (`@benborla29/mcp-server-mysql`, writes disabled by default). Its query tool appears as `mcp__mysql__mysql_query`. If it isn't connected, the user must export `MYSQL_PASS` (and `MYSQL_USER` / `MYSQL_DB` if not `root` / `platinum_event`) before starting Claude Code.

Read-only queries only — never write to the user's DB through MCP.

```sql
SHOW TABLES;
SHOW CREATE TABLE stocks;
SHOW INDEX FROM stocks;

SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stocks'
ORDER BY ORDINAL_POSITION;

SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;
```

The dev DB may differ from migrations (some production columns were added retroactively) — when they disagree, report it.

## 2. Artisan

```bash
php8.5 artisan migrate:status
php8.5 artisan db:table stocks          # columns, indexes, FKs (native in Laravel 13, no doctrine/dbal)
php8.5 artisan db:show                  # tables overview
```

## 3. Migrations

`database/migrations/` (85 files). `grep -l "'stocks'" database/migrations/*` finds every migration touching a table.

## Key tables

| Table | Key columns |
|-------|-------------|
| `products` | product_category_id, product_brand_id, company (`pas`/`pa`), name |
| `product_units` | product_id, uom_id, refer_id, refer_qty, code, price, is_generate_qr, is_ppn, is_auto_stock, points_per_unit, loyalty_eligible |
| `product_unit_relations` | product_unit_id, related_product_unit_id, qty |
| `stock_product_units` | product_unit_id, warehouse_id, qty |
| `stocks` | **id ULID**, parent_id, stock_product_unit_id, expired_date, description (group parents: `"{code} GR-..."`), is_stock |
| `stock_histories` | model_type, model_id, stock_product_unit_id, value, is_increment |
| `stock_transfers` | user_id, stock_id (nullable, QR), from/to_stock_product_unit_id, from/to_warehouse_id, product_unit_id, qty |
| `sales_orders` | user_id, reseller_id, spg_id, warehouse_id, company, invoice_no, type, is_invoice, marketplace, merged_into_sales_order_id, price, raw_source, records |
| `sales_order_details` | sales_order_id, product_unit_id, qty, fulfilled_qty, unit_price, tax |
| `sales_order_items` | stock_id, sales_order_detail_id, delivery_order_detail_id, is_parent, is_returned |
| `sales_order_vouchers` | sales_order_id, voucher_id, discount_amount |
| `delivery_orders` | sales_order_id, warehouse_id, company, invoice_no, is_done |
| `delivery_order_details` | delivery_order_id, sales_order_detail_id, qty |
| `receive_orders` / `receive_order_details` | is_done / qty, adjust_qty, is_verified |
| `document_counters` | company, document_type, period (`Y-m`), sequence — unique (company, document_type, period) |
| `loyalty_users`, `claims`, `claim_line_items`, `claim_photos`, `points_transactions`, `prizes`, `prize_categories`, `redemptions` | ULID PKs |

Column names above are a guide — confirm with `SHOW CREATE TABLE` before relying on one.
