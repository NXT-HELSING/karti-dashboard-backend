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
        $this->baseUrl = rtrim(config('services.karti.base_url', 'https://stg.kartishop.xyz'), '/');
        $this->opId = config('services.karti.op_id');
        $this->partnerId = config('services.karti.partner_id');

        [$this->username, $this->password] = $this->resolveCredentials();
    }

    /**
     * Credentials from KARTI_USERNAME/KARTI_PASSWORD or KARTI_BASIC_AUTH (user:pass or base64).
     */
    protected function resolveCredentials(): array
    {
        $username = config('services.karti.username');
        $password = config('services.karti.password');

        if ($username && $password) {
            return [$username, $password];
        }

        $basicAuth = config('services.karti.basic_auth');
        if (!$basicAuth) {
            return [$username, $password];
        }

        $decoded = base64_decode($basicAuth, true);
        $pair = ($decoded !== false && str_contains($decoded, ':'))
            ? $decoded
            : $basicAuth;

        if (str_contains($pair, ':')) {
            return explode(':', $pair, 2);
        }

        return [$username, $password];
    }

    /**
     * Make an authenticated request to Karti API
     */
    protected function request($method, $endpoint, $params = [])
    {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'auth' => [$this->username, $this->password],
        ];

        Log::info('Karti API Request', [
            'method' => $method,
            'url' => $url,
            'params' => $params
        ]);

        if ($method === 'GET') {
            $options['query'] = $params;
            $response = Http::withOptions($options)->get($url);
        } else {
            $options['json'] = $params;
            $response = Http::withOptions($options)->post($url);
        }

        Log::info('Karti API Response', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);

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
     * CORRECT ENDPOINT: /KartiShop/BrandList/EN?opId={opId}
     */
    public function getBrands(): array
    {
        $response = $this->request('GET', '/KartiShop/BrandList/EN', [
            'opId' => $this->opId,
        ]);
        
        if (isset($response['errorCode']) && $response['errorCode'] !== '1000') {
            throw new \Exception($response['erroreDesc'] ?? 'Failed to fetch brands');
        }

        return $this->normalizeBrandList($response);
    }

    /**
     * Karti may return a flat list or { data: [...] } — normalize to brand rows.
     */
    protected function normalizeBrandList(array $response): array
    {
        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        foreach (['data', 'brandList', 'brands', 'BrandList'] as $key) {
            if (!empty($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }

        return array_values(array_filter(
            $response,
            fn ($row) => is_array($row) && (isset($row['brandId']) || isset($row['id']))
        ));
    }

    /**
     * Get denominations for a specific brand
     * CORRECT ENDPOINT: /KartiShop/DenomList?lang=EN&opId={opId}&brandId={brandId}
     */
    public function getDenoms(int $brandId): array
    {
        $response = $this->request('GET', '/KartiShop/DenomList', [
            'lang' => 'EN',
            'opId' => $this->opId,
            'brandId' => $brandId,
        ]);
        
        if (isset($response['errorCode']) && $response['errorCode'] !== '1000') {
            if ($response['errorCode'] === '1007') {
                return [];
            }
            throw new \Exception($response['erroreDesc'] ?? 'Failed to fetch denominations');
        }
        
        if (isset($response['data'])) {
            return $response['data'];
        }
        
        return is_array($response) ? $response : [];
    }

    /**
     * Reserve a card
     * CORRECT ENDPOINT: /KartiShop/cardReserve
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
        ]);
        
        return $response;
    }

    /**
     * Verify PIN (Optional - return mock success)
     */
    public function confirmPin(string $reserveId, string $pin): array
    {
        return ['success' => true];
    }

    /**
     * Get card details after successful PIN verification
     * CORRECT ENDPOINT: /KartiShop/CardDetails/en
     */
    public function getCardDetails(string $reserveId, string $partnerTxId): array
    {
        return $this->request('POST', '/KartiShop/CardDetails/en', [
            'opId' => (string)$this->opId,
            'partnerTransactionId' => $partnerTxId,
            'reserveID' => $reserveId,
        ]);
    }

    /**
     * Get account balance
     * CORRECT ENDPOINT: /KartiShop/balances?opId={opId}
     */
    public function getBalance(): array
    {
        $response = $this->request('GET', '/KartiShop/balances', [
            'opId' => $this->opId,
        ]);
        
        return $response;
    }
}