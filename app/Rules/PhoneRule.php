<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneRule implements ValidationRule
{
    // add maxLength 255 by default, can be overriden, and can be nullable if needed
    public function __construct(
        public int|null $maxLength = 255,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_null($this->maxLength) && strlen($value) > $this->maxLength) {
            $fail('The :attribute must not be greater than ' . $this->maxLength . ' characters.');
        }
    }
}
