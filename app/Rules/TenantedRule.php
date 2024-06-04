<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TenantedRule implements ValidationRule
{
    private string $message;

    public function __construct(
        private $model = null,
        string $message = null,
        private ?Closure $query = null
    ) {
        if (is_null($model)) {
            $this->model = \App\Models\Warehouse::class;
        }

        if ($message) {
            $this->message = $message;
        } else {
            $this->message = class_basename($this->model) . ' not found';
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $data = $this->model::tenanted()
            ->when($this->query, $this->query)
            ->firstWhere('id', $value);

        if (!$data) {
            $fail($this->message);
        }
    }
}
