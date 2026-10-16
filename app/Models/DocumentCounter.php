<?php

namespace App\Models;

use App\Enums\CompanyEnum;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;

class DocumentCounter extends Model
{
    protected $fillable = [
        'company',
        'document_type',
        'period',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'company' => CompanyEnum::class,
        'document_type' => DocumentType::class,
    ];
}
