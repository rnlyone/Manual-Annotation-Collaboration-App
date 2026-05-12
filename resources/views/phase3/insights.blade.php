@php
    $d = $contentdata;

    // Kappa color helper
    $kappaColor = $d['kappaInterpret']['color'] ?? 'secondary';
    $kappaLabel = $d['kappaInterpret']['label'] ?? '—';

    // Progress bucket shorthand
    $pb = $d['progressBuckets'] ?? [0=>0, 1=>0, 2=>0, 3=>0];

    // Source breakdown
    $sb = $d['sourceBreakdown'] ?? ['llm_flagged'=>0,'qc_sample'=>0,'non_normal'=>0];

    // IAA
    $iaaTotal  = $d['iaaTotal'] ?? 0;
    $iaaTotal3 = $d['iaaTotal3'] ?? 0;
    $iaaStats  = $d['iaaStats'] ?? [];

    // LLM vs Human
    $lc = $d['llmComparison'] ?? [];

    // Category distribution
    $catFreq        = $d['categoryFrequency'] ?? [];
    $normalAnnot    = $d['normalAnnotCount'] ?? 0;
    $llmLabelCounts = $d['llmLabelCounts'] ?? collect();

    // LLM vs Human label matrix
    $llmVsHuman      = $d['llmVsHuman'] ?? [];
    $allHumanLabels  = $d['allHumanLabels'] ?? [];

    // Totals helper
    $totalItems = $d['totalItems'] ?? 0;
@endphp

@push('styles')
<style>
    .kappa-badge {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    .insight-stat-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: .06em; color: #6d6b7b; }
    .insight-stat-value { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
    .agreement-bar { height: 14px; border-radius: 8px; overflow: hidden; }
    .chart-container-md { position: relative; height: 240px; }
    .chart-container-lg { position: relative; height: 320px; }
</style>
@endpush

<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb breadcrumb-style1">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            <li class="breadcrumb-item active">Phase 3 Insights</li>
        </ol>
    </nav>

    @if($d['empty'])
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ti ti-chart-infographic fs-1 text-muted d-block mb-3"></i>
                <h5 class="text-muted">No Phase 3 data yet</h5>
                <p class="text-muted mb-4">Phase 3 packages are created from completed Phase 2 screening runs.</p>
                <a href="{{ route('phase2.index') }}" class="btn btn-primary">
                    <i class="ti ti-robot me-1"></i>Go to Phase 2 Screening
                </a>
            </div>
        </div>
    @else

    {{-- ── OVERVIEW STAT CARDS ─────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body py-4">
                    <div class="insight-stat-value text-primary">{{ number_format($totalItems) }}</div>
                    <div class="insight-stat-label mt-1">Total Phase 3 Items</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body py-4">
                    <div class="insight-stat-value text-success">{{ number_format($pb[3]) }}</div>
                    <div class="insight-stat-label mt-1">Fully Annotated (3×)</div>
                    @if($totalItems > 0)
                        <div class="small text-muted">{{ round($pb[3]/$totalItems*100,1) }}% of total</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body py-4">
                    <div class="insight-stat-value text-secondary">{{ number_format($pb[1]) }}</div>
                    <div class="insight-stat-label mt-1">Awaiting Phase 3</div>
                    @if($totalItems > 0)
                        <div class="small text-muted">{{ round($pb[1]/$totalItems*100,1) }}% of total</div>
                    @endif
                    <div class="small text-muted mt-1">Phase 1 only (1 annotator)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card text-center h-100">
                <div class="card-body py-4">
                    @if($d['fleissKappa'] !== null)
                        <div class="kappa-badge text-{{ $kappaColor }}">{{ $d['fleissKappa'] }}</div>
                        <div class="insight-stat-label mt-1">Fleiss' κ</div>
                        <div class="small mt-1">
                            <span class="badge bg-label-{{ $kappaColor }}">{{ $kappaLabel }}</span>
                        </div>
                    @else
                        <div class="insight-stat-value text-muted">—</div>
                        <div class="insight-stat-label mt-1">Fleiss' κ</div>
                        <div class="small text-muted">Needs ≥ 3 annotations per item</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">

        {{-- ── ANNOTATION PROGRESS BREAKDOWN ─────────────────────────── --}}
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-chart-donut me-2 text-primary"></i>Human Annotator Count per Item</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container-md">
                        <canvas id="progressDonut"></canvas>
                    </div>
                    @php
                        $donuts = [
                            ['key'=>1, 'label'=>'1 annotator',  'sub'=>'Phase 1 only — not yet reviewed in Phase 3', 'color'=>'#c0c0c8'],
                            ['key'=>2, 'label'=>'2 annotators', 'sub'=>'Phase 3 ongoing — 1 reviewer so far',        'color'=>'#ffb547'],
                            ['key'=>3, 'label'=>'3 annotators', 'sub'=>'Phase 3 complete — 2 reviewers done',        'color'=>'#71dd37'],
                        ];
                    @endphp
                    <div class="mt-3">
                        @foreach($donuts as $d2)
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $d2['color'] }};flex-shrink:0;margin-top:3px;"></span>
                            <div class="flex-grow-1">
                                <div class="small fw-semibold">{{ $d2['label'] }}</div>
                                <div class="small text-muted">{{ $d2['sub'] }}</div>
                            </div>
                            <strong>{{ number_format($pb[$d2['key']]) }}</strong>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-2 border-top">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Phase 3 complete</span>
                            <strong class="text-success">{{ $totalItems > 0 ? round($pb[3]/$totalItems*100,1) : 0 }}%</strong>
                        </div>
                        <div class="progress mt-1" style="height:6px;">
                            <div class="progress-bar bg-success" style="width:{{ $totalItems > 0 ? round($pb[3]/$totalItems*100,1) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── SOURCE BREAKDOWN ────────────────────────────────────────── --}}
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-chart-pie me-2 text-info"></i>Item Source</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container-md">
                        <canvas id="sourceDonut"></canvas>
                    </div>
                    <div class="mt-3">
                        @foreach([
                            ['label'=>'LLM Flagged','desc'=>'LLM detected possible DAS','color'=>'#ff4560','val'=>$sb['llm_flagged']],
                            ['label'=>'QC Sample','desc'=>'Random 10% to catch false negatives','color'=>'#ffb300','val'=>$sb['qc_sample']],
                            ['label'=>'Non-Normal (Phase 1)','desc'=>'Already labeled DAS in Phase 1','color'=>'#826af9','val'=>$sb['non_normal']],
                        ] as $src)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $src['color'] }};flex-shrink:0;"></span>
                            <span class="small">{{ $src['label'] }}</span>
                            <strong class="ms-auto">{{ number_format($src['val']) }}</strong>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── LLM CONTRIBUTION RATE ────────────────────────────────────── --}}
        <div class="col-12 col-lg-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-robot me-2 text-warning"></i>LLM Contribution</h6>
                </div>
                <div class="card-body">
                    @php
                        $srcTotal = array_sum($sb);
                        $llmPct   = $srcTotal > 0 ? round(($sb['llm_flagged'] + $sb['qc_sample']) / $srcTotal * 100, 1) : 0;
                        $nnPct    = $srcTotal > 0 ? round($sb['non_normal'] / $srcTotal * 100, 1) : 0;
                    @endphp
                    <div class="text-center py-2">
                        <div class="display-5 fw-bold text-warning">{{ $llmPct }}%</div>
                        <div class="small text-muted">of Phase 3 items sourced from LLM screening</div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>LLM Flagged</span><strong>{{ number_format($sb['llm_flagged']) }}</strong>
                    </div>
                    <div class="progress mb-3" style="height:6px;">
                        <div class="progress-bar bg-danger" style="width:{{ $srcTotal > 0 ? round($sb['llm_flagged']/$srcTotal*100,1) : 0 }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>QC Sample</span><strong>{{ number_format($sb['qc_sample']) }}</strong>
                    </div>
                    <div class="progress mb-3" style="height:6px;">
                        <div class="progress-bar bg-warning" style="width:{{ $srcTotal > 0 ? round($sb['qc_sample']/$srcTotal*100,1) : 0 }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Phase 1 Non-Normal</span><strong>{{ number_format($sb['non_normal']) }}</strong>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-label-secondary" style="width:{{ $nnPct }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── IAA + LLM PRECISION ROW ─────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">

        {{-- IAA Agreement Detail --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="ti ti-users me-2 text-success"></i>Inter-Annotator Agreement</h6>
                    <div class="d-flex gap-2 align-items-center">
                        @if($iaaTotal3 > 0)
                            <span class="badge bg-label-success">{{ number_format($iaaTotal3) }} items × 3 raters</span>
                        @endif
                        <small class="text-muted">{{ number_format($iaaTotal) }} items with ≥ 2 annotations</small>
                    </div>
                </div>
                <div class="card-body">
                    @if($iaaTotal === 0)
                        <div class="text-center text-muted py-4">
                            <i class="ti ti-clock fs-1 d-block mb-2"></i>
                            <p class="mb-1">Waiting for items to receive at least 2 annotations.</p>
                            <small>IAA requires a data item to be annotated by ≥ 2 different annotators. Fleiss' κ will appear once enough items are covered.</small>
                        </div>
                    @else
                        {{-- Fleiss' Kappa bar --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small fw-semibold">
                                    Fleiss' Kappa (κ)
                                    @if($iaaTotal3 < $iaaTotal)
                                        <span class="badge bg-label-warning ms-1" title="{{ $iaaTotal - $iaaTotal3 }} items have only 2 annotations — preliminary estimate">preliminary</span>
                                    @endif
                                </span>
                                <span class="badge bg-label-{{ $kappaColor }}">{{ $d['fleissKappa'] }} — {{ $kappaLabel }}</span>
                            </div>
                            <div class="progress" style="height:10px;">
                                @php $kappaPct = max(0, round(($d['fleissKappa'] + 1) / 2 * 100, 1)); @endphp
                                <div class="progress-bar bg-{{ $kappaColor }}" style="width:{{ $kappaPct }}%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">−1 (no agreement)</small>
                                <small class="text-muted">0</small>
                                <small class="text-muted">+1 (perfect)</small>
                            </div>
                        </div>

                        {{-- Agreement stacked bar --}}
                        <p class="small fw-semibold mb-2">Agreement Breakdown ({{ number_format($iaaTotal) }} items with ≥ 2 annotations)</p>
                        <div class="agreement-bar d-flex mb-2">
                            @php
                                $segDefs = [
                                    ['val'=>$iaaStats['full_das'],        'color'=>'bg-danger',          'label'=>'Full DAS'],
                                    ['val'=>$iaaStats['majority_das'],    'color'=>'bg-warning',         'label'=>'Majority DAS'],
                                    ['val'=>$iaaStats['split'] ?? 0,      'color'=>'bg-secondary',       'label'=>'Split (1-1)'],
                                    ['val'=>$iaaStats['majority_normal'], 'color'=>'bg-info',            'label'=>'Majority Normal'],
                                    ['val'=>$iaaStats['full_normal'],     'color'=>'bg-success',         'label'=>'Full Normal'],
                                ];
                            @endphp
                            @foreach($segDefs as $seg)
                            @if($seg['val'] > 0)
                                @php $segPct = round($seg['val']/$iaaTotal*100,1); @endphp
                                <div class="{{ $seg['color'] }}" style="width:{{ $segPct }}%" title="{{ $seg['label'] }}: {{ number_format($seg['val']) }} ({{ $segPct }}%)"></div>
                            @endif
                            @endforeach
                        </div>
                        <div class="row g-2 small">
                            @foreach([
                                ['val'=>$iaaStats['full_das'],        'color'=>'danger',    'label'=>'Full DAS (unanimous)'],
                                ['val'=>$iaaStats['majority_das'],    'color'=>'warning',   'label'=>'Majority DAS (2 of 3)'],
                                ['val'=>$iaaStats['split'] ?? 0,      'color'=>'secondary', 'label'=>'Split 1-1 (2 raters)'],
                                ['val'=>$iaaStats['majority_normal'], 'color'=>'info',      'label'=>'Majority Normal (2 of 3)'],
                                ['val'=>$iaaStats['full_normal'],     'color'=>'success',   'label'=>'Full Normal (unanimous)'],
                            ] as $seg)
                            @if($seg['val'] > 0)
                            <div class="col-6 d-flex align-items-center gap-1">
                                <span class="badge bg-{{ $seg['color'] }}" style="width:10px;height:10px;padding:0;border-radius:50%;"></span>
                                <span class="text-muted">{{ $seg['label'] }}</span>
                                <strong class="ms-auto">{{ number_format($seg['val']) }}</strong>
                            </div>
                            @endif
                            @endforeach
                        </div>
                        <div class="mt-3 pt-3 border-top d-flex gap-4 flex-wrap">
                            <div class="text-center">
                                <div class="fw-bold text-success">{{ $d['pctFullAgreement'] }}%</div>
                                <div class="small text-muted">Full Agreement</div>
                            </div>
                            @if($iaaTotal > 0)
                            <div class="text-center">
                                <div class="fw-bold text-danger">{{ round(($iaaStats['full_das'] + $iaaStats['majority_das'])/$iaaTotal*100,1) }}%</div>
                                <div class="small text-muted">DAS Majority</div>
                            </div>
                            @if(($iaaStats['split'] ?? 0) > 0)
                            <div class="text-center">
                                <div class="fw-bold text-secondary">{{ round($iaaStats['split']/$iaaTotal*100,1) }}%</div>
                                <div class="small text-muted">Disputed (2-rater split)</div>
                            </div>
                            @endif
                            @if($iaaTotal3 > 0)
                            <div class="text-center">
                                <div class="fw-bold text-primary">{{ number_format($iaaTotal3) }}</div>
                                <div class="small text-muted">Items × 3 raters</div>
                            </div>
                            @endif
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- LLM vs Human --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-vs me-2 text-danger"></i>LLM vs Human Comparison</h6>
                </div>
                <div class="card-body">

                    {{-- LLM Flagged precision --}}
                    <p class="small fw-semibold mb-2">LLM-Flagged Items <span class="text-muted fw-normal">(LLM said possibly DAS on Normal items)</span></p>
                    @if($d['flaggedAnnotated'] > 0)
                        @php
                            $fcdPct = round($lc['flagged_confirmed_das']/$d['flaggedAnnotated']*100,1);
                            $fcnPct = round($lc['flagged_confirmed_normal']/$d['flaggedAnnotated']*100,1);
                        @endphp
                        <div class="d-flex mb-1">
                            <div class="progress flex-grow-1 me-2" style="height:22px;border-radius:6px;">
                                <div class="progress-bar bg-danger" style="width:{{ $fcdPct }}%" title="Confirmed DAS: {{ number_format($lc['flagged_confirmed_das']) }}">
                                    @if($fcdPct > 12) <span class="px-1">{{ $fcdPct }}% DAS</span> @endif
                                </div>
                                <div class="progress-bar bg-success" style="width:{{ $fcnPct }}%" title="Normal after human review: {{ number_format($lc['flagged_confirmed_normal']) }}">
                                    @if($fcnPct > 12) <span class="px-1">{{ $fcnPct }}% Normal</span> @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 small text-muted mb-3">
                            <span><span class="text-danger fw-bold">{{ number_format($lc['flagged_confirmed_das']) }}</span> confirmed DAS</span>
                            <span><span class="text-success fw-bold">{{ number_format($lc['flagged_confirmed_normal']) }}</span> humans said Normal</span>
                            @if($lc['flagged_no_votes'] > 0)
                                <span><span class="fw-bold">{{ number_format($lc['flagged_no_votes']) }}</span> pending</span>
                            @endif
                        </div>
                        <div class="alert alert-secondary py-2 px-3 small mb-3">
                            <strong>LLM Precision: {{ $d['llmPrecision'] }}%</strong> — of flagged items reviewed by humans, {{ $d['llmPrecision'] }}% were confirmed DAS.
                        </div>
                    @else
                        <p class="text-muted small mb-3">No flagged items have been annotated yet.</p>
                    @endif

                    {{-- QC sample false-negative rate --}}
                    <p class="small fw-semibold mb-2">QC Sample Items <span class="text-muted fw-normal">(LLM said Normal — checking for false negatives)</span></p>
                    @if($d['qcAnnotated'] > 0)
                        @php
                            $qcDasPct = round($lc['qc_das_found']/$d['qcAnnotated']*100,1);
                            $qcNorPct = round($lc['qc_confirmed_normal']/$d['qcAnnotated']*100,1);
                        @endphp
                        <div class="d-flex mb-1">
                            <div class="progress flex-grow-1 me-2" style="height:22px;border-radius:6px;">
                                <div class="progress-bar bg-danger" style="width:{{ $qcDasPct }}%" title="False negative — actually DAS: {{ number_format($lc['qc_das_found']) }}">
                                    @if($qcDasPct > 12) <span class="px-1">{{ $qcDasPct }}% DAS</span> @endif
                                </div>
                                <div class="progress-bar bg-success" style="width:{{ $qcNorPct }}%" title="Confirmed Normal: {{ number_format($lc['qc_confirmed_normal']) }}">
                                    @if($qcNorPct > 12) <span class="px-1">{{ $qcNorPct }}% Normal</span> @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 small text-muted mb-3">
                            <span><span class="text-danger fw-bold">{{ number_format($lc['qc_das_found']) }}</span> were actually DAS</span>
                            <span><span class="text-success fw-bold">{{ number_format($lc['qc_confirmed_normal']) }}</span> confirmed Normal</span>
                        </div>
                        <div class="alert alert-secondary py-2 px-3 small">
                            <strong>Est. LLM False-Negative Rate: {{ $d['fnrEstimate'] }}%</strong> — of reviewed QC items, {{ $d['fnrEstimate'] }}% were mislabeled as Normal by the LLM.
                        </div>
                    @else
                        <p class="text-muted small">No QC items have been annotated yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── LABEL DISTRIBUTION ROW ──────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">

        {{-- Human category distribution --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-tags me-2 text-primary"></i>Human Label Distribution (Phase 3)</h6>
                </div>
                <div class="card-body">
                    @php
                        $catTotal = $normalAnnot + array_sum($catFreq);
                        $catChartLabels = array_merge(['Normal'], array_keys($catFreq));
                        $catChartValues = array_merge([$normalAnnot], array_values($catFreq));
                    @endphp
                    <div class="chart-container-lg">
                        <canvas id="catDistChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Normal</span>
                            <strong>{{ number_format($normalAnnot) }}
                                @if($catTotal > 0) <small class="text-muted">({{ round($normalAnnot/$catTotal*100,1) }}%)</small> @endif
                            </strong>
                        </div>
                        @foreach($catFreq as $name => $count)
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">{{ $name }}</span>
                            <strong>{{ number_format($count) }}
                                @if($catTotal > 0) <small class="text-muted">({{ round($count/$catTotal*100,1) }}%)</small> @endif
                            </strong>
                        </div>
                        @endforeach
                        <div class="small text-muted mt-2">Total annotation votes: {{ number_format($catTotal) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- LLM label distribution --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-robot me-2 text-warning"></i>LLM Label Distribution (Phase 2 Screening)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container-lg">
                        <canvas id="llmLabelChart"></canvas>
                    </div>
                    <div class="mt-3">
                        @php $llmTotal = $llmLabelCounts->sum(); @endphp
                        @foreach($llmLabelCounts as $label => $count)
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">{{ $label ?: 'Unknown' }}</span>
                            <strong>{{ number_format($count) }}
                                @if($llmTotal > 0) <small class="text-muted">({{ round($count/$llmTotal*100,1) }}%)</small> @endif
                            </strong>
                        </div>
                        @endforeach
                        <div class="small text-muted mt-2">Total items screened: {{ number_format($llmTotal) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── LLM LABEL vs HUMAN LABEL MATRIX ─────────────────────────────────── --}}
    @if(!empty($llmVsHuman))
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="ti ti-arrows-exchange me-2 text-danger"></i>LLM Label vs Human Majority Label</h6>
            <small class="text-muted">Each bar = one LLM-predicted label · segments = what humans labeled those items</small>
        </div>
        <div class="card-body">
            <div style="position:relative; height:{{ max(180, count($llmVsHuman) * 52) }}px;">
                <canvas id="llmVsHumanChart"></canvas>
            </div>

            {{-- Legend --}}
            <div class="d-flex flex-wrap gap-3 mt-3 small">
                @php
                    $palette = ['#71dd37','#ff4560','#ffb300','#29ccef','#826af9','#fd7e14','#20c997','#e83e8c','#6c757d','#0dcaf0'];
                    $labelPaletteMap = [];
                    foreach ($allHumanLabels as $i => $lbl) {
                        $labelPaletteMap[$lbl] = $palette[$i % count($palette)];
                    }
                @endphp
                @foreach($allHumanLabels as $lbl)
                <div class="d-flex align-items-center gap-1">
                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $labelPaletteMap[$lbl] }};flex-shrink:0;"></span>
                    <span>{{ $lbl }}</span>
                </div>
                @endforeach
            </div>

            {{-- Detail table --}}
            <div class="table-responsive mt-3">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:160px;">LLM Label</th>
                            @foreach($allHumanLabels as $lbl)
                                <th class="text-center">{{ $lbl }}</th>
                            @endforeach
                            <th class="text-center">Total</th>
                            <th style="min-width:120px;">Agreement rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($llmVsHuman as $llmLbl => $humanCounts)
                            @php
                                $rowTotal = array_sum($humanCounts);
                                // Agreement = human majority said the same category as LLM (name match)
                                $agreedCount = $humanCounts[$llmLbl] ?? 0;
                                $agreedPct   = $rowTotal > 0 ? round($agreedCount / $rowTotal * 100, 1) : 0;
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $llmLbl }}</td>
                                @foreach($allHumanLabels as $lbl)
                                    @php $c = $humanCounts[$lbl] ?? 0; @endphp
                                    <td class="text-center {{ $c > 0 && $lbl === $llmLbl ? 'table-success' : ($c > 0 ? 'table-warning' : '') }}">
                                        {{ $c > 0 ? $c : '—' }}
                                    </td>
                                @endforeach
                                <td class="text-center fw-semibold">{{ $rowTotal }}</td>
                                <td>
                                    @if($llmLbl !== 'Normal')
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:6px;">
                                                <div class="progress-bar {{ $agreedPct >= 70 ? 'bg-success' : ($agreedPct >= 40 ? 'bg-warning' : 'bg-danger') }}"
                                                     style="width:{{ $agreedPct }}%"></div>
                                            </div>
                                            <small>{{ $agreedPct }}%</small>
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">
                <span class="badge bg-label-success">Green cells</span> = LLM and human agreed on the label.
                <span class="badge bg-label-warning ms-1">Yellow cells</span> = human majority chose a different label.
                <strong>Agreement rate</strong> applies only to non-Normal LLM labels (name match with human majority).
            </p>
        </div>
    </div>
    @endif

    {{-- ── PER-RUN SUMMARY TABLE ────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="ti ti-table me-2 text-secondary"></i>Per-Run Summary</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Run</th>
                            <th>Source Package</th>
                            <th>Phase 3 Package</th>
                            <th>Total Items</th>
                            <th>Started</th>
                            <th>Fully Done (3×)</th>
                            <th style="min-width:150px;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($d['runs'] as $run)
                        <tr>
                            <td class="text-muted small">#{{ $run['run_id'] }}</td>
                            <td>{{ $run['source'] }}</td>
                            <td>{{ $run['phase3_package'] }}</td>
                            <td>{{ number_format($run['total']) }}</td>
                            <td>{{ number_format($run['started']) }}</td>
                            <td>
                                {{ number_format($run['fully_done']) }}
                                @if($run['total'] > 0)
                                    <small class="text-muted">({{ round($run['fully_done']/$run['total']*100,1) }}%)</small>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;">
                                        <div class="progress-bar" style="width:{{ $run['progress_pct'] }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ $run['progress_pct'] }}%</small>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const isDark = document.documentElement.classList.contains('dark-style') || document.body.classList.contains('dark-layout');
    const textColor  = isDark ? '#ccc' : '#555';
    const gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';

    @unless($d['empty'])

    // ── Progress Donut ─────────────────────────────────────────────────
    // ── Progress Donut ─────────────────────────────────────────────────
    new Chart(document.getElementById('progressDonut'), {
        type: 'doughnut',
        data: {
            labels: ['1 annotator (Phase 1 only)', '2 annotators (Phase 3 ongoing)', '3 annotators (Phase 3 complete)'],
            datasets: [{
                data: @json([$pb[1], $pb[2], $pb[3]]),
                backgroundColor: ['#c0c0c8', '#ffb547', '#71dd37'],
                borderWidth: 0,
            }],
        },
        options: {
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw.toLocaleString()}` } },
            },
            responsive: true,
            maintainAspectRatio: false,
        },
    });

    // ── Source Donut ───────────────────────────────────────────────────
    new Chart(document.getElementById('sourceDonut'), {
        type: 'doughnut',
        data: {
            labels: ['LLM Flagged', 'QC Sample', 'Non-Normal (Phase 1)'],
            datasets: [{
                data: @json(array_values($sb)),
                backgroundColor: ['#ff4560', '#ffb300', '#826af9'],
                borderWidth: 0,
            }],
        },
        options: {
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw.toLocaleString()}` } },
            },
            responsive: true,
            maintainAspectRatio: false,
        },
    });

    // ── Human Category Distribution (horizontal bar) ───────────────────
    new Chart(document.getElementById('catDistChart'), {
        type: 'bar',
        data: {
            labels: @json($catChartLabels),
            datasets: [{
                label: 'Annotation votes',
                data: @json($catChartValues),
                backgroundColor: ['#71dd37','#ff4560','#ffb300','#29ccef','#826af9','#fd7e14'].slice(0, {{ count($catChartLabels) }}),
                borderRadius: 6,
                borderWidth: 0,
            }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor } },
                y: { grid: { display: false }, ticks: { color: textColor } },
            },
            responsive: true,
            maintainAspectRatio: false,
        },
    });

    // ── LLM Label Distribution (horizontal bar) ────────────────────────
    const llmLabels = @json($llmLabelCounts->keys()->values()->all());
    const llmValues = @json($llmLabelCounts->values()->all());

    new Chart(document.getElementById('llmLabelChart'), {
        type: 'bar',
        data: {
            labels: llmLabels,
            datasets: [{
                label: 'Items screened',
                data: llmValues,
                backgroundColor: ['#71dd37','#ff4560','#ffb300','#29ccef','#826af9'].slice(0, llmLabels.length),
                borderRadius: 6,
                borderWidth: 0,
            }],
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor } },
                y: { grid: { display: false }, ticks: { color: textColor } },
            },
            responsive: true,
            maintainAspectRatio: false,
        },
    });

    @endunless

    // ── LLM vs Human label matrix (stacked horizontal bar) ────────────────
    @if(!empty($llmVsHuman))
    (function () {
        const palette      = ['#71dd37','#ff4560','#ffb300','#29ccef','#826af9','#fd7e14','#20c997','#e83e8c','#6c757d','#0dcaf0'];
        const humanLabels  = @json(array_values($allHumanLabels));
        const llmVsHuman   = @json($llmVsHuman);
        const llmRowLabels = Object.keys(llmVsHuman);

        // Build one dataset per human label
        const datasets = humanLabels.map((hLabel, i) => ({
            label: hLabel,
            data: llmRowLabels.map(llmLbl => llmVsHuman[llmLbl][hLabel] ?? 0),
            backgroundColor: palette[i % palette.length],
            borderRadius: 4,
            borderWidth: 0,
        }));

        new Chart(document.getElementById('llmVsHumanChart'), {
            type: 'bar',
            data: { labels: llmRowLabels, datasets },
            options: {
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => {
                                const total = datasets.reduce((s, ds) => s + (ds.data[ctx.dataIndex] || 0), 0);
                                const pct   = total > 0 ? (ctx.raw / total * 100).toFixed(1) : 0;
                                return ` ${ctx.dataset.label}: ${ctx.raw} (${pct}%)`;
                            },
                        },
                    },
                },
                scales: {
                    x: { stacked: true, grid: { color: gridColor }, ticks: { color: textColor } },
                    y: { stacked: true, grid: { display: false }, ticks: { color: textColor } },
                },
                responsive: true,
                maintainAspectRatio: false,
            },
        });
    })();
    @endif

})();
</script>
@endpush
