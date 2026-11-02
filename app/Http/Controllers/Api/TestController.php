<?php

namespace App\Http\Controllers\Api;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TestController extends BaseController
{
    public function index()
    {
        $data = QrCode::size(114)->format('png')->merge(public_path('images/logo-platinum.png'), .2, true)->generate('5gs0peom2635dy781ka0peorux009384');
        // $data = QrCode::size(300)
        //     ->format('png')
        //     ->merge('http://localhost:8000/images/logo-platinum.png', absolute: true)
        //     ->generate('qwerty');

        return response($data)
            ->header('Content-type', 'image/png');
    }
}
