<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\CustomerPurchase;
use App\Models\Denomination;
use App\Models\BalanceHistory;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }

    // Get customer purchase history
    public function history(Request $request)
    {
        $user = $request->user();
        
        $purchases = CustomerPurchase::with(['denomination.brand', 'transaction'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }

    // Get customer balance
    public function balance(Request $request)
    {
        $user = $request->user();
        
        $balances = BalanceHistory::where('user_id', $user->id)
            ->select('currency', DB::raw('SUM(CASE WHEN type = "credit" THEN amount ELSE -amount END) as balance'))
            ->groupBy('currency')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $balances
        ]);
    }

    // Purchase card
    public function purchase(Request $request)
    {
        $request->validate([
            'denomination_id' => 'required|exists:denominations,id',
            'userID' => 'nullable|string',
        ]);

        $user = $request->user();
        $userID = $request->userID ?: $user->email ?: $user->phone ?: $user->name ?: 'customer';
        $denomination = Denomination::with('brand')->findOrFail($request->denomination_id);
        
        // Check if denomination is available
        if (!$denomination->is_available) {
            return response()->json([
                'error' => 'This product is currently unavailable'
            ], 400);
        }
        
        // Check stock
        if ($denomination->stock_quantity === 0) {
            return response()->json([
                'error' => 'Out of stock'
            ], 400);
        }

        // Check if customer has enough local balance
        if ($user->balance < $denomination->price) {
            return response()->json([
                'error' => 'Insufficient local balance. Your balance is ' . $denomination->currency . ' ' . number_format($user->balance, 2) . '. Price is ' . $denomination->currency . ' ' . number_format($denomination->price, 2) . '. Please top up.'
            ], 400);
        }

        // Pre-check Karti API balance (Merchant master account balance)
        try {
            $kartiBalances = $this->kartiProvider->getBalance();
            $usdBalance = 0.0;
            if (is_array($kartiBalances)) {
                foreach ($kartiBalances as $kb) {
                    if (isset($kb['currency']) && strtoupper($kb['currency']) === 'USD') {
                        $usdBalance = (float)($kb['balance'] ?? 0.0);
                        break;
                    }
                }
            }
            
            if ($usdBalance < $denomination->price) {
                return response()->json([
                    'error' => "Temporary provider outage: Insufficient master balance on provider account (Available: \${$usdBalance} USD, Card: \${$denomination->price} USD). Please contact the administrator to recharge."
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Failed to pre-check Karti balance: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to verify provider availability: ' . $e->getMessage()
            ], 500);
        }
        
        $partnerTransactionId = uniqid('txn_' . $user->id . '_');
        
        DB::beginTransaction();
        
        try {
            // Step 1: Reserve card from Karti
            $reserveResponse = $this->kartiProvider->reserveCard(
                $denomination->provider_denom_id,
                $denomination->brand->getApiBrandId(),
                $userID,
                $partnerTransactionId
            );
            
            if (!isset($reserveResponse['errorCode']) || $reserveResponse['errorCode'] !== '1000') {
                throw new \Exception($reserveResponse['erroreDesc'] ?? 'Reservation failed');
            }
            
            // Step 2: Get card details
            $reserveId = $reserveResponse['reserveID'] ?? $reserveResponse['reserveId'];
            $cardResponse = $this->kartiProvider->getCardDetails($reserveId, $partnerTransactionId);
            
            if (!isset($cardResponse['errorCode']) || $cardResponse['errorCode'] !== '1000') {
                throw new \Exception($cardResponse['resultDesc'] ?? 'Failed to get card');
            }
            
            // Step 3: Save transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'provider' => 'karti',
                'denom_id' => $denomination->provider_denom_id,
                'brand_id' => $denomination->brand_id,
                'amount_paid' => $denomination->price,
                'currency' => $denomination->currency,
                'status' => 'completed',
                'reserve_id' => $reserveId,
                'partner_transaction_id' => $partnerTransactionId,
            ]);
            
            $purchase = CustomerPurchase::create([
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'denomination_id' => $denomination->id,
                'card_code' => $cardResponse['cardCode'] ?? '',
                'serial_number' => $cardResponse['serial'] ?? null,
                'face_value' => $cardResponse['cardFaceValue'] ?? '',
                'currency' => $denomination->currency,
                'expiry_date' => $cardResponse['expireDate'] ?? null,
                'status' => 'completed',
                'provider_response' => json_encode($cardResponse),
            ]);
            
            // Deduct local balance
            $user->decrement('balance', $denomination->price);

            // Record balance history debit
            BalanceHistory::create([
                'user_id' => $user->id,
                'amount' => $denomination->price,
                'currency' => $denomination->currency,
                'type' => 'debit',
                'description' => 'Purchased ' . $denomination->brand->name . ' - ' . $denomination->name,
                'transaction_id' => $transaction->id,
            ]);

            // Decrease stock if tracking
            if ($denomination->stock_quantity > 0) {
                $denomination->decrement('stock_quantity');
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'card_code' => $purchase->card_code,
                    'serial' => $purchase->serial_number,
                    'face_value' => $purchase->face_value,
                    'expiry_date' => $purchase->expiry_date,
                    'brand' => $denomination->brand->name,
                    'product' => $denomination->name,
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'denomination_id' => $denomination->id
            ]);
            
            return response()->json([
                'error' => 'Purchase failed: ' . $e->getMessage()
            ], 500);
        }
    }
}