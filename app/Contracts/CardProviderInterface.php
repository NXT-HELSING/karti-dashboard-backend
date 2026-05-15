<?php

namespace App\Contracts;

interface CardProviderInterface
{
    public function getBrands(): array;
    public function getDenoms(int $brandId): array;
    public function reserveCard(int $denomId, int $brandId, string $userIdentifier, string $partnerTxId): array;
    public function confirmPin(string $reserveId, string $pin): bool;
    public function getCardDetails(string $reserveId, string $partnerTxId): array;
    public function getBalance(): array;
}