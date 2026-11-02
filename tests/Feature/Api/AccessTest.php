<?php

namespace Tests\Feature\Api;

use App\Helpers\PermissionsHelper;
use App\Models\Permission;
use App\Models\ProductBrand;
use App\Models\User;
use App\Models\UserDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    public function test_permission_index_defaults_to_100_per_page_and_orders_by_id_desc(): void
    {
        $this->actingAsAdmin();
        Permission::create(['name' => 'zzz_perm', 'guard_name' => 'web']);
        Permission::create(['name' => 'aaa_perm', 'guard_name' => 'web']);

        $response = $this->getJson('/api/permissions')->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
        $this->assertSame('aaa_perm', $response->json('data.0.name'));
    }

    public function test_permission_index_applies_requested_sort_before_id_desc(): void
    {
        $this->actingAsAdmin();
        Permission::create(['name' => 'zzz_perm', 'guard_name' => 'web']);
        Permission::create(['name' => 'aaa_perm', 'guard_name' => 'web']);

        $this->getJson('/api/permissions?sort=name&filter[name]=_perm')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'aaa_perm')
            ->assertJsonPath('data.1.name', 'zzz_perm');
    }

    public function test_permission_crud_and_all(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/permissions', ['name' => 'custom_perm'])->assertStatus(201)->json('data.id');
        $this->getJson("/api/permissions/{$id}")->assertOk()->assertJsonPath('data.name', 'custom_perm');
        $this->deleteJson("/api/permissions/{$id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);

        $all = $this->getJson('/api/permissions/all')->assertOk()->json();
        $this->assertArrayHasKey('sales_order_access', $all);
    }

    public function test_known_quirk_permission_update_rule_is_missing_a_comma_and_fails_with_500(): void
    {
        $this->actingAsAdmin();
        $permission = Permission::create(['name' => 'custom_perm', 'guard_name' => 'web']);

        $this->putJson("/api/permissions/{$permission->id}", ['name' => 'custom_perm_2'])->assertStatus(500);
        $this->assertSame('custom_perm', $permission->fresh()->name);
    }

    public function test_role_store_syncs_related_permissions_and_flushes_cache(): void
    {
        $this->actingAsAdmin();
        foreach (['sales_order_access', ...PermissionsHelper::getRelatedPermissions('sales_order_access')] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $salesOrderAccess = Permission::where('name', 'sales_order_access')->first();
        Cache::put('something', 'cached');

        $response = $this->postJson('/api/roles', ['name' => 'Sales', 'permission_ids' => [$salesOrderAccess->id, 999999]])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Sales');

        $role = Role::findByName('Sales', 'web');
        $this->assertEqualsCanonicalizing(
            ['sales_order_access', ...PermissionsHelper::getRelatedPermissions('sales_order_access')],
            $role->permissions->pluck('name')->all()
        );
        $this->assertNull(Cache::get('something'));
        $this->assertSame('web', $role->guard_name);
        $this->assertSame($role->id, $response->json('data.id'));
    }

    public function test_role_update_index_show_and_destroy(): void
    {
        $this->actingAsAdmin();
        $perm = Permission::firstOrCreate(['name' => 'user_access', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'role_read', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Staff', 'guard_name' => 'web']);

        $this->putJson("/api/roles/{$role->id}", ['name' => 'Staff 2', 'permission_ids' => [$perm->id]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Staff 2');
        $this->assertEqualsCanonicalizing(['user_access', 'role_read'], $role->fresh()->permissions->pluck('name')->all());

        $this->getJson('/api/roles?filter[name]=Staff')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/roles/{$role->id}")->assertOk()->assertJsonPath('data.name', 'Staff 2');
        $this->deleteJson("/api/roles/{$role->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
    }

    public function test_role_one_cannot_be_updated_or_deleted(): void
    {
        Role::query()->insert(['id' => 1, 'name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAsAdmin();
        $admin = Role::findById(1, 'web');

        $this->putJson('/api/roles/1', ['name' => 'Changed'])
            ->assertOk()
            ->assertExactJson(['message' => 'Role admin tidak dapat diupdate!']);
        $this->deleteJson('/api/roles/1')
            ->assertOk()
            ->assertExactJson(['message' => 'Role admin tidak dapat dihapus!']);
        $this->assertSame('admin', $admin->fresh()->name);
    }

    public function test_user_store_update_and_relations(): void
    {
        $admin = $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $role = Role::create(['name' => 'Gudang', 'guard_name' => 'web']);

        $response = $this->postJson('/api/users', [
            'name' => 'Budi',
            'email' => 'budi@test.com',
            'password' => 'secret',
            'type' => 'admin',
            'role_ids' => [$role->id],
            'warehouse_ids' => [$warehouse->id],
        ])->assertStatus(201)->assertJsonPath('data.name', 'Budi');

        $user = User::findOrFail($response->json('data.id'));
        $this->assertSame(['Gudang'], $user->getRoleNames()->all());
        $this->assertSame([$warehouse->id], $user->warehouses()->pluck('warehouses.id')->all());

        $this->putJson("/api/users/{$user->id}", ['name' => 'Budi 2', 'role_ids' => [], 'warehouse_ids' => []])
            ->assertStatus(202)
            ->assertJsonPath('data.name', 'Budi 2');
        $this->assertSame([], $user->fresh()->getRoleNames()->all());
        $this->assertSame(0, $user->warehouses()->count());

        $this->getJson("/api/users/{$user->id}")->assertOk()->assertJsonPath('data.type', 'Admin');
        $this->assertNotSame($admin->id, $user->id);
    }

    public function test_user_index_filters_sorts_and_includes(): void
    {
        $this->actingAsAdmin(['name' => 'Zed Admin']);
        $warehouse = $this->makeWarehouse(['code' => 'INCL']);
        $user = $this->makeUser(['name' => 'Ani', 'phone' => '0812345', 'type' => 'customer']);
        $user->warehouses()->attach($warehouse->id);

        $this->getJson('/api/users?filter[name]=12345&include=warehouses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ani')
            ->assertJsonPath('data.0.warehouses.0.code', 'INCL');

        $this->getJson('/api/users?sort=-name&filter[type]=admin')->assertOk()->assertJsonPath('data.0.name', 'Zed Admin');
        $this->getJson('/api/users?include=unknown')->assertStatus(400);
    }

    public function test_user_one_cannot_be_deleted_and_soft_delete_restore_force_delete(): void
    {
        $first = User::forceCreate(['id' => 1, 'name' => 'Root', 'email' => 'root@test.com', 'password' => 'secret', 'type' => 'admin']);
        $this->actingAsAdmin();
        $user = $this->makeUser(['type' => 'customer']);

        $this->deleteJson('/api/users/1')->assertOk()->assertExactJson(['message' => 'Admin dengan id 1 tidak dapat dihapus!']);
        $this->deleteJson('/api/users/1/force-delete')->assertOk()->assertExactJson(['message' => 'Admin dengan id 1 tidak dapat dihapus!']);
        $this->assertNotSoftDeleted('users', ['id' => $first->id]);

        $this->deleteJson("/api/users/{$user->id}")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->putJson("/api/users/{$user->id}/restore")->assertOk()->assertJsonPath('data.id', $user->id);
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);

        $this->deleteJson("/api/users/{$user->id}/force-delete")->assertOk()->assertExactJson(['message' => 'Data deleted successfully']);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->putJson('/api/users/999999/restore')->assertNotFound();
    }

    public function test_users_me_returns_roles_and_permissions(): void
    {
        $user = $this->actingAsAdmin();
        Sanctum::actingAs($user, ['loyalty']);

        $this->getJson('/api/users/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles.0.name', 'admin')
            ->assertJsonPath('data.permissions.user_access.user_read', true);
    }

    public function test_user_discounts_for_resellers(): void
    {
        $this->actingAsAdmin();
        ProductBrand::create(['name' => 'Existing Brand']);
        $reseller = $this->makeUser(['type' => 'reseller']);
        $brand = ProductBrand::create(['name' => 'New Brand']);

        $index = $this->getJson("/api/users/{$reseller->id}/discounts?sort=-id")->assertOk();
        $index->assertJsonCount(1, 'data');
        $existingId = $index->json('data.0.id');

        $this->getJson("/api/users/{$reseller->id}/discounts/{$existingId}")
            ->assertOk()
            ->assertJsonPath('data.product_brand.name', 'Existing Brand');

        $created = $this->postJson("/api/users/{$reseller->id}/discounts", ['product_brand_id' => $brand->id, 'value' => 7, 'is_percentage' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.product_brand_id', $brand->id);

        $this->putJson("/api/users/{$reseller->id}/discounts/{$created->json('data.id')}", ['product_brand_id' => $brand->id, 'value' => 9])
            ->assertStatus(202)
            ->assertJsonPath('data.value', 9);

        $this->getJson("/api/users/{$reseller->id}/discounts/999999")->assertNotFound();

        $customer = $this->makeUser(['type' => 'customer']);
        $this->putJson("/api/users/{$customer->id}/discounts/1", ['product_brand_id' => $brand->id, 'value' => 9])
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Tipe user harus reseller']);
        $this->assertSame(2, UserDiscount::where('user_id', $reseller->id)->count());
    }

    public function test_token_login_abilities_bypass_and_logout(): void
    {
        $user = $this->makeUser(['email' => 'login@test.com', 'password' => 'secret', 'type' => 'admin']);

        $this->postJson('/api/auth/token', ['email' => 'login@test.com', 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
        $this->postJson('/api/auth/token', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);

        $token = $this->postJson('/api/auth/token', ['email' => 'login@test.com', 'password' => 'secret'])
            ->assertOk()
            ->json('data.token');
        $this->assertSame(['*'], $user->tokens()->latest('id')->first()->abilities);
        $this->assertSame('default', $user->tokens()->latest('id')->first()->name);

        Config::set('app.bypass_password', 'master-key');
        $this->postJson('/api/auth/token', ['email' => 'login@test.com', 'password' => 'master-key', 'device_name' => 'phone'])->assertOk();
        $this->assertSame('phone', $user->tokens()->latest('id')->first()->name);

        $loyaltyRole = Role::create(['name' => 'loyalty reviewer', 'guard_name' => 'web']);
        $reviewer = $this->makeUser(['email' => 'reviewer@test.com', 'password' => 'secret', 'type' => 'admin']);
        $reviewer->assignRole($loyaltyRole);
        $this->postJson('/api/auth/token', ['email' => 'reviewer@test.com', 'password' => 'secret'])->assertOk();
        $this->assertSame(['loyalty'], $reviewer->tokens()->latest('id')->first()->abilities);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Berhasil keluar.']);
        $this->assertSame(1, $user->tokens()->count());
    }
}
