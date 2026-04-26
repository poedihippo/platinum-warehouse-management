<?php

namespace Tests\Feature\Api\Public;

use App\Enums\CompanyEnum;
use App\Models\Stock;
use Database\Factories\ProductBrandFactory;
use Database\Factories\ProductCategoryFactory;
use Database\Factories\ProductFactory;
use Database\Factories\ProductUnitFactory;
use Database\Factories\StockFactory;
use Database\Factories\StockProductUnitFactory;
use Database\Factories\UomFactory;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StockVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Stock $stockWithExpiry;
    private Stock $stockWithoutExpiry;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any per-IP rate-limit buckets carried between tests. Cache driver
        // is `array` per phpunit.xml, but flushing is cheap insurance.
        Cache::flush();

        // The Warehouse and ProductUnit models have boot/event side-effects
        // that auto-create stock_product_units rows for every existing peer.
        // Bypass them so the test owns the exact rows.
        Model::withoutEvents(function () {
            $product = ProductFactory::new()->create([
                'product_category_id' => ProductCategoryFactory::new()->create()->id,
                'product_brand_id'    => ProductBrandFactory::new()->create()->id,
                'company'             => CompanyEnum::PAS,
                'name'                => 'Champion Dog Food 5kg',
            ]);

            $productUnit = ProductUnitFactory::new()->create([
                'product_id' => $product->id,
                'uom_id'     => UomFactory::new()->create()->id,
                'name'       => 'Champion Dog Food 5kg PCS',
            ]);

            $warehouse = WarehouseFactory::new()->create();

            $stockProductUnit = StockProductUnitFactory::new()->create([
                'product_unit_id' => $productUnit->id,
                'warehouse_id'    => $warehouse->id,
                'qty'             => 10,
            ]);

            $this->stockWithExpiry = StockFactory::new()->create([
                'stock_product_unit_id' => $stockProductUnit->id,
                'expired_date'          => '2026-12-31',
            ]);

            $this->stockWithoutExpiry = StockFactory::new()->create([
                'stock_product_unit_id' => $stockProductUnit->id,
                'expired_date'          => null,
            ]);
        });
    }

    public function test_returns_serial_product_name_and_expiry_when_stock_has_expired_date(): void
    {
        $response = $this->getJson($this->url($this->stockWithExpiry->id));

        $response->assertOk()
            ->assertExactJson([
                'verified' => true,
                'data' => [
                    'serial_number' => $this->stockWithExpiry->id,
                    'product_name'  => 'Champion Dog Food 5kg',
                    'expired_date'  => '2026-12-31',
                ],
            ]);
    }

    public function test_returns_null_expired_date_when_stock_has_no_expiry(): void
    {
        $response = $this->getJson($this->url($this->stockWithoutExpiry->id));

        $response->assertOk();

        $payload = $response->json();
        $this->assertTrue($payload['verified']);
        $this->assertSame('Champion Dog Food 5kg', $payload['data']['product_name']);

        // expired_date must be present in the JSON and explicitly null — not absent, not "".
        $this->assertArrayHasKey('expired_date', $payload['data']);
        $this->assertNull($payload['data']['expired_date']);
    }

    public function test_returns_404_when_ulid_is_well_formed_but_not_in_db(): void
    {
        // Valid ULID shape, guaranteed not in DB.
        $response = $this->getJson($this->url('01HZZZZZZZZZZZZZZZZZZZZZZZ'));

        $response->assertStatus(404)
            ->assertExactJson([
                'verified' => false,
                'message'  => 'Product not found',
            ]);
    }

    /**
     * @dataProvider invalidUlidProvider
     */
    public function test_returns_404_for_invalid_ulid_shapes(string $rawPathSegment): void
    {
        $response = $this->getJson($this->url($rawPathSegment));

        $response->assertStatus(404)
            ->assertExactJson([
                'verified' => false,
                'message'  => 'Product not found',
            ]);
    }

    public static function invalidUlidProvider(): array
    {
        return [
            'too short'                 => ['abc'],
            'wrong characters (lowercase i)' => ['01HZZZZZZZZZZZZZZZZZZZZZZi'],
            'wrong characters (l)'      => ['01HZZZZZZZZZZZZZZZZZZZZZZl'],
            'sql injection attempt'     => ["01HZZZZZZZ'%20OR%201=1--"],
            'numeric id'                => ['12345'],
            'uuid v4'                   => ['550e8400-e29b-41d4-a716-446655440000'],
        ];
    }

    public function test_response_does_not_leak_price_cost_or_other_internal_fields(): void
    {
        $response = $this->getJson($this->url($this->stockWithExpiry->id));

        $response->assertOk();

        // Whitelist: exactly these keys, nothing more.
        $this->assertSame(
            ['verified', 'data'],
            array_keys($response->json())
        );
        $this->assertSame(
            ['serial_number', 'product_name', 'expired_date'],
            array_keys($response->json('data'))
        );

        $forbidden = [
            'price', 'cost', 'warehouse_id', 'warehouse', 'supplier', 'supplier_id',
            'created_at', 'updated_at', 'deleted_at', 'user_id', 'product_id',
            'product_unit_id', 'stock_product_unit_id', 'code', 'uom', 'uom_id',
            'product_category_id', 'product_brand_id', 'qty', 'is_tempel', 'qr_code',
            'scanned_count', 'scanned_datetime', 'printed_at',
        ];

        $body = $response->getContent();
        foreach ($forbidden as $field) {
            $this->assertStringNotContainsString(
                "\"{$field}\"",
                $body,
                "Public response leaked forbidden field: {$field}"
            );
        }
    }

    public function test_soft_deleted_stock_returns_same_404_shape_as_missing(): void
    {
        $deletedId = $this->stockWithExpiry->id;
        $this->stockWithExpiry->delete();

        $response = $this->getJson($this->url($deletedId));

        $response->assertStatus(404)
            ->assertExactJson([
                'verified' => false,
                'message'  => 'Product not found',
            ]);
    }

    public function test_rate_limit_kicks_in_on_31st_request_within_a_minute(): void
    {
        $url = $this->url($this->stockWithExpiry->id);

        for ($i = 1; $i <= 30; $i++) {
            $this->getJson($url)->assertOk();
        }

        $this->getJson($url)->assertStatus(429);
    }

    private function url(string $ulid): string
    {
        return '/api/public/stocks/' . $ulid;
    }
}
