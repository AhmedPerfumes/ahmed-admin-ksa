<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrackerEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TrackerController extends Controller
{
    /**
     * Batch collection endpoint for standalone custom analytics tracker.
     */
    public function collect(Request $request)
    {
        $payload = $request->all();

        // Support both { "events": [...] } and direct array of events
        $events = isset($payload['events']) && is_array($payload['events'])
            ? $payload['events']
            : (is_array($payload) && isset($payload[0]) ? $payload : []);

        if (empty($events)) {
            return response()->json(['status' => 'error', 'message' => 'No events provided'], 422);
        }

        if (count($events) > 15) {
            return response()->json(['status' => 'error', 'message' => 'Batch size exceeds maximum limit of 15 events'], 422);
        }

        $serverIp = $request->ip();
        $serverAgent = substr($request->userAgent() ?? '', 0, 500);
        $now = Carbon::now();

        $rowsToInsert = [];
        foreach ($events as $event) {
            if (empty($event['event_name']) || empty($event['session_id']) || empty($event['visitor_id']) || !is_string($event['event_name']) || strlen($event['event_name']) > 50) {
                continue;
            }

            $eventData = isset($event['event_data']) && is_array($event['event_data'])
                ? json_encode($event['event_data'])
                : (is_string($event['event_data'] ?? null) ? $event['event_data'] : null);

            $clientIp = !empty($event['ip_address']) ? $event['ip_address'] : $serverIp;
            $location = $this->resolveLocation($clientIp, $event['country'] ?? null, $event['city'] ?? null);

            $pageUrl = $event['page_url'] ?? '';
            $referrerUrl = $event['referrer'] ?? '';
            
            // Extract URL parameters for Google Ads & Organic fallbacks
            $urlParams = [];
            if ($pageUrl && str_contains($pageUrl, '?')) {
                parse_str(parse_url($pageUrl, PHP_URL_QUERY) ?? '', $urlParams);
            }

            $utmSource   = !empty($event['utm_source']) ? $event['utm_source'] : null;
            $utmMedium   = !empty($event['utm_medium']) ? $event['utm_medium'] : null;
            $utmCampaign = !empty($event['utm_campaign']) ? $event['utm_campaign'] : null;

            // 1. Google Ads Auto-Attribution (gclid / gad_source / gad_campaignid)
            if (!$utmSource && (!empty($urlParams['gclid']) || !empty($urlParams['gad_source']) || !empty($urlParams['gad_campaignid']))) {
                $utmSource = 'google';
            }
            if (!$utmMedium) {
                $gad = $urlParams['gad_source'] ?? null;
                if ($gad === '1' || (!$gad && !empty($urlParams['gclid']))) {
                    $utmMedium = 'cpc'; // Google Paid Search / Shopping Sponsored
                } elseif ($gad === '2') {
                    $utmMedium = 'video'; // YouTube Video Ads
                } elseif ($gad === '5') {
                    $utmMedium = 'display'; // Google Display Network Banner Ads
                } elseif ($gad === '6') {
                    $utmMedium = 'shopping'; // Google Shopping Feed
                }
            }
            if (!$utmCampaign && !empty($urlParams['gad_campaignid'])) {
                $utmCampaign = $urlParams['gad_campaignid'];
            }

            // 2. Organic Search Auto-Attribution (e.g. google.com referrer without ad click)
            if (!$utmSource && $referrerUrl) {
                $refHost = parse_url($referrerUrl, PHP_URL_HOST) ?? '';
                if (str_contains($refHost, 'google.')) {
                    $utmSource = 'google';
                    $utmMedium = 'organic';
                } elseif (str_contains($refHost, 'bing.')) {
                    $utmSource = 'bing';
                    $utmMedium = 'organic';
                }
            }

            $eventName = substr($event['event_name'], 0, 50);

            $customerPhone = !empty($event['customer_phone']) ? $event['customer_phone'] : null;
            if (!$customerPhone && !empty($event['event_data']) && is_array($event['event_data'])) {
                $customerPhone = $event['event_data']['phone'] ?? $event['event_data']['customer_phone'] ?? null;
            }
            if ($customerPhone) {
                $customerPhone = preg_replace('/[^\d]/', '', $customerPhone);
                if (strlen($customerPhone) > 20) {
                    $customerPhone = substr($customerPhone, 0, 20);
                }
            }

            $row = [
                'event_name'    => $eventName,
                'event_data'    => $eventData,
                'session_id'    => substr($event['session_id'], 0, 64),
                'visitor_id'    => substr($event['visitor_id'], 0, 64),
                'customer_id'   => !empty($event['customer_id']) ? intval($event['customer_id']) : null,
                'customer_phone'=> !empty($customerPhone) ? $customerPhone : null,
                'page_url'     => substr($pageUrl, 0, 500),
                'page_title'   => !empty($event['page_title']) ? substr($event['page_title'], 0, 300) : null,
                'referrer'     => !empty($referrerUrl) ? substr($referrerUrl, 0, 500) : null,
                'utm_source'   => !empty($utmSource) ? substr($utmSource, 0, 255) : null,
                'utm_medium'   => !empty($utmMedium) ? substr($utmMedium, 0, 255) : null,
                'utm_campaign' => !empty($utmCampaign) ? substr($utmCampaign, 0, 255) : null,
                'utm_term'     => !empty($event['utm_term']) ? substr($event['utm_term'], 0, 255) : null,
                'utm_content'  => !empty($event['utm_content']) ? substr($event['utm_content'], 0, 255) : null,
                'device_type'  => !empty($event['device_type']) ? substr($event['device_type'], 0, 20) : null,
                'browser'      => !empty($event['browser']) ? substr($event['browser'], 0, 100) : null,
                'os'           => !empty($event['os']) ? substr($event['os'], 0, 100) : null,
                'screen_width' => isset($event['screen_width']) ? intval($event['screen_width']) : null,
                'screen_height'=> isset($event['screen_height']) ? intval($event['screen_height']) : null,
                'language'     => !empty($event['language']) ? substr($event['language'], 0, 50) : null,
                'country'      => substr($location['country'], 0, 10),
                'city'         => substr($location['city'], 0, 100),
                'currency'     => !empty($event['currency']) ? substr($event['currency'], 0, 10) : null,
                'ip_address'   => substr($clientIp, 0, 45),
                'user_agent'   => !empty($event['user_agent']) ? substr($event['user_agent'], 0, 500) : $serverAgent,
                'revenue'      => ($eventName === 'purchase' && isset($event['revenue'])) ? floatval($event['revenue']) : null,
                'created_at'   => !empty($event['created_at']) ? Carbon::parse($event['created_at'])->setTimezone(config('app.timezone')) : $now,
            ];

            // Deduplication Check & True Order Date Sync for Purchase Events
            if ($eventName === 'purchase') {
                $eData = is_array($event['event_data'] ?? null) ? $event['event_data'] : (json_decode($eventData, true) ?? []);
                $orderId = $eData['order_id'] ?? $eData['order_number'] ?? null;

                if (!empty($orderId)) {
                    $cleanOrderId = ltrim($orderId, '#');

                    // 1. Fetch true order creation date from ec_orders table to attribute revenue to actual purchase date
                    try {
                        $actualOrder = \Illuminate\Support\Facades\DB::table('ec_orders')
                            ->where('id', intval($cleanOrderId))
                            ->orWhere('code', $orderId)
                            ->orWhere('code', '#' . $cleanOrderId)
                            ->select('created_at')
                            ->first();

                        if ($actualOrder && !empty($actualOrder->created_at)) {
                            $row['created_at'] = Carbon::parse($actualOrder->created_at)->setTimezone(config('app.timezone'));
                        }
                    } catch (\Exception $e) {
                        // ignore DB query error if table schema differs
                    }
                    
                    // 2. Search for existing purchase event with this order_id
                    $existingEvent = TrackerEvent::where('event_name', 'purchase')
                        ->where(function($q) use ($orderId, $cleanOrderId) {
                            $q->where('event_data', 'LIKE', '%"order_id":"' . $orderId . '"%')
                              ->orWhere('event_data', 'LIKE', '%"order_id":"#' . $cleanOrderId . '"%')
                              ->orWhere('event_data', 'LIKE', '%"order_id":"' . $cleanOrderId . '"%');
                        })
                        ->first();

                    if ($existingEvent) {
                        // Update existing entry with the latest event payload & correct order timestamp
                        $existingEvent->update($row);
                        continue;
                    }
                }
            }

            $rowsToInsert[] = $row;
        }

        if (!empty($rowsToInsert)) {
            TrackerEvent::insert($rowsToInsert);
        }

        return response()->json([
            'status' => 'success',
            'inserted' => count($rowsToInsert),
        ]);
    }

    /**
     * Resolve Country & City from IP address or provided parameters.
     */
    private function resolveLocation(?string $ip, ?string $providedCountry, ?string $providedCity): array
    {
        $country = !empty($providedCountry) ? strtoupper($providedCountry) : null;
        $city = !empty($providedCity) ? ucfirst($providedCity) : null;

        if ($country && $city) {
            return ['country' => $country, 'city' => $city];
        }

        if (empty($ip) || in_array($ip, ['127.0.0.1', '::1', 'localhost']) || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return [
                'country' => $country ?: 'Local',
                'city'    => $city ?: 'Testing Server',
            ];
        }

        $cacheKey = "ip_geo_{$ip}";
        return cache()->remember($cacheKey, 86400, function () use ($ip, $country, $city) {
            try {
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=countryCode,country,city");
                if ($response->ok()) {
                    $data = $response->json();
                    return [
                        'country' => $country ?: ($data['countryCode'] ?? 'Local'),
                        'city'    => $city ?: ($data['city'] ?? 'Testing Server'),
                    ];
                }
            } catch (\Exception $e) {}

            return [
                'country' => $country ?: 'Local',
                'city'    => $city ?: 'Testing Server',
            ];
        });
    }

    /**
     * Endpoint for Cloudways Cron curl to trigger tracker:aggregate command
     */
    public function aggregateStats()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('tracker:aggregate');
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'status'  => 'success',
                'message' => 'Tracker stats aggregated successfully.',
                'output'  => trim($output),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to aggregate tracker stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint for Cloudways Cron curl to trigger analytics:cart-recovery command
     */
    public function runCartRecovery()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('analytics:cart-recovery');
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'status'  => 'success',
                'message' => 'Cart recovery process executed successfully.',
                'output'  => trim($output),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to run cart recovery: ' . $e->getMessage(),
            ], 500);
        }
    }
}
