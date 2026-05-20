<?php

namespace App\Http\Controllers\Admin;

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

    public function index()
    {
        $brands = Brand::orderBy('sort_order')->get();
        
        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:brands,code',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'api_brand_id' => 'nullable|integer',
        ]);
        
        $brand = Brand::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'logo_url' => $request->logo_url,
            'api_config' => $request->api_brand_id ? ['brand_id' => (int)$request->api_brand_id] : null,
            'is_active' => true,
            'sort_order' => Brand::max('sort_order') + 1,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully',
            'data' => $brand
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'api_brand_id' => 'nullable|integer',
        ]);
        
        if ($request->has('api_brand_id')) {
            $apiConfig = $brand->api_config ?? [];
            if ($request->api_brand_id !== null) {
                $apiConfig['brand_id'] = (int)$request->api_brand_id;
            } else {
                unset($apiConfig['brand_id']);
            }
            $brand->api_config = $apiConfig;
        }
        
        $brand->update($request->only(['name', 'description', 'logo_url', 'is_active', 'sort_order']));
        
        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully',
            'data' => $brand
        ]);
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Brand deleted successfully'
        ]);
    }

    public function syncFromProvider()
    {
        try {
            $apiBrands = $this->kartiProvider->getBrands();
            
            $syncedCount = 0;
            foreach ($apiBrands as $apiBrand) {
                $brandId = $apiBrand['brandId'] ?? $apiBrand['brand_id'] ?? null;
                $brandName = $apiBrand['brandName'] ?? $apiBrand['brand_name'] ?? $apiBrand['name'] ?? null;
                $logoUrl = $apiBrand['brandImage'] ?? $apiBrand['logo_url'] ?? $apiBrand['logo'] ?? null;
                $brandDesc = $apiBrand['brandDesc'] ?? $apiBrand['description'] ?? null;
                
                if (!$brandId) continue;
                
                Brand::updateOrCreate(
                    ['id' => $brandId],
                    [
                        'name' => $brandName ?: 'Brand ' . $brandId,
                        'code' => $apiBrand['brandCode'] ?? $apiBrand['code'] ?? ('BRAND_' . $brandId),
                        'logo_url' => $logoUrl,
                        'description' => $brandDesc,
                        'is_active' => true
                    ]
                );
                
                $syncedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Brands synced successfully from Karti provider',
                'synced_count' => $syncedCount
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to sync brands: ' . $e->getMessage()
            ], 500);
        }
    }
}
