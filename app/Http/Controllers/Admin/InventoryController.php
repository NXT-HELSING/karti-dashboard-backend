<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Denomination;
use App\Models\Brand;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }
    
    public function index(Request $request)
    {
        $query = Denomination::with('brand');
        
        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        
        if ($request->has('is_available')) {
            $query->where('is_available', $request->is_available);
        }
        
        $denominations = $query->orderBy('sort_order')->paginate(20);
        
        $brands = Brand::all();
        
        return response()->json([
            'success' => true,
            'data' => $denominations,
            'brands' => $brands
        ]);
    }
    
    public function updateDenomination(Request $request, $id)
    {
        $request->validate([
            'price' => 'sometimes|numeric|min:0',
            'is_available' => 'sometimes|boolean',
            'stock_quantity' => 'sometimes|integer|min:-1',
            'sort_order' => 'sometimes|integer',
        ]);
        
        $denomination = Denomination::findOrFail($id);
        $denomination->update($request->only(['price', 'is_available', 'stock_quantity', 'sort_order']));
        
        return response()->json([
            'success' => true,
            'message' => 'Denomination updated successfully',
            'data' => $denomination
        ]);
    }
    
    public function syncFromProvider(Request $request)
    {
        $request->validate([
            'brand_id' => 'required|exists:brands,id'
        ]);
        
        $brand = Brand::findOrFail($request->brand_id);
        
        try {
            $apiDenoms = $this->kartiProvider->getDenoms($brand->id);
            
            foreach ($apiDenoms as $apiDenom) {
                Denomination::updateOrCreate(
                    [
                        'brand_id' => $brand->id,
                        'provider_denom_id' => $apiDenom['denomId']
                    ],
                    [
                        'name' => $apiDenom['denomDesc'],
                        'value' => $apiDenom['denomValue'] . ' ' . $apiDenom['denomCurrency'],
                        'price' => $apiDenom['denomPrice'],
                        'currency' => $apiDenom['denomPriceCurrency'],
                        'description' => $apiDenom['denomDesc'],
                        'image_url' => $apiDenom['cardBrandImage'] ?? null,
                        'is_available' => true,
                    ]
                );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Inventory synced successfully from provider',
                'synced_count' => count($apiDenoms)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to sync: ' . $e->getMessage()
            ], 500);
        }
    }
}
