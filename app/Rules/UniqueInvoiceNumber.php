<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Rejects a document number that already exists on the given table.
 *
 * Soft deleted rows are ignored so a number stays free once its document is gone.
 * Sales Orders and Delivery Orders are hard deleted, so for those tables the filter
 * is simply a no-op.
 */
class UniqueInvoiceNumber implements ValidationRule
{
    public function __construct(
        private string $table,
        private int|string|null $ignoreId = null,
        private string $message = 'Invoice number sudah digunakan',
    ) {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->whereNull('deleted_at')
            ->where('invoice_no', trim((string) $value))
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail($this->message);
        }
    }
}
