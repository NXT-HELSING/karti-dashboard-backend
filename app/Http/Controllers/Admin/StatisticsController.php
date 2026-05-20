<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Models\CustomerPurchase;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin'); // You'll need to create this middleware
    }

    // Dashboard overview
    public function overview()
    {
        $totalCustomers = User::count();
        $totalRevenue = Transaction::where('status', 'completed')->sum('amount_paid');
        $totalPurchases = CustomerPurchase::count();
        $activeBrands = Brand::where('is_active', true)->count();
        
        // Recent purchases
        $recentPurchases = CustomerPurchase::with(['user', 'denomination.brand'])
            ->latest()
            ->limit(10)
            ->get();
        
        // Sales by brand
        $salesByBrand = CustomerPurchase::select('brands.name', DB::raw('COUNT(*) as count'))
            ->join('denominations', 'customer_purchases.denomination_id', '=', 'denominations.id')
            ->join('brands', 'denominations.brand_id', '=', 'brands.id')
            ->groupBy('brands.name')
            ->get();
        
        // Daily sales (last 7 days)
        $dailySales = CustomerPurchase::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(denominations.price) as revenue')
            )
            ->join('denominations', 'customer_purchases.denomination_id', '=', 'denominations.id')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_customers' => $totalCustomers,
                'total_revenue' => $totalRevenue,
                'total_purchases' => $totalPurchases,
                'active_brands' => $activeBrands,
                'recent_purchases' => $recentPurchases,
                'sales_by_brand' => $salesByBrand,
                'daily_sales' => $dailySales,
            ]
        ]);
    }
    
    // Top customers
    public function topCustomers()
    {
        $topCustomers = User::select('users.*', DB::raw('SUM(transactions.amount_paid) as total_spent'))
            ->join('transactions', 'users.id', '=', 'transactions.user_id')
            ->where('transactions.status', 'completed')
            ->groupBy('users.id')
            ->orderBy('total_spent', 'desc')
            ->limit(20)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $topCustomers
        ]);
    }
}
