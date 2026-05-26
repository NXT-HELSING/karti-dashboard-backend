<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandSyncService;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    protected $kartiProvider;

    protected $brandSync;

    public function __construct(KartiProvider $kartiProvider, BrandSyncService $brandSync)
    {
        $this->kartiProvider = $kartiProvider;
        $this->brandSync = $brandSync;
    }

    /**
     * Get all brands from local database
     */
    public function index()
    {
        $brands = Brand::orderBy('sort_order')->get();
        
        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * Sync brands from Karti API
     * Uses the correct endpoint: /KartiShop/BrandList/EN?opId=1062
     */
    public function syncFromKarti(Request $request)
    {
        try {
            $kartiBrands = $this->kartiProvider->getBrands();

            if (empty($kartiBrands)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No brands found from Karti API',
                ], 404);
            }

            $synced = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($kartiBrands as $kartiBrand) {
                if (!is_array($kartiBrand)) {
                    $skipped++;
                    continue;
                }

                if (empty($kartiBrand['brandId'] ?? $kartiBrand['id'] ?? null)) {
                    $skipped++;
                    continue;
                }

                $result = $this->brandSync->upsertFromKarti($kartiBrand);
                if ($result === 'created') {
                    $synced++;
                } else {
                    $updated++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Synced $synced new brands, updated $updated existing brands",
                'data' => [
                    'synced' => $synced,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'total' => count($kartiBrands),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to sync brands from Karti', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to sync brands: ' . $e->getMessage(),
            ], 500);
        }
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
}
