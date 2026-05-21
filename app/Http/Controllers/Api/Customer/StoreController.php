<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }

    /**
     * Get ALL products from ALL brands at once (for storefront)
     */
    public function getAllProducts(Request $request)
    {
        try {
            // Cache products for 5 minutes to reduce API calls
            $products = Cache::remember('all_products_' . md5(json_encode($request->all())), 300, function () use ($request) {
                $brands = Brand::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get();
                
                $allProducts = [];
                
                foreach ($brands as $brand) {
                    $apiBrandId = $brand->api_config['brand_id'] ?? null;
                    
                    if (!$apiBrandId) {
                        continue;
                    }
                    
                    try {
                        $apiDenoms = $this->kartiProvider->getDenoms($apiBrandId);
                        
                        foreach ($apiDenoms as $denom) {
                            $allProducts[] = [
                                'id' => $denom['denomId'],
                                'brand_id' => $brand->id,
                                'brand_name' => $brand->name,
                                'brand_logo' => $brand->logo_url,
                                'brand_code' => $brand->code,
                                'name' => $denom['denomDesc'],
                                'value' => $denom['denomValue'] . ' ' . $denom['denomCurrency'],
                                'price' => (float)$denom['denomPrice'],
                                'currency' => $denom['denomPriceCurrency'],
                                'image' => $denom['cardBrandImage'] ?? $brand->logo_url,
                                'description' => $denom['denomDesc'],
                                'category' => $this->getCategoryFromBrand($brand->code),
                                'is_available' => true
                            ];
                        }
                        
                    } catch (\Exception $e) {
                        \Log::error('Failed to fetch denominations for brand', [
                            'brand_id' => $brand->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                return $allProducts;
            });
            
            // Apply filters
            $query = collect($products);
            
            // Filter by brand
            if ($request->has('brand') && $request->brand) {
                $query = $query->where('brand_code', $request->brand);
            }
            
            // Filter by category
            if ($request->has('category') && $request->category) {
                $query = $query->where('category', $request->category);
            }
            
            // Search
            if ($request->has('search') && $request->search) {
                $search = strtolower($request->search);
                $query = $query->filter(function ($product) use ($search) {
                    return str_contains(strtolower($product['name']), $search) ||
                           str_contains(strtolower($product['brand_name']), $search) ||
                           str_contains(strtolower($product['description']), $search);
                });
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'price');
            $sortOrder = $request->get('sort_order', 'asc');
            
            if ($sortBy === 'price') {
                $query = $sortOrder === 'asc' 
                    ? $query->sortBy('price') 
                    : $query->sortByDesc('price');
            } else {
                $query = $sortOrder === 'asc' 
                    ? $query->sortBy('name') 
                    : $query->sortByDesc('name');
            }
            
            // Get unique brands for filter
            $brands = $query->unique('brand_code')->values()->map(function ($item) {
                return [
                    'code' => $item['brand_code'],
                    'name' => $item['brand_name'],
                    'logo' => $item['brand_logo']
                ];
            });
            
            // Get unique categories
            $categories = $query->unique('category')->pluck('category')->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $query->values(),
                    'filters' => [
                        'brands' => $brands,
                        'categories' => $categories
                    ],
                    'total' => $query->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Failed to load all products', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Unable to load products'
            ], 500);
        }
    }
    
    /**
     * Get customer's credit balance
     */
    public function getBalance(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $user->credit_balance,
                'total_paid' => $user->total_paid,
                'total_spent' => $user->total_spent
            ]
        ]);
    }
    
    /**
     * Map brand code to category
     */
    private function getCategoryFromBrand($brandCode)
    {
        $categories = [
            'LAMSA' => 'Education',
            'SHAHID' => 'Streaming',
            'JAWWY' => 'IPTV',
            'ITUNES' => 'Digital Gift Cards',
            'GOOGLE' => 'Digital Gift Cards',
            'PLAYSTATION' => 'Gaming',
            'XBOX' => 'Gaming',
            'STEAM' => 'Gaming',
            'PUBG' => 'Gaming',
        ];
        
        return $categories[$brandCode] ?? 'Gift Cards';
    }
}
