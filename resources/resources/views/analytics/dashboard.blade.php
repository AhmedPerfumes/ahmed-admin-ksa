@extends($layout ?? BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
<div class="container-fluid py-3">
    <!-- Header & Date Filter -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fa fa-chart-line text-primary me-2"></i> Custom First-Party Analytics
            </h1>
            <p class="text-muted small mb-0">Real-time visitor telemetry, ecommerce conversion metrics, and server-aggregated stats.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <div class="btn-group shadow-sm" role="group" aria-label="Date Range Filter">
                <a href="{{ route('analytics.dashboard', ['range' => '24h']) }}" class="btn btn-sm {{ $range === '24h' ? 'btn-primary' : 'btn-outline-secondary' }}">Last 24 Hours</a>
                <a href="{{ route('analytics.dashboard', ['range' => '7d']) }}" class="btn btn-sm {{ $range === '7d' ? 'btn-primary' : 'btn-outline-secondary' }}">Last 7 Days</a>
                <a href="{{ route('analytics.dashboard', ['range' => '30d']) }}" class="btn btn-sm {{ $range === '30d' ? 'btn-primary' : 'btn-outline-secondary' }}">Last 30 Days</a>
                <a href="{{ route('analytics.dashboard', ['range' => '90d']) }}" class="btn btn-sm {{ $range === '90d' ? 'btn-primary' : 'btn-outline-secondary' }}">Last 90 Days</a>
            </div>
        </div>
    </div>

    <!-- KPI Cards Row -->
    <div class="row g-3 mb-4">
        <!-- Total Revenue -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Revenue</div>
                            <div class="h3 mb-0 fw-bold text-gray-800">{{ number_format($totalRevenue, 2) }} SAR</div>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                            <i class="fa fa-coins fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unique Visitors -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">Unique Visitors</div>
                            <div class="h3 mb-0 fw-bold text-gray-800">{{ number_format($uniqueVisitors) }}</div>
                        </div>
                        <div class="bg-success-subtle text-success p-3 rounded-circle">
                            <i class="fa fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversion Rate -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">Conversion Rate</div>
                            <div class="h3 mb-0 fw-bold text-gray-800">{{ number_format($conversionRate, 2) }}%</div>
                        </div>
                        <div class="bg-info-subtle text-info p-3 rounded-circle">
                            <i class="fa fa-percentage fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Abandonment Rate -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-white border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">Cart Abandonment Rate</div>
                            <div class="h3 mb-0 fw-bold text-gray-800">{{ number_format($cartAbandonmentRate, 2) }}%</div>
                        </div>
                        <div class="bg-warning-subtle text-warning p-3 rounded-circle">
                            <i class="fa fa-shopping-cart fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1: Revenue Trend & Conversion Funnel -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary"><i class="fa fa-chart-area me-2"></i> Revenue Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueTrendChart" height="260"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary"><i class="fa fa-filter me-2"></i> Conversion Funnel</h6>
                </div>
                <div class="card-body">
                    <canvas id="funnelChart" height="260"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: Top Products, Traffic Sources, Device Breakdown -->
    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary"><i class="fa fa-box me-2"></i> Top 10 Viewed Products</h6>
                </div>
                <div class="card-body">
                    <canvas id="topProductsChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary"><i class="fa fa-share-alt me-2"></i> Traffic Sources</h6>
                </div>
                <div class="card-body">
                    <canvas id="trafficSourcesChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary"><i class="fa fa-desktop me-2"></i> Device Breakdown</h6>
                </div>
                <div class="card-body">
                    <canvas id="deviceChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Event Feed Table -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fa fa-stream me-2 text-danger"></i> Live Event Stream <span class="badge bg-danger rounded-pill ms-1">30 Most Recent</span>
                    </h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Event</th>
                                <th>Page Path</th>
                                <th>Visitor ID</th>
                                <th>Device / OS</th>
                                <th>Revenue</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentEvents as $event)
                            @php
                                $badgeClass = match($event->event_name) {
                                    'purchase' => 'bg-success',
                                    'begin_checkout', 'add_payment_info' => 'bg-warning text-dark',
                                    'add_to_cart' => 'bg-info text-dark',
                                    'view_item' => 'bg-primary',
                                    'page_view' => 'bg-secondary',
                                    default => 'bg-dark'
                                };
                                $parsedUrl = parse_url($event->page_url, PHP_URL_PATH) ?? $event->page_url;
                            @endphp
                            <tr>
                                <td>
                                    <span class="badge {{ $badgeClass }} px-2 py-1 fs-7 fw-semibold">
                                        {{ $event->event_name }}
                                    </span>
                                </td>
                                <td class="text-truncate" style="max-width: 250px;" title="{{ $event->page_url }}">
                                    <code>{{ $parsedUrl ?: '/' }}</code>
                                </td>
                                <td>
                                    <span class="font-monospace text-muted small">{{ substr($event->visitor_id, 0, 14) }}...</span>
                                </td>
                                <td>
                                    <small class="text-capitalize"><i class="fa fa-laptop me-1 text-muted"></i> {{ $event->device_type ?? 'desktop' }} ({{ $event->os ?? 'OS' }})</small>
                                </td>
                                <td>
                                    @if($event->revenue > 0)
                                        <strong class="text-success">+{{ number_format($event->revenue, 2) }} SAR</strong>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted" title="{{ $event->created_at }}">
                                        {{ $event->created_at ? $event->created_at->diffForHumans() : '-' }}
                                    </small>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fa fa-info-circle me-1"></i> No events captured yet in this timeframe.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
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
                    backgroundColor: ['#36b9cc', '#4e73df', '#f6c23e', '#e74a3b', '#1cc88a']
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    // 3. Top Products Chart
    const topProdCanvas = document.getElementById('topProductsChart');
    if (topProdCanvas) {
        const topProds = @json($topProducts);
        new Chart(topProdCanvas, {
            type: 'bar',
            data: {
                labels: Object.keys(topProds),
                datasets: [{
                    label: 'Views',
                    data: Object.values(topProds),
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } } },
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // 4. Traffic Sources Chart
    const trafficCanvas = document.getElementById('trafficSourcesChart');
    if (trafficCanvas) {
        const trafficData = @json($trafficSources);
        new Chart(trafficCanvas, {
            type: 'doughnut',
            data: {
                labels: Object.keys(trafficData),
                datasets: [{
                    data: Object.values(trafficData),
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }

    // 5. Device Breakdown Chart
    const deviceCanvas = document.getElementById('deviceChart');
    if (deviceCanvas) {
        const deviceData = @json($deviceBreakdown);
        new Chart(deviceCanvas, {
            type: 'doughnut',
            data: {
                labels: Object.keys(deviceData).map(d => d.toUpperCase()),
                datasets: [{
                    data: Object.values(deviceData),
                    backgroundColor: ['#4e73df', '#1cc88a', '#f6c23e']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
            }
        });
    }
});
</script>
@endsection
