<?php

namespace Tests\Feature\Loyalty\Admin;

use App\Models\Loyalty\Prize;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrizeShowTest extends TestCase
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

    private function actingAsAdmin(bool $withPermission): User
    {
        $admin = User::factory()->create(['type' => 'admin']);

        if ($withPermission) {
            $admin->givePermissionTo('manage prizes');
        }

        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_can_load_a_single_prize_for_editing(): void
    {
        $prize = Prize::factory()->create();
        // product_url isn't in Prize::$fillable yet, so set it directly
        // rather than via mass assignment (a separately-tracked gap).
        $prize->product_url = 'https://example.com/tote-bag';
        $prize->save();

        $this->actingAsAdmin(true);

        $this->getJson("/api/admin/loyalty/prizes/{$prize->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $prize->id)
            ->assertJsonPath('data.product_url', 'https://example.com/tote-bag');
    }

    public function test_requires_manage_prizes_permission(): void
    {
        $prize = Prize::factory()->create();

        $this->actingAsAdmin(false);

        $this->getJson("/api/admin/loyalty/prizes/{$prize->id}")
            ->assertStatus(403);
    }

    public function test_returns_404_for_unknown_prize(): void
    {
        $this->actingAsAdmin(true);

        $this->getJson('/api/admin/loyalty/prizes/01jznotarealprize0000000000')
            ->assertStatus(404);
    }
}
