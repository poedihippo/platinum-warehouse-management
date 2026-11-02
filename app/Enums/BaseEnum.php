<?php

namespace App\Enums;

use Illuminate\Support\Str;

trait BaseEnum
{
    public static function getValues(): array
    {
        return array_map(fn (self $enum) => $enum->value, self::cases());
    }

    public static function getKeys(): array
    {
        return array_map(fn (self $enum) => $enum->name, self::cases());
    }

    public static function hasValue(mixed $value, bool $strict = true): bool
    {
        $validValues = static::getValues();

        if ($strict) {
            return in_array($value, $validValues, true);
        }

        return in_array((string) $value, array_map('strval', $validValues), true);
    }

    public static function hasKey(string $key): bool
    {
        return in_array($key, static::getKeys(), true);
    }

    public static function coerce(mixed $enumKeyOrValue): ?static
    {
        if ($enumKeyOrValue === null) {
            return null;
        }

        if ($enumKeyOrValue instanceof static) {
            return $enumKeyOrValue;
        }

        if (static::hasValue($enumKeyOrValue)) {
            return static::from($enumKeyOrValue);
        }

        if (is_string($enumKeyOrValue) && static::hasKey($enumKeyOrValue)) {
            return constant(static::class.'::'.$enumKeyOrValue);
        }

        return null;
    }

    public static function getValue(?string $value = null): ?self
    {
        if (empty($value)) {
            return null;
        }

        return collect(self::cases())->first(fn (self $enum) => $enum->name == $value || $enum->value == $value);
    }

    public static function all(): array
    {
        $return = [];
        foreach (self::cases() as $enum) {
            $return[$enum->value] = $enum->getLabel();
        }

        return $return;
    }

    public function is(mixed $enumValue): bool
    {
        if ($enumValue instanceof static) {
            return $this->value === $enumValue->value;
        }

        return $this->value === $enumValue;
    }

    public function isNot(mixed $enumValue): bool
    {
        return ! $this->is($enumValue);
    }

    public function in(iterable $values): bool
    {
        foreach ($values as $value) {
            if ($this->is($value)) {
                return true;
            }
        }

        return false;
    }

    public function getLabel(): string
    {
        return Str::of($this->name)
            ->lower()
            ->pipe(fn ($str) => Str::headline($str))
            ->value();
    }

    public function getDescription(): string
    {
        $name = $this->name;

        if (ctype_upper(preg_replace('/[^a-zA-Z]/', '', $name))) {
            $name = strtolower($name);
        }

        return ucfirst(str_replace('_', ' ', Str::snake($name)));
    }
}
