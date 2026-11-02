<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\InteractsWithWarehouse;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    use InteractsWithWarehouse;
    use RefreshDatabase;

    private function xlsx(string $name, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_stock_import_skips_unknown_codes_and_increments_non_qr_stock(): void
    {
        $this->actingAsAdmin();
        $warehouse = $this->makeWarehouse();
        $unit = $this->makeProductUnit(false, ['code' => 'IMP-NQ']);
        $spu = $this->spu($unit, $warehouse);

        $file = $this->xlsx('stock.xlsx', [
            ['code', 'stock'],
            ['UNKNOWN-CODE', 5],
            ['IMP-NQ', 3],
            ['IMP-NQ', 0],
        ]);

        $this->post('/api/stocks/import', ['file' => $file, 'warehouse_id' => $warehouse->id], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertExactJson(['message' => 'Data imported successfully']);

        $this->assertSame(3, (int) $spu->fresh()->qty);
        $this->assertHistory($spu, ['value' => 3, 'is_increment' => 1, 'description' => 'Import stock']);
    }

    public function test_product_import_skips_existing_products(): void
    {
        $this->actingAsAdmin();
        $product = $this->makeProduct(['name' => 'Existing Product']);

        $file = $this->xlsx('products.xlsx', [
            ['category_name', 'company', 'brand_name', 'product_name'],
            [$product->productCategory->name, 'pas', $product->productBrand->name, 'Existing Product'],
            [$product->productCategory->name, 'pas', $product->productBrand->name, 'New Product'],
        ]);

        $this->post('/api/products/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertExactJson(['message' => 'Data imported successfully']);

        $this->assertSame(1, Product::where('name', 'Existing Product')->count());
        $this->assertSame(1, Product::where('name', 'New Product')->count());
    }

    public function test_product_unit_import_skips_existing_units(): void
    {
        $this->actingAsAdmin();
        $unit = $this->makeProductUnit(true, ['code' => 'IMP-QR', 'name' => 'Existing Unit']);

        $file = $this->xlsx('product_units.xlsx', [
            ['product_unit_name', 'product_name', 'uom_name', 'price', 'code'],
            ['Existing Unit', $unit->product->name, $unit->uom->name, 5000, 'IMP-QR'],
        ]);

        $this->post('/api/product-units/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertExactJson(['message' => 'Data imported successfully']);

        $this->assertSame(1, ProductUnit::where('code', 'IMP-QR')->count());
    }
}
