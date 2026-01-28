@php
    $packages = collect($contentdata['packages'] ?? []);
    $metrics = $contentdata['metrics'] ?? [
        'packageCount' => 0,
        'annotatedPackages' => 0,
        'rowCount' => 0,
        'coverage' => 0,
    ];
    $exportEndpoint = $contentdata['exportEndpoint'] ?? '#';
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600&family=Manrope:wght@500;700&display=swap');

    .package-export-hero,
    .export-stat,
    .selected-pill,
    .package-name {
        font-family: 'Space Grotesk', 'Manrope', 'Segoe UI', sans-serif;
    }
    .package-export-hero {
        background: radial-gradient(circle at 10% 20%, #10121c 0%, #1e133d 35%, #42237a 75%);
        border-radius: 1.2rem;
        padding: 2.4rem;
        color: #f7f5ff;
        position: relative;
        overflow: hidden;
    }
    .package-export-hero::after {
        content: '';
        position: absolute;
        inset: 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 1rem;
        pointer-events: none;
    }
    .package-export-hero h2,
    .package-export-hero p {
        position: relative;
        z-index: 1;
    }
    .hero-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
        position: relative;
        z-index: 1;
    }
    .export-stat {
        background: rgba(13, 16, 32, 0.45);
        border-radius: 0.9rem;
        padding: 1rem 1.2rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .export-stat .label {
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.65);
    }
    .export-stat .value {
        font-size: 2rem;
        font-weight: 600;
        line-height: 1.2;
    }
    .package-table-card {
        border-radius: 1rem;
        border: 1px solid rgba(99, 102, 241, 0.15);
    }
    .coverage-track {
        height: 6px;
        border-radius: 999px;
        background: rgba(99, 102, 241, 0.15);
        overflow: hidden;
    }
    .coverage-track span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #93c5fd, #6366f1, #ec4899);
    }
    .selected-pill {
        border-radius: 999px;
        background: #f5f5fa;
        padding: 0.35rem 0.9rem;
        font-weight: 600;
        color: #4c1d95;
    }
    .export-alert {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 0.65rem;
        padding: 0.9rem 1rem;
        color: #9a3412;
    }
    .package-name {
        font-weight: 600;
        font-size: 1rem;
    }
    .package-meta {
        font-size: 0.85rem;
        color: #6b7280;
    }
    .data-detail-row {
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
    }
    .data-item {
        padding: 0.5rem 0.75rem;
        border-left: 3px solid #6366f1;
        background: white;
        border-radius: 0.4rem;
        margin-bottom: 0.5rem;
    }
    .data-item:last-child {
        margin-bottom: 0;
    }
    .data-id {
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        color: #6366f1;
        font-weight: 600;
    }
    .data-content {
        font-size: 0.9rem;
        color: #374151;
        margin-top: 0.25rem;
    }
    .expand-toggle {
        cursor: pointer;
        user-select: none;
        transition: transform 0.2s ease;
    }
    .expand-toggle.expanded {
        transform: rotate(90deg);
    }
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="package-export-hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-12 col-lg-6">
                <span class="badge bg-light text-dark mb-2">Admin export cockpit</span>
                <h2 class="mb-2">Ship annotated packages in one sweep</h2>
                <p class="mb-0">Pick any mix of packages, merge their annotated rows, and drop them into a CSV that includes each annotation label.</p>
            </div>
            <div class="col-12 col-lg-6">
                <div class="hero-stats">
                    <div class="export-stat">
                        <div class="label">Packages ready</div>
                        <div class="value">{{ number_format($metrics['packageCount'] ?? 0) }}</div>
                        <small>{{ number_format($metrics['annotatedPackages'] ?? 0) }} contain annotations</small>
                    </div>
                    <div class="export-stat">
                        <div class="label">Rows linked</div>
                        <div class="value">{{ number_format($metrics['rowCount'] ?? 0) }}</div>
                        <small>Across all listed packages</small>
                    </div>
                    <div class="export-stat">
                        <div class="label">Coverage</div>
                        <div class="value">{{ number_format($metrics['coverage'] ?? 0, 1) }}%</div>
                        <small>Annotated vs assigned data</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card package-table-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1">Packages</h5>
                <small class="text-muted">Use the checkboxes to blend multiple packages into the same download.</small>
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAllPackages" />
                    <label class="form-check-label" for="selectAllPackages">Select all</label>
                </div>
                <div class="selected-pill">
                    <span id="selectedPackagesCount">0</span> chosen
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ $exportEndpoint }}" id="packageExportForm">
                @csrf
                <div class="export-alert mb-3">
                    CSV output merges rows from every checked package. Annotation labels appear as a pipe-delimited list so multi-label items stay intact.
                </div>
                @error('package_ids')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th style="width: 30px;"></th>
                                <th>Package</th>
                                <th class="text-end">Assigned rows</th>
                                <th class="text-end">Annotated here</th>
                                <th style="width: 220px;">Coverage</th>
                                <th style="width: 200px;">Last annotation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $package)
                                <tr class="package-row-{{ $package['id'] }}">
                                    <td>
                                        <input type="checkbox" class="form-check-input package-checkbox" name="package_ids[]" value="{{ $package['id'] }}" />
                                    </td>
                                    <td>
                                        @if($package['annotated_rows'] > 0)
                                            <i class="ti ti-chevron-right expand-toggle" data-package-id="{{ $package['id'] }}" title="Show annotated data"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="package-name">{{ $package['name'] }}</div>
                                        <div class="package-meta">ID: {{ $package['id'] }} · {{ number_format($package['annotated_rows']) }} annotated</div>
                                    </td>
                                    <td class="text-end">{{ number_format($package['total_rows']) }}</td>
                                    <td class="text-end fw-semibold text-primary">{{ number_format($package['annotated_at_rows'] ?? 0) }}</td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small>{{ number_format($package['coverage'], 1) }}%</small>
                                            <small>{{ $package['total_rows'] ? number_format($package['annotated_rows']) . ' / ' . number_format($package['total_rows']) : '—' }}</small>
                                        </div>
                                        <div class="coverage-track mt-1">
                                            @php
                                                $coverageWidth = max(0, min(100, $package['coverage'] ?? 0));
                                            @endphp
                                            <span style="width: {{ $coverageWidth }}%;"></span>
                                        </div>
                                    </td>
                                    <td>
                                        @if(! empty($package['last_annotation_at']))
                                            <div>{{ $package['last_annotation_at']->diffForHumans() }}</div>
                                            <small class="text-muted">{{ $package['last_annotation_at']->format('M j, Y \a\t H:i') }}</small>
                                        @else
                                            <span class="text-muted">No activity yet</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($package['annotated_rows'] > 0 && !empty($package['annotated_data']))
                                    <tr class="data-detail-row data-detail-{{ $package['id'] }}" style="display: none;">
                                        <td colspan="7">
                                            <div class="p-3">
                                                <h6 class="mb-3 text-muted">Annotated data in this package ({{ count($package['annotated_data']) }} items)</h6>
                                                <div class="row g-2">
                                                    @foreach($package['annotated_data'] as $dataItem)
                                                        <div class="col-12 col-md-6 col-lg-4">
                                                            <div class="data-item">
                                                                <div class="data-id">{{ $dataItem['data_id'] }}</div>
                                                                <div class="data-content">{{ Str::limit($dataItem['content'], 80) }}</div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No packages available for export.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
                    <small class="text-muted">Need a package that is not listed? Assign data first, then refresh this page.</small>
                    <button type="submit" class="btn btn-dark" id="exportSelectedBtn" disabled>
                        <span class="ti ti-download me-1"></span>Export selection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAllPackages');
        const checkboxes = Array.from(document.querySelectorAll('.package-checkbox'));
        const counter = document.getElementById('selectedPackagesCount');
        const exportButton = document.getElementById('exportSelectedBtn');

        function refreshSelectionState() {
            const selected = checkboxes.filter(cb => cb.checked).length;
            counter.textContent = selected;
            exportButton.disabled = selected === 0;

            if (! checkboxes.length) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
                return;
            }

            if (selected === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (selected === checkboxes.length) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        }

        selectAll?.addEventListener('change', function () {
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            refreshSelectionState();
        });

        checkboxes.forEach(cb => {
            cb.addEventListener('change', refreshSelectionState);
        });

        refreshSelectionState();

        // Expand/collapse annotated data details
        const expandToggles = document.querySelectorAll('.expand-toggle');
        expandToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const packageId = this.getAttribute('data-package-id');
                const detailRow = document.querySelector('.data-detail-' + packageId);

                if (detailRow) {
                    if (detailRow.style.display === 'none') {
                        detailRow.style.display = 'table-row';
                        this.classList.add('expanded');
                    } else {
                        detailRow.style.display = 'none';
                        this.classList.remove('expanded');
                    }
                }
            });
        });
    });
</script>
