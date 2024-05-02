<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Traits\CustomSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Payment extends Model implements HasMedia
{
    use CustomSoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'sales_order_id',
        'user_id',
        'amount',
        'type',
        'note',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'media',
    ];

    protected $casts = [
        'amount' => 'float',
        'type' => PaymentType::class,
    ];

    protected $appends = [
        'files'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->user_id = auth()->id();
        });
    }

    public function getFilesAttribute()
    {
        // $files = $this->getMedia();
        // $files->each(function ($item) {
        //     $item->url       = $item->getUrl();
        //     // $item->thumbnail = $item->getUrl('thumb');
        //     // $item->preview   = $item->getUrl('preview');
        // });

        // return $files;

        return $this->getMedia()->map(function ($media) {
            return [
                'id'              => $media->id,
                'url'             => $media->getUrl(),
                // 'thumbnail'       => $media->thumbnail,
                // 'preview'         => $media->preview,
                'mime_type'       => $media->mime_type,
                'collection_name' => $media->collection_name,
                'name'            => $media->name,
            ];
        })->all();
    }

    // public function getFilesAttribute()
    // {
    //     $files = $this->getMedia();
    //     $data = [];
    //     if ($files->count() > 0) {
    //         foreach ($files as $file) {
    //             $data[] = $file->getUrl();
    //         }
    //     }
    //     return $data;
    // }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
