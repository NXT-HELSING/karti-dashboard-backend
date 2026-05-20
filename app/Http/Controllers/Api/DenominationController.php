<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Denomination;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;

class DenominationController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }

    // Get denominations for a brand
    public function index($brandId)
    {
        $brand = Brand::findOrFail($brandId);
        
        // First try to get from local database
        $denominations = Denomination::where('brand_id', $brandId)
            ->where('is_available', true)
            ->get();
        
        // If empty, sync from API
        if ($denominations->isEmpty()) {
            $this->syncDenominations($brandId);
            $denominations = Denomination::where('brand_id', $brandId)
                ->where('is_available', true)
                ->get();
        }
        
        return response()->json([
            'success' => true,
            'brand' => $brand,
            'data' => $denominations
        ]);
    }

    // Sync denominations from Karti API
    public function syncDenominations($brandId)
    {
        try {
            $brand = Brand::findOrFail($brandId);
            $apiDenoms = $this->kartiProvider->getDenoms($brandId);
            
            foreach ($apiDenoms as $apiDenom) {
                Denomination::updateOrCreate(
                    [
                        'brand_id' => $brandId,
                        'provider_denom_id' => $apiDenom['denomId']
                    ],
                    [
                        'name' => $apiDenom['denomDesc'],
                        'value' => $apiDenom['denomValue'] . ' ' . $apiDenom['denomCurrency'],
                        'price' => $apiDenom['denomPrice'],
                        'currency' => $apiDenom['denomPriceCurrency'],
                        'description' => $apiDenom['denomDesc'],
                        'image_url' => $apiDenom['cardBrandImage'] ?? null,
                        'is_available' => true
                    ]
                );
            }
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to sync denominations', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
