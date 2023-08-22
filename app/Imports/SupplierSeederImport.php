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
            'code' => trim($row[0]),
            'name' => trim($row[1]),
            'phone' => trim($row[2]),
            'contact_person' => trim($row[3]),
            'city' => trim($row[4]),
            'province' => trim($row[5]),
            'address' => trim($row[6]),
        ]);
    }
}
