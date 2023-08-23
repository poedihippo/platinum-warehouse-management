<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SupplierSeederImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Supplier([
            'code' => trim($row['code']),
            'name' => trim($row['name']),
            'phone' => trim($row['phone']),
            'contact_person' => trim($row['contact_person']),
            'city' => trim($row['city']),
            'province' => trim($row['province']),
            'address' => trim($row['address']),
        ]);
    }
}
