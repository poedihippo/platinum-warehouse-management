<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToCollection;

class ReceiveOrderImport implements ToCollection
{
    use Importable;

    public function collection(Collection $collection)
    {
        dd($collection);
    }
}
