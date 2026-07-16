<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\ProductBrand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductBrandUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        Permission::firstOrCreate([
            'name' => 'product_brand_edit',
            'guard_name' => 'web',
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->givePermissionTo('product_brand_edit');

        // This route sits behind the 'ability:warehouse' group in
        // routes/api.php, so the Sanctum token also needs that ability.
        Sanctum::actingAs($admin, ['warehouse']);

        return $admin;
    }

    private function makeBrand(): ProductBrand
    {
        return ProductBrand::create(['name' => 'Test Brand ' . uniqid()]);
    }

    public function test_user_can_update_brand_with_png_logo(): void
    {
        $this->actingAsAdmin();
        $brand = $this->makeBrand();

        $response = $this->putJson("/api/product-brands/{$brand->id}", [
            'name' => 'Updated Brand Name',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertStatus(202);

        $brand->refresh();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('s3')->assertExists($brand->logo_path);
    }

    public function test_svg_logo_upload_is_rejected_with_422(): void
    {
        $this->actingAsAdmin();
        $brand = $this->makeBrand();

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $response = $this->putJson("/api/product-brands/{$brand->id}", [
            'name' => 'Updated Brand Name',
            'logo' => $svg,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['logo']);

        $this->assertNull($brand->fresh()->logo_path);
    }
}
