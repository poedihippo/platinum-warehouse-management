# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

The project guide shared with other agents (OpenCode) lives in `AGENTS.md` and is imported below — it is the source of truth for architecture, business flows, tenancy, RBAC and conventions. Keep it accurate: when you discover it disagrees with the code, fix `AGENTS.md`, not this file.

@AGENTS.md

---

## Claude Code specifics

### Commands

Laravel 13 needs PHP ≥ 8.4 and the runtime is 8.5; the machine's default `php` is still 8.3, so always call `php8.5` (and `php8.5 $(which composer)`).

```bash
php8.5 artisan test                                        # all tests
php8.5 artisan test --filter=DocumentCounterTest           # one class
php8.5 artisan test --filter='NumberingTest::test_name'    # one method
php8.5 artisan test tests/Feature/Loyalty                  # one directory
php8.5 vendor/bin/pint app/Path/To/ChangedFile.php         # format only what you touched
php8.5 vendor/bin/pint --dirty                             # or every uncommitted file
ENABLE_DOCUMENT_COUNTER=true php8.5 artisan route:list --path=api/stocks   # inspect routes + middleware
```

Do not run Pint on the whole repo — ~20 existing files are not Pint-clean and it would create unrelated diffs.

### Test environment

- Tests hit a **real MySQL** database: `phpunit.xml` forces `DB_DATABASE=platinum_event_test` (create it once; credentials come from `.env`). SQLite will not work — migrations contain MySQL-only SQL.
- `tests/TestCase.php` refuses to run unless the connection is MySQL and the database name ends with `_test`, and drops an ambient `DB_URL`/`DATABASE_URL`.
- Every DB test uses `RefreshDatabase`. Queue is `sync`, so queued listeners (RO stock, opname, product-unit slots) run inline.
- `tests/Feature/ExampleTest` fails by design (`/` redirects). `.phpunit.result.cache` is stale — don't trust it.

### Writing tests (patterns that work here)

- Warehouse endpoints: `Sanctum::actingAs($user, ['warehouse'])` — the route group uses `ability:warehouse`.
- Loyalty customer: `Sanctum::actingAs($loyaltyUser, ['loyalty'], 'loyalty')`. Loyalty admin: `Sanctum::actingAs($staff)`.
- Permissions are created inline, never via seeders: `Permission::firstOrCreate(['name' => 'product_brand_edit', 'guard_name' => 'web'])` (`App\Models\Permission`) then `$user->givePermissionTo(...)`. A full admin needs the Spatie role `admin` (`App\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])` + `assignRole`), not just `type => 'admin'`.
- Most models don't use `HasFactory`; call factories directly (`WarehouseFactory::new()->create()`). Wrap fixture creation in `Model::withoutEvents(fn () => ...)` when you don't want Warehouse/ProductUnit hooks to auto-create `stock_product_units`.
- Feature flag: `config(['app.enable_document_counter' => true])`.
- Fakes in use: `Storage::fake('s3')`, `Storage::fake('local')`, `Mail::fake()`, `Cache::flush()` (rate limits).
- Reference tests: `tests/Feature/NumberingTest.php`, `tests/Feature/Api/Public/StockVerificationTest.php`, `tests/Feature/Loyalty/*`.

### Database safety

- `DatabaseSeeder` is intentionally empty (deploys must never reseed). Seed with `php8.5 artisan db:seed --class=...`.
- Never run `migrate:fresh`, `migrate:refresh`, `db:wipe` or `db:seed` against the dev DB (`platinum_event`) unless the user asks.
- `ENABLE_DOCUMENT_COUNTER` and other env flags are read from config cache — `php8.5 artisan config:clear` / `config:cache` after changing `.env`.

### Project tooling

- Project skills in `.claude/skills/`: `laravel-controller`, `laravel-model`, `laravel-migration`, `laravel-test`, `db-schema`. Use them when adding endpoints, models, migrations, tests, or inspecting the schema.
- MCP server `mysql` (`.mcp.json`, read-only by default) — export `MYSQL_PASS` (and `MYSQL_USER`/`MYSQL_DB` if not `root`/`platinum_event`) in your shell before starting Claude Code.
- Use context7 for current docs of Laravel 13 and the Spatie / maatwebsite packages. Read AGENTS.md §17 (Laravel 13 structure and compatibility rules) before touching `bootstrap/app.php`, config, migrations or the compat layers.
- `.opencode/` and `opencode.json` belong to OpenCode — leave them alone unless asked.

### Conventions not covered above

- Commit messages: `[FIX] ...`, `[ADD] ...`, `[PERF] ...`; English or Indonesian both fine.
- Longer specs live in `PRD.md`, `LOYALTY_SPEC.md`, `BACKEND_AUDIT.md`, `SECURITY_FINDINGS.md` and `docs/tasks/**` — read them on demand for the feature you are touching.
