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
     * Step 1: Reserve a card
     */
    public function reserve(Request $request)
    {
        $request->validate([
            'denomId' => 'required|integer',
            'brandId' => 'required|integer',
            'userID' => 'required|string', // Phone number or user identifier
        ]);

        $user = $request->user();
        $partnerTransactionId = uniqid('txn_' . $user->id . '_');

        try {
            $response = $this->kartiProvider->reserveCard(
                $request->denomId,
                $request->brandId,
                $request->userID,
                $partnerTransactionId
            );

            // Check if reservation was successful
            if (isset($response['errorCode']) && $response['errorCode'] !== '1000') {
                return response()->json([
                    'success' => false,
                    'error' => $response['reserveStatus'] ?? 'Reservation failed',
                    'errorCode' => $response['errorCode']
                ], 400);
            }

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'provider' => 'karti',
                'denom_id' => $request->denomId,
                'brand_id' => $request->brandId,
                'amount_paid' => 0, // Will update after PIN verification
                'currency' => $response['currency'] ?? 'USD',
                'status' => 'pending',
                'reserve_id' => $response['reserved'] ?? null,
                'partner_transaction_id' => $partnerTransactionId,
            ]);

            return response()->json([
                'success' => true,
                'reserve_id' => $response['reserved'],
                'transaction_id' => $transaction->id,
                'balance' => $response['balance'] ?? null,
                'currency' => $response['currency'] ?? 'USD',
                'message' => 'Card reserved. Please enter the PIN sent to your phone.'
            ]);

        } catch (\Exception $e) {
            Log::error('Card reserve failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to reserve card: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 2: Verify PIN and complete purchase
     */
    public function verify(Request $request)
    {
        $request->validate([
            'reserve_id' => 'required|string',
            'pin' => 'required|string',
        ]);

        $user = $request->user();

        // Find the transaction
        $transaction = Transaction::where('reserve_id', $request->reserve_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found'
            ], 404);
        }

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => 'Transaction already processed'
            ], 400);
        }

        try {
            $response = $this->kartiProvider->confirmPin($request->reserve_id, $request->pin);

            // Check if PIN verification was successful (219 = success)
            $isSuccess = isset($response['transactionStatus']) && $response['transactionStatus'] === '219';

            if (!$isSuccess) {
                return response()->json([
                    'success' => false,
                    'error' => $response['transactionDesc'] ?? 'PIN verification failed',
                    'errorCode' => $response['transactionStatus'] ?? 'unknown'
                ], 400);
            }

            // Update transaction status
            $transaction->status = 'completed';
            $transaction->save();

            return response()->json([
                'success' => true,
                'message' => 'PIN verified successfully. You can now retrieve your card.',
                'reserve_id' => $request->reserve_id
            ]);

        } catch (\Exception $e) {
            Log::error('PIN verification failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'PIN verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 3: Get card details after successful verification
     */
    public function getCard(Request $request, $reserveId)
    {
        $user = $request->user();

        $transaction = Transaction::where('reserve_id', $reserveId)
            ->where('user_id', $user->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found'
            ], 404);
        }

        if ($transaction->status !== 'completed') {
            return response()->json([
                'success' => false,
                'error' => 'Card not ready. Complete PIN verification first.'
            ], 400);
        }

        // Check if card already retrieved
        $existingCard = PurchasedCard::where('transaction_id', $transaction->id)->first();
        if ($existingCard) {
            return response()->json([
                'success' => true,
                'card' => [
                    'code' => $existingCard->card_code,
                    'serial' => $existingCard->serial,
                    'face_value' => $existingCard->face_value,
                    'currency' => $existingCard->currency,
                    'expiry_date' => $existingCard->expiry_date,
                ]
            ]);
        }

        try {
            $response = $this->kartiProvider->getCardDetails($reserveId, $transaction->partner_transaction_id);

            if (isset($response['errorCode']) && $response['errorCode'] !== '1000') {
                return response()->json([
                    'success' => false,
                    'error' => $response['resultDesc'] ?? 'Failed to retrieve card'
                ], 400);
            }

            // Save purchased card
            $purchasedCard = PurchasedCard::create([
                'transaction_id' => $transaction->id,
                'card_code' => $response['cardCode'] ?? '',
                'serial' => $response['serial'] ?? null,
                'face_value' => $response['cardFaceValue'] ?? '',
                'currency' => $response['cardDenomVal'] ?? '',
                'expiry_date' => $response['expireDate'] ?? null,
            ]);

            // Update transaction with amount
            $transaction->amount_paid = $response['balance'] ?? 0;
            $transaction->save();

            return response()->json([
                'success' => true,
                'card' => [
                    'code' => $purchasedCard->card_code,
                    'serial' => $purchasedCard->serial,
                    'face_value' => $purchasedCard->face_value,
                    'currency' => $purchasedCard->currency,
                    'expiry_date' => $purchasedCard->expiry_date,
                ],
                'balance_remaining' => $response['balance'] ?? null,
                'currency' => $response['currency'] ?? 'USD'
            ]);

        } catch (\Exception $e) {
            Log::error('Get card failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve card: ' . $e->getMessage()
            ], 500);
        }
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