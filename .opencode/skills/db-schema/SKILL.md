---
name: db-schema
description: Use when inspecting database schema, checking table structures, viewing column definitions, or understanding database relationships in this project. Front-load keywords: database schema, table structure, column type, foreign key, migration, database inspection, show table, describe table.
---

# Database Schema Inspection Skill

## Methods to Inspect Database

### Method 1: MySQL MCP (Preferred)

If the MySQL MCP server is connected, use the `mysql_query` tool to inspect the database:

```sql
-- List all tables
SHOW TABLES;

-- Describe table structure
DESCRIBE table_name;

-- Show CREATE TABLE (full schema)
SHOW CREATE TABLE table_name;

-- Show foreign keys
SELECT
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'table_name';

-- Show indexes
SHOW INDEX FROM table_name;

-- Count records
SELECT COUNT(*) FROM table_name;
```

### Method 2: Laravel Artisan Commands

```bash
# Show all migrations status
php artisan migrate:status

# Show migration status in table format
php artisan migrate:status --table

# Show specific migration
php artisan migrate:status --filter=create_stocks_table
```

### Method 3: Read Migration Files

Migration files are in `database/migrations/`. Read them to understand table structure:

```bash
# List all migrations
ls database/migrations/

# Read specific migration
# The file content shows the exact schema definition
```

## Quick Reference: Key Tables

### Product Management
| Table | Key Columns |
|-------|-------------|
| `products` | id, product_category_id, product_brand_id, company, name |
| `product_units` | id, product_id, refer_id, uom_id, name, price, code, is_generate_qr, is_ppn |
| `product_brands` | id, name, logo_path |
| `product_categories` | id, name |
| `uoms` | id, name |

### Stock/Inventory
| Table | Key Columns |
|-------|-------------|
| `stocks` | **id (ULID)**, parent_id, stock_product_unit_id, expired_date, batch_number |
| `stock_product_units` | id, product_unit_id, warehouse_id, qty |
| `stock_histories` | id, model_type, model_id, stock_product_unit_id, value, is_increment |

### Orders
| Table | Key Columns |
|-------|-------------|
| `sales_orders` | id, user_id, reseller_id, spg_id, warehouse_id, invoice_no, type, price |
| `sales_order_details` | id, sales_order_id, product_unit_id, qty, fulfilled_qty, unit_price |
| `sales_order_items` | id, stock_id, sales_order_detail_id, delivery_order_detail_id |
| `delivery_orders` | id, user_id, warehouse_id, invoice_no, is_done |
| `delivery_order_details` | id, delivery_order_id, sales_order_detail_id, qty |

### Receive Orders
| Table | Key Columns |
|-------|-------------|
| `receive_orders` | id, user_id, supplier_id, warehouse_id, invoice_no, is_done |
| `receive_order_details` | id, receive_order_id, product_unit_id, qty, adjust_qty, is_verified |

### Loyalty
| Table | Key Columns |
|-------|-------------|
| `loyalty_users` | **id (ULID)**, email, name, is_active |
| `claims` | **id (ULID)**, loyalty_user_id, invoice_number, status, total_points |
| `redemptions` | **id (ULID)**, loyalty_user_id, prize_id, points_spent, status |
| `points_transactions` | **id (ULID)**, loyalty_user_id, direction, amount, source_type, source_id |
| `prizes` | **id (ULID)**, name, points_cost, stock, is_active |

## Useful Queries

### Check Table Relationships
```sql
-- Show all foreign keys in database
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;
```

### Check Column Types
```sql
-- Show column types for a table
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'table_name'
ORDER BY ORDINAL_POSITION;
```

### Check Indexes
```sql
-- Show all indexes for a table
SHOW INDEX FROM table_name;
```

### Check Enums
```sql
-- Show enum values (MySQL)
SELECT
    COLUMN_NAME,
    COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'table_name'
  AND DATA_TYPE = 'enum';
```

## After Inspecting Schema

1. Use findings to create/update models
2. Verify relationships match code
3. Check for missing indexes on frequently queried columns
4. Ensure soft deletes are used where needed
