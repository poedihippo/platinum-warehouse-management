<?php

namespace App\Imports;

use App\Models\ProductCategory;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductCategorySeederImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new ProductCategory([
            'name' => $row[0],
            'description' => $row[0],
        ]);
    }
}
