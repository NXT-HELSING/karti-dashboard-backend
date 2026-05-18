<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\PurchasedCard;
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

    /**
     * Purchase: Reserve and get card in one step
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'denomId' => 'required|integer',
            'brandId' => 'required|integer',
            'userID' => 'required|string',
        ]);

        $user = $request->user();
        $partnerTransactionId = uniqid('txn_' . $user->id . '_');

        // Step 1: Reserve
        try {
            $reserveResponse = $this->kartiProvider->reserveCard(
                $request->denomId,
                $request->brandId,
                $request->userID,
                $partnerTransactionId
            );
        } catch (\Exception $e) {
            Log::error('Card reserve failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Reservation failed: ' . $e->getMessage()
            ], 500);
        }

        if (
            !isset($reserveResponse['errorCode']) ||
            $reserveResponse['errorCode'] !== '1000'
        ) {
            return response()->json(['error' => $reserveResponse['reserveStatus'] ?? 'Reservation failed'], 400);
        }

        // Step 2: Get card details
        try {
            $cardResponse = $this->kartiProvider->getCardDetails(
                isset($reserveResponse['reserveId']) ? $reserveResponse['reserveId'] : ($reserveResponse['reserved'] ?? null),
                $partnerTransactionId
            );
        } catch (\Exception $e) {
            Log::error('Get card failed', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to retrieve card: ' . $e->getMessage()
            ], 500);
        }

        if (
            !isset($cardResponse['errorCode']) ||
            $cardResponse['errorCode'] !== '1000'
        ) {
            return response()->json(['error' => $cardResponse['resultDesc'] ?? 'Failed to retrieve card'], 400);
        }

        // Save transaction and card to database
        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'provider' => 'karti',
                'denom_id' => $request->denomId,
                'brand_id' => $request->brandId,
                'amount_paid' => $cardResponse['balance'] ?? 0,
                'currency' => $cardResponse['currency'] ?? $reserveResponse['currency'] ?? 'USD',
                'status' => 'completed',
                'reserve_id' => isset($reserveResponse['reserveId']) ? $reserveResponse['reserveId'] : ($reserveResponse['reserved'] ?? null),
                'partner_transaction_id' => $partnerTransactionId,
            ]);

            $purchasedCard = PurchasedCard::create([
                'transaction_id' => $transaction->id,
                'card_code' => $cardResponse['cardCode'] ?? '',
                'serial' => $cardResponse['serial'] ?? null,
                'face_value' => $cardResponse['cardFaceValue'] ?? '',
                'currency' => $cardResponse['cardDenomVal'] ?? '',
                'expiry_date' => $cardResponse['expireDate'] ?? null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save purchase or card', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Failed to save purchase: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'card' => [
                'code' => $cardResponse['cardCode'] ?? '',
                'serial' => $cardResponse['serial'] ?? null,
                'face_value' => $cardResponse['cardFaceValue'] ?? '',
                'expiry_date' => $cardResponse['expireDate'] ?? null,
            ]
        ]);
    }

    /**
     * Get purchase history for the authenticated user
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $transactions = Transaction::where('user_id', $user->id)
            ->with('purchasedCard')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'transactions' => $transactions
        ]);
    }
}