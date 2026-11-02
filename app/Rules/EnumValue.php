<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class EnumValue implements ValidationRule
{
    public function __construct(
        protected string $enumClass,
        protected bool $strict = true,
    ) {
        if (! enum_exists($this->enumClass)) {
            throw new InvalidArgumentException("Cannot validate against the enum, the class {$this->enumClass} doesn't exist.");
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->enumClass::hasValue($value, $this->strict)) {
            $fail($this->message());
        }
    }

    public function message(): string
    {
        return trans()->has('validation.enum_value')
            ? __('validation.enum_value')
            : 'The value you have entered is invalid.';
    }
}
