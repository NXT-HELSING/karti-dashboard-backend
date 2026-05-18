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
                'brandId' => '3',
                'brandName' => 'Google Play',
                'storeName' => 'US',
                'brandDesc' => 'Google Play Card for US Account'
            ],
            [
                'brandId' => '4',
                'brandName' => 'PlayStation',
                'storeName' => 'US',
                'brandDesc' => 'Play Station Network Card'
            ],
            [
                'brandId' => '5',
                'brandName' => 'Xbox Live',
                'storeName' => 'US',
                'brandDesc' => 'Xbox Live Card'
            ],
        ];

        return response()->json($brands);
    }
}
