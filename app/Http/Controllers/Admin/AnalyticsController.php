<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrackerEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('analytics.dashboard'), 403);

        $startDateStr = $request->get('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDateStr = $request->get('end_date', Carbon::now()->format('Y-m-d'));

        try {
            $startDate = Carbon::parse($startDateStr)->startOfDay();
        } catch (\Exception $e) {
            $startDate = Carbon::now()->subDays(7)->startOfDay();
            $startDateStr = $startDate->format('Y-m-d');
        }

        try {
            $endDate = Carbon::parse($endDateStr)->endOfDay();
        } catch (\Exception $e) {
            $endDate = Carbon::now()->endOfDay();
            $endDateStr = $endDate->format('Y-m-d');
        }

        $daysDiff = $startDate->diffInDays($endDate);
        $dateFormat = ($daysDiff <= 1) ? '%H:00' : '%Y-%m-%d';

        $eventsQuery = TrackerEvent::whereBetween('created_at', [$startDate, $endDate]);

        // 1. KPI Cards
        $totalRevenue = (clone $eventsQuery)->where('event_name', 'purchase')->sum('revenue') ?? 0;
        $uniqueVisitors = (clone $eventsQuery)->distinct('visitor_id')->count('visitor_id');
        $uniqueCustomers = (clone $eventsQuery)->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id');
        $purchaseVisitors = (clone $eventsQuery)->where('event_name', 'purchase')->distinct('visitor_id')->count('visitor_id');
        $addToCartVisitors = (clone $eventsQuery)->where('event_name', 'add_to_cart')->distinct('visitor_id')->count('visitor_id');

        $conversionRate = $uniqueVisitors > 0 ? round(($purchaseVisitors / $uniqueVisitors) * 100, 2) : 0;
        $cartAbandonmentRate = $addToCartVisitors > 0 
            ? round((max(0, $addToCartVisitors - $purchaseVisitors) / $addToCartVisitors) * 100, 2) 
            : 0;

        // 2. Revenue Trend Line Chart
        $revenueTrendData = (clone $eventsQuery)
            ->where('event_name', 'purchase')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as date_key"),
                DB::raw("SUM(revenue) as total")
            )
            ->groupBy('date_key')
            ->orderBy('date_key', 'ASC')
            ->pluck('total', 'date_key')
            ->toArray();

        // 3. Conversion Funnel
        $funnelEvents = ['page_view', 'view_item', 'add_to_cart', 'begin_checkout', 'purchase'];
        $funnelData = [];
        foreach ($funnelEvents as $evt) {
            $funnelData[$evt] = (clone $eventsQuery)->where('event_name', $evt)->distinct('visitor_id')->count('visitor_id');
        }

        // 4. Top 10 Viewed Products (MySQL Native JSON Aggregation)
        $topProducts = (clone $eventsQuery)
            ->where('event_name', 'view_item')
            ->whereNotNull('event_data')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.product_name')) as name, COUNT(*) as total")
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'name')
            ->toArray();

        // 5. Traffic Sources (Grouped purely by unique external referrers, null/internal marked as Direct)
        $rawSources = (clone $eventsQuery)
            ->select('referrer', DB::raw('COUNT(DISTINCT session_id) as count'))
            ->groupBy('referrer')
            ->get();

        $trafficSourcesMap = [];
        foreach ($rawSources as $row) {
            $ref = $row->referrer;
            $sourceName = 'Direct';

            if (!empty($ref)) {
                $host = parse_url($ref, PHP_URL_HOST);
                if ($host && !str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1') && !str_contains($host, 'ahmedalmaghribi.com')) {
                    // Normalize domain (e.g. www.google.com -> google.com, l.instagram.com -> instagram.com)
                    $cleanHost = preg_replace('/^www\./i', '', $host);
                    if (str_contains($cleanHost, 'instagram.')) {
                        $cleanHost = 'instagram.com';
                    } elseif (str_contains($cleanHost, 'facebook.')) {
                        $cleanHost = 'facebook.com';
                    } elseif (str_contains($cleanHost, 'google.')) {
                        $cleanHost = 'google.com';
                    } elseif (str_contains($cleanHost, 'tiktok.')) {
                        $cleanHost = 'tiktok.com';
                    }
                    $sourceName = $cleanHost;
                }
            }

            $trafficSourcesMap[$sourceName] = ($trafficSourcesMap[$sourceName] ?? 0) + $row->count;
        }

        arsort($trafficSourcesMap);
        $trafficSources = array_slice($trafficSourcesMap, 0, 7, true);

        // 6. Device Breakdown (Grouped by unique sessions)
        $deviceBreakdown = (clone $eventsQuery)
            ->select(DB::raw("COALESCE(device_type, 'desktop') as device"), DB::raw('COUNT(DISTINCT session_id) as count'))
            ->groupBy('device')
            ->pluck('count', 'device')
            ->toArray();

        // 7. Top Locations
        $topLocations = (clone $eventsQuery)
            ->select(DB::raw("CONCAT(COALESCE(city, 'Testing Server'), ', ', COALESCE(country, 'Local')) as location"), DB::raw('COUNT(DISTINCT session_id) as count'))
            ->groupBy('location')
            ->orderBy('count', 'DESC')
            ->limit(7)
            ->pluck('count', 'location')
            ->toArray();

        // 8. Top 10 Bought Products
        $rawPurchases = (clone $eventsQuery)
            ->where('event_name', 'purchase')
            ->whereNotNull('event_data')
            ->get();

        $boughtProductsCount = [];
        foreach ($rawPurchases as $purchase) {
            $data = is_array($purchase->event_data) ? $purchase->event_data : json_decode($purchase->event_data, true);
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $pName = $item['product_name'] ?? $item['name'] ?? null;
                    $qty = intval($item['quantity'] ?? $item['qty'] ?? 1);
                    if ($pName) {
                        $boughtProductsCount[$pName] = ($boughtProductsCount[$pName] ?? 0) + $qty;
                    }
                }
            }
        }
        arsort($boughtProductsCount);
        $topBoughtProducts = array_slice($boughtProductsCount, 0, 10, true);

        // 9. Count of Payment Types (Completed Orders Only)
        $rawPaymentEvents = (clone $eventsQuery)
            ->where('event_name', 'purchase')
            ->whereNotNull('event_data')
            ->get();

        $paymentTypesCount = [];
        foreach ($rawPaymentEvents as $evt) {
            $data = is_array($evt->event_data) ? $evt->event_data : json_decode($evt->event_data, true);
            $type = $data['payment_type'] ?? $data['payment_method'] ?? $data['payment_mode'] ?? null;
            if ($type) {
                $formattedType = match(strtolower($type)) {
                    'cod', 'cash_on_delivery' => 'Cash on Delivery (COD)',
                    'card', 'credit_card', 'stripe', 'checkout' => 'Credit / Debit Card',
                    'tabby' => 'Tabby (Pay Later)',
                    'tamara' => 'Tamara (Installments)',
                    'apple_pay', 'applepay' => 'Apple Pay',
                    'stc_pay', 'stcpay' => 'STC Pay',
                    default => ucfirst($type)
                };
                $paymentTypesCount[$formattedType] = ($paymentTypesCount[$formattedType] ?? 0) + 1;
            }
        }
        arsort($paymentTypesCount);

        // 10. Top 10 Most Added to Cart Products (MySQL Native JSON Aggregation)
        $topCartProducts = (clone $eventsQuery)
            ->where('event_name', 'add_to_cart')
            ->whereNotNull('event_data')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.product_name')) as name, SUM(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.quantity')) AS UNSIGNED), 1)) as total")
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'name')
            ->toArray();

        // 11. Products Currently in Carts Right Now (Active Unpurchased Carts - Scoped to 48 hours for high-scale performance)
        $activeCartLookback = Carbon::now()->subHours(48);
        $cartEvents = TrackerEvent::where('created_at', '>=', $activeCartLookback)
            ->whereIn('event_name', ['add_to_cart', 'remove_from_cart', 'purchase'])
            ->whereNotNull('event_data')
            ->select('session_id', 'visitor_id', 'event_name', 'event_data', 'created_at')
            ->orderBy('created_at', 'ASC')
            ->get();

        $sessionCarts = [];
        $sessionPurchased = [];

        foreach ($cartEvents as $event) {
            $sessId = $event->session_id ?: $event->visitor_id;
            $evtName = $event->event_name;
            $data = is_array($event->event_data) ? $event->event_data : json_decode($event->event_data, true);

            if ($evtName === 'purchase') {
                $sessionPurchased[$sessId] = true;
                unset($sessionCarts[$sessId]); // Order completed, empty cart
                continue;
            }

            if (!empty($sessionPurchased[$sessId])) {
                continue; // Ignore post-purchase modifications for this session
            }

            if (is_array($data) && !empty($data['product_name'])) {
                $pName = $data['product_name'];
                $qty = isset($data['quantity']) ? intval($data['quantity']) : 1;

                if (!isset($sessionCarts[$sessId])) {
                    $sessionCarts[$sessId] = [];
                }

                if ($evtName === 'add_to_cart') {
                    $sessionCarts[$sessId][$pName] = ($sessionCarts[$sessId][$pName] ?? 0) + $qty;
                } elseif ($evtName === 'remove_from_cart') {
                    $sessionCarts[$sessId][$pName] = max(0, ($sessionCarts[$sessId][$pName] ?? 0) - $qty);
                    if ($sessionCarts[$sessId][$pName] === 0) {
                        unset($sessionCarts[$sessId][$pName]);
                    }
                }
            }
        }

        $activeProductsInCarts = [];
        foreach ($sessionCarts as $sessId => $items) {
            foreach ($items as $pName => $qty) {
                if ($qty > 0) {
                    $activeProductsInCarts[$pName] = ($activeProductsInCarts[$pName] ?? 0) + $qty;
                }
            }
        }
        arsort($activeProductsInCarts);
        $topActiveCartProducts = array_slice($activeProductsInCarts, 0, 10, true);

        // 12. Live Recent Events (Displaying latest event for top 50 distinct visitor IDs)
        $selectedEventFilter = $request->get('event_filter', 'all');
        $latestVisitorIdsQuery = (clone $eventsQuery)
            ->select('visitor_id', DB::raw('MAX(id) as latest_event_id'))
            ->groupBy('visitor_id');

        if ($selectedEventFilter && $selectedEventFilter !== 'all') {
            $latestVisitorIdsQuery->where('event_name', $selectedEventFilter);
        }

        $latestEventIds = $latestVisitorIdsQuery
            ->orderByDesc('latest_event_id')
            ->limit(50)
            ->pluck('latest_event_id')
            ->toArray();

        $recentEvents = !empty($latestEventIds)
            ? TrackerEvent::whereIn('id', $latestEventIds)->orderByDesc('id')->get()
            : collect([]);

        $availableEvents = (clone $eventsQuery)->distinct()->pluck('event_name')->sort()->values()->toArray();

        // 13. Abandoned Cart Recovery (Sessions with unpurchased items abandoned >= 30 mins ago, max 7-day actionable window)
        $thresholdTime = Carbon::now()->subMinutes(30);
        $abandonedLookback = Carbon::now()->subDays(7);
        $recoveryCartEvents = TrackerEvent::where('created_at', '>=', $abandonedLookback)
            ->whereIn('event_name', ['add_to_cart', 'remove_from_cart', 'purchase', 'begin_checkout', 'add_payment_info', 'verify_otp'])
            ->select('session_id', 'visitor_id', 'customer_id', 'customer_phone', 'event_name', 'event_data', 'city', 'country', 'device_type', 'os', 'created_at')
            ->orderBy('created_at', 'ASC')
            ->get();

        $sessionCartMap = [];
        $purchasedSessions = [];

        foreach ($recoveryCartEvents as $evt) {
            $sessId = $evt->session_id;
            $evtName = $evt->event_name;
            $data = is_array($evt->event_data) ? $evt->event_data : (json_decode($evt->event_data, true) ?? []);

            if ($evtName === 'purchase') {
                $purchasedSessions[$sessId] = true;
                unset($sessionCartMap[$sessId]);
                continue;
            }

            if (!isset($sessionCartMap[$sessId])) {
                $sessionCartMap[$sessId] = [
                    'session_id' => $sessId,
                    'visitor_id' => $evt->visitor_id,
                    'customer_id' => $evt->customer_id,
                    'customer_email' => null,
                    'customer_name' => null,
                    'customer_phone' => null,
                    'city' => $evt->city,
                    'country' => $evt->country,
                    'device_type' => $evt->device_type,
                    'os' => $evt->os,
                    'last_activity' => $evt->created_at,
                    'items' => [],
                ];
            }

            if ($evt->created_at > $sessionCartMap[$sessId]['last_activity']) {
                $sessionCartMap[$sessId]['last_activity'] = $evt->created_at;
            }

            if ($evt->customer_id && !$sessionCartMap[$sessId]['customer_id']) {
                $sessionCartMap[$sessId]['customer_id'] = $evt->customer_id;
            }

            if (!empty($data['email'])) {
                $sessionCartMap[$sessId]['customer_email'] = $data['email'];
            }
            if (!empty($data['customer_email'])) {
                $sessionCartMap[$sessId]['customer_email'] = $data['customer_email'];
            }
            if (!empty($data['name']) || !empty($data['customer_name'])) {
                $sessionCartMap[$sessId]['customer_name'] = $data['name'] ?? $data['customer_name'];
            }
            if (!empty($data['phone']) || !empty($data['customer_phone'])) {
                $sessionCartMap[$sessId]['customer_phone'] = $data['phone'] ?? $data['customer_phone'];
            }
            if (!empty($evt->customer_phone)) {
                $sessionCartMap[$sessId]['customer_phone'] = $evt->customer_phone;
            }

            $pName = !empty($data['product_name']) ? html_entity_decode($data['product_name']) : null;
            $price = floatval($data['price'] ?? 0);
            $qty = intval($data['quantity'] ?? $data['qty'] ?? 1);

            if ($pName) {
                if (!isset($sessionCartMap[$sessId]['items'][$pName])) {
                    $sessionCartMap[$sessId]['items'][$pName] = [
                        'name' => $pName,
                        'qty' => 0,
                        'price' => $price,
                    ];
                }
                if ($evtName === 'add_to_cart') {
                    $sessionCartMap[$sessId]['items'][$pName]['qty'] += $qty;
                    if ($price > 0) {
                        $sessionCartMap[$sessId]['items'][$pName]['price'] = $price;
                    }
                } elseif ($evtName === 'remove_from_cart') {
                    $sessionCartMap[$sessId]['items'][$pName]['qty'] = max(0, $sessionCartMap[$sessId]['items'][$pName]['qty'] - $qty);
                    if ($sessionCartMap[$sessId]['items'][$pName]['qty'] === 0) {
                        unset($sessionCartMap[$sessId]['items'][$pName]);
                    }
                }
            }
        }

        // Fetch already sent recovery email & SMS timestamps
        $sentEmails = TrackerEvent::where('event_name', 'recovery_email_sent')
            ->pluck('created_at', 'session_id')
            ->toArray();

        $sentSmsLogs = \App\Models\TrackerRecoveryLog::pluck('created_at', 'session_id')
            ->toArray();

        // Fetch all email recovery purchase events
        $recoveryPurchases = (clone $eventsQuery)
            ->where('event_name', 'purchase')
            ->where(function($q) {
                $q->where('utm_source', 'email_recovery')
                  ->orWhere('event_data', 'LIKE', '%recovery_session_id%');
            })
            ->get();

        $convertedMap = [];
        $totalRecoveredRevenue = 0;
        $totalRecoveredOrders = 0;

        foreach ($recoveryPurchases as $purch) {
            $pData = is_array($purch->event_data) ? $purch->event_data : (json_decode($purch->event_data, true) ?? []);
            $recSessId = $pData['recovery_session_id'] ?? $purch->session_id;
            $orderId = $pData['order_id'] ?? ('#' . $purch->id);
            $rev = floatval($pData['total'] ?? $pData['revenue'] ?? $purch->revenue ?? 0);

            $convertedMap[$recSessId] = [
                'order_id' => $orderId,
                'revenue' => $rev,
                'converted_at' => $purch->created_at->diffForHumans(),
            ];
            $totalRecoveredRevenue += $rev;
            $totalRecoveredOrders++;
        }

        $recoveredStats = [
            'count' => $totalRecoveredOrders,
            'revenue' => $totalRecoveredRevenue
        ];

        // Also check if any sessions in $sessionCartMap converted (even if unpurchased in initial pass)
        $abandonedCartSessions = [];
        foreach ($sessionCartMap as $sessId => $info) {
            $isConverted = isset($convertedMap[$sessId]);

            // If session was converted via recovery email OR (not purchased & abandoned >= 30m)
            if ($isConverted || (!isset($purchasedSessions[$sessId]))) {
                $totalItemsCount = 0;
                $totalCartValue = 0;
                $itemsList = [];
                foreach ($info['items'] as $item) {
                    if ($item['qty'] > 0) {
                        $totalItemsCount += $item['qty'];
                        $totalCartValue += ($item['qty'] * $item['price']);
                        $itemsList[] = $item;
                    }
                }

                if ($isConverted || ($totalItemsCount > 0 && $info['last_activity'] <= $thresholdTime)) {
                    if ($info['customer_id'] && !$info['customer_email']) {
                        try {
                            $customer = DB::table('ec_customers')->where('id', $info['customer_id'])->first();
                            if ($customer) {
                                $info['customer_email'] = $customer->email ?? null;
                                $info['customer_name'] = $info['customer_name'] ?: ($customer->name ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')));
                                $info['customer_phone'] = $info['customer_phone'] ?: ($customer->phone ?? null);
                            }
                        } catch (\Exception $e) {}
                    }

                    if (!empty($info['customer_email']) || !empty($info['customer_phone'])) {
                        $info['items_list'] = $itemsList;
                        $info['total_items_count'] = $totalItemsCount;
                        $info['total_cart_value'] = $totalCartValue;
                        $info['abandoned_ago'] = $info['last_activity']->diffForHumans();
                        $info['email_sent_at'] = isset($sentEmails[$sessId]) ? Carbon::parse($sentEmails[$sessId])->diffForHumans() : null;
                        $info['sms_sent_at'] = isset($sentSmsLogs[$sessId]) ? Carbon::parse($sentSmsLogs[$sessId])->diffForHumans() : null;
                        $info['is_converted'] = $isConverted;
                        $info['conversion_details'] = $convertedMap[$sessId] ?? null;
                        $abandonedCartSessions[] = $info;
                    }
                }
            }
        }

        usort($abandonedCartSessions, fn($a, $b) => $b['last_activity'] <=> $a['last_activity']);

        // 14. Phase 1 A: UTM Campaign Performance Breakdown
        $utmEvents = (clone $eventsQuery)
            ->select(
                DB::raw("COALESCE(NULLIF(utm_source, ''), 'direct') as source"),
                DB::raw("COALESCE(NULLIF(utm_medium, ''), 'none') as medium"),
                DB::raw("COALESCE(NULLIF(utm_campaign, ''), 'None') as campaign"),
                DB::raw("COUNT(DISTINCT visitor_id) as visitors"),
                DB::raw("SUM(CASE WHEN event_name = 'purchase' THEN 1 ELSE 0 END) as purchases"),
                DB::raw("COALESCE(SUM(CASE WHEN event_name = 'purchase' THEN revenue ELSE 0 END), 0) as revenue")
            )
            ->groupBy('source', 'medium', 'campaign')
            ->orderBy('revenue', 'DESC')
            ->get();

        $campaignRoiStats = [];
        foreach ($utmEvents as $row) {
            $visitors = intval($row->visitors);
            $purchases = intval($row->purchases);
            $revenue = floatval($row->revenue);
            $aov = $purchases > 0 ? ($revenue / $purchases) : 0;
            $cvr = $visitors > 0 ? (($purchases / $visitors) * 100) : 0;

            $campaignRoiStats[] = [
                'source' => $row->source,
                'medium' => $row->medium,
                'campaign' => $row->campaign,
                'visitors' => $visitors,
                'purchases' => $purchases,
                'revenue' => $revenue,
                'aov' => $aov,
                'conversion_rate' => $cvr,
                'rev_percent' => $totalRevenue > 0 ? round(($revenue / $totalRevenue) * 100, 1) : 0,
            ];
        }

        // 15. Phase 1 B: First-Touch vs. Last-Touch Attribution Matrix
        $purchasingEvents = (clone $eventsQuery)
            ->where('event_name', 'purchase')
            ->select('visitor_id', 'revenue', 'utm_source', 'referrer')
            ->get();

        $attributionMap = [];
        foreach ($purchasingEvents as $pEvt) {
            $vId = $pEvt->visitor_id;
            $rev = floatval($pEvt->revenue);

            // Last-Touch Source
            $lastTouchSource = !empty($pEvt->utm_source) ? strtolower($pEvt->utm_source) : 'direct';

            // First-Touch Source (earliest event recorded for this visitor)
            $firstEvt = TrackerEvent::where('visitor_id', $vId)
                ->orderBy('created_at', 'ASC')
                ->first(['utm_source', 'referrer']);

            $firstTouchSource = 'direct';
            if ($firstEvt) {
                if (!empty($firstEvt->utm_source)) {
                    $firstTouchSource = strtolower($firstEvt->utm_source);
                } elseif (!empty($firstEvt->referrer)) {
                    $host = parse_url($firstEvt->referrer, PHP_URL_HOST);
                    if ($host) {
                        $cleanHost = preg_replace('/^www\./i', '', $host);
                        $firstTouchSource = match(true) {
                            str_contains($cleanHost, 'google') => 'google',
                            str_contains($cleanHost, 'instagram') => 'instagram',
                            str_contains($cleanHost, 'facebook') => 'facebook',
                            str_contains($cleanHost, 'tiktok') => 'tiktok',
                            str_contains($cleanHost, 'snapchat') => 'snapchat',
                            default => $cleanHost
                        };
                    }
                }
            }

            // Init First Touch Map
            if (!isset($attributionMap[$firstTouchSource])) {
                $attributionMap[$firstTouchSource] = [
                    'channel' => ucfirst($firstTouchSource),
                    'first_touch_orders' => 0,
                    'first_touch_revenue' => 0,
                    'last_touch_orders' => 0,
                    'last_touch_revenue' => 0,
                ];
            }
            $attributionMap[$firstTouchSource]['first_touch_orders'] += 1;
            $attributionMap[$firstTouchSource]['first_touch_revenue'] += $rev;

            // Init Last Touch Map
            if (!isset($attributionMap[$lastTouchSource])) {
                $attributionMap[$lastTouchSource] = [
                    'channel' => ucfirst($lastTouchSource),
                    'first_touch_orders' => 0,
                    'first_touch_revenue' => 0,
                    'last_touch_orders' => 0,
                    'last_touch_revenue' => 0,
                ];
            }
            $attributionMap[$lastTouchSource]['last_touch_orders'] += 1;
            $attributionMap[$lastTouchSource]['last_touch_revenue'] += $rev;
        }

        // Compute attribution roles / insights
        $attributionMatrix = [];
        foreach ($attributionMap as $chKey => $attData) {
            $ftRev = $attData['first_touch_revenue'];
            $ltRev = $attData['last_touch_revenue'];

            $role = '⚖️ Balanced Channel';
            $badgeClass = 'badge bg-dark text-info border border-info border-opacity-25';

            if ($ftRev > 0 && ($ltRev == 0 || $ftRev >= $ltRev * 1.25)) {
                $role = '🚀 Top Brand Discovery';
                $badgeClass = 'badge bg-dark text-warning border border-warning border-opacity-25';
            } elseif ($ltRev > 0 && ($ftRev == 0 || $ltRev >= $ftRev * 1.25)) {
                $role = '🎯 High Intent Closer';
                $badgeClass = 'badge bg-dark text-success border border-success border-opacity-25';
            }

            $attData['role'] = $role;
            $attData['role_badge'] = $badgeClass;
            $attributionMatrix[] = $attData;
        }

        usort($attributionMatrix, fn($a, $b) => ($b['first_touch_revenue'] + $b['last_touch_revenue']) <=> ($a['first_touch_revenue'] + $a['last_touch_revenue']));

        // 16. Phase 2 A: Product Drop-off & Interest-to-Cart Analyzer
        $productEvents = (clone $eventsQuery)
            ->whereIn('event_name', ['view_item', 'add_to_cart', 'purchase'])
            ->whereNotNull('event_data')
            ->get();

        $productStatsMap = [];
        foreach ($productEvents as $pEvt) {
            $evtName = $pEvt->event_name;
            $data = is_array($pEvt->event_data) ? $pEvt->event_data : (json_decode($pEvt->event_data, true) ?? []);

            if ($evtName === 'view_item' && !empty($data['product_name'])) {
                $pName = html_entity_decode($data['product_name']);
                if (!isset($productStatsMap[$pName])) {
                    $productStatsMap[$pName] = ['views' => 0, 'cart_adds' => 0, 'purchases' => 0];
                }
                $productStatsMap[$pName]['views'] += 1;
            } elseif ($evtName === 'add_to_cart' && !empty($data['product_name'])) {
                $pName = html_entity_decode($data['product_name']);
                $qty = intval($data['quantity'] ?? $data['qty'] ?? 1);
                if (!isset($productStatsMap[$pName])) {
                    $productStatsMap[$pName] = ['views' => 0, 'cart_adds' => 0, 'purchases' => 0];
                }
                $productStatsMap[$pName]['cart_adds'] += $qty;
            } elseif ($evtName === 'purchase' && !empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $pName = !empty($item['product_name']) ? html_entity_decode($item['product_name']) : (!empty($item['name']) ? html_entity_decode($item['name']) : null);
                    $qty = intval($item['quantity'] ?? $item['qty'] ?? 1);
                    if ($pName) {
                        if (!isset($productStatsMap[$pName])) {
                            $productStatsMap[$pName] = ['views' => 0, 'cart_adds' => 0, 'purchases' => 0];
                        }
                        $productStatsMap[$pName]['purchases'] += $qty;
                    }
                }
            }
        }

        $productFrictionStats = [];
        foreach ($productStatsMap as $pName => $counts) {
            $views = $counts['views'];
            $adds = $counts['cart_adds'];
            $bought = $counts['purchases'];

            if ($views == 0 && $adds == 0 && $bought == 0) continue;

            $cartRate = $views > 0 ? round(($adds / $views) * 100, 1) : ($adds > 0 ? 100 : 0);
            $buyRate = $views > 0 ? round(($bought / $views) * 100, 1) : ($bought > 0 ? 100 : 0);

            $status = '📊 Steady Performer';
            $badgeClass = 'badge bg-dark text-info border border-info border-opacity-25';

            if ($views >= 10 && $cartRate < 5.0) {
                $status = '⚠️ High Interest, Low Carting';
                $badgeClass = 'badge bg-dark text-danger border border-danger border-opacity-25';
            } elseif ($cartRate >= 15.0 || $buyRate >= 10.0) {
                $status = '⭐ Top Converting Star';
                $badgeClass = 'badge bg-dark text-success border border-success border-opacity-25';
            }

            $productFrictionStats[] = [
                'name' => $pName,
                'views' => $views,
                'cart_adds' => $adds,
                'purchases' => $bought,
                'cart_rate' => $cartRate,
                'buy_rate' => $buyRate,
                'status' => $status,
                'badge_class' => $badgeClass,
            ];
        }

        usort($productFrictionStats, fn($a, $b) => $b['views'] <=> $a['views']);
        $productFrictionStats = array_slice($productFrictionStats, 0, 15);

        // 17. Phase 2 B: Frequently Bought Together (Product Bundles Up to 5 Items)
        $purchaseEvents = (clone $eventsQuery)
            ->where('event_name', 'purchase')
            ->whereNotNull('event_data')
            ->get();

        $bundleCoOccurrence = [];
        foreach ($purchaseEvents as $pEvt) {
            $data = is_array($pEvt->event_data) ? $pEvt->event_data : (json_decode($pEvt->event_data, true) ?? []);
            $items = $data['items'] ?? [];
            if (!is_array($items) || count($items) < 2) continue;

            $distinctNames = [];
            foreach ($items as $itm) {
                $name = !empty($itm['product_name']) ? html_entity_decode($itm['product_name']) : (!empty($itm['name']) ? html_entity_decode($itm['name']) : null);
                if ($name && !in_array($name, $distinctNames)) {
                    $distinctNames[] = $name;
                }
            }

            $totalItems = count($distinctNames);
            if ($totalItems < 2) continue;

            // Cap bundle size to max 5 items per order combination
            $bundleSubset = array_slice($distinctNames, 0, 5);
            sort($bundleSubset);
            $bundleKey = implode(' + ', $bundleSubset);

            if (!isset($bundleCoOccurrence[$bundleKey])) {
                $bundleCoOccurrence[$bundleKey] = [
                    'items' => $bundleSubset,
                    'item_count' => count($bundleSubset),
                    'count' => 0,
                    'total_order_revenue' => 0,
                ];
            }
            $bundleCoOccurrence[$bundleKey]['count'] += 1;
            $bundleCoOccurrence[$bundleKey]['total_order_revenue'] += floatval($pEvt->revenue);
        }

        usort($bundleCoOccurrence, fn($a, $b) => $b['count'] <=> $a['count']);
        $productBundleStats = array_slice($bundleCoOccurrence, 0, 10);

        return view('analytics.dashboard', compact(
            'startDateStr',
            'endDateStr',
            'selectedEventFilter',
            'availableEvents',
            'totalRevenue',
            'uniqueVisitors',
            'uniqueCustomers',
            'conversionRate',
            'cartAbandonmentRate',
            'revenueTrendData',
            'funnelData',
            'topProducts',
            'topCartProducts',
            'topActiveCartProducts',
            'topBoughtProducts',
            'paymentTypesCount',
            'trafficSources',
            'deviceBreakdown',
            'topLocations',
            'recentEvents',
            'abandonedCartSessions',
            'recoveredStats',
            'campaignRoiStats',
            'attributionMatrix',
            'productFrictionStats',
            'productBundleStats'
        ));
    }

    /**
     * Send Abandoned Cart Recovery Email
     */
    public function sendRecoveryEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'session_id' => 'required|string',
            'items' => 'required|array',
        ]);

        $email = $request->get('email');
        $sessionId = $request->get('session_id');
        $items = $request->get('items');
        $customerName = $request->get('customer_name', 'Valued Customer');
        $cartValue = number_format(floatval($request->get('cart_value', 0)), 2);

        /*
        // OPTIONAL: Uncomment the lines below to automatically attach promo code 'review10'
        $couponCode = 'review10';
        $discountMessage = "Use coupon code <strong style='color:#b9a16b; font-size: 16px;'>" . $couponCode . "</strong> for a special 10% discount on your order!";
        */
        $couponCode = null;
        $discountMessage = null;

        try {
            $itemsHtml = '';
            foreach ($items as $item) {
                $name = htmlspecialchars($item['name'] ?? 'Perfume');
                $qty = intval($item['qty'] ?? 1);
                $price = number_format(floatval($item['price'] ?? 0), 2);
                $itemsHtml .= "
                    <tr>
                        <td style='padding: 12px; border-bottom: 1px solid #334155; color: #f8fafc;'>{$name}</td>
                        <td style='padding: 12px; border-bottom: 1px solid #334155; color: #cbd5e1; text-align: center;'>{$qty}</td>
                        <td style='padding: 12px; border-bottom: 1px solid #334155; color: #38bdf8; text-align: right;'>{$price} SAR</td>
                    </tr>";
            }

            $checkoutUrl = "https://ksa.ahmedalmaghribi.com/en/shop-cart?utm_source=email_recovery&utm_medium=email&utm_campaign=cart_recovery&recovery_session_id=" . urlencode($sessionId);

            $htmlContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <title>Complete Your Order - Ahmed Al Maghribi Perfumes</title>
            </head>
            <body style='font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; margin: 0; padding: 20px;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #1e293b; border-radius: 12px; padding: 30px; border: 1px solid #334155;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <h2 style='color: #b9a16b; margin: 0; font-size: 24px;'>AHMED AL MAGHRIBI PERFUMES</h2>
                        <p style='color: #94a3b8; font-size: 14px; margin-top: 5px;'>KSA Official Store</p>
                    </div>
                    
                    <h3 style='color: #ffffff;'>Hello {$customerName},</h3>
                    <p style='color: #cbd5e1; line-height: 1.6;'>You left some exquisite fragrances sitting in your shopping cart. Don't miss out on your favorite scents!</p>

                    " . ($discountMessage ? "<div style='background: rgba(185, 161, 107, 0.15); border: 1px solid #b9a16b; padding: 14px; border-radius: 8px; text-align: center; margin: 20px 0;'>{$discountMessage}</div>" : "") . "

                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <thead>
                            <tr style='background-color: #0f172a;'>
                                <th style='padding: 10px; text-align: left; color: #94a3b8; font-size: 12px; text-transform: uppercase;'>Item</th>
                                <th style='padding: 10px; text-align: center; color: #94a3b8; font-size: 12px; text-transform: uppercase;'>Qty</th>
                                <th style='padding: 10px; text-align: right; color: #94a3b8; font-size: 12px; text-transform: uppercase;'>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                        </tbody>
                    </table>

                    <div style='text-align: right; font-size: 16px; margin-bottom: 25px;'>
                        <strong>Total Cart Value: <span style='color: #10b981;'>{$cartValue} SAR</span></strong>
                    </div>

                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='{$checkoutUrl}' style='background-color: #b9a16b; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block;'>Complete My Purchase Now</a>
                    </div>

                    <div style='margin-top: 40px; border-top: 1px solid #334155; padding-top: 20px; text-align: center; font-size: 12px; color: #64748b;'>
                        <p>© " . date('Y') . " Ahmed Al Maghribi Perfumes KSA. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST', config('mail.mailers.smtp.host'));
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME', config('mail.mailers.smtp.username'));
            $mail->Password = env('MAIL_PASSWORD', config('mail.mailers.smtp.password'));
            
            $encryption = env('MAIL_ENCRYPTION', config('mail.mailers.smtp.encryption'));
            if ($encryption) {
                $mail->SMTPSecure = $encryption;
            }
            $mail->Port = intval(env('MAIL_PORT', config('mail.mailers.smtp.port', 587)));

            $fromAddress = config('mail.from.address') ?: env('MAIL_FROM_ADDRESS', 'info@ahmedalmaghribi.com');
            $fromName = config('mail.from.name') ?: env('MAIL_FROM_NAME', 'Ahmed Al Maghribi Perfumes');

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Complete Your Order - Items Waiting in Your Cart 🛒';
            $mail->Body = $htmlContent;

            $mail->send();

            // Log recovery_email_sent event in database to prevent duplicates & track status
            TrackerEvent::create([
                'event_name' => 'recovery_email_sent',
                'session_id' => $sessionId,
                'visitor_id' => $request->get('visitor_id', $sessionId),
                'page_url'   => 'https://ksa.ahmedalmaghribi.com/en/shop-cart',
                'event_data' => json_encode([
                    'email' => $email,
                    'customer_name' => $customerName,
                    'cart_value' => $cartValue,
                ]),
                'created_at' => Carbon::now(),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Recovery email sent successfully to ' . $email]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send Manual Abandoned Cart Recovery SMS
     */
    public function sendRecoverySms(Request $request, \App\Services\MyInboxMediaRecoveryService $recoveryService)
    {
        abort_unless(auth()->user()->hasPermission('analytics.dashboard'), 403);

        $request->validate([
            'phone' => 'required|string',
            'session_id' => 'required|string',
        ]);

        $phone = $request->get('phone');
        $sessionId = $request->get('session_id');

        $lastEvent = TrackerEvent::where('session_id', $sessionId)->orderByDesc('created_at')->first();
        $isEnglish = str_contains(strtolower($lastEvent->page_url ?? ''), '/en');
        $langCode  = $isEnglish ? 'en' : 'ar';
        $checkoutUrl = "https://ksa.ahmedalmaghribi.com/{$langCode}/shop-cart?utm_source=sms_recovery&utm_medium=sms&utm_campaign=cart_abandonment&recovery_session_id={$sessionId}";

        if ($isEnglish) {
            $message = "Dear Customer, items in your cart at Ahmed Al Maghribi Perfumes are waiting for you! 🛒 Complete your order now: {$checkoutUrl}";
        } else {
            $message = "عزيزي العميل، منتجاتك المفضلة في أحمد المغربي للعطور تنتظرك بالسلة! 🛒 أتمم طلبك الآن: {$checkoutUrl}";
        }

        $result = $recoveryService->sendRecoverySMS($phone, $message);

        \App\Models\TrackerRecoveryLog::create([
            'session_id'     => $sessionId,
            'phone'          => $phone,
            'channel'        => 'sms',
            'status'         => $result['success'] ? 'sent' : 'failed',
            'message_content'=> $message,
            'api_response'   => substr($result['response'] ?? '', 0, 1000),
            'created_at'     => Carbon::now(),
        ]);

        if ($result['success']) {
            return response()->json(['status' => 'success', 'message' => 'Recovery SMS sent successfully to ' . $phone]);
        }

        return response()->json(['status' => 'error', 'message' => 'Failed to send SMS: ' . ($result['response'] ?? 'Unknown error')], 500);
    }

    /**
     * Get Visitor Clickstream Journey Timeline
     */
    public function visitorJourney($visitorId)
    {
        abort_unless(auth()->user()->hasPermission('analytics.visitor-journey'), 403);

        $events = TrackerEvent::where('visitor_id', $visitorId)
            ->orderBy('created_at', 'ASC')
            ->get();

        if ($events->isEmpty()) {
            return response()->json(['error' => 'No session events found for this visitor.'], 404);
        }

        $formattedEvents = $events->map(function ($event) {
            $data = is_array($event->event_data) ? $event->event_data : (json_decode($event->event_data, true) ?? []);
            
            $detail = null;
            $icon = 'fa-circle-notch';
            $badgeColor = 'secondary';

            switch ($event->event_name) {
                case 'session_start':
                    $icon = 'fa-globe';
                    $badgeColor = 'info';
                    $ref = $event->referrer ? parse_url($event->referrer, PHP_URL_HOST) : 'Direct Traffic';
                    $location = ($event->city ? $event->city . ', ' : '') . ($event->country ?? 'KSA');
                    $detail = "Session started via " . ($ref ?: 'Direct') . " (" . $location . ")";
                    break;
                case 'page_view':
                    $icon = 'fa-eye';
                    $badgeColor = 'primary';
                    $path = parse_url($event->page_url, PHP_URL_PATH) ?? $event->page_url;
                    $detail = "Viewed page: " . ($path ?: '/');
                    break;
                case 'view_item':
                    $icon = 'fa-box';
                    $badgeColor = 'primary';
                    $name = !empty($data['product_name']) ? html_entity_decode($data['product_name']) : 'Product';
                    $detail = "Viewed product: " . $name;
                    break;
                case 'time_on_page':
                    $icon = 'fa-clock';
                    $badgeColor = 'warning';
                    $sec = intval($data['duration_seconds'] ?? $data['duration'] ?? $data['seconds'] ?? $data['time_seconds'] ?? $data['time'] ?? 0);
                    $duration = $sec >= 60 ? floor($sec / 60) . 'm ' . ($sec % 60) . 's' : $sec . 's';
                    $path = parse_url($event->page_url, PHP_URL_PATH) ?? $event->page_url;
                    $detail = "Spent " . $duration . " on " . ($path ?: 'page');
                    break;
                case 'scroll_depth':
                    $icon = 'fa-arrow-down';
                    $badgeColor = 'secondary';
                    $depth = $data['depth'] ?? $data['percent'] ?? '0';
                    $detail = "Scrolled " . $depth . "% of page";
                    break;
                case 'add_to_cart':
                    $icon = 'fa-shopping-cart';
                    $badgeColor = 'success';
                    $name = !empty($data['product_name']) ? html_entity_decode($data['product_name']) : 'Item';
                    $qty = $data['quantity'] ?? $data['qty'] ?? 1;
                    $detail = "Added to Cart: " . $name . " (Qty: " . $qty . ")";
                    break;
                case 'remove_from_cart':
                    $icon = 'fa-trash-alt';
                    $badgeColor = 'danger';
                    $name = !empty($data['product_name']) ? html_entity_decode($data['product_name']) : 'Item';
                    $detail = "Removed from Cart: " . $name;
                    break;
                case 'view_cart':
                    $icon = 'fa-shopping-bag';
                    $badgeColor = 'info';
                    $detail = "Viewed Shopping Cart";
                    break;
                case 'begin_checkout':
                    $icon = 'fa-credit-card';
                    $badgeColor = 'warning';
                    $detail = "Started Checkout Process";
                    break;
                case 'add_payment_info':
                    $icon = 'fa-lock';
                    $badgeColor = 'warning';
                    $detail = "Entered Payment Information";
                    break;
                case 'purchase':
                    $icon = 'fa-check-circle';
                    $badgeColor = 'success';
                    $orderId = $data['order_id'] ?? 'Order';
                    $total = number_format(floatval($data['total'] ?? $event->revenue), 2);
                    $payType = strtoupper($data['payment_type'] ?? 'CARD');
                    $detail = "Completed Purchase " . $orderId . " (" . $total . " SAR via " . $payType . ")";
                    break;
                default:
                    $detail = $event->event_name;
            }

            return [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'detail' => $detail,
                'icon' => $icon,
                'badge_color' => $badgeColor,
                'time_formatted' => $event->created_at ? $event->created_at->format('H:i:s') : '-',
                'date_formatted' => $event->created_at ? $event->created_at->format('Y-m-d') : '-',
                'created_at_human' => $event->created_at ? $event->created_at->diffForHumans() : '-',
                'page_url' => $event->page_url,
                'payload' => $data,
            ];
        });

        $firstEvent = $events->first();
        $lastEvent = $events->last();
        $totalPurchased = $events->where('event_name', 'purchase')->sum('revenue');
        $hasPurchased = $events->where('event_name', 'purchase')->count() > 0;

        return response()->json([
            'visitor_id' => $visitorId,
            'customer_id' => $firstEvent->customer_id,
            'device_type' => $firstEvent->device_type ?? 'desktop',
            'os' => $firstEvent->os ?? 'OS',
            'browser' => $firstEvent->browser ?? 'Browser',
            'country' => $firstEvent->country ?? 'SA',
            'city' => $firstEvent->city ?? 'Riyadh',
            'ip_address' => $firstEvent->ip_address,
            'first_seen' => $firstEvent->created_at ? $firstEvent->created_at->format('Y-m-d H:i:s') : '-',
            'last_seen' => $lastEvent->created_at ? $lastEvent->created_at->format('Y-m-d H:i:s') : '-',
            'total_events' => $events->count(),
            'total_spent' => number_format($totalPurchased, 2),
            'has_purchased' => $hasPurchased,
            'timeline' => $formattedEvents,
        ]);
    }

    /**
     * Load More Events for Live Event Stream Table (Pagination AJAX)
     */
    public function loadMoreEvents(Request $request)
    {
        $startDateStr = $request->get('start_date');
        $endDateStr = $request->get('end_date');
        $selectedEventFilter = $request->get('event_filter', 'all');
        $offset = intval($request->get('offset', 50));
        $limit = intval($request->get('limit', 50));

        $eventsQuery = TrackerEvent::query();

        if ($startDateStr && $endDateStr) {
            try {
                $startDate = Carbon::parse($startDateStr)->startOfDay();
                $endDate = Carbon::parse($endDateStr)->endOfDay();
                $eventsQuery->whereBetween('created_at', [$startDate, $endDate]);
            } catch (\Exception $e) {}
        }

        if ($selectedEventFilter && $selectedEventFilter !== 'all') {
            $eventsQuery->where('event_name', $selectedEventFilter);
        }

        $events = $eventsQuery->orderBy('created_at', 'DESC')
            ->skip($offset)
            ->take($limit)
            ->get();

        $formatted = $events->map(function ($event) {
            $badgeClass = match($event->event_name) {
                'purchase' => 'badge-event-purchase',
                'begin_checkout' => 'badge-event-begin_checkout',
                'add_payment_info' => 'badge-event-add_payment_info',
                'add_to_cart' => 'badge-event-add_to_cart',
                'remove_from_cart' => 'badge-event-remove_from_cart',
                'view_cart' => 'badge-event-view_cart',
                'view_item' => 'badge-event-view_item',
                'page_view' => 'badge-event-page_view',
                'time_on_page' => 'badge-event-time_on_page',
                'scroll_depth' => 'badge-event-scroll_depth',
                'session_start' => 'badge-event-session_start',
                default => 'badge-event-default'
            };

            $parsedUrl = parse_url($event->page_url, PHP_URL_PATH) ?? $event->page_url;
            $data = is_array($event->event_data) ? $event->event_data : (json_decode($event->event_data, true) ?? []);
            $eventDetail = null;

            if ($event->event_name === 'time_on_page') {
                $sec = intval($data['duration_seconds'] ?? $data['duration'] ?? $data['seconds'] ?? $data['time_seconds'] ?? $data['time'] ?? 0);
                $eventDetail = $sec >= 60 ? floor($sec / 60) . 'm ' . ($sec % 60) . 's' : $sec . 's';
            } elseif ($event->event_name === 'scroll_depth' && (isset($data['depth']) || isset($data['percent']))) {
                $eventDetail = ($data['depth'] ?? $data['percent']) . '%';
            } elseif (in_array($event->event_name, ['view_item', 'add_to_cart', 'remove_from_cart']) && !empty($data['product_name'])) {
                $eventDetail = $data['product_name'];
            } elseif ($event->event_name === 'purchase' && !empty($data['order_id'])) {
                $eventDetail = $data['order_id'];
            }

            return [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'badge_class' => $badgeClass,
                'event_detail' => $eventDetail,
                'page_url' => $event->page_url,
                'page_path' => $parsedUrl ?: '/',
                'visitor_id' => $event->visitor_id,
                'visitor_id_short' => substr($event->visitor_id, 0, 14),
                'customer_id' => $event->customer_id,
                'utm_source' => $event->utm_source,
                'utm_medium' => $event->utm_medium,
                'utm_campaign' => $event->utm_campaign,
                'city' => $event->city,
                'country' => $event->country ?? 'KSA',
                'device_type' => $event->device_type ?? 'desktop',
                'os' => $event->os ?? 'OS',
                'revenue' => floatval($event->revenue),
                'created_at_human' => $event->created_at ? $event->created_at->diffForHumans() : '-',
                'created_at_str' => $event->created_at ? $event->created_at->format('Y-m-d H:i:s') : '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'count' => $formatted->count(),
            'events' => $formatted,
            'next_offset' => $offset + $formatted->count(),
        ]);
    }
}
