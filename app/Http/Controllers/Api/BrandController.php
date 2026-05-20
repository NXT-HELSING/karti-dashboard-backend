<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }

    // Get all active brands
    public function index()
    {
        $brands = Brand::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    // Sync brands from Karti API (Admin only)
    public function syncBrands(Request $request)
    {
        // This is where you'd fetch brands from Karti API
        // For now, manually add brands
        
        $brands = [
            ['name' => 'Lamsa', 'code' => 'LAMSA', 'description' => 'Educational content platform'],
            ['name' => 'Shahid', 'code' => 'SHAHID', 'description' => 'Streaming service'],
            ['name' => 'Jawwy TV', 'code' => 'JAWWY', 'description' => 'IPTV service'],
        ];
        
        foreach ($brands as $brandData) {
            Brand::updateOrCreate(
                ['code' => $brandData['code']],
                $brandData
            );
        }
        
        return response()->json(['success' => true, 'message' => 'Brands synced']);
    }
}
