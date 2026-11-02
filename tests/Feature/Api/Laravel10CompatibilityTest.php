<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class Laravel10CompatibilityTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_role_store_and_update_return_the_synced_permissions(): void
    {
        $this->actingAsAdmin();
        $permission = Permission::firstOrCreate(['name' => 'uom_read', 'guard_name' => 'web']);

        $id = $this->postJson('/api/roles', ['name' => 'Viewer', 'permission_ids' => [$permission->id]])
            ->assertCreated()
            ->assertJsonPath('data.permissions.0.name', 'uom_read')
            ->json('data.id');

        $this->putJson("/api/roles/{$id}", ['name' => 'Viewer 2', 'permission_ids' => []])
            ->assertJsonPath('data.permissions', []);
    }

    public function test_user_store_and_update_return_the_synced_roles(): void
    {
        $this->actingAsAdmin();
        $role = Role::create(['name' => 'Gudang', 'guard_name' => 'web']);

        $id = $this->postJson('/api/users', [
            'name' => 'Budi',
            'email' => 'budi@test.com',
            'password' => 'secret',
            'type' => 'admin',
            'role_ids' => [$role->id],
            'warehouse_ids' => [],
        ])->assertCreated()->assertJsonPath('data.roles.0.name', 'Gudang')->json('data.id');

        $this->putJson("/api/users/{$id}", ['name' => 'Budi 2', 'role_ids' => [], 'warehouse_ids' => []])
            ->assertStatus(202)
            ->assertJsonPath('data.roles', []);
    }

    public function test_pagination_links_keep_the_laravel_10_keys(): void
    {
        $this->actingAsAdmin();
        Uom::create(['name' => 'pcs']);

        $links = $this->getJson('/api/uoms')->assertOk()->json('meta.links');

        $this->assertNotEmpty($links);
        foreach ($links as $link) {
            $this->assertSame(['url', 'label', 'active'], array_keys($link));
        }
    }

    public function test_sales_order_print_keeps_the_document_number_in_the_quoted_file_name(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $salesOrder = $this->makeSalesOrder($warehouse, [[$this->makeProductUnit(false), 1]], ['invoice_no' => 'PAS/SO/11/26/01']);

        $response = $this->get("/api/sales-orders/{$salesOrder->id}/print")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="sales-order-PAS/SO/11/26/01.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
    }

    public function test_delivery_order_print_uses_a_quoted_file_name(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $salesOrder = $this->makeSalesOrder($warehouse, [[$this->makeProductUnit(false), 1]]);
        $deliveryOrder = $this->makeDeliveryOrder($salesOrder);

        $response = $this->get("/api/delivery-orders/{$deliveryOrder->id}/print")->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="delivery-order-'.$deliveryOrder->code.'.pdf"', $response->headers->get('Content-Disposition'));
    }

    public function test_svg_brand_logo_is_rejected_by_mimes_with_the_laravel_10_message(): void
    {
        Storage::fake('s3');
        $this->actingAsAdmin();
        $brand = $this->makeProduct()->productBrand;
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->putJson("/api/product-brands/{$brand->id}", ['name' => 'Brand', 'logo' => $svg])
            ->assertStatus(422)
            ->assertExactJson([
                'message' => 'The logo must be a file of type: png, jpg, jpeg, webp.',
                'errors' => ['logo' => ['The logo must be a file of type: png, jpg, jpeg, webp.']],
            ]);
    }

    public function test_svg_prize_photo_is_still_an_image(): void
    {
        Storage::fake('s3');
        Permission::firstOrCreate(['name' => 'manage prizes', 'guard_name' => 'web']);
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->givePermissionTo('manage prizes');
        Sanctum::actingAs($admin);
        $svg = UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->postJson('/api/admin/loyalty/prizes', [
            'name' => 'Tote Bag',
            'points_cost' => 100,
            'stock' => 1,
            'photo' => $svg,
        ])->assertCreated();
    }

    public function test_import_sample_is_downloaded_as_text_csv(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/api/exports/sample/voucher')->assertOk();

        $this->assertSame('text/csv; charset=utf-8', strtolower($response->headers->get('Content-Type')));
    }
}
