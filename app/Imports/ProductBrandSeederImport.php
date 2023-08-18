<?php

namespace App\Imports;

use App\Models\ProductBrand;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductBrandSeederImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new ProductBrand([
            'name' => $row[0],
            'description' => $row[0],
        ]);
    }
}
