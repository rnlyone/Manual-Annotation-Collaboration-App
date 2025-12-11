<div class="container-xxl flex-grow-1 container-p-y">

    <div class="row g-6">
        {{-- Breadcrumb --}}
        <div class="col-12 mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Home</a>
                    </li>
                    <li class="breadcrumb-item active">Annotations</li>
                </ol>
            </nav>
        </div>
    </div>
        {{-- /Breadcrumb --}}

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Your Assigned Packages</h5>
                        <small class="text-muted">Track your annotation progress across packages</small>
                    </div>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sessionHistoryModal">
                        Session History
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th>Package</th>
                                    <th style="width: 140px;">Assigned Data</th>
                                    <th style="width: 180px;">Your Annotations</th>
                                    <th style="width: 180px;">Remaining</th>
                                    <th style="width: 220px;">Overall Progress</th>
                                    <th style="width: 150px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($assignedPackages ?? collect()) as $index => $package)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $package['name'] }}</td>
                                        <td>{{ number_format($package['data_total']) }}</td>
                                        <td>{{ number_format($package['user_annotated']) }}</td>
                                        <td>{{ number_format(max($package['remaining'], 0)) }}</td>
                                        <td>
                                            @php
                                                $progressPercent = number_format($package['overall_progress'], 1);
                                                $overallAnnotated = number_format($package['overall_annotated']);
                                            @endphp
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress w-100" style="height: 8px;">
                                                    <div
                                                        class="progress-bar"
                                                        role="progressbar"
                                                        style="width: {{ $progressPercent }}%;"
                                                        aria-valuenow="{{ $progressPercent }}"
                                                        aria-valuemin="0"
                                                        aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted">{{ $progressPercent }}%</small>
                                            </div>
                                            <small class="text-muted">{{ $overallAnnotated }} / {{ number_format($package['data_total']) }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('annotations.create') }}?package_id={{ $package['id'] }}" class="btn btn-sm btn-primary">
                                                Continue
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            You do not have any packages assigned yet.
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



</div>

<!-- Session History Modal -->
<div class="modal fade" id="sessionHistoryModal" tabindex="-1" aria-labelledby="sessionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="sessionHistoryModalLabel">Annotation Session History</h5>
                    <small class="text-muted">Only your sessions are visible here</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="sessionHistoryTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Package</th>
                                <th>Started</th>
                                <th>Ended</th>
                                <th>Annotations</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Loading…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('session-logs.index') }}" class="btn btn-outline-secondary">Manage Sessions</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('sessionHistoryModal');
        if (!modalEl) {
            return;
        }

        const tableBody = modalEl.querySelector('tbody');
        const reviewUrlTemplate = @json(route('session-logs.show', ['session_log' => '__SESSION__']));
        let hasLoaded = false;

        modalEl.addEventListener('show.bs.modal', function () {
            if (hasLoaded) {
                return;
            }

            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading…</td></tr>';

            fetch('{{ route('session-logs.history') }}', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(({ data }) => {
                    if (!data || !data.length) {
                        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No sessions recorded yet.</td></tr>';
                        return;
                    }

                    tableBody.innerHTML = data.map((log, index) => {
                        const statusBadge = log.is_active
                            ? '<span class="badge bg-label-info">Active</span>'
                            : '<span class="badge bg-label-secondary">Closed</span>';
                        const ended = log.ended_at ?? '—';

                        return `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${log.package ?? '—'}</td>
                                <td>${log.started_at ?? '—'}</td>
                                <td>${ended}</td>
                                <td>${log.annotation_count}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <a href="${reviewUrlTemplate.replace('__SESSION__', log.id)}" class="btn btn-sm btn-link">Review</a>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    hasLoaded = true;
                })
                .catch(() => {
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load sessions.</td></tr>';
                });
        });
    });
</script>
@endpush
