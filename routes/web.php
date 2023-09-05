<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('clear-cache', function () {
    Artisan::call('clear-compiled');
    echo "clear-compiled: complete<br>";
    Artisan::call('cache:clear');
    echo "cache:clear: complete<br>";
    Artisan::call('config:clear');
    echo "config:clear: complete<br>";
    Artisan::call('view:clear');
    echo "view:clear: complete<br>";
    Artisan::call('optimize:clear');
    echo "optimize:clear: complete<br>";
    Artisan::call('config:cache');
    echo "config:cache: complete<br>";
    Artisan::call('view:cache');
    echo "view:cache: complete<br>";
});

Route::get('migrate', function () {
    Artisan::call('migrate');
    echo "migrate - complete<br>";
});

Route::get('migrate-fresh', function () {
    Artisan::call('migrate:fresh --seed');
    echo "migrate:fresh --seed - complete<br>";
});

Route::get('/', function () {
    return redirect()->away('https://platinumadisentosa.com/');
    // return view('welcome');
});

Route::get('test', function () {
    $from = [255, 0, 0];
    $to = [0, 0, 255];
    return QrCode::size(200)
        ->gradient($from[0], $from[1], $from[2], $to[0], $to[1], $to[2], 'horizontal')
        ->email('gmail@gmail.com');

    // return response()->streamDownload(
    //     function () use($from) {
    //         echo QrCode::size(200)
    //             ->errorCorrection('L')
    //             ->gradient($from[0], $from[1], $from[2], $to[0], $to[1], $to[2], 'horizontal')
    //             ->generate(request()->url());
    //     },
    //     'qr-code.png',
    //     [
    //         'Content-Type' => 'image/png',
    //     ]
    // );
});
