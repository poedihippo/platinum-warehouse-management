<?php

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

Route::get('/', function () {
    return view('welcome');
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
