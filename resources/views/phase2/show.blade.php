@php
    $run                   = $contentdata['run'];
    $screenings            = $contentdata['screenings'];
    $lcr                   = $contentdata['lcr'];
    $fnr                   = $contentdata['fnr'];
    $nonNormalCount        = $contentdata['nonNormalCount'];
    $missingNonNormalCount = $contentdata['missingNonNormalCount'];

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
            <li class="breadcrumb-item"><a href="{{ route('phase2.index') }}">Phase 2 Screening</a></li>
            <li class="breadcrumb-item active">Run #{{ $run->id }}</li>
        </ol>
    </nav>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible mb-4">
            <i class="ti ti-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible mb-4">
            <i class="ti ti-alert-triangle me-2"></i>{{ $errors->first() }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Header row --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1">
                                Run #{{ $run->id }}
                                <span class="badge bg-label-{{ $statusColors[$run->status] ?? 'secondary' }} ms-2">
                                    {{ ucfirst($run->status) }}
                                </span>
                            </h4>
                            <div class="text-muted">
                                Source package: <strong>{{ $run->sourcePackage?->name ?? '—' }}</strong>
                                @if($run->phase3Package)
                                    &nbsp;·&nbsp; Phase 3: <a href="{{ route('packages.index') }}">{{ $run->phase3Package->name }}</a>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            @if($run->canCreatePhase3())
                                <form action="{{ route('phase2.createPhase3', $run->id) }}" method="POST"
                                      onsubmit="return confirm('Create Phase 3 package from this run?')">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="ti ti-package me-1"></i>Create Phase 3 Package
                                    </button>
                                </form>
                            @endif
                            <form action="{{ route('phase2.destroy', $run->id) }}" method="POST"
                                  onsubmit="return confirm('Delete Run #{{ $run->id }}? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="ti ti-trash me-1"></i>Delete Run
                                </button>
                            </form>
                            <a href="{{ route('phase2.index') }}" class="btn btn-outline-secondary">
                                <i class="ti ti-arrow-left me-1"></i>Back
                            </a>
                        </div>
                    </div>

                    @if($run->error_message)
                        <div class="alert alert-danger mt-3 mb-0">
                            <strong>Error:</strong> {{ $run->error_message }}
                        </div>
                    @endif

                    {{-- Progress bar --}}
                    @if($run->total_normal > 0)
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Normal items screened</small>
                                <small class="text-muted">{{ number_format($run->processed) }} / {{ number_format($run->total_normal) }}</small>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar" style="width: {{ $run->progressPercent() }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="col-12 col-lg-4">
            <div class="row g-3">
                <div class="col-6">
                    <div class="card text-center py-2">
                        <div class="card-body p-2">
                            <div class="fw-bold fs-4 text-info">{{ number_format($run->total_normal) }}</div>
                            <div class="text-muted" style="font-size:0.78rem;">Normal (screened)</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center py-2">
                        <div class="card-body p-2">
                            <div class="fw-bold fs-4 text-secondary">{{ number_format($nonNormalCount) }}</div>
                            <div class="text-muted" style="font-size:0.78rem;">Non-Normal (Phase 1)</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center py-2">
                        <div class="card-body p-2">
                            <div class="fw-bold fs-4 text-danger">{{ number_format($run->flagged_count) }}</div>
                            <div class="text-muted" style="font-size:0.78rem;">Flagged for Phase 3</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card text-center py-2">
                        <div class="card-body p-2">
                            <div class="fw-bold fs-4 text-warning">{{ number_format($run->qc_sample_count) }}</div>
                            <div class="text-muted" style="font-size:0.78rem;">QC Sample (10%)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- LLM Quality Metrics --}}
    @if($run->isCompleted())
    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-percentage"></i>
                            </span>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">
                                @if($lcr !== null)
                                    {{ number_format($lcr * 100, 1) }}%
                                @else
                                    <span class="text-muted fs-6">Pending Phase 3 data</span>
                                @endif
                            </div>
                            <div class="text-muted small">
                                <strong>LCR</strong> — LLM Contribution Rate
                                <br>Flagged items confirmed as DAS by Phase 3 annotators
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-danger">
                                <i class="ti ti-eye-off"></i>
                            </span>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">
                                @if($fnr !== null)
                                    {{ number_format($fnr * 100, 1) }}%
                                @else
                                    <span class="text-muted fs-6">Pending Phase 3 data</span>
                                @endif
                            </div>
                            <div class="text-muted small">
                                <strong>FNR</strong> — LLM False Negative Rate
                                <br>DAS cases missed by the LLM in the 10% QC sample
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Phase 3 Items Summary --}}
    @if($run->isCompleted())
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Items That Will Enter Phase 3</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-borderless mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Source</th>
                            <th class="text-end">Count</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-label-danger">LLM-Flagged Normal</span></td>
                            <td class="text-end fw-bold">{{ number_format($run->flagged_count) }}</td>
                            <td class="text-muted small">Normal items where LLM detected possible DAS — full 3-annotator re-annotation</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-label-warning">QC Sample</span></td>
                            <td class="text-end fw-bold">{{ number_format($run->qc_sample_count) }}</td>
                            <td class="text-muted small">Random 10% of LLM-confirmed Normals — estimates LLM false negative rate</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-label-secondary">Non-Normal (Phase 1)</span></td>
                            <td class="text-end fw-bold">{{ number_format($nonNormalCount) }}</td>
                            <td class="text-muted small">Items already labeled Depresi/Ansietas/Stres — gets 2 more annotators for majority vote</td>
                        </tr>
                        <tr class="table-light fw-bold">
                            <td>Total Phase 3 items</td>
                            <td class="text-end">{{ number_format($run->flagged_count + $run->qc_sample_count + $nonNormalCount) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if(! $run->phase3_package_id)
                <div class="alert alert-info mt-3 mb-0 d-flex align-items-center gap-2">
                    <i class="ti ti-info-circle fs-5 flex-shrink-0"></i>
                    <span>Click <strong>Create Phase 3 Package</strong> above to generate the re-annotation package and notify annotators.</span>
                </div>
            @elseif($missingNonNormalCount > 0)
                <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ti ti-alert-triangle fs-5 flex-shrink-0"></i>
                        <span>{{ number_format($missingNonNormalCount) }} Phase 1 non-normal item(s) are missing from the Phase 3 package.</span>
                    </div>
                    <form action="{{ route('phase2.syncNonNormal', $run->id) }}" method="POST"
                          onsubmit="return confirm('Add {{ number_format($missingNonNormalCount) }} missing Phase 1 non-normal items to the Phase 3 package?')">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="ti ti-refresh me-1"></i>Sync Non-Normal Items
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Screening Results Table --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Screening Results</h5>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge bg-label-danger">🚩 Flagged = {{ number_format($run->flagged_count) }}</span>
                <span class="badge bg-label-warning">QC = {{ number_format($run->qc_sample_count) }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            @if($screenings->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="ti ti-inbox fs-1 d-block mb-2"></i>
                    @if($run->status === 'pending')
                        Run is queued and will start shortly.
                    @elseif($run->status === 'running')
                        Screening in progress...
                    @else
                        No screening results.
                    @endif
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.875rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">Flags</th>
                            <th>Content</th>
                            <th style="width:110px;">LLM Label</th>
                            <th style="width:90px;">Confidence</th>
                            <th>Reasoning</th>
                            <th style="width:70px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($screenings as $s)
                            <tr class="{{ $s->flagged ? 'table-danger' : ($s->in_qc_sample ? 'table-warning' : '') }}">
                                <td class="text-center">
                                    @if($s->flagged)
                                        <span title="Flagged for Phase 3">🚩</span>
                                    @endif
                                    @if($s->in_qc_sample)
                                        <span title="QC Sample">🎲</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 360px;" title="{{ $s->data->content ?? '' }}">
                                        {{ $s->data->content ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $labelColor = match(strtolower($s->llm_label ?? '')) {
                                            'depresi' => 'danger',
                                            'ansietas' => 'warning',
                                            'stres' => 'info',
                                            'normal' => 'secondary',
                                            default => 'light',
                                        };
                                    @endphp
                                    <span class="badge bg-label-{{ $labelColor }}">{{ $s->llm_label ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($s->confidence !== null)
                                        <div class="d-flex align-items-center gap-1">
                                            <div class="progress flex-grow-1" style="height:5px;">
                                                <div class="progress-bar {{ $s->confidence >= 0.7 ? 'bg-danger' : 'bg-secondary' }}"
                                                     style="width: {{ $s->confidence * 100 }}%"></div>
                                            </div>
                                            <small>{{ number_format($s->confidence, 2) }}</small>
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate text-muted" style="max-width: 260px; font-size:0.8rem;"
                                          title="{{ $s->reasoning ?? '' }}">
                                        {{ $s->reasoning ?? ($s->error_message ?? '—') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-label-{{ $s->status === 'done' ? 'success' : ($s->status === 'error' ? 'danger' : 'secondary') }}">
                                        {{ $s->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $screenings->links() }}
            </div>
            @endif
        </div>
    </div>

</div>
