<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\CustomerPurchase;
use App\Models\CustomerCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    /**
     * Get all customers with REAL credit balance (same as Finance page)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $customers = User::where('is_admin', false)
            ->select('id', 'name', 'email', 'phone', 'is_active', 'total_paid', 'total_spent', 'credit_balance', 'created_at')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Get single customer details
     */
    public function show($id)
    {
        $customer = User::findOrFail($id);

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

    /**
     * Update customer status (activate/deactivate)
     */
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

    /**
     * Bulk delete customers
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id'
        ]);

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->ids as $id) {
                $customer = User::find($id);
                if ($customer && !$customer->is_admin) {
                    // Delete related records first
                    CustomerCredit::where('user_id', $id)->delete();
                    CustomerPurchase::where('user_id', $id)->delete();
                    $customer->delete();
                    $deletedCount++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk delete failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deletedCount} customers successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors
        ]);
    }
}
