<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index()
    {
        $brands = [
            [
                'brandId' => '9',
                'brandName' => 'Lamsa',
                'storeName' => 'Global',
                'brandDesc' => 'Lamsa Application subscription Codes',
                'cardBrandImage' => 'https://assets.kartistore.com/lamsa-card-resized.webp'
            ],
            [
                'brandId' => '3',
                'brandName' => 'Google Play',
                'storeName' => 'USA',
                'brandDesc' => 'Google Play Card for US Account',
                'cardBrandImage' => 'https://assets.kartistore.com/googleplay-resized.webp'
            ],
            [
                'brandId' => '4',
                'brandName' => 'PlayStation',
                'storeName' => 'USA',
                'brandDesc' => 'Play Station Network Card',
                'cardBrandImage' => 'https://assets.kartistore.com/playstation-resized.webp'
            ],
        ];
        
        return response()->json($brands);
    }
}
