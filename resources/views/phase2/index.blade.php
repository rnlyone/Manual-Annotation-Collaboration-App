@php
    $runs = $contentdata['runs'];

    $statusColors = [
        'pending'   => 'secondary',
        'running'   => 'primary',
        'completed' => 'success',
        'failed'    => 'danger',
    ];
@endphp

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb breadcrumb-style1">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active">Phase 2 — LLM Screening</li>
        </ol>
    </nav>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible mb-4" role="alert">
            <i class="ti ti-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible mb-4" role="alert">
            <i class="ti ti-alert-triangle me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4 mb-4">
        {{-- Import CSV card --}}
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-file-upload me-2 text-primary"></i>Import LLM Screening CSV</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Upload a CSV with columns: <code>id</code>, <code>llm_label</code>,
                        <code>llm_confidence</code>, <code>llm_reasoning</code>.
                        The system automatically groups rows by package. One run is created per matched Phase 1 package.
                    </p>
                    <form action="{{ route('phase2.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="csv_file">CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file"
                                   accept=".csv,text/csv" required>
                            @error('csv_file')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ti ti-upload me-1"></i>Import & Create Runs
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Summary stats --}}
        <div class="col-12 col-lg-8">
            <div class="row g-3 h-100">
                @php
                    $totalRuns      = $runs->count();
                    $completedRuns  = $runs->where('status', 'completed')->count();
                    $runningRuns    = $runs->whereIn('status', ['pending','running'])->count();
                    $totalFlagged   = $runs->sum('flagged_count');
                @endphp
                <div class="col-6">
                    <div class="card text-center py-3">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-primary">{{ $totalRuns }}</div>
                            <div class="text-muted small">Total Runs</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center py-3">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-success">{{ $completedRuns }}</div>
                            <div class="text-muted small">Completed</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center py-3">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-warning">{{ $runningRuns }}</div>
                            <div class="text-muted small">In Progress</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center py-3">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-danger">{{ number_format($totalFlagged) }}</div>
                            <div class="text-muted small">Total Items Flagged</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Runs table --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Screening Run History</h5>
        </div>
        <div class="card-body p-0">
            @if($runs->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="ti ti-inbox fs-1 d-block mb-2"></i>No screening runs yet.
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Source Package</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Normal</th>
                            <th>Flagged</th>
                            <th>QC Sample</th>
                            <th>Phase 3</th>
                            <th>Started</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($runs as $run)
                            <tr>
                                <td class="text-muted small">{{ $run['id'] }}</td>
                                <td>
                                    <a href="{{ route('packages.index') }}">{{ $run['source_package'] }}</a>
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $statusColors[$run['status']] ?? 'secondary' }}">
                                        {{ ucfirst($run['status']) }}
                                    </span>
                                    @if(in_array($run['status'], ['running']))
                                        <span class="spinner-border spinner-border-sm text-primary ms-1" role="status"></span>
                                    @endif
                                </td>
                                <td style="min-width: 120px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar" style="width: {{ $run['progress'] }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $run['progress'] }}%</small>
                                    </div>
                                </td>
                                <td>{{ number_format($run['total_normal']) }}</td>
                                <td>
                                    @if($run['total_normal'] > 0)
                                        {{ number_format($run['flagged_count']) }}
                                        <small class="text-muted">({{ $run['total_normal'] > 0 ? round($run['flagged_count']/$run['total_normal']*100,1) : 0 }}%)</small>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ number_format($run['qc_sample_count']) }}</td>
                                <td>
                                    @if($run['phase3_package'])
                                        <span class="badge bg-label-success">
                                            <i class="ti ti-check me-1"></i>Created
                                        </span>
                                    @elseif($run['can_create_phase3'])
                                        <a href="{{ route('phase2.show', $run['id']) }}" class="btn btn-xs btn-outline-primary" style="font-size:0.75rem;padding:0.2rem 0.5rem;">
                                            Create
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $run['started_at'] ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('phase2.show', $run['id']) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>
