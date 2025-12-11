@php
    $data = $dashboardData ?? [];
    $rangeDays = $data['rangeDays'] ?? 30;
    $rangeStartLabel = $data['rangeStartLabel'] ?? '';
    $trendChart = $data['trendChart'] ?? ['labels' => [], 'series' => []];
    $contributorsChart = $data['contributorsChart'] ?? ['labels' => [], 'series' => []];
    $categoryChart = $data['categoryChart'] ?? ['labels' => [], 'series' => []];
    $recentSessions = $data['recentSessions'] ?? collect();
    $personalStats = $data['personalStats'] ?? [];
    $globalStats = $data['globalStats'] ?? [];
    $suggestions = $data['suggestions'] ?? ['personal' => [], 'admin' => []];
    $rangeOptions = $data['rangeOptions'] ?? [7, 30, 90, 180];
@endphp

@push('styles')
<style>
    .insight-card-min-height {
        min-height: 320px;
    }
    .chart-wrapper {
        min-height: 300px;
    }
</style>
@endpush

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h4 class="mb-1">Insight Dashboard</h4>
            <small class="text-muted">Showing activity since {{ $rangeStartLabel }} (last {{ $rangeDays }} days)</small>
        </div>
        <div class="col-auto">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <label for="range" class="text-muted mb-0">Range</label>
                <select name="range" id="range" class="form-select" onchange="this.form.submit()">
                    @foreach($rangeOptions as $option)
                        <option value="{{ $option }}" {{ $option === $rangeDays ? 'selected' : '' }}>
                            {{ $option < 30 ? $option . ' days' : ($option === 30 ? '30 days' : ($option === 90 ? 'Quarter' : '6 months')) }}
                        </option>
                    @endforeach
                </select>
                <noscript>
                    <button class="btn btn-primary" type="submit">Apply</button>
                </noscript>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Your Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Annotations this period</span>
                        <strong>{{ number_format($personalStats['my_annotations'] ?? 0) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Avg. per day</span>
                        <strong>{{ number_format($personalStats['avg_per_day'] ?? 0, 1) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Open sessions</span>
                        <strong>{{ number_format($personalStats['open_sessions'] ?? 0) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Last activity</span>
                        <strong>{{ optional($personalStats['last_annotation'] ?? null)->diffForHumans() ?? '—' }}</strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Global Snapshot</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Total annotations</span>
                        <strong>{{ number_format($globalStats['total_annotations'] ?? 0) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">This range</span>
                        <strong>{{ number_format($globalStats['range_annotations'] ?? 0) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Active annotators</span>
                        <strong>{{ number_format($globalStats['active_annotators'] ?? 0) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Active packages</span>
                        <strong>{{ number_format($globalStats['active_packages'] ?? 0) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Operational Signals</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Annotations / package</span>
                        <strong>{{ number_format($globalStats['annotations_per_package'] ?? 0, 1) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted">Open sessions now</span>
                        <strong>{{ number_format($globalStats['open_sessions'] ?? 0) }}</strong>
                    </div>
                    <p class="text-muted small mb-0">Use these signals to detect bottlenecks and rebalance assignments early.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-12 col-xxl-8">
            <div class="card insight-card-min-height">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Annotation Velocity</h6>
                    <small class="text-muted">Daily totals</small>
                </div>
                <div class="card-body">
                    <div id="annotationTrendChart" class="chart-wrapper"></div>
                    @if(empty($trendChart['labels']))
                        <p class="text-muted text-center mt-3 mb-0">No annotations recorded in this range.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xxl-4">
            <div class="card insight-card-min-height">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Category Mix</h6>
                    <small class="text-muted">Top 8 labels</small>
                </div>
                <div class="card-body">
                    <div id="categoryDistributionChart" class="chart-wrapper"></div>
                    @if(empty($categoryChart['labels']))
                        <p class="text-muted text-center mt-3 mb-0">No category selections available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-12 col-xl-6">
            <div class="card insight-card-min-height">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Top Contributors</h6>
                    <small class="text-muted">{{ $rangeDays }}-day leaderboard</small>
                </div>
                <div class="card-body">
                    <div id="contributorsChart" class="chart-wrapper"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card insight-card-min-height">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent Sessions</h6>
                    <small class="text-muted">Across all users</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Package</th>
                                    <th>Started</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentSessions as $session)
                                    <tr>
                                        <td>{{ $session->user->name ?? '—' }}</td>
                                        <td>{{ $session->package->name ?? '—' }}</td>
                                        <td>{{ optional($session->created_at)->diffForHumans() }}</td>
                                        <td>
                                            @if($session->ended_at)
                                                <span class="badge bg-label-secondary">Closed</span>
                                            @else
                                                <span class="badge bg-label-success">Active</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No session logs in this range.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Suggested Next Metrics</h6>
                </div>
                <div class="card-body row">
                    <div class="col-md-6">
                        <h6 class="text-muted">For Annotators</h6>
                        <ul class="mb-0">
                            @foreach($suggestions['personal'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">For Administrators</h6>
                        <ul class="mb-0">
                            @foreach($suggestions['admin'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.dashboardInsights = {
        trend: @json($trendChart),
        categories: @json($categoryChart),
        contributors: @json($contributorsChart)
    };

    (function () {
        const charts = window.dashboardInsights || {};
        const hasApex = typeof window.ApexCharts !== 'undefined';
        if (!hasApex) {
            return;
        }

        const colors = window.getComputedStyle(document.documentElement);
        const primary = colors.getPropertyValue('--bs-primary') || '#696cff';
        const info = colors.getPropertyValue('--bs-info') || '#03c3ec';
        const gray = '#b6bee3';

        const trendOptions = {
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            stroke: { curve: 'smooth', width: 3 },
            dataLabels: { enabled: false },
            series: [{ name: 'Annotations', data: charts.trend.series || [] }],
            xaxis: {
                categories: charts.trend.labels || [],
                labels: { rotate: -45 }
            },
            colors: [primary],
            fill: { type: 'gradient', gradient: { shadeIntensity: 0.4, opacityFrom: 0.6, opacityTo: 0.05 } }
        };

        const categoryOptions = {
            chart: { type: 'donut', height: 300 },
            labels: charts.categories.labels || [],
            series: charts.categories.series && charts.categories.series.length ? charts.categories.series : [1],
            colors: ['#7367f0', '#28c76f', '#ff9f43', '#ea5455', '#00cfe8', '#ffe700', '#1e40af', '#9f8bff'],
            legend: { position: 'bottom' },
            dataLabels: { enabled: true }
        };

        const contributorsOptions = {
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 6 } },
            series: [{ name: 'Annotations', data: charts.contributors.series || [] }],
            xaxis: { categories: charts.contributors.labels || [] },
            colors: [info]
        };

        const trendEl = document.querySelector('#annotationTrendChart');
        const categoryEl = document.querySelector('#categoryDistributionChart');
        const contributorsEl = document.querySelector('#contributorsChart');

        if (trendEl) {
            new ApexCharts(trendEl, trendOptions).render();
        }
        if (categoryEl) {
            new ApexCharts(categoryEl, categoryOptions).render();
        }
        if (contributorsEl) {
            new ApexCharts(contributorsEl, contributorsOptions).render();
        }
    })();
</script>
@endpush
