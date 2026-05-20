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
        $this->baseUrl = config('services.karti.base_url');
        $this->username = config('services.karti.username');
        $this->password = config('services.karti.password');
        $this->opId = config('services.karti.op_id');
        $this->partnerId = config('services.karti.partner_id');
    }

    protected function request($method, $endpoint, $params = [], $isJson = true)
    {
        $url = $this->baseUrl . $endpoint;
        $options = [
            'auth' => [$this->username, $this->password],
        ];

        if ($method === 'GET') {
            $options['query'] = $params;
        } else {
            $options['json'] = $params;
        }

        $response = Http::withOptions($options)->$method($url);

        if ($response->failed()) {
            Log::error('Karti API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Karti API request failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getBrands(): array
    {
        // Note: The correct endpoint is unknown – we saw 404 earlier.
        // We'll use a placeholder; you need to ask Karti for the correct path.
        // For now, return empty or hardcoded.
        // TODO: Replace with actual endpoint when known.
        return [];
    }

    public function getDenoms(int $brandId): array
    {
        $response = $this->request('GET', '/KartiShop/DenomList', [
            'lang' => 'EN',
            'brandId' => $brandId,
            'opId' => $this->opId,
        ]);
        return $response;
    }

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

    public function confirmPin(string $reserveId, string $pin): bool
    {
        $response = $this->request('POST', '/KartiShop/verifyPin', [
            'otpld' => $reserveId,
            'pin' => $pin,
        ]);
        return ($response['transactionStatus'] ?? '') === '219';
    }

    public function getCardDetails(string $reserveId, string $partnerTxId): array
    {
        $response = $this->request('POST', '/KartiShop/CardDetails/en', [
            'opId' => (string)$this->opId,
            'partnerTransactionId' => $partnerTxId,
            'reserveID' => $reserveId,
        ], true);
        
        return $response;
    }

    public function getBalance(): array
    {
        $response = $this->request('GET', '/KartiShop/balances', [
            'opId' => $this->opId,
        ]);
        return $response;
    }
}