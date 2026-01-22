@php
    $filters = $contentdata['filters'] ?? [];
    $annotators = $contentdata['annotators'] ?? collect();
    $packages = $contentdata['packages'] ?? collect();
    $payload = $contentdata['initialPayload'] ?? [];
    $summary = $payload['summary'] ?? [];
    $perUser = collect($payload['per_user'] ?? []);
    $perPackage = collect($payload['per_package'] ?? []);
    $timeline = $payload['timeline'] ?? ['labels' => [], 'session_counts' => [], 'annotation_totals' => []];
    $hourly = $payload['hourly'] ?? ['labels' => [], 'counts' => [], 'peak_hour' => null];
    $recentSessions = collect($payload['recent_sessions'] ?? []);
    $endpoint = $contentdata['endpoint'] ?? route('reports.working');
    $maxPackageSessions = max($perPackage->pluck('total_sessions')->all() ?: [0]);
@endphp

<style>
    .report-hero {
        background: linear-gradient(120deg, #1d1f3b, #3f2b96, #a8c0ff);
        border-radius: 1rem;
        padding: 2rem;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .report-hero::after {
        content: '';
        position: absolute;
        inset: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 0.85rem;
        pointer-events: none;
    }
    .report-chip {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 999px;
        padding: 0.25rem 0.75rem;
        font-size: 0.85rem;
        margin-right: 0.5rem;
    }
    .metric-card {
        border-radius: 0.85rem;
        border: 1px solid rgba(105, 108, 255, 0.15);
        box-shadow: 0 15px 40px rgba(15, 23, 42, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .metric-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 55px rgba(15, 23, 42, 0.12);
    }
    .metric-label {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.75rem;
        color: #8286a3;
    }
    .metric-value {
        font-size: 2rem;
        font-weight: 600;
        color: #2f2b43;
    }
    .metric-subtle {
        font-size: 0.9rem;
        color: #7a7f9a;
    }
    .filter-pill {
        border-radius: 999px;
        background: #f4f5fb;
        padding: 0.4rem 0.9rem;
    }
    .toggle-pill button {
        border: none;
        padding: 0.4rem 1.2rem;
        border-radius: 999px;
        font-weight: 600;
        background: transparent;
        color: #7a7f9a;
        transition: background 0.2s ease, color 0.2s ease;
    }
    .toggle-pill button.active {
        background: #7367f0;
        color: #fff;
        box-shadow: 0 8px 20px rgba(115, 103, 240, 0.35);
    }
    .package-progress {
        height: 6px;
        border-radius: 999px;
        background: rgba(115, 103, 240, 0.12);
        overflow: hidden;
    }
    .package-progress span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #38bdf8, #6366f1, #8b5cf6);
    }
    .insights-list li + li {
        margin-top: 0.35rem;
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="report-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="report-chip mb-2">Administrator Only</div>
                <h2 class="mb-2">Working Session Intelligence</h2>
                <p class="mb-0">Explore annotator rhythms, surface bottlenecks, and spot coaching opportunities with live session telemetry.</p>
            </div>
            <div class="text-lg-end">
                <div class="metric-label text-white-50">Active Range</div>
                <div class="fs-4 fw-semibold">{{ $summary['range_label'] ?? '—' }}</div>
                <div class="text-white-50">{{ $summary['range_days'] ?? 0 }} days &middot; Peak hour {{ isset($summary['peak_hour']) ? sprintf('%02d:00', $summary['peak_hour']) : 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header border-bottom">
            <h5 class="mb-0">Tune your view</h5>
            <small class="text-muted">Combine filters to focus on specific annotators, packages, and time windows.</small>
        </div>
        <div class="card-body">
            <form id="workingReportFilters" action="{{ $endpoint }}" method="GET" class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="start_date">Start date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $filters['start'] ?? '' }}" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="end_date">End date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $filters['end'] ?? '' }}" />
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="user_ids">Annotators</label>
                    <select name="user_ids[]" id="user_ids" class="form-select" multiple size="4">
                        @foreach($annotators as $annotator)
                            <option value="{{ $annotator->id }}" {{ in_array($annotator->id, $filters['user_ids'] ?? []) ? 'selected' : '' }}>
                                {{ $annotator->name }} ({{ $annotator->username }})
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Hold Cmd/Ctrl to multi-select.</small>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="package_ids">Packages</label>
                    <select name="package_ids[]" id="package_ids" class="form-select" multiple size="4">
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}" {{ in_array($package->id, $filters['package_ids'] ?? []) ? 'selected' : '' }}>
                                {{ $package->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-6">
                    <label class="form-label d-block">Grouping</label>
                    <div class="toggle-pill bg-light d-inline-flex p-1">
                        <button type="button" class="active" data-grouping="user">Annotators</button>
                        <button type="button" data-grouping="package">Packages</button>
                    </div>
                    <input type="hidden" name="grouping" id="groupingInput" value="{{ $filters['grouping'] ?? 'user' }}">
                </div>
                <div class="col-12 col-lg-6 text-lg-end">
                    <button type="submit" class="btn btn-primary" id="applyFiltersBtn">
                        <span class="ti ti-sliders me-1"></span>Apply filters
                    </button>
                    <noscript>
                        <button type="submit" class="btn btn-outline-secondary mt-2">Reload report</button>
                    </noscript>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-1">
        <div class="col-12 col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="metric-label">Sessions</div>
                    <div class="metric-value" id="summarySessions">{{ number_format($summary['total_sessions'] ?? 0) }}</div>
                    <div class="metric-subtle">Tracked in this window</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="metric-label">Annotations</div>
                    <div class="metric-value" id="summaryAnnotations">{{ number_format($summary['total_annotations'] ?? 0) }}</div>
                    <div class="metric-subtle">{{ number_format($summary['avg_annotations_per_session'] ?? 0, 1) }} avg / session</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="metric-label">Avg duration</div>
                    <div class="metric-value" id="summaryDuration">{{ number_format($summary['avg_session_duration'] ?? 0, 1) }}m</div>
                    <div class="metric-subtle">Across all sessions</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card metric-card h-100">
                <div class="card-body">
                    <div class="metric-label">Active annotators</div>
                    <div class="metric-value" id="summaryAnnotators">{{ number_format($summary['active_annotators'] ?? 0) }}</div>
                    <div class="metric-subtle">Peak hour <span id="summaryPeakHour">{{ isset($summary['peak_hour']) ? sprintf('%02d:00', $summary['peak_hour']) : 'N/A' }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Timeline pulse</h6>
                        <small class="text-muted">Sessions vs annotations per day</small>
                    </div>
                        <div class="filter-pill" id="rangeLabel">{{ $summary['range_label'] ?? '' }}</div>
                </div>
                        <div class="card-body">
                    <canvas id="timelineChart" height="240"></canvas>
                    @if(empty($timeline['labels']))
                        <p class="text-muted text-center mt-3 mb-0">No session activity inside this range.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xxl-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Habit snapshots</h6>
                        <small class="text-muted">Auto-generated sentences</small>
                    </div>
                    <span class="badge bg-label-primary text-uppercase">Live</span>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled insights-list" id="insightList">
                        @forelse($summary['insights'] ?? [] as $insight)
                            <li>
                                <strong>{{ $insight['label'] }}</strong>
                                <div class="text-muted">{{ $insight['detail'] }}</div>
                            </li>
                        @empty
                            <li class="text-muted">Not enough data to describe habits yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-0">Annotator comparison</h6>
                        <small class="text-muted">Switch metrics to reshape the chart.</small>
                    </div>
                    <div class="toggle-pill bg-light d-inline-flex p-1" id="metricToggle">
                        <button type="button" class="active" data-metric="total_sessions">Sessions</button>
                        <button type="button" data-metric="avg_duration_minutes">Avg duration</button>
                        <button type="button" data-metric="avg_annotations">Avg data count</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="userMetricChart" height="260"></canvas>
                    @if($perUser->isEmpty())
                        <p class="text-muted text-center mt-3 mb-0">No annotators to plot for this slice.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Working-hour fingerprint</h6>
                        <small class="text-muted">Track focused minutes per hour and overlay an annotator to compare.</small>
                    </div>
                    <select class="form-select form-select-sm w-auto" id="habitUserSelect">
                        <option value="">All annotators</option>
                        @foreach($perUser as $userStat)
                            <option value="{{ $userStat['user_id'] }}">{{ $userStat['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="260"></canvas>
                    @if(empty($hourly['minutes']))
                        <p class="text-muted text-center mt-3 mb-0">Hour-by-hour data unavailable for this range.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12 col-xxl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Annotator habits table</h6>
                    <small class="text-muted">Peek into averages and peaks.</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Annotator</th>
                                <th>Sessions</th>
                                <th>Avg data</th>
                                <th>Avg duration</th>
                                <th>Peak hour</th>
                            </tr>
                        </thead>
                        <tbody id="annotatorTableBody">
                            @forelse($perUser as $userStat)
                                <tr>
                                    <td>
                                        <strong>{{ $userStat['name'] }}</strong>
                                        <div class="text-muted small">{{ $userStat['username'] }}</div>
                                    </td>
                                    <td>{{ number_format($userStat['total_sessions']) }}</td>
                                    <td>{{ number_format($userStat['avg_annotations'], 1) }}</td>
                                    <td>{{ number_format($userStat['avg_duration_minutes'], 1) }}m</td>
                                    <td>{{ isset($userStat['peak_start_hour']) ? sprintf('%02d:00', $userStat['peak_start_hour']) : 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No annotator metrics available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xxl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Recent sessions</h6>
                    <small class="text-muted">Latest 12 entries</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Annotator</th>
                                <th>Package</th>
                                <th>Duration</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody id="recentSessionsBody">
                            @forelse($recentSessions as $session)
                                <tr>
                                    <td>{{ $session['user'] }}</td>
                                    <td>{{ $session['package'] }}</td>
                                    <td>{{ number_format($session['duration_minutes'] ?? 0) }}m</td>
                                    <td>{{ $session['annotations'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No sessions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Package throughput</h6>
                    <small class="text-muted">Ranked by total sessions.</small>
                </div>
                <div class="card-body">
                    <div class="row" id="packageStack">
                        @forelse($perPackage as $index => $package)
                            <div class="col-12 col-md-6 col-xl-4 mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $package['name'] }}</strong>
                                        <div class="text-muted small">{{ number_format($package['total_sessions']) }} sessions &middot; {{ number_format($package['unique_annotators']) }} annotators</div>
                                    </div>
                                    <span class="badge bg-label-primary">{{ number_format($package['total_annotations']) }}</span>
                                </div>
                                <div class="package-progress mt-2">
                                    <span style="width: {{ $maxPackageSessions ? (($package['total_sessions'] / $maxPackageSessions) * 100) : 0 }}%;"></span>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-muted text-center">No package activity for this range.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const state = {
            endpoint: @json($endpoint),
            payload: @json($payload),
            currentMetric: 'total_sessions',
            charts: {
                timeline: null,
                userMetric: null,
                hourly: null,
            }
        };

        const ChartLib = window.Chart;
        const filterForm = document.getElementById('workingReportFilters');
        const applyBtn = document.getElementById('applyFiltersBtn');
        const groupingButtons = document.querySelectorAll('[data-grouping]');
        const groupingInput = document.getElementById('groupingInput');
        const metricToggle = document.getElementById('metricToggle');
        const habitUserSelect = document.getElementById('habitUserSelect');

        const summaryEls = {
            sessions: document.getElementById('summarySessions'),
            annotations: document.getElementById('summaryAnnotations'),
            duration: document.getElementById('summaryDuration'),
            annotators: document.getElementById('summaryAnnotators'),
            peak: document.getElementById('summaryPeakHour'),
            rangeLabel: document.getElementById('rangeLabel')
        };

        function formatNumber(value, digits = 0) {
            const number = Number(value ?? 0);
            return number.toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits });
        }

        function setLoading(isLoading) {
            if (!applyBtn) {
                return;
            }
            applyBtn.disabled = isLoading;
            applyBtn.innerHTML = isLoading ? '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing' : '<span class="ti ti-sliders me-1"></span>Apply filters';
        }

        function updateSummary(summary) {
            summaryEls.sessions && (summaryEls.sessions.textContent = formatNumber(summary.total_sessions || 0));
            summaryEls.annotations && (summaryEls.annotations.textContent = formatNumber(summary.total_annotations || 0));
            summaryEls.duration && (summaryEls.duration.textContent = `${formatNumber(summary.avg_session_duration || 0, 1)}m`);
            summaryEls.annotators && (summaryEls.annotators.textContent = formatNumber(summary.active_annotators || 0));
            summaryEls.peak && (summaryEls.peak.textContent = summary.peak_hour !== null && summary.peak_hour !== undefined ? `${String(summary.peak_hour).padStart(2, '0')}:00` : '—');
            summaryEls.rangeLabel && (summaryEls.rangeLabel.textContent = summary.range_label || '');

            const insightList = document.getElementById('insightList');
            if (insightList) {
                if (Array.isArray(summary.insights) && summary.insights.length) {
                    insightList.innerHTML = summary.insights.map((insight) => `
                        <li>
                            <strong>${insight.label}</strong>
                            <div class="text-muted">${insight.detail}</div>
                        </li>
                    `).join('');
                } else {
                    insightList.innerHTML = '<li class="text-muted">Not enough data to describe habits yet.</li>';
                }
            }
        }

        function createTimelineChart(payload) {
            if (!ChartLib) {
                return;
            }
            const ctx = document.getElementById('timelineChart');
            if (!ctx) {
                return;
            }
            if (state.charts.timeline) {
                state.charts.timeline.destroy();
            }
            state.charts.timeline = new ChartLib(ctx, {
                type: 'bar',
                data: {
                    labels: payload.timeline.labels || [],
                    datasets: [
                        {
                            type: 'line',
                            label: 'Annotations',
                            data: payload.timeline.annotation_totals || [],
                            borderColor: '#34c38f',
                            backgroundColor: 'rgba(52,195,143,0.15)',
                            tension: 0.4,
                            borderWidth: 3,
                            yAxisID: 'y1',
                        },
                        {
                            label: 'Sessions',
                            data: payload.timeline.session_counts || [],
                            backgroundColor: '#7367f0',
                            borderRadius: 8,
                            yAxisID: 'y',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false }
                        }
                    },
                    plugins: {
                        legend: { display: true }
                    }
                }
            });
        }

        function createUserMetricChart(payload) {
            if (!ChartLib) {
                return;
            }
            const ctx = document.getElementById('userMetricChart');
            if (!ctx) {
                return;
            }
            const labels = (payload.per_user || []).map((item) => item.name);
            const data = (payload.per_user || []).map((item) => item[state.currentMetric] || 0);

            if (state.charts.userMetric) {
                state.charts.userMetric.destroy();
            }

            state.charts.userMetric = new ChartLib(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        {
                            label: state.currentMetric.replace(/_/g, ' '),
                            data,
                            backgroundColor: '#ffa600',
                            borderRadius: 12,
                            barThickness: 24,
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    scales: { x: { beginAtZero: true } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => `${formatNumber(context.parsed.x, 1)} ${state.currentMetric.includes('duration') ? 'min' : ''}`
                            }
                        }
                    }
                }
            });
        }

        function createHourlyChart(payload, userHistogram = null) {
            if (!ChartLib) {
                return;
            }
            const ctx = document.getElementById('hourlyChart');
            if (!ctx) {
                return;
            }
            if (state.charts.hourly) {
                state.charts.hourly.destroy();
            }

            const baseMinutes = (payload.hourly && (payload.hourly.minutes || payload.hourly.counts)) || [];
            const datasets = [
                {
                    label: 'Minutes active',
                    data: baseMinutes,
                    backgroundColor: 'rgba(99,102,241,0.6)',
                    borderColor: '#6366f1',
                    fill: true,
                    tension: 0.3,
                }
            ];

            if (Array.isArray(userHistogram)) {
                datasets.push({
                    label: 'Selected annotator',
                    data: userHistogram,
                    backgroundColor: 'rgba(14,165,233,0.2)',
                    borderColor: '#0ea5e9',
                    borderDash: [6, 3],
                    fill: false,
                    tension: 0.3,
                });
            }

            state.charts.hourly = new ChartLib(ctx, {
                type: 'line',
                data: {
                    labels: payload.hourly.labels || [],
                    datasets
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Minutes' }
                        }
                    },
                    plugins: {
                        legend: { display: true },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed.y ?? 0;
                                    return `${context.dataset.label}: ${formatNumber(value, 1)} min`;
                                }
                            }
                        }
                    }
                }
            });
        }

        function refreshTables(payload) {
            const tableBody = document.getElementById('annotatorTableBody');
            if (tableBody) {
                if (Array.isArray(payload.per_user) && payload.per_user.length) {
                    tableBody.innerHTML = payload.per_user.map((user) => `
                        <tr>
                            <td>
                                <strong>${user.name}</strong>
                                <div class="text-muted small">${user.username}</div>
                            </td>
                            <td>${formatNumber(user.total_sessions)}</td>
                            <td>${formatNumber(user.avg_annotations, 1)}</td>
                            <td>${formatNumber(user.avg_duration_minutes, 1)}m</td>
                            <td>${user.peak_start_hour === null ? '—' : `${String(user.peak_start_hour).padStart(2, '0')}:00`}</td>
                        </tr>
                    `).join('');
                } else {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No annotator metrics available.</td></tr>';
                }
            }

            const recentBody = document.getElementById('recentSessionsBody');
            if (recentBody) {
                if (Array.isArray(payload.recent_sessions) && payload.recent_sessions.length) {
                    recentBody.innerHTML = payload.recent_sessions.map((session) => `
                        <tr>
                            <td>${session.user}</td>
                            <td>${session.package}</td>
                            <td>${formatNumber(session.duration_minutes)}m</td>
                            <td>${session.annotations}</td>
                        </tr>
                    `).join('');
                } else {
                    recentBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No sessions found.</td></tr>';
                }
            }

            const packageStack = document.getElementById('packageStack');
            if (packageStack) {
                if (Array.isArray(payload.per_package) && payload.per_package.length) {
                    const maxSessions = Math.max(...payload.per_package.map((item) => item.total_sessions || 0), 1);
                    packageStack.innerHTML = payload.per_package.map((pkg) => `
                        <div class="col-12 col-md-6 col-xl-4 mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${pkg.name}</strong>
                                    <div class="text-muted small">${formatNumber(pkg.total_sessions)} sessions · ${formatNumber(pkg.unique_annotators)} annotators</div>
                                </div>
                                <span class="badge bg-label-primary">${formatNumber(pkg.total_annotations)}</span>
                            </div>
                            <div class="package-progress mt-2">
                                <span style="width: ${(pkg.total_sessions / maxSessions) * 100}%;"></span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    packageStack.innerHTML = '<div class="col-12 text-muted text-center">No package activity for this range.</div>';
                }
            }

            if (habitUserSelect) {
                const currentValue = habitUserSelect.value;
                habitUserSelect.innerHTML = '<option value="">All annotators</option>';
                (payload.per_user || []).forEach((user) => {
                    const option = document.createElement('option');
                    option.value = user.user_id;
                    option.textContent = user.name;
                    habitUserSelect.appendChild(option);
                });
                habitUserSelect.value = currentValue;
            }
        }

        function getSelectedHistogram() {
            if (!habitUserSelect || !habitUserSelect.value) {
                return null;
            }
            const userId = Number(habitUserSelect.value);
            const target = (state.payload.per_user || []).find((user) => Number(user.user_id) === userId);
            return target ? target.hour_histogram : null;
        }

        function wireGroupingToggle() {
            groupingButtons.forEach((button) => {
                button.classList.toggle('active', button.dataset.grouping === (groupingInput?.value || 'user'));
                button.addEventListener('click', () => {
                    groupingButtons.forEach((btn) => btn.classList.remove('active'));
                    button.classList.add('active');
                    if (groupingInput) {
                        groupingInput.value = button.dataset.grouping;
                    }
                });
            });
        }

        function wireMetricToggle() {
            if (!metricToggle) {
                return;
            }
            metricToggle.querySelectorAll('button').forEach((button) => {
                button.classList.toggle('active', button.dataset.metric === state.currentMetric);
                button.addEventListener('click', () => {
                    state.currentMetric = button.dataset.metric;
                    metricToggle.querySelectorAll('button').forEach((btn) => btn.classList.remove('active'));
                    button.classList.add('active');
                    createUserMetricChart(state.payload);
                });
            });
        }

        function fetchReport(params) {
            const query = new URLSearchParams(params).toString();
            return fetch(`${state.endpoint}?${query}`, {
                headers: { 'Accept': 'application/json' }
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to refresh report');
                }
                return response.json();
            });
        }

        function handleFormSubmit(event) {
            if (!filterForm) {
                return;
            }
            event.preventDefault();
            const formData = new FormData(filterForm);
            setLoading(true);
            fetchReport(formData)
                .then((payload) => {
                    state.payload = payload;
                    updateSummary(payload.summary || {});
                    refreshTables(payload);
                    createTimelineChart(payload);
                    createUserMetricChart(payload);
                    createHourlyChart(payload, getSelectedHistogram());
                })
                .catch((error) => {
                    console.error(error);
                    if (window.Swal) {
                        window.Swal.fire('Unable to refresh', 'Please adjust your filters and try again.', 'error');
                    } else {
                        alert('Unable to refresh report.');
                    }
                })
                .finally(() => setLoading(false));
        }

        function handleHabitSelect() {
            if (!state.payload.hourly) {
                return;
            }
            createHourlyChart(state.payload, getSelectedHistogram());
        }

        function init() {
            updateSummary(state.payload.summary || {});
            createTimelineChart(state.payload);
            createUserMetricChart(state.payload);
            createHourlyChart(state.payload, getSelectedHistogram());
            refreshTables(state.payload);
            wireGroupingToggle();
            wireMetricToggle();

            if (filterForm) {
                filterForm.addEventListener('submit', handleFormSubmit);
            }
            if (habitUserSelect) {
                habitUserSelect.addEventListener('change', handleHabitSelect);
            }
        }

        init();
    })();
</script>
@endpush
