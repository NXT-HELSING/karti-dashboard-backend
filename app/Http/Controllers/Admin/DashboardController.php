<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Models\CustomerPurchase;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function overview(Request $request)
    {
        // Date range filter
        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());
        
        // Main stats
        $stats = [
            'total_customers' => User::count(),
            'active_customers' => User::where('is_active', true)->count(),
            'total_revenue' => Transaction::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('amount_paid'),
            'total_purchases' => CustomerPurchase::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_brands' => Brand::where('is_active', true)->count(),
            'total_products' => \App\Models\Denomination::count(),
        ];
        
        // Recent purchases
        $recentPurchases = CustomerPurchase::with(['user', 'denomination.brand'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($purchase) {
                return [
                    'id' => $purchase->id,
                    'customer_name' => $purchase->user->name,
                    'customer_email' => $purchase->user->email,
                    'product' => $purchase->denomination->name,
                    'brand' => $purchase->denomination->brand->name,
                    'amount' => $purchase->denomination->price,
                    'currency' => $purchase->currency,
                    'status' => $purchase->status,
                    'date' => $purchase->created_at->format('Y-m-d H:i:s'),
                ];
            });
        
        // Sales by brand
        $salesByBrand = CustomerPurchase::select('brands.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(denominations.price) as revenue'))
            ->join('denominations', 'customer_purchases.denomination_id', '=', 'denominations.id')
            ->join('brands', 'denominations.brand_id', '=', 'brands.id')
            ->whereBetween('customer_purchases.created_at', [$startDate, $endDate])
            ->groupBy('brands.id', 'brands.name')
            ->get();
        
        // Daily sales (last 7 days)
        $dailySales = CustomerPurchase::select(
                DB::raw('DATE(customer_purchases.created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(denominations.price) as revenue')
            )
            ->join('denominations', 'customer_purchases.denomination_id', '=', 'denominations.id')
            ->where('customer_purchases.created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();
        
        // Top products
        $topProducts = CustomerPurchase::select('denominations.name', DB::raw('COUNT(*) as count'))
            ->join('denominations', 'customer_purchases.denomination_id', '=', 'denominations.id')
            ->whereBetween('customer_purchases.created_at', [$startDate, $endDate])
            ->groupBy('denominations.id', 'denominations.name')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_purchases' => $recentPurchases,
                'sales_by_brand' => $salesByBrand,
                'daily_sales' => $dailySales,
                'top_products' => $topProducts,
            ]
        ]);
    }
}
