<?php

namespace App\Services;

use App\Models\Brand;

class BrandSyncService
{
    /**
     * Upsert one brand from Karti (match by api_config.brand_id; safe unique code).
     *
     * @return 'created'|'updated'
     */
    public function upsertFromKarti(array $kartiBrand): string
    {
        $kartiBrandId = (int) ($kartiBrand['brandId'] ?? $kartiBrand['id'] ?? 0);
        if ($kartiBrandId <= 0) {
            throw new \InvalidArgumentException('Karti brand missing brandId');
        }

        $name = $kartiBrand['brandName'] ?? $kartiBrand['name'] ?? 'Unknown';
        $preferredCode = $this->preferredCodeFromName($name, $kartiBrandId);

        $payload = [
            'name' => $name,
            'description' => $kartiBrand['brandDescription'] ?? $kartiBrand['description'] ?? null,
            'logo_url' => $kartiBrand['brandLogo'] ?? $kartiBrand['logo'] ?? null,
            'is_active' => true,
            'api_config' => ['brand_id' => $kartiBrandId],
        ];

        $existing = Brand::where('api_config->brand_id', $kartiBrandId)->first();

        if ($existing) {
            $payload['code'] = $this->codeForUpdate($preferredCode, $existing);
            $existing->update($payload);

            return 'updated';
        }

        $payload['code'] = $this->resolveUniqueCodeForCreate($preferredCode, $kartiBrandId);
        $payload['sort_order'] = ((int) Brand::max('sort_order')) + 1;
        Brand::create($payload);

        return 'created';
    }

    protected function preferredCodeFromName(string $name, int $kartiBrandId): string
    {
        $clean = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10));

        return $clean !== '' ? $clean : 'BRAND' . $kartiBrandId;
    }

    protected function resolveUniqueCodeForCreate(string $preferredCode, int $kartiBrandId): string
    {
        if (!Brand::where('code', $preferredCode)->exists()) {
            return $preferredCode;
        }

        $withId = $preferredCode . $kartiBrandId;
        if (!Brand::where('code', $withId)->exists()) {
            return $withId;
        }

        return 'K' . $kartiBrandId;
    }

    protected function codeForUpdate(string $preferredCode, Brand $brand): string
    {
        if ($preferredCode === $brand->code) {
            return $brand->code;
        }

        $taken = Brand::where('code', $preferredCode)
            ->where('id', '!=', $brand->id)
            ->exists();

        return $taken ? $brand->code : $preferredCode;
    }
}
