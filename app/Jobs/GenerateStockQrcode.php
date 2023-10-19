<?php

namespace App\Jobs;

use App\Models\StockProductUnit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateStockQrcode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public StockProductUnit $stockProductUnit, public int $qty, public string $folder = 'qrcode/')
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $folder = $this->folder ?? 'qrcode/';
        for ($i = 0; $i < $this->qty ?? 0; $i++) {
            $stock = $this->stockProductUnit->stocks()->create();

            // $logo = public_path('images/logo-platinum.png');

            $data = QrCode::size(350)
                ->format('png')
                // ->merge($logo, absolute: true)
                ->generate($stock->id);

            // $fileName = $receiveOrderDetail->id . '/' . $stock->id . '.png';
            $fileName = 'import/' . $stock->id . '.png';
            $fullPath = $folder .  $fileName;
            Storage::put($fullPath, $data);

            $stock->update(['qr_code' => $fullPath]);
        }
    }
}
