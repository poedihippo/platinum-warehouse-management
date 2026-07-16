<?php

namespace Tests\Feature\Loyalty\Admin;

use App\Models\Permission;
use App\Models\ProductBrand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        Permission::firstOrCreate([
            'name' => 'manage brands',
            'guard_name' => 'web',
        ]);
    }

    private function actingAsAdmin(bool $withPermission): User
    {
        $admin = User::factory()->create(['type' => 'admin']);

        if ($withPermission) {
            $admin->givePermissionTo('manage brands');
        }

        Sanctum::actingAs($admin);

        return $admin;
    }

    private function makeBrand(): ProductBrand
    {
        return ProductBrand::create(['name' => 'Test Brand ' . uniqid()]);
    }

    public function test_user_with_permission_can_upload_png_logo(): void
    {
        $this->actingAsAdmin(withPermission: true);
        $brand = $this->makeBrand();

        $this->postJson("/api/admin/loyalty/brands/{$brand->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $brand->refresh();
        $this->assertNotNull($brand->logo_path);
        Storage::disk('s3')->assertExists($brand->logo_path);
    }

    public function test_svg_logo_upload_is_rejected_with_422(): void
    {
        $this->actingAsAdmin(withPermission: true);
        $brand = $this->makeBrand();

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $response = $this->postJson("/api/admin/loyalty/brands/{$brand->id}/logo", [
            'logo' => $svg,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['logo']);

        $this->assertNull($brand->fresh()->logo_path);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAsAdmin(withPermission: false);
        $brand = $this->makeBrand();

        $this->postJson("/api/admin/loyalty/brands/{$brand->id}/logo", [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertStatus(403);
    }
}
