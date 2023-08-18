<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;

class SupplierSeederImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Supplier([
            'code' => $row[0],
            'name' => $row[1],
            'phone' => $row[2],
            'contact_person' => $row[3],
            'city' => $row[4],
            'province' => $row[5],
            'address' => $row[6],
        ]);
    }
}
