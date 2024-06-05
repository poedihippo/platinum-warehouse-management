<?php

namespace App\Imports;

use App\Enums\BatchSource;
use App\Models\Voucher;
use App\Models\VoucherGenerateBatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class VoucherImport implements ToCollection, SkipsEmptyRows, WithHeadingRow, SkipsOnFailure, WithValidation
{
    use Importable;
    public int $totalInserted = 0;

    public function __construct(
        private string $voucherCategoryId,
        private string $description = '',
    ) {
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function collection(Collection $rows)
    {
        $batch = null;
        foreach ($rows as $row) {
            if (is_null($batch)) {
                $batch = VoucherGenerateBatch::create([
                    'user_id' => auth('sanctum')->id(),
                    'source' => BatchSource::IMPORT,
                    'description' => $this->description ?? ''
                ]);
            }

            Voucher::create([
                'voucher_generate_batch_id' => $batch->id,
                'voucher_category_id' => $this->voucherCategoryId,
                'code' => trim($row['code']),
                'description' => trim($row['description'] ?? '')
            ]);
            $this->totalInserted++;
        }
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'unique:vouchers,code'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function onFailure(Failure ...$failures)
    {
    }

    public function getTotalInserted(): int
    {
        return $this->totalInserted;
    }
}
