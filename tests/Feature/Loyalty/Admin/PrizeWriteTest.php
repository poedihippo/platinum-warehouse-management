<?php

namespace Tests\Feature\Loyalty\Admin;

use App\Models\Loyalty\Prize;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrizeWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        Permission::firstOrCreate([
            'name' => 'manage prizes',
            'guard_name' => 'web',
        ]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->givePermissionTo('manage prizes');
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_store_persists_product_url(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/loyalty/prizes', [
            'name' => 'Platinum Tote Bag',
            'points_cost' => 1200,
            'stock' => 5,
            'product_url' => 'https://shop.example.com/tote-bag',
        ])->assertCreated();

        $response->assertJsonPath('data.product_url', 'https://shop.example.com/tote-bag');
        $this->assertDatabaseHas('prizes', [
            'id' => $response->json('data.id'),
            'product_url' => 'https://shop.example.com/tote-bag',
        ]);
    }

    public function test_update_persists_product_url(): void
    {
        $prize = Prize::factory()->create(['product_url' => null]);

        $this->actingAsAdmin();

        $this->patchJson("/api/admin/loyalty/prizes/{$prize->id}", [
            'product_url' => 'https://shop.example.com/updated-link',
        ])
            ->assertOk()
            ->assertJsonPath('data.product_url', 'https://shop.example.com/updated-link');

        $this->assertDatabaseHas('prizes', [
            'id' => $prize->id,
            'product_url' => 'https://shop.example.com/updated-link',
        ]);
    }
}
