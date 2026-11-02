<?php

namespace App\Validation;

use Illuminate\Validation\Validator as BaseValidator;

/**
 * Laravel 12 changed the `image` rule: SVG is rejected unless `image:allow_svg`
 * is given, and avif / heic / heif are accepted. The rule keeps the Laravel 10
 * list so uploads (brand logos, prize photos) pass or fail exactly as before,
 * with the same messages.
 */
class Validator extends BaseValidator
{
    public function validateImage($attribute, $value, $parameters = [])
    {
        return $this->validateMimes($attribute, $value, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp']);
    }
}
