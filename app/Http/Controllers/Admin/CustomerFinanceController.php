<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerFinanceController extends Controller
{
    /**
     * Get customer financial summary
     */
    public function summary($userId)
    {
        $customer = User::findOrFail($userId);
        
        $payments = CustomerCredit::where('user_id', $userId)
            ->where('type', 'payment')
            ->sum('amount');
        
        $purchases = CustomerCredit::where('user_id', $userId)
            ->where('type', 'purchase')
            ->sum('amount');
        
        $recentTransactions = CustomerCredit::where('user_id', $userId)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'total_paid' => $customer->total_paid,
                    'total_spent' => $customer->total_spent,
                    'credit_balance' => $customer->credit_balance
                ],
                'summary' => [
                    'total_payments' => (float)$payments,
                    'total_purchases' => (float)abs($purchases),
                    'current_balance' => $customer->credit_balance
                ],
                'recent_transactions' => $recentTransactions
            ]
        ]);
    }
    
    /**
     * Add credit to customer (when they pay you)
     */
    public function addCredit(Request $request, $userId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,tpe',
            'reference' => 'nullable|string|max:100',
            'description' => 'required|string|max:255'
        ]);
        
        $admin = $request->user();
        $customer = User::findOrFail($userId);
        
        DB::beginTransaction();
        
        try {
            // Record the credit transaction
            $credit = CustomerCredit::create([
                'user_id' => $customer->id,
                'amount' => $request->amount,
                'currency' => 'USD',
                'type' => 'payment',
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'description' => $request->description,
                'admin_id' => $admin->id
            ]);
            
            // Update customer totals
            $customer->total_paid += $request->amount;
            $customer->credit_balance += $request->amount;
            $customer->save();
            
            DB::commit();
            
            Log::info('Credit added to customer', [
                'customer_id' => $customer->id,
                'amount' => $request->amount,
                'admin_id' => $admin->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Successfully added \${$request->amount} credit to {$customer->name}",
                'data' => [
                    'credit' => $credit,
                    'new_balance' => $customer->credit_balance
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add credit', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to add credit: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get customer transaction history
     */
    public function transactions($userId)
    {
        $customer = User::findOrFail($userId);
        
        $transactions = CustomerCredit::where('user_id', $userId)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
    
    /**
     * Get all customers with credit summary
     */
    public function allCustomers()
    {
        $customers = User::where('is_admin', false)
            ->select('id', 'name', 'email', 'phone', 'total_paid', 'total_spent', 'credit_balance', 'created_at')
            ->orderBy('credit_balance', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }
}
