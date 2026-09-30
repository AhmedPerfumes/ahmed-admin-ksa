<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SaudiAddressProService
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->apiKey = env('SAUDI_ADDRESS_PRO_API_KEY');
        $this->baseUrl = rtrim(env('SAUDI_ADDRESS_PRO_BASE_URL', 'https://app.saudiaddresspro.com/api'), '/');
        $this->cacheTtl = (int) env('SAUDI_ADDRESS_PRO_CACHE_TTL', 2592000); // 30 days default
    }

    /**
     * Lookup an address by 8-character Saudi National Short Address code (e.g. RCTB4359).
     *
     * @param string $shortAddress
     * @param string $language 'en' or 'ar'
     * @return array
     */
    public function lookup(string $shortAddress, string $language = 'en'): array
    {
        $shortAddress = strtoupper(trim($shortAddress));
        $language = in_array(strtolower($language), ['ar', 'en']) ? strtolower($language) : 'en';

        if (!preg_match('/^[A-Z]{4}[0-9]{4}$/', $shortAddress)) {
            return [
                'isValid' => false,
                'message' => $language === 'ar'
                    ? 'صيغة العنوان الوطني القصير غير صحيحة (يجب أن يتكون من 4 أحرف و 4 أرقام مثل RCTB4359)'
                    : 'Invalid Short National Address format. Must be 4 letters followed by 4 numbers (e.g. RCTB4359).'
            ];
        }

        if (empty($this->apiKey)) {
            Log::warning('SaudiAddressProService: SAUDI_ADDRESS_PRO_API_KEY is not configured in .env');
            return [
                'isValid' => false,
                'message' => 'Saudi Address Pro service is not configured. Please enter your address manually.'
            ];
        }

        $cacheKey = "saudi_address_lookup_{$shortAddress}_{$language}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($shortAddress, $language) {
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-API-Key' => $this->apiKey,
                    ])
                    ->post("{$this->baseUrl}/address/lookup", [
                        'shortAddress' => $shortAddress,
                        'language' => $language,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    $buildingNumber = $data['buildingNumber'] ?? '';
                    $street = $data['street'] ?? '';
                    $district = $data['district'] ?? '';
                    $city = $data['city'] ?? '';
                    $postalCode = $data['postalCode'] ?? '';
                    $additionalNumber = $data['additionalNumber'] ?? '';
                    $isValid = !empty($data['isValid']) || !empty($city);

                    // Build user-friendly formatted address
                    $addressParts = [];
                    if (!empty($buildingNumber)) {
                        $addressParts[] = ($language === 'ar' ? 'مبنى ' : 'Building ') . $buildingNumber;
                    }
                    if (!empty($street)) {
                        $addressParts[] = $street;
                    }
                    if (!empty($district)) {
                        $addressParts[] = $district;
                    }

                    $formattedAddress = implode(', ', $addressParts);

                    return [
                        'isValid' => (bool) $isValid,
                        'shortAddress' => $data['shortAddress'] ?? $shortAddress,
                        'buildingNumber' => $buildingNumber,
                        'street' => $street,
                        'district' => $district,
                        'city' => $city,
                        'postalCode' => $postalCode,
                        'additionalNumber' => $additionalNumber,
                        'formattedAddress' => $formattedAddress,
                        'raw' => $data,
                    ];
                }

                Log::warning('SaudiAddressPro lookup failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'shortAddress' => $shortAddress,
                ]);

                return [
                    'isValid' => false,
                    'message' => $language === 'ar'
                        ? 'لم يتم العثور على عنوان لهذا الرمز'
                        : 'No address found for this short address code.'
                ];
            } catch (Exception $e) {
                Log::error('SaudiAddressPro lookup exception: ' . $e->getMessage(), [
                    'shortAddress' => $shortAddress,
                ]);

                return [
                    'isValid' => false,
                    'message' => $language === 'ar'
                        ? 'تعذر التحقق من العنوان تلقائياً، يرجى كتابة العنوان يدوياً'
                        : 'Unable to verify address automatically. Please enter your address manually.'
                ];
            }
        });
    }

    /**
     * Search address / autocomplete by query string.
     *
     * @param string $query
     * @param string $language 'en' or 'ar'
     * @param int $limit
     * @return array
     */
    public function search(string $query, string $language = 'en', int $limit = 5): array
    {
        $query = trim($query);
        $language = in_array(strtolower($language), ['ar', 'en']) ? strtolower($language) : 'en';
        $limit = max(1, min($limit, 20));

        if (mb_strlen($query) < 2) {
            return [
                'status' => true,
                'data' => []
            ];
        }

        if (empty($this->apiKey)) {
            Log::warning('SaudiAddressProService: SAUDI_ADDRESS_PRO_API_KEY is not configured in .env');
            return [
                'status' => false,
                'message' => 'Saudi Address Pro service is not configured.',
                'data' => []
            ];
        }

        $cacheKey = 'saudi_address_search_' . md5($query) . "_{$language}_{$limit}";

        return Cache::remember($cacheKey, 86400, function () use ($query, $language, $limit) {
            try {
                $response = Http::timeout(6)
                    ->withHeaders([
                        'X-API-Key' => $this->apiKey,
                    ])
                    ->get("{$this->baseUrl}/address/search", [
                        'q' => $query,
                        'language' => $language,
                        'limit' => $limit,
                    ]);

                if ($response->successful()) {
                    $results = $response->json();
                    return [
                        'status' => true,
                        'data' => is_array($results) ? $results : ($results['data'] ?? []),
                    ];
                }

                Log::warning('SaudiAddressPro search failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query' => $query,
                ]);

                return [
                    'status' => false,
                    'message' => 'Search failed',
                    'data' => []
                ];
            } catch (Exception $e) {
                Log::error('SaudiAddressPro search exception: ' . $e->getMessage(), [
                    'query' => $query,
                ]);

                return [
                    'status' => false,
                    'message' => 'Search service temporarily unavailable',
                    'data' => []
                ];
            }
        });
    }
}
