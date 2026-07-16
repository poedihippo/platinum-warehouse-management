<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ProductBrand extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * Public S3 URL for the brand logo, or null when none is uploaded.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path
            ? Storage::disk('s3')->url($this->logo_path)
            : null);
    }
}
