<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }

    public function index()
    {
        try {
            $brands = $this->kartiProvider->getBrands();
            return response()->json($brands);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch brands',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
