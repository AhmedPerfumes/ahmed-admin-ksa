@extends($layout ?? BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
<style>
    /* Analytics Dashboard Custom Styling */
    .analytics-dashboard {
        color: #f8fafc;
    }
    .analytics-card {
        background-color: #1e293b !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
    }
    .analytics-card-header {
        background: rgba(255, 255, 255, 0.03) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        padding: 0.85rem 1.25rem;
    }
    .analytics-card-title {
        color: #38bdf8 !important;
        font-weight: 700;
        font-size: 0.95rem;
    }
    .analytics-stat-title {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    .analytics-stat-value {
        color: #f8fafc;
        font-weight: 800;
    }
    .analytics-table {
        color: #e2e8f0;
    }
    .analytics-table thead th {
        background-color: #0f172a !important;
        color: #94a3b8 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .analytics-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    }
    .analytics-table tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.04) !important;
    }
    .analytics-code-path {
        background-color: #0f172a !important;
        color: #38bdf8 !important;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        border: 1px solid rgba(56, 189, 248, 0.2);
    }

    /* Event Badge Styling - All text white for high contrast */
    .badge {
        color: #ffffff !important;
    }
    .badge-event-purchase { background-color: #059669 !important; color: #ffffff !important; }
    .badge-event-begin_checkout, .badge-event-add_payment_info { background-color: #d97706 !important; color: #ffffff !important; }
    .badge-event-add_to_cart { background-color: #0891b2 !important; color: #ffffff !important; }
    .badge-event-remove_from_cart { background-color: #ea580c !important; color: #ffffff !important; }
    .badge-event-view_cart { background-color: #0d9488 !important; color: #ffffff !important; }
    .badge-event-view_item { background-color: #7c3aed !important; color: #ffffff !important; }
    .badge-event-page_view { background-color: #2563eb !important; color: #ffffff !important; }
    .badge-event-time_on_page { background-color: #9333ea !important; color: #ffffff !important; }
    .badge-event-scroll_depth { background-color: #db2777 !important; color: #ffffff !important; }
    .badge-event-session_start { background-color: #0284c7 !important; color: #ffffff !important; }
    .badge-event-default { background-color: #475569 !important; color: #ffffff !important; }

    /* Scrollable Live Event Table */
    .analytics-scrollable-table {
        max-height: 420px;
        overflow-y: auto;
    }
    .analytics-scrollable-table::-webkit-scrollbar {
        width: 6px;
    }
    .analytics-scrollable-table::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, 0.6);
    }
    .analytics-scrollable-table::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 4px;
    }
    .analytics-scrollable-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #1e293b !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }
    .text-purple { color: #a855f7 !important; }
    .border-purple { border-color: #a855f7 !important; }
</style>

<div class="container-fluid py-3 analytics-dashboard">
    <!-- Header & Date Filter -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
        <div>
            <h1 class="h3 mb-1 fw-bold text-white">
                <i class="fa fa-chart-line text-info me-2"></i> Custom First-Party Analytics
            </h1>
            <p class="text-secondary small mb-0">Real-time visitor telemetry, ecommerce conversion metrics, and server-aggregated stats.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <form id="analyticsDateForm" method="GET" action="{{ route('analytics.dashboard') }}" class="d-flex align-items-center flex-wrap gap-2">
                <input type="hidden" name="event_filter" id="hiddenEventFilterInput" value="{{ $selectedEventFilter }}">
                <div class="d-flex align-items-center">
                    <span class="text-secondary fs-8 me-2 fw-semibold"><i class="fa fa-calendar-alt text-info me-1"></i> From:</span>
                    <input type="date" name="start_date" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 py-1 px-2 rounded-2" value="{{ $startDateStr }}" required>
                </div>
                <div class="d-flex align-items-center ms-md-1">
                    <span class="text-secondary fs-8 me-2 fw-semibold">To:</span>
                    <input type="date" name="end_date" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 py-1 px-2 rounded-2" value="{{ $endDateStr }}" required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary fw-semibold px-3 py-1 shadow-sm fs-8 ms-md-1">
                    <i class="fa fa-filter me-1"></i> Filter
                </button>
            </form>
        </div>
    </div>

    <!-- KPI Cards Row -->
    <div class="row g-3 mb-4">
        <!-- Total Revenue -->
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card analytics-card h-100 border-start border-primary border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="analytics-stat-title text-primary mb-1">Total Revenue</div>
                            <div class="h4 mb-0 analytics-stat-value text-nowrap">{{ number_format($totalRevenue, 2) }} SAR</div>
                        </div>
                        <div class="bg-primary bg-opacity-20 text-primary p-2.5 rounded-circle ms-2">
                            <i class="fa fa-coins fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unique Visitors -->
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card analytics-card h-100 border-start border-success border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="analytics-stat-title text-success mb-1">Unique Visitors</div>
                            <div class="h4 mb-0 analytics-stat-value">{{ number_format($uniqueVisitors) }}</div>
                        </div>
                        <div class="bg-success bg-opacity-20 text-success p-2.5 rounded-circle ms-2">
                            <i class="fa fa-users fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unique Customers (Logged In) -->
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card analytics-card h-100 border-start border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="analytics-stat-title text-purple mb-1">Logged-In Customers</div>
                            <div class="h4 mb-0 analytics-stat-value">{{ number_format($uniqueCustomers) }}</div>
                        </div>
                        <div class="bg-purple bg-opacity-20 text-purple p-2.5 rounded-circle ms-2">
                            <i class="fa fa-user-check fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversion Rate -->
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card analytics-card h-100 border-start border-info border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="analytics-stat-title text-info mb-1">Conversion Rate</div>
                            <div class="h4 mb-0 analytics-stat-value">{{ number_format($conversionRate, 2) }}%</div>
                        </div>
                        <div class="bg-info bg-opacity-20 text-info p-2.5 rounded-circle ms-2">
                            <i class="fa fa-percentage fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Abandonment Rate -->
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card analytics-card h-100 border-start border-warning border-4">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="analytics-stat-title text-warning mb-1">Cart Abandonment</div>
                            <div class="h4 mb-0 analytics-stat-value">{{ number_format($cartAbandonmentRate, 2) }}%</div>
                        </div>
                        <div class="bg-warning bg-opacity-20 text-warning p-2.5 rounded-circle ms-2">
                            <i class="fa fa-shopping-cart fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1: Revenue Trend & Conversion Funnel -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-chart-area me-2"></i> Revenue Trend</span>
                </div>
                <div class="card-body">
                    <canvas id="revenueTrendChart" height="260"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-filter me-2"></i> Conversion Funnel</span>
                </div>
                <div class="card-body">
                    <canvas id="funnelChart" height="260"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Phase 1: Marketing & Campaign ROI Attribution -->
    <div class="row g-3 mb-4">
        <!-- 1. UTM Campaign ROI Performance Table -->
        <div class="col-lg-8">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title">
                        <i class="fa fa-bullhorn me-2 text-warning"></i> UTM Campaign ROI & Ad Breakdown
                    </span>
                    <span class="badge bg-dark text-warning border border-warning border-opacity-25 fs-8">Marketing ROI</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table analytics-table align-middle mb-0 fs-8">
                            <thead>
                                <tr>
                                    <th>Source / Medium</th>
                                    <th>Campaign</th>
                                    <th class="text-center">Visitors</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-center">Conv. Rate</th>
                                    <th class="text-end">AOV</th>
                                    <th class="text-end">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($campaignRoiStats as $cStat)
                                @php
                                    $cvr = $cStat['conversion_rate'];
                                    $cvrClass = match(true) {
                                        $cvr >= 3.0 => 'bg-success text-white',
                                        $cvr >= 1.0 => 'bg-dark text-warning border border-warning border-opacity-25',
                                        default => 'bg-dark text-secondary border border-secondary border-opacity-25'
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge bg-dark text-warning border border-warning border-opacity-25 px-2 py-1 font-monospace fs-8">
                                            <i class="fa fa-bullhorn me-1"></i>{{ $cStat['source'] }} / {{ $cStat['medium'] }}
                                        </span>
                                    </td>
                                    <td class="fw-semibold text-light text-truncate" style="max-width: 160px;" title="{{ $cStat['campaign'] }}">
                                        {{ $cStat['campaign'] }}
                                    </td>
                                    <td class="text-center font-monospace text-light">
                                        {{ number_format($cStat['visitors']) }}
                                    </td>
                                    <td class="text-center font-monospace fw-bold text-info">
                                        {{ number_format($cStat['purchases']) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $cvrClass }} px-2 py-1 font-monospace">
                                            {{ number_format($cvr, 2) }}%
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace text-light">
                                        {{ number_format($cStat['aov'], 2) }} SAR
                                    </td>
                                    <td class="text-end">
                                        @if($cStat['revenue'] > 0)
                                            <strong class="text-success font-monospace">+{{ number_format($cStat['revenue'], 2) }} SAR</strong>
                                            <div class="progress bg-dark ms-auto mt-1" style="height: 3px; width: 80px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $cStat['rev_percent'] }}%;"></div>
                                            </div>
                                        @else
                                            <span class="text-secondary">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-secondary">
                                        <i class="fa fa-info-circle me-1"></i> No campaign tracking data recorded in this date range.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. First-Touch vs. Last-Touch Attribution Matrix -->
        <div class="col-lg-4">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title">
                        <i class="fa fa-exchange-alt me-2 text-info"></i> First-Touch vs. Last-Touch
                    </span>
                    <span class="badge bg-dark text-info border border-info border-opacity-25 fs-8">Attribution</span>
                </div>
                <div class="card-body p-2" style="max-height: 350px; overflow-y: auto;">
                    <div class="list-group list-group-flush border-0">
                        @forelse($attributionMatrix as $att)
                        <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-2 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-bold text-white fs-7">
                                    <i class="fa fa-compass text-info me-1"></i> {{ $att['channel'] }}
                                </span>
                                <span class="{{ $att['role_badge'] }} fs-8">
                                    {{ $att['role'] }}
                                </span>
                            </div>
                            <div class="row g-2 mt-1 fs-8">
                                <div class="col-6 border-end border-secondary border-opacity-25 pe-2">
                                    <span class="text-secondary d-block fs-9">FIRST-TOUCH (Discovery)</span>
                                    <span class="fw-semibold text-warning font-monospace">{{ number_format($att['first_touch_revenue'], 2) }} SAR</span>
                                    <small class="text-secondary d-block">({{ $att['first_touch_orders'] }} orders)</small>
                                </div>
                                <div class="col-6 ps-2">
                                    <span class="text-secondary d-block fs-9">LAST-TOUCH (Closer)</span>
                                    <span class="fw-semibold text-success font-monospace">{{ number_format($att['last_touch_revenue'], 2) }} SAR</span>
                                    <small class="text-secondary d-block">({{ $att['last_touch_orders'] }} orders)</small>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">
                            <i class="fa fa-info-circle me-1"></i> No attribution data recorded yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Phase 2: Product Performance & Bundle Intelligence -->
    <div class="row g-3 mb-4">
        <!-- 1. Product Drop-off & Conversion Table -->
        <div class="col-lg-7">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title">
                        <i class="fa fa-boxes me-2 text-info"></i> Product Drop-off & Cart Conversion
                    </span>
                    <span class="badge bg-dark text-info border border-info border-opacity-25 fs-8">Catalog Friction</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table analytics-table align-middle mb-0 fs-8">
                            <thead>
                                <tr>
                                    <th>Perfume Name</th>
                                    <th class="text-center">Views</th>
                                    <th class="text-center">Cart Adds</th>
                                    <th class="text-center">Purchases</th>
                                    <th class="text-center">Cart Rate</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productFrictionStats as $pFric)
                                <tr>
                                    <td class="fw-semibold text-light text-truncate" style="max-width: 200px;" title="{{ $pFric['name'] }}">
                                        {{ $pFric['name'] }}
                                    </td>
                                    <td class="text-center font-monospace text-light">
                                        <i class="fa fa-eye me-1 text-info fs-9"></i>{{ number_format($pFric['views']) }}
                                    </td>
                                    <td class="text-center font-monospace text-warning">
                                        <i class="fa fa-cart-plus me-1 text-warning fs-9"></i>{{ number_format($pFric['cart_adds']) }}
                                    </td>
                                    <td class="text-center font-monospace text-success fw-bold">
                                        <i class="fa fa-shopping-bag me-1 text-success fs-9"></i>{{ number_format($pFric['purchases']) }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark text-info border border-info border-opacity-25 px-2 py-1 font-monospace">
                                            {{ number_format($pFric['cart_rate'], 1) }}%
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="{{ $pFric['badge_class'] }} fs-8">
                                            {{ $pFric['status'] }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-secondary">
                                        <i class="fa fa-info-circle me-1"></i> No product interaction events recorded in this date range.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Frequently Bought Together (Product Bundles Up to 5 Items) -->
        <div class="col-lg-5">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title">
                        <i class="fa fa-layer-group me-2 text-success"></i> Frequently Bought Together
                    </span>
                    <span class="badge bg-dark text-success border border-success border-opacity-25 fs-8">Up to 5 Items</span>
                </div>
                <div class="card-body p-2" style="max-height: 350px; overflow-y: auto;">
                    <div class="list-group list-group-flush border-0">
                        @forelse($productBundleStats as $bnd)
                        @php
                            $iCount = $bnd['item_count'] ?? count($bnd['items'] ?? []);
                            $actionText = match($iCount) {
                                2 => 'Create a 2-Pack Duo Bundle Offer',
                                3 => 'Create a 3-Piece Gift Box Collection',
                                4 => 'Create a 4-Piece Deluxe Fragrance Set',
                                5 => 'Create a 5-Piece Master VIP Collection Box',
                                default => 'Create a Multi-Pack Bundle Special'
                            };
                        @endphp
                        <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-2 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-25 px-2 py-1 fs-8">
                                    <i class="fa fa-layer-group me-1"></i> {{ $iCount }}-Item Bundle ({{ $bnd['count'] }} Orders)
                                </span>
                                <span class="fw-bold text-success font-monospace fs-8">
                                    +{{ number_format($bnd['total_order_revenue'], 2) }} SAR
                                </span>
                            </div>
                            <div class="mt-1 fs-8 fw-semibold text-light">
                                @foreach($bnd['items'] as $idx => $item)
                                    <div class="text-truncate" style="max-width: 100%;" title="{{ $item }}">
                                        <i class="fa fa-box-open text-warning me-1 fs-9"></i> {{ $item }}
                                    </div>
                                @endforeach
                            </div>
                            <small class="d-block text-secondary mt-1 fs-9">
                                💡 <em>Suggested Action: {{ $actionText }}</em>
                            </small>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">
                            <i class="fa fa-info-circle me-1"></i> No multi-item orders recorded in this date range.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: Top Products, Traffic Sources, Device Breakdown, Top Locations -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-fire me-2 text-warning"></i> Top 10 Viewed Products</span>
                    <span class="badge bg-dark text-warning border border-warning border-opacity-25 fs-8">Ranked</span>
                </div>
                <div class="card-body p-2" style="max-height: 270px; overflow-y: auto;">
                    @php $maxViews = !empty($topProducts) ? max($topProducts) : 1; @endphp
                    <div class="list-group list-group-flush border-0">
                        @forelse($topProducts as $name => $views)
                        @php
                            $rank = $loop->iteration;
                            $rankBadgeClass = match($rank) {
                                1 => 'bg-warning text-dark font-weight-bold',
                                2 => 'bg-secondary text-white',
                                3 => 'bg-danger text-white',
                                default => 'bg-dark text-secondary border border-secondary border-opacity-25'
                            };
                            $percent = round(($views / $maxViews) * 100);
                        @endphp
                        <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-1 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center text-truncate me-2">
                                    <span class="badge {{ $rankBadgeClass }} rounded-circle me-2 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.7rem;">
                                        #{{ $rank }}
                                    </span>
                                    <span class="fw-semibold text-truncate fs-7" title="{{ $name }}">{{ $name }}</span>
                                </div>
                                <span class="badge bg-dark text-info border border-info border-opacity-25 font-monospace px-2 py-1 fs-8 ms-2">
                                    <i class="fa fa-eye me-1 text-info"></i>{{ number_format($views) }}
                                </span>
                            </div>
                            <div class="progress bg-dark" style="height: 4px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">
                            <i class="fa fa-info-circle me-1"></i> No product views recorded yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-share-alt me-2 text-info"></i> Traffic Sources</span>
                    <span class="badge bg-dark text-info border border-info border-opacity-25 fs-8">Channels</span>
                </div>
                <div class="card-body p-2" style="max-height: 270px; overflow-y: auto;">
                    @php $maxTraffic = !empty($trafficSources) ? max($trafficSources) : 1; @endphp
                    <div class="list-group list-group-flush border-0">
                        @forelse($trafficSources as $source => $count)
                        @php
                            $sourceLower = strtolower($source);
                            $iconClass = match(true) {
                                str_contains($sourceLower, 'direct') => 'fa fa-globe text-primary',
                                str_contains($sourceLower, 'google') => 'fab fa-google text-danger',
                                str_contains($sourceLower, 'instagram') => 'fab fa-instagram text-pink',
                                str_contains($sourceLower, 'facebook') => 'fab fa-facebook text-info',
                                str_contains($sourceLower, 'tiktok') => 'fab fa-tiktok text-light',
                                str_contains($sourceLower, 'snapchat') => 'fab fa-snapchat text-warning',
                                default => 'fa fa-link text-secondary'
                            };
                            $percent = round(($count / $maxTraffic) * 100);
                        @endphp
                        <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-1 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center text-truncate me-2">
                                    <i class="{{ $iconClass }} me-2 fs-6"></i>
                                    <span class="fw-semibold text-truncate fs-7" title="{{ $source }}">{{ $source }}</span>
                                </div>
                                <span class="badge bg-dark text-success border border-success border-opacity-25 font-monospace px-2 py-1 fs-8 ms-2">
                                    {{ number_format($count) }} {{ $count === 1 ? 'session' : 'sessions' }}
                                </span>
                            </div>
                            <div class="progress bg-dark" style="height: 4px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">
                            <i class="fa fa-info-circle me-1"></i> No traffic source data yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header">
                    <span class="analytics-card-title"><i class="fa fa-desktop me-2"></i> Devices</span>
                </div>
                <div class="card-body">
                    <canvas id="deviceChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header">
                    <span class="analytics-card-title"><i class="fa fa-map-marker-alt me-2 text-danger"></i> Top Locations</span>
                </div>
                <div class="card-body">
                    <canvas id="locationChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 3: Top Added to Cart, Top Bought & Payment Types -->
    <div class="row g-3 mb-4">
        <!-- Top 10 Added to Cart / Active Carts -->
        <div class="col-lg-4">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-cart-plus me-2 text-warning"></i> Cart Insights</span>
                    <ul class="nav nav-pills card-header-pills fs-8" id="cartTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-0 px-2 text-warning fw-semibold fs-8" id="cart-total-tab" data-bs-toggle="tab" data-bs-target="#cart-total" type="button" role="tab">Total Added</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-0 px-2 text-info fw-semibold fs-8" id="cart-active-tab" data-bs-toggle="tab" data-bs-target="#cart-active" type="button" role="tab">In Carts Now</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-2" style="max-height: 270px; overflow-y: auto;">
                    <div class="tab-content" id="cartTabContent">
                        <!-- Tab 1: Total Added to Cart -->
                        <div class="tab-pane fade show active" id="cart-total" role="tabpanel">
                            @php $maxCart = !empty($topCartProducts) ? max($topCartProducts) : 1; @endphp
                            <div class="list-group list-group-flush border-0">
                                @forelse($topCartProducts as $name => $addedQty)
                                @php
                                    $rank = $loop->iteration;
                                    $rankBadgeClass = match($rank) {
                                        1 => 'bg-warning text-dark font-weight-bold',
                                        2 => 'bg-secondary text-white',
                                        3 => 'bg-danger text-white',
                                        default => 'bg-dark text-secondary border border-secondary border-opacity-25'
                                    };
                                    $percent = round(($addedQty / $maxCart) * 100);
                                @endphp
                                <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-1 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="d-flex align-items-center text-truncate me-2">
                                            <span class="badge {{ $rankBadgeClass }} rounded-circle me-2 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.7rem;">
                                                #{{ $rank }}
                                            </span>
                                            <span class="fw-semibold text-truncate fs-7" title="{{ $name }}">{{ $name }}</span>
                                        </div>
                                        <span class="badge bg-dark text-warning border border-warning border-opacity-25 font-monospace px-2 py-1 fs-8 ms-2">
                                            <i class="fa fa-cart-plus me-1 text-warning"></i>{{ number_format($addedQty) }} Added
                                        </span>
                                    </div>
                                    <div class="progress bg-dark" style="height: 4px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                @empty
                                <div class="text-center py-4 text-secondary small">
                                    <i class="fa fa-info-circle me-1"></i> No add to cart events recorded yet.
                                </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Tab 2: Products Currently In Carts Right Now -->
                        <div class="tab-pane fade" id="cart-active" role="tabpanel">
                            @php $maxActiveCart = !empty($topActiveCartProducts) ? max($topActiveCartProducts) : 1; @endphp
                            <div class="list-group list-group-flush border-0">
                                @forelse($topActiveCartProducts as $name => $activeQty)
                                @php
                                    $rank = $loop->iteration;
                                    $rankBadgeClass = match($rank) {
                                        1 => 'bg-info text-dark font-weight-bold',
                                        2 => 'bg-secondary text-white',
                                        3 => 'bg-danger text-white',
                                        default => 'bg-dark text-secondary border border-secondary border-opacity-25'
                                    };
                                    $percent = round(($activeQty / $maxActiveCart) * 100);
                                @endphp
                                <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-1 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="d-flex align-items-center text-truncate me-2">
                                            <span class="badge {{ $rankBadgeClass }} rounded-circle me-2 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.7rem;">
                                                #{{ $rank }}
                                            </span>
                                            <span class="fw-semibold text-truncate fs-7" title="{{ $name }}">{{ $name }}</span>
                                        </div>
                                        <span class="badge bg-dark text-info border border-info border-opacity-25 font-monospace px-2 py-1 fs-8 ms-2">
                                            <i class="fa fa-shopping-basket me-1 text-info"></i>{{ number_format($activeQty) }} In Cart
                                        </span>
                                    </div>
                                    <div class="progress bg-dark" style="height: 4px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                @empty
                                <div class="text-center py-4 text-secondary small">
                                    <i class="fa fa-info-circle me-1"></i> No items currently sitting in active carts.
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 10 Bought Products -->
        <div class="col-lg-4">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-shopping-bag me-2 text-success"></i> Top 10 Bought Products</span>
                    <span class="badge bg-dark text-success border border-success border-opacity-25 fs-8">Purchases</span>
                </div>
                <div class="card-body p-2" style="max-height: 270px; overflow-y: auto;">
                    @php $maxBought = !empty($topBoughtProducts) ? max($topBoughtProducts) : 1; @endphp
                    <div class="list-group list-group-flush border-0">
                        @forelse($topBoughtProducts as $name => $sold)
                        @php
                            $rank = $loop->iteration;
                            $rankBadgeClass = match($rank) {
                                1 => 'bg-warning text-dark font-weight-bold',
                                2 => 'bg-secondary text-white',
                                3 => 'bg-danger text-white',
                                default => 'bg-dark text-secondary border border-secondary border-opacity-25'
                            };
                            $percent = round(($sold / $maxBought) * 100);
                        @endphp
                        <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-1 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center text-truncate me-2">
                                    <span class="badge {{ $rankBadgeClass }} rounded-circle me-2 d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 0.7rem;">
                                        #{{ $rank }}
                                    </span>
                                    <span class="fw-semibold text-truncate fs-7" title="{{ $name }}">{{ $name }}</span>
                                </div>
                                <span class="badge bg-dark text-success border border-success border-opacity-25 font-monospace px-2 py-1 fs-8 ms-2">
                                    <i class="fa fa-shopping-cart me-1"></i>{{ number_format($sold) }} Sold
                                </span>
                            </div>
                            <div class="progress bg-dark" style="height: 4px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">
                            <i class="fa fa-info-circle me-1"></i> No completed purchases recorded yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Count of Payment Types -->
        <div class="col-lg-4">
            <div class="card analytics-card h-100">
                <div class="analytics-card-header d-flex align-items-center justify-content-between">
                    <span class="analytics-card-title"><i class="fa fa-credit-card me-2 text-info"></i> Count of Payment Types</span>
                    <span class="badge bg-dark text-info border border-info border-opacity-25 fs-8">Payment Methods</span>
                </div>
                <div class="card-body p-2" style="max-height: 270px; overflow-y: auto;">
                    @php $maxPayment = !empty($paymentTypesCount) ? max($paymentTypesCount) : 1; @endphp
                    <div class="list-group list-group-flush border-0">
                        @forelse($paymentTypesCount as $type => $count)
                        @php
                            $typeLower = strtolower($type);
                            $iconClass = match(true) {
                                str_contains($typeLower, 'cash') || str_contains($typeLower, 'cod') => 'fa fa-money-bill-wave text-success',
                                str_contains($typeLower, 'card') || str_contains($typeLower, 'credit') => 'fa fa-credit-card text-info',
                                str_contains($typeLower, 'tabby') => 'fa fa-calendar-alt text-primary',
                                str_contains($typeLower, 'tamara') => 'fa fa-clock text-warning',
                                str_contains($typeLower, 'apple') => 'fab fa-apple text-light',
                                str_contains($typeLower, 'stc') => 'fa fa-wallet text-purple',
                                default => 'fa fa-receipt text-secondary'
                            };
                            $percent = round(($count / $maxPayment) * 100);
                        @endphp
                        <div class="list-group-item bg-transparent border-0 px-2 py-2 mb-1 rounded-2 text-light" style="background: rgba(30, 41, 59, 0.4);">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center text-truncate me-2">
                                    <i class="{{ $iconClass }} me-2 fs-6"></i>
                                    <span class="fw-semibold text-truncate fs-7" title="{{ $type }}">{{ $type }}</span>
                                </div>
                                <span class="badge bg-dark text-info border border-info border-opacity-25 font-monospace px-2 py-1 fs-8 ms-2">
                                    {{ number_format($count) }} {{ $count === 1 ? 'order' : 'orders' }}
                                </span>
                            </div>
                            <div class="progress bg-dark" style="height: 4px;">
                                <div class="progress-bar bg-info" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-4 text-secondary small">
                            <i class="fa fa-info-circle me-1"></i> No payment data captured yet.
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Abandoned Cart Recovery Widget Table -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card analytics-card border-start border-warning border-4">
                <div class="analytics-card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="analytics-card-title text-white">
                            <i class="fa fa-shopping-cart me-2 text-warning"></i> Abandoned Cart Recovery 
                            <span class="badge bg-warning text-dark rounded-pill ms-2 fs-7">{{ count($abandonedCartSessions) }} Carts Monitored</span>
                        </span>
                        @if(($recoveredStats['count'] ?? 0) > 0)
                            <span class="badge bg-success text-white rounded-pill ms-2 fs-7 shadow-sm">
                                <i class="fa fa-trophy me-1 text-warning"></i> {{ $recoveredStats['count'] }} Recovered (+{{ number_format($recoveredStats['revenue'], 2) }} SAR)
                            </span>
                        @endif
                    </div>
                    @php
                        $recoverableRevenue = array_sum(array_column($abandonedCartSessions, 'total_cart_value'));
                    @endphp
                    <div class="text-end">
                        <span class="text-secondary fs-8">Recoverable Revenue:</span>
                        <strong class="text-success fs-7 ms-1">+{{ number_format($recoverableRevenue, 2) }} SAR</strong>
                    </div>
                </div>
                <div class="table-responsive analytics-scrollable-table" style="max-height: 380px;">
                    <table class="table analytics-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Customer / Session</th>
                                <th>Abandoned Items</th>
                                <th>Cart Value</th>
                                <th>Abandoned Ago</th>
                                <th>Contact Details</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($abandonedCartSessions as $sess)
                            <tr>
                                <td>
                                    <a href="javascript:void(0)" class="font-monospace text-info text-decoration-underline small fw-semibold" onclick="loadVisitorJourney('{{ $sess['visitor_id'] }}')" title="Click to view Clickstream Timeline">
                                        {{ substr($sess['session_id'], 0, 14) }}...
                                    </a>
                                    <div class="small text-secondary mt-0.5 fs-8">
                                        <i class="fa fa-map-marker-alt text-danger me-1"></i>{{ $sess['city'] ? $sess['city'] . ', ' : '' }}{{ $sess['country'] ?? 'KSA' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1" style="max-width: 320px;">
                                        @foreach($sess['items_list'] as $item)
                                            <span class="badge bg-dark border border-secondary text-light fs-8 font-monospace px-2 py-1">
                                                <i class="fa fa-box text-warning me-1"></i>{{ \Illuminate\Support\Str::limit($item['name'], 20) }} <span class="text-info">x{{ $item['qty'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-success fs-7">{{ number_format($sess['total_cart_value'], 2) }} SAR</strong>
                                    <div class="small text-secondary fs-8">{{ $sess['total_items_count'] }} {{ $sess['total_items_count'] === 1 ? 'item' : 'items' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-warning text-dark px-2 py-1 fs-8">
                                        <i class="fa fa-clock me-1"></i>{{ $sess['abandoned_ago'] }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($sess['customer_email']))
                                        <div class="text-light fw-semibold fs-8"><i class="fa fa-envelope text-info me-1"></i>{{ $sess['customer_email'] }}</div>
                                    @endif
                                    @if(!empty($sess['customer_phone']))
                                        <div class="text-success fw-semibold fs-8 mt-0.5"><i class="fa fa-mobile-alt me-1"></i>{{ $sess['customer_phone'] }}</div>
                                    @endif
                                    @if(!empty($sess['customer_name']))
                                        <div class="text-secondary fs-8">{{ $sess['customer_name'] }}</div>
                                    @endif
                                    @if(empty($sess['customer_email']) && empty($sess['customer_phone']))
                                        <span class="text-secondary fs-8 font-italic"><i class="fa fa-user-slash me-1"></i>Guest (No Contact Info)</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($sess['is_converted']))
                                        <span class="badge bg-success text-white px-2.5 py-1.5 fs-8 shadow-sm" title="Purchased after recovery click">
                                            <i class="fa fa-trophy text-warning me-1"></i> Converted ({{ $sess['conversion_details']['order_id'] ?? 'Order' }} - {{ number_format($sess['conversion_details']['revenue'] ?? 0, 2) }} SAR)
                                        </span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @if(!empty($sess['customer_email']))
                                                @if(!empty($sess['email_sent_at']))
                                                    <span class="badge bg-info text-dark px-2 py-1 fs-8 fw-semibold" title="Recovery email sent {{ $sess['email_sent_at'] }}">
                                                        <i class="fa fa-paper-plane me-1"></i> Email Sent
                                                    </span>
                                                @else
                                                    <button class="btn btn-sm btn-outline-warning text-white fw-semibold px-2 py-1 fs-8 shadow-sm" onclick="sendRecoveryEmail(this, '{{ $sess['session_id'] }}', '{{ $sess['customer_email'] }}', '{{ addslashes($sess['customer_name'] ?? 'Valued Customer') }}', '{{ $sess['total_cart_value'] }}', {{ json_encode($sess['items_list']) }})">
                                                        <i class="fa fa-paper-plane me-1"></i> Send Email
                                                    </button>
                                                @endif
                                            @endif

                                            @if(!empty($sess['customer_phone']))
                                                @if(!empty($sess['sms_sent_at']))
                                                    <span class="badge bg-success text-dark px-2 py-1 fs-8 fw-semibold" title="Recovery SMS sent {{ $sess['sms_sent_at'] }}">
                                                        <i class="fa fa-comment-sms me-1"></i> SMS Sent
                                                    </span>
                                                @else
                                                    <button class="btn btn-sm btn-outline-success text-white fw-semibold px-2 py-1 fs-8 shadow-sm" onclick="sendRecoverySms(this, '{{ $sess['session_id'] }}', '{{ $sess['customer_phone'] }}')">
                                                        <i class="fa fa-comment-sms me-1"></i> Send SMS
                                                    </button>
                                                @endif
                                            @endif

                                            @if(empty($sess['customer_email']) && empty($sess['customer_phone']))
                                                <button class="btn btn-sm btn-dark text-secondary px-2 py-1 fs-8 disabled" disabled title="No contact info available for guest session">
                                                    <i class="fa fa-ban me-1"></i> Unavailable
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-secondary">
                                    <i class="fa fa-check-circle text-success me-1"></i> No recoverable abandoned carts with valid customer emails found exceeding 30 minutes in this timeframe!
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Event Feed Table -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="analytics-card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div class="d-flex align-items-center">
                        <span class="analytics-card-title text-white">
                            <i class="fa fa-stream me-2 text-danger"></i> Live Event Stream 
                            <span class="badge bg-danger rounded-pill ms-2 fs-7">50 Unique Visitors</span>
                        </span>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="input-group input-group-sm" style="width: 240px;">
                            <span class="input-group-text bg-dark border-secondary text-secondary"><i class="fa fa-search"></i></span>
                            <input type="text" id="liveStreamSearchInput" class="form-control bg-dark text-light border-secondary fs-8" placeholder="Search events, UTMs, visitor..." onkeyup="filterLiveStreamTable(this.value)">
                        </div>
                        <span class="text-secondary fs-8 fw-semibold"><i class="fa fa-filter text-info me-1"></i> Event Type:</span>
                        <select id="liveEventFilterSelect" class="form-select form-select-sm bg-dark text-light border-secondary fs-8 py-1 px-2 rounded-2" style="width: auto; cursor: pointer;" onchange="document.getElementById('hiddenEventFilterInput').value = this.value; document.getElementById('analyticsDateForm').submit();">
                            <option value="all" {{ $selectedEventFilter === 'all' ? 'selected' : '' }}>All Events</option>
                            @foreach($availableEvents as $evtName)
                                <option value="{{ $evtName }}" {{ $selectedEventFilter === $evtName ? 'selected' : '' }}>{{ $evtName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="table-responsive analytics-scrollable-table">
                    <table id="liveEventStreamTable" class="table analytics-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Page Path</th>
                                <th>Visitor ID</th>
                                <th>UTM Source</th>
                                <th>UTM Medium</th>
                                <th>Location</th>
                                <th>Device / OS</th>
                                <th>Revenue</th>
                                <th>Timestamp</th>
                            </tr>
                            <tr class="bg-dark border-bottom border-secondary">
                                <th class="py-1 px-1" style="min-width: 110px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="0" placeholder="Filter Event..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 120px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="1" placeholder="Filter Path..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 110px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="2" placeholder="Filter Visitor..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 110px;"><input type="text" class="form-control form-control-sm bg-dark text-warning border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="3" placeholder="Filter Source..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 110px;"><input type="text" class="form-control form-control-sm bg-dark text-info border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="4" placeholder="Filter Medium..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 110px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="5" placeholder="Filter Location..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 110px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="6" placeholder="Filter Device..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 90px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="7" placeholder="Filter Rev..." onkeyup="filterLiveStreamColumns()"></th>
                                <th class="py-1 px-1" style="min-width: 100px;"><input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fs-8 px-1 py-0.5 col-filter-input" data-col="8" placeholder="Filter Time..." onkeyup="filterLiveStreamColumns()"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentEvents as $event)
                            @php
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

                                // Parse event_data JSON payload for contextual details
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
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge {{ $badgeClass }} px-2.5 py-1 fs-7 rounded-2">
                                        {{ $event->event_name }}
                                    </span>
                                    @if($eventDetail)
                                        <small class="d-block text-info mt-1 font-monospace fs-8" title="Event Payload Detail">
                                            @if($event->event_name === 'time_on_page')
                                                <i class="fa fa-clock me-1 text-warning"></i>{{ $eventDetail }}
                                            @elseif($event->event_name === 'scroll_depth')
                                                <i class="fa fa-arrow-down me-1 text-info"></i>{{ $eventDetail }}
                                            @elseif(in_array($event->event_name, ['view_item', 'add_to_cart', 'remove_from_cart']))
                                                <i class="fa fa-box me-1 text-secondary"></i>{{ \Illuminate\Support\Str::limit($eventDetail, 25) }}
                                            @elseif($event->event_name === 'purchase')
                                                <i class="fa fa-receipt me-1 text-success"></i>{{ $eventDetail }}
                                            @else
                                                {{ $eventDetail }}
                                            @endif
                                        </small>
                                    @endif
                                </td>
                                <td class="text-truncate" style="max-width: 250px;" title="{{ $event->page_url }}">
                                    <code class="analytics-code-path">{{ $parsedUrl ?: '/' }}</code>
                                </td>
                                <td>
                                    <a href="javascript:void(0)" class="font-monospace text-info text-decoration-underline small fw-semibold" onclick="loadVisitorJourney('{{ $event->visitor_id }}')" title="Click to view Clickstream Timeline">
                                        {{ substr($event->visitor_id, 0, 14) }}...
                                    </a>
                                    @if($event->customer_id)
                                        <span class="badge bg-purple text-white ms-1" title="Logged-In Customer ID: {{ $event->customer_id }}"><i class="fa fa-user-check me-1"></i>#{{ $event->customer_id }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($event->utm_source)
                                        <span class="badge bg-dark text-warning border border-warning border-opacity-25 px-2 py-1 fs-8 font-monospace" title="UTM Campaign: {{ $event->utm_campaign ?? 'None' }}">
                                            <i class="fa fa-bullhorn text-warning me-1"></i>{{ $event->utm_source }}
                                        </span>
                                    @else
                                        <span class="text-secondary fs-8 font-monospace">Direct</span>
                                    @endif
                                </td>
                                <td>
                                    @if($event->utm_medium)
                                        <span class="badge bg-dark text-info border border-info border-opacity-25 px-2 py-1 fs-8 font-monospace">
                                            {{ $event->utm_medium }}
                                        </span>
                                    @else
                                        <span class="text-secondary fs-8 font-monospace">Organic</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-dark text-info border border-info border-opacity-25 px-2 py-1">
                                        <i class="fa fa-map-marker-alt text-danger me-1"></i> {{ $event->city ? $event->city . ', ' : '' }}{{ $event->country ?? 'KSA' }}
                                    </span>
                                </td>
                                <td>
                                    <small class="text-capitalize text-light"><i class="fa fa-laptop me-1 text-secondary"></i> {{ $event->device_type ?? 'desktop' }} ({{ $event->os ?? 'OS' }})</small>
                                </td>
                                <td>
                                    @if($event->revenue > 0)
                                        <strong class="text-success">+{{ number_format($event->revenue, 2) }} SAR</strong>
                                    @else
                                        <span class="text-secondary">-</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-secondary" title="{{ $event->created_at }}">
                                        {{ $event->created_at ? $event->created_at->diffForHumans() : '-' }}
                                    </small>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-secondary">
                                    <i class="fa fa-info-circle me-1"></i> No events captured yet in this timeframe.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-dark border-top border-secondary text-center py-2.5">
                    <button id="loadMoreEventsBtn" class="btn btn-sm btn-outline-info text-white fw-semibold px-4 py-1.5 fs-8 shadow-sm" onclick="loadMoreLiveEvents()">
                        <i class="fa fa-sync me-1"></i> Load More Events (+50)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart Defaults for Dark Theme
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.08)';

    // 1. Revenue Trend Line Chart
    const revCanvas = document.getElementById('revenueTrendChart');
    if (revCanvas) {
        const trendData = @json($revenueTrendData);
        new Chart(revCanvas, {
            type: 'line',
            data: {
                labels: Object.keys(trendData),
                datasets: [{
                    label: 'Revenue (SAR)',
                    data: Object.values(trendData),
                    borderColor: '#38bdf8',
                    backgroundColor: 'rgba(56, 189, 248, 0.15)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#38bdf8'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                    y: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255, 255, 255, 0.08)' } }
                }
            }
        });
    }

    // 2. Conversion Funnel Horizontal Bar Chart
    const funnelCanvas = document.getElementById('funnelChart');
    if (funnelCanvas) {
        const funnelData = @json($funnelData);
        new Chart(funnelCanvas, {
            type: 'bar',
            data: {
                labels: ['Page View', 'View Item', 'Add to Cart', 'Begin Checkout', 'Purchase'],
                datasets: [{
                    axis: 'y',
                    label: 'Unique Visitors',
                    data: Object.values(funnelData),
                    backgroundColor: ['#38bdf8', '#8b5cf6', '#06b6d4', '#f59e0b', '#10b981']
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                hover: {
                    mode: 'index',
                    intersect: false
                },
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255, 255, 255, 0.08)' } },
                    y: { ticks: { color: '#e2e8f0' }, grid: { display: false } }
                }
            }
        });
    }

    // 3. Device Breakdown Chart
    const deviceCanvas = document.getElementById('deviceChart');
    if (deviceCanvas) {
        const deviceData = @json($deviceBreakdown);
        new Chart(deviceCanvas, {
            type: 'doughnut',
            data: {
                labels: Object.keys(deviceData).map(d => d.toUpperCase()),
                datasets: [{
                    data: Object.values(deviceData),
                    backgroundColor: ['#38bdf8', '#10b981', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, color: '#cbd5e1' } } }
            }
        });
    }

    // 6. Top Locations Chart
    const locationCanvas = document.getElementById('locationChart');
    if (locationCanvas) {
        const locationData = @json($topLocations);
        new Chart(locationCanvas, {
            type: 'doughnut',
            data: {
                labels: Object.keys(locationData),
                datasets: [{
                    data: Object.values(locationData),
                    backgroundColor: ['#10b981', '#38bdf8', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4', '#64748b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, color: '#cbd5e1' } } }
            }
        });
    }
});
</script>

<!-- Visitor Journey Timeline Modal -->
<div class="modal fade" id="visitorJourneyModal" tabindex="-1" aria-labelledby="visitorJourneyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content bg-dark text-light border border-secondary shadow-lg">
            <div class="modal-header border-bottom border-secondary pb-3">
                <div class="d-flex align-items-center justify-content-between w-100 me-3">
                    <div>
                        <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="visitorJourneyModalLabel">
                            <i class="fa fa-route me-2 text-info"></i> Customer Journey Clickstream
                        </h5>
                        <span id="vjVisitorIdBadge" class="font-monospace text-secondary fs-8"></span>
                    </div>
                    <div id="vjConversionBadge"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="visitorJourneyBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="text-secondary small mt-2">Loading clickstream timeline...</p>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-tree {
    position: relative;
    padding-left: 30px;
}
.timeline-tree::before {
    content: '';
    position: absolute;
    top: 10px;
    bottom: 10px;
    left: 12px;
    width: 2px;
    background: rgba(255, 255, 255, 0.15);
}
.timeline-node {
    position: relative;
    margin-bottom: 16px;
}
.timeline-node-icon {
    position: absolute;
    left: -30px;
    top: 4px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: #fff;
    z-index: 1;
}
.timeline-node-content {
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 10px 14px;
}
</style>

<script>
function loadVisitorJourney(visitorId) {
    const modalEl = document.getElementById('visitorJourneyModal');
    const modal = new bootstrap.Modal(modalEl);
    const modalBody = document.getElementById('visitorJourneyBody');
    const visitorIdBadge = document.getElementById('vjVisitorIdBadge');
    const conversionBadge = document.getElementById('vjConversionBadge');

    visitorIdBadge.innerText = 'Visitor ID: ' + visitorId;
    conversionBadge.innerHTML = '';
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-info" role="status"></div>
            <p class="text-secondary small mt-2">Loading clickstream timeline...</p>
        </div>
    `;

    modal.show();

    fetch('{{ url('/admin/analytics/visitor-journey') }}/' + visitorId)
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                modalBody.innerHTML = `<div class="alert alert-danger py-2 fs-8">${data.error}</div>`;
                return;
            }

            // Summary conversion status badge
            if (data.has_purchased) {
                conversionBadge.innerHTML = `<span class="badge bg-success px-3 py-1 fs-7"><i class="fa fa-shopping-bag me-1"></i> Converted Customer (${data.total_spent} SAR)</span>`;
            } else {
                conversionBadge.innerHTML = `<span class="badge bg-secondary px-3 py-1 fs-7"><i class="fa fa-user me-1"></i> Browsing Visitor</span>`;
            }

            // Render Header metadata KPIs
            let html = `
                <div class="row g-2 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="p-2 rounded bg-dark border border-secondary text-center">
                            <span class="text-secondary fs-8 d-block">Location</span>
                            <strong class="text-info fs-7"><i class="fa fa-map-marker-alt text-danger me-1"></i>${data.city ? data.city + ', ' : ''}${data.country}</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2 rounded bg-dark border border-secondary text-center">
                            <span class="text-secondary fs-8 d-block">Device / OS</span>
                            <strong class="text-light fs-7 text-capitalize"><i class="fa fa-laptop me-1 text-secondary"></i>${data.device_type} (${data.os})</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2 rounded bg-dark border border-secondary text-center">
                            <span class="text-secondary fs-8 d-block">Total Actions</span>
                            <strong class="text-warning fs-7"><i class="fa fa-list me-1"></i>${data.total_events} events</strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2 rounded bg-dark border border-secondary text-center">
                            <span class="text-secondary fs-8 d-block">First / Last Seen</span>
                            <strong class="text-secondary fs-8 font-monospace">${data.first_seen.split(' ')[1]} - ${data.last_seen.split(' ')[1]}</strong>
                        </div>
                    </div>
                </div>

                <div class="timeline-tree">
            `;

            // Render timeline items
            data.timeline.forEach(evt => {
                const bgMap = {
                    'info': '#0ea5e9',
                    'primary': '#3b82f6',
                    'warning': '#f59e0b',
                    'secondary': '#64748b',
                    'success': '#10b981',
                    'danger': '#ef4444'
                };
                const iconColor = bgMap[evt.badge_color] || '#64748b';

                html += `
                    <div class="timeline-node">
                        <div class="timeline-node-icon" style="background-color: ${iconColor};">
                            <i class="fa ${evt.icon}"></i>
                        </div>
                        <div class="timeline-node-content shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="badge bg-${evt.badge_color} text-white fs-8 px-2 py-0.5">${evt.event_name}</span>
                                <span class="text-secondary fs-8 font-monospace"><i class="fa fa-clock me-1"></i>${evt.time_formatted} (${evt.created_at_human})</span>
                            </div>
                            <div class="fw-medium text-light fs-7">${evt.detail}</div>
                        </div>
                    </div>
                `;
            });

            html += `</div>`;
            modalBody.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            modalBody.innerHTML = `<div class="alert alert-danger py-2 fs-8">Failed to load journey timeline.</div>`;
        });
}

function sendRecoveryEmail(btnEl, sessionId, email, customerName, cartValue, items) {
    if (!confirm('Are you sure you want to send an Abandoned Cart Recovery Email to ' + email + '?')) {
        return;
    }

    const originalHtml = btnEl ? btnEl.innerHTML : '';
    if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Sending...';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('{{ route("analytics.send-recovery-email") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            session_id: sessionId,
            email: email,
            customer_name: customerName,
            cart_value: cartValue,
            items: items
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert('✅ ' + data.message);
            if (btnEl && btnEl.parentElement) {
                btnEl.parentElement.innerHTML = `<span class="badge bg-success text-white px-2.5 py-1.5 fs-8"><i class="fa fa-check-circle me-1"></i> Email Sent (Just Now)</span>`;
            }
        } else {
            alert('❌ ' + (data.message || 'Failed to send recovery email.'));
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.innerHTML = originalHtml;
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('❌ Error sending recovery email. Check server mail logs.');
        if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = originalHtml;
        }
    });
}

function filterLiveStreamTable(query) {
    const term = (query || '').toLowerCase().trim();
    const tableBody = document.querySelector('#liveEventStreamTable tbody');
    if (!tableBody) return;
    const rows = tableBody.querySelectorAll('tr');

    rows.forEach(row => {
        if (row.children.length <= 1) return; // Skip empty state row
        const text = row.textContent.toLowerCase();
        if (term === '' || text.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterLiveStreamColumns() {
    const inputs = document.querySelectorAll('.col-filter-input');
    const filters = [];
    inputs.forEach(input => {
        const colIdx = parseInt(input.getAttribute('data-col'));
        const val = input.value.toLowerCase().trim();
        filters.push({ colIdx, val });
    });

    const tableBody = document.querySelector('#liveEventStreamTable tbody');
    if (!tableBody) return;
    const rows = tableBody.querySelectorAll('tr');

    rows.forEach(row => {
        if (row.children.length <= 1) return; // Skip empty state row
        let matchAll = true;

        filters.forEach(f => {
            if (f.val !== '' && matchAll) {
                const cell = row.children[f.colIdx];
                if (cell) {
                    const cellText = cell.textContent.toLowerCase();
                    if (!cellText.includes(f.val)) {
                        matchAll = false;
                    }
                }
            }
        });

        row.style.display = matchAll ? '' : 'none';
    });
}

let liveStreamOffset = 50;

function loadMoreLiveEvents() {
    const btn = document.getElementById('loadMoreEventsBtn');
    if (!btn) return;

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Loading...';

    const startDate = '{{ $startDateStr }}';
    const endDate = '{{ $endDateStr }}';
    const eventFilter = '{{ $selectedEventFilter }}';

    const url = `{{ route('analytics.load-more-events') }}?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&event_filter=${encodeURIComponent(eventFilter)}&offset=${liveStreamOffset}&limit=50`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.events && data.events.length > 0) {
                const tbody = document.querySelector('#liveEventStreamTable tbody');
                if (tbody) {
                    data.events.forEach(evt => {
                        let detailHtml = '';
                        if (evt.event_detail) {
                            if (evt.event_name === 'time_on_page') {
                                detailHtml = `<small class="d-block text-info mt-1 font-monospace fs-8"><i class="fa fa-clock me-1 text-warning"></i>${evt.event_detail}</small>`;
                            } else if (evt.event_name === 'scroll_depth') {
                                detailHtml = `<small class="d-block text-info mt-1 font-monospace fs-8"><i class="fa fa-arrow-down me-1 text-info"></i>${evt.event_detail}</small>`;
                            } else if (['view_item', 'add_to_cart', 'remove_from_cart'].includes(evt.event_name)) {
                                detailHtml = `<small class="d-block text-info mt-1 font-monospace fs-8"><i class="fa fa-box me-1 text-secondary"></i>${evt.event_detail.length > 25 ? evt.event_detail.substring(0, 25) + '...' : evt.event_detail}</small>`;
                            } else if (evt.event_name === 'purchase') {
                                detailHtml = `<small class="d-block text-info mt-1 font-monospace fs-8"><i class="fa fa-receipt me-1 text-success"></i>${evt.event_detail}</small>`;
                            } else {
                                detailHtml = `<small class="d-block text-info mt-1 font-monospace fs-8">${evt.event_detail}</small>`;
                            }
                        }

                        let customerBadge = evt.customer_id ? `<span class="badge bg-purple text-white ms-1" title="Logged-In Customer ID: ${evt.customer_id}"><i class="fa fa-user-check me-1"></i>#${evt.customer_id}</span>` : '';

                        let sourceBadge = evt.utm_source ? `<span class="badge bg-dark text-warning border border-warning border-opacity-25 px-2 py-1 fs-8 font-monospace" title="UTM Campaign: ${evt.utm_campaign || 'None'}"><i class="fa fa-bullhorn text-warning me-1"></i>${evt.utm_source}</span>` : `<span class="text-secondary fs-8 font-monospace">Direct</span>`;

                        let mediumBadge = evt.utm_medium ? `<span class="badge bg-dark text-info border border-info border-opacity-25 px-2 py-1 fs-8 font-monospace">${evt.utm_medium}</span>` : `<span class="text-secondary fs-8 font-monospace">Organic</span>`;

                        let revHtml = evt.revenue > 0 ? `<strong class="text-success">+${evt.revenue.toFixed(2)} SAR</strong>` : `<span class="text-secondary">-</span>`;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>
                                <span class="badge ${evt.badge_class} px-2.5 py-1 fs-7 rounded-2">${evt.event_name}</span>
                                ${detailHtml}
                            </td>
                            <td class="text-truncate" style="max-width: 250px;" title="${evt.page_url}">
                                <code class="analytics-code-path">${evt.page_path}</code>
                            </td>
                            <td>
                                <a href="javascript:void(0)" class="font-monospace text-info text-decoration-underline small fw-semibold" onclick="loadVisitorJourney('${evt.visitor_id}')" title="Click to view Clickstream Timeline">
                                    ${evt.visitor_id_short}...
                                </a>
                                ${customerBadge}
                            </td>
                            <td>${sourceBadge}</td>
                            <td>${mediumBadge}</td>
                            <td>
                                <span class="badge bg-dark text-info border border-info border-opacity-25 px-2 py-1">
                                    <i class="fa fa-map-marker-alt text-danger me-1"></i> ${evt.city ? evt.city + ', ' : ''}${evt.country}
                                </span>
                            </td>
                            <td>
                                <small class="text-capitalize text-light"><i class="fa fa-laptop me-1 text-secondary"></i> ${evt.device_type} (${evt.os})</small>
                            </td>
                            <td>${revHtml}</td>
                            <td>
                                <small class="text-secondary" title="${evt.created_at_str}">${evt.created_at_human}</small>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    liveStreamOffset += data.events.length;
                    
                    // Re-apply column search filters if any exist
                    filterLiveStreamColumns();

                    if (data.events.length < 50) {
                        btn.disabled = true;
                        btn.className = 'btn btn-sm btn-dark text-secondary px-4 py-1.5 fs-8 disabled';
                        btn.innerHTML = '<i class="fa fa-check-circle me-1"></i> All Events Loaded';
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                }
            } else {
                btn.disabled = true;
                btn.className = 'btn btn-sm btn-dark text-secondary px-4 py-1.5 fs-8 disabled';
                btn.innerHTML = '<i class="fa fa-check-circle me-1"></i> No More Events';
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Failed to load more events. Please try again.');
        });
}

function sendRecoverySms(btn, sessionId, phone) {
    if (!confirm(`Are you sure you want to send a Cart Recovery SMS to ${phone}?`)) return;

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Sending...';

    fetch('{{ route("analytics.send-recovery-sms") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            session_id: sessionId,
            phone: phone
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            btn.className = 'btn btn-sm btn-success text-dark disabled';
            btn.innerHTML = '<i class="fa fa-check me-1"></i> SMS Sent';
            alert(data.message);
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Error: ' + (data.message || 'Failed to send SMS'));
        }
    })
    .catch(err => {
        console.error(err);
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Network error occurred while sending recovery SMS.');
    });
}
</script>
@endsection
