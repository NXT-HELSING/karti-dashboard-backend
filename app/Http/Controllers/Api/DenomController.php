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
            $denoms = $this->kartiProvider->getDenoms((int)$brandId);

            // Check if Karti returned error code 1007 (no denoms)
            if (isset($denoms['errorCode']) && $denoms['errorCode'] === 1007) {
                return response()->json([
                    'message' => 'No denominations available for this brand'
                ], 404);
            }

            return response()->json($denoms);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch denominations',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
