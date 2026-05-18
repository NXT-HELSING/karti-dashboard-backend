<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Providers\KartiProvider;
use Illuminate\Http\Request;

class DenomController extends Controller
{
    protected $kartiProvider;

    public function __construct(KartiProvider $kartiProvider)
    {
        $this->kartiProvider = $kartiProvider;
    }

    public function index($brandId)
    {
        try {
            $response = $this->kartiProvider->getDenoms((int)$brandId);

            // If Karti returns error code 1007 (no denoms available)
            if (isset($response['errorCode']) && $response['errorCode'] === 1007) {
                return response()->json([
                    'message' => 'No denominations available for this brand'
                ], 404);
            }

            // If response is an array of denoms, return it directly
            if (is_array($response) && !isset($response['errorCode'])) {
                return response()->json($response);
            }

            // Fallback
            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch denominations',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
