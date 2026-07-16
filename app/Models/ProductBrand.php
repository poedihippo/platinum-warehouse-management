<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
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

    /**
     * Uploads a new logo to S3, deleting the previous one if any, and
     * returns the new storage path. Does not persist — callers assign
     * the result to logo_path and save. Shared by the bejo
     * (ProductBrandController) and verify-panel (BrandManagementController)
     * upload paths so there is one storage implementation, not two.
     */
    public function storeLogo(UploadedFile $file): string
    {
        if ($this->logo_path) {
            Storage::disk('s3')->delete($this->logo_path);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));

        return $file->storeAs("brands/{$this->id}", "logo.{$ext}", 's3');
    }
}
