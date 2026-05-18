<?php

namespace App\Services\Providers;

use App\Contracts\CardProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KartiProvider implements CardProviderInterface
{
    protected $baseUrl;
    protected $username;
    protected $password;
    protected $opId;
    protected $partnerId;

    public function __construct()
    {
        $this->baseUrl = config('services.karti.base_url', 'https://stg.kartishop.xyz');
        $this->username = config('services.karti.username');
        $this->password = config('services.karti.password');
        $this->opId = config('services.karti.op_id');
        $this->partnerId = config('services.karti.partner_id');
    }

    /**
     * Make an authenticated request to Karti API
     */
    protected function request($method, $endpoint, $params = [], $isPost = false)
    {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'auth' => [$this->username, $this->password],
        ];

        if ($method === 'GET') {
            $options['query'] = $params;
            $response = Http::withOptions($options)->get($url);
        } else {
            $options['json'] = $params;
            $response = Http::withOptions($options)->post($url);
        }

        if ($response->failed()) {
            Log::error('Karti API error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Karti API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get all brands from Karti
     * GET /KartiShop/BrandList
     */
    public function getBrands(): array
    {
        $response = $this->request('GET', '/KartiShop/BrandList', [
            'lang' => 'EN',
        ]);
        
        // The API returns an array of brands directly
        return $response;
    }

    /**
     * Get denominations for a specific brand
     * GET /KartiShop/DenomList
     */
    public function getDenoms(int $brandId): array
    {
        $response = $this->request('GET', '/KartiShop/DenomList', [
            'lang' => 'EN',
            'brandId' => $brandId,
            'opId' => $this->opId,
        ]);
        
        return $response;
    }

    /**
     * Get details for a specific denomination
     * GET /KartiShop/Denom
     */
    public function getDenomDetails(int $brandId, int $denomId): array
    {
        $response = $this->request('GET', '/KartiShop/Denom', [
            'lang' => 'EN',
            'brandId' => $brandId,
            'opId' => $this->opId,
            'denomId' => $denomId,
        ]);
        
        return $response;
    }

    /**
     * Reserve a card
     * POST /KartiShop/cardReserve
     */
    public function reserveCard(int $denomId, int $brandId, string $userIdentifier, string $partnerTxId): array
    {
        $response = $this->request('POST', '/KartiShop/cardReserve', [
            'denomId' => (string)$denomId,
            'brandId' => (string)$brandId,
            'partnerID' => $this->partnerId,
            'userID' => $userIdentifier,
            'partnerTransactionId' => $partnerTxId,
            'opId' => (string)$this->opId,
        ], true);
        
        return $response;
    }

    /**
     * Verify PIN and complete purchase
     * POST /KartiShop/verifyPin
     */
    public function confirmPin(string $reserveId, string $pin): array
    {
        $response = $this->request('POST', '/KartiShop/verifyPin', [
            'otpld' => $reserveId,
            'pin' => $pin,
        ], true);
        
        return $response;
    }

    /**
     * Get card details after successful PIN verification
     * POST /KartiShop/cardDetails
     */
    public function getCardDetails(string $reserveId, string $partnerTxId): array
    {
        $response = $this->request('POST', '/KartiShop/cardDetails', [
            'opId' => (string)$this->opId,
            'reservedID' => $reserveId,
            'partnerTransactionId' => $partnerTxId,
        ], true);
        
        return $response;
    }

    /**
     * Get account balance
     * GET /KartiShop/balances
     */
    public function getBalance(): array
    {
        $response = $this->request('GET', '/KartiShop/balances', [
            'opId' => $this->opId,
        ]);
        
        return $response;
    }
}