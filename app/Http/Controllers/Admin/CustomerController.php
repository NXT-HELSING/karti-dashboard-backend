<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerPurchase;
use App\Models\BalanceHistory;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = User::withCount('transactions')
            ->withSum('transactions', 'amount_paid')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }
    
    public function show($id)
    {
        $customer = User::with(['transactions.purchasedCard', 'balanceHistory'])
            ->findOrFail($id);
        
        $purchases = CustomerPurchase::with(['denomination.brand'])
            ->where('user_id', $id)
            ->latest()
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'purchases' => $purchases,
            ]
        ]);
    }
    
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|boolean'
        ]);
        
        $customer = User::findOrFail($id);
        $customer->is_active = $request->is_active;
        $customer->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Customer status updated successfully',
            'data' => $customer
        ]);
    }
    
    public function addBalance(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
            'description' => 'required|string'
        ]);
        
        $customer = User::findOrFail($id);
        
        $balanceHistory = BalanceHistory::create([
            'user_id' => $customer->id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'type' => 'credit',
            'description' => $request->description,
        ]);
        
        // Update user balance
        $customer->balance += $request->amount;
        $customer->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Balance added successfully',
            'data' => $balanceHistory
        ]);
    }
}
