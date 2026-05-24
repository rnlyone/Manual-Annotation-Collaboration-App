<div class="container-xxl flex-grow-1 container-p-y">

    <div class="row g-6">
        {{-- Breadcrumb --}}
        <div class="col-12 mb-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Home</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('data.index') }}">Data Management</a>
                    </li>
                    <li class="breadcrumb-item active">Dataset Preview</li>
                </ol>
            </nav>
        </div>

        {{-- Stats: total records --}}
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-4">
                    <div class="avatar avatar-lg flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="ti ti-database ti-md"></i>
                        </span>
                    </div>
                    <div>
                        <p class="mb-0 text-muted small">Total Records</p>
                        <h4 class="mb-0 fw-bold">{{ number_format($contentdata['totalCount']) }}</h4>
                        <small class="text-muted">Entries in Data Management</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats: annotators --}}
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-4">
                    <div class="avatar avatar-lg flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="ti ti-users ti-md"></i>
                        </span>
                    </div>
                    <div>
                        <p class="mb-0 text-muted small">Annotators</p>
                        <h4 class="mb-0 fw-bold">{{ count($contentdata['annotators']) }}</h4>
                        <small class="text-muted">Users with annotations</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-header pb-1">
                    <h6 class="mb-0"><i class="ti ti-columns me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body pt-2 d-flex gap-2 align-items-center">
                    <button class="btn btn-sm btn-label-primary" id="checkAllCols">Select All</button>
                    <button class="btn btn-sm btn-label-secondary" id="uncheckAllCols">Deselect All</button>
                    <button type="button" id="exportCsvBtn" class="btn btn-sm btn-success ms-auto">
                        <i class="ti ti-download me-1"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>

        {{-- Filter: complete annotation --}}
        <div class="col-12">
            <div class="card border-0 bg-label-primary">
                <div class="card-body py-3 d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="completeOnlyToggle" role="switch">
                        <label class="form-check-label fw-semibold" for="completeOnlyToggle">
                            Complete Annotation Only
                        </label>
                    </div>
                    <span class="text-body-secondary small">
                        Show only data annotated by
                        <strong>all {{ $contentdata['annotatorRoleCount'] }} annotator{{ $contentdata['annotatorRoleCount'] !== 1 ? 's' : '' }}</strong>
                        with role <code>annotator</code>.
                        @if ($contentdata['annotatorRoleCount'] === 0)
                            <span class="badge bg-label-warning ms-1">No annotator-role users found</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Column Picker --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-2">
                    <h6 class="mb-0"><i class="ti ti-adjustments-horizontal me-2"></i>Customize Columns</h6>
                    <small class="text-muted">Toggle columns shown in the preview and included in CSV export. Annotator columns show "-" if that annotator has not yet labelled the row.</small>
                </div>
                <div class="card-body pt-2">
                    <div class="row g-3">
                        {{-- Base columns --}}
                        <div class="col-12">
                            <p class="mb-1 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em">Base Columns</p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col-id" value="id" checked>
                                    <label class="form-check-label" for="col-id">ID</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col-content" value="content" checked>
                                    <label class="form-check-label" for="col-content">Content</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col-created_at" value="created_at" checked>
                                    <label class="form-check-label" for="col-created_at">Created At</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col-updated_at" value="updated_at">
                                    <label class="form-check-label" for="col-updated_at">Updated At</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col-packages_count" value="packages_count">
                                    <label class="form-check-label" for="col-packages_count">Package Count</label>
                                </div>
                            </div>
                        </div>
                        {{-- LLM Label --}}
                        <div class="col-12">
                            <p class="mb-1 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em">LLM Screening</p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col-llm_label" value="llm_label">
                                    <label class="form-check-label" for="col-llm_label">
                                        <span class="badge bg-label-info me-1">LLM</span>LLM Label
                                    </label>
                                </div>
                            </div>
                        </div>
                        {{-- Annotator columns --}}
                        @if (count($contentdata['annotators']) > 0)
                        <div class="col-12">
                            <p class="mb-1 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em">Annotator Labels</p>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($contentdata['annotators'] as $annotator)
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input col-toggle" type="checkbox"
                                        id="col-{{ $annotator['key'] }}" value="{{ $annotator['key'] }}">
                                    <label class="form-check-label" for="col-{{ $annotator['key'] }}">
                                        <span class="badge bg-label-secondary me-1">A</span>{{ $annotator['name'] }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @else
                        <div class="col-12">
                            <p class="text-muted small mb-0">No annotators found — no annotations have been submitted yet.</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- DataTable Preview --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Dataset Preview</h5>
                        <small class="text-muted">Live server-side preview. Export CSV downloads all records matching the current search with selected columns.</small>
                    </div>
                </div>
                <div class="card-datatable table-responsive position-relative">
                    <div id="datasetOverlay"
                        class="datatable-overlay d-flex flex-column align-items-center justify-content-center text-center"
                        style="position:absolute; inset:0; background:rgba(255,255,255,0.92); z-index:5;">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mb-0 text-muted">Loading dataset...</p>
                    </div>
                    <table class="datatables-dataset table table-hover w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th data-col="id">ID</th>
                                <th data-col="content">Content</th>
                                <th data-col="created_at">Created At</th>
                                <th data-col="updated_at">Updated At</th>
                                <th data-col="packages_count">Package Count</th>
                                <th data-col="llm_label">LLM Label</th>
                                @foreach ($contentdata['annotators'] as $annotator)
                                <th data-col="{{ $annotator['key'] }}">{{ $annotator['name'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
    const tableDataUrl = @json($contentdata['tableDataUrl']);
    const exportUrl    = @json($contentdata['exportUrl']);
    const annotators   = @json($contentdata['annotators']);

    // Fixed column definitions (index 0–6)
    const fixedColumns = [
        {
            data: null, name: 'index', orderable: false, searchable: false,
            render: function (data, type, row, meta) { return meta.row + 1; }
        },
        { data: 'id',             name: 'id' },
        { data: 'content',        name: 'content' },
        { data: 'created_at',     name: 'created_at' },
        { data: 'updated_at',     name: 'updated_at' },
        { data: 'packages_count', name: 'packages_count', orderable: false, searchable: false },
        {
            data: 'llm_label', name: 'llm_label', orderable: false, searchable: false,
            render: function (val) {
                if (!val || val === '-') return '<span class="text-muted">—</span>';
                return '<span class="badge bg-label-info">' + $('<div>').text(val).html() + '</span>';
            }
        },
    ];

    // Dynamic annotator columns (index 7+)
    const annotatorColumns = annotators.map(function (a) {
        const userId = a.id;
        return {
            data: a.key, name: a.key, orderable: false, searchable: false,
            render: function (val, type, row) {
                if (!val || val === '-') return '<span class="text-muted">—</span>';
                const isPhase1 = Array.isArray(row.phase1_annotator_ids) && row.phase1_annotator_ids.includes(userId);
                const badgeClass = isPhase1 ? 'bg-label-warning' : 'bg-label-secondary';
                return val.split(' | ').map(function (l) {
                    return '<span class="badge ' + badgeClass + ' me-1">' + $('<div>').text(l.trim()).html() + '</span>';
                }).join('');
            }
        };
    });

    const allColumns = fixedColumns.concat(annotatorColumns);

    // Build key → column index map
    const colIndexMap = {
        id: 1, content: 2, created_at: 3, updated_at: 4,
        packages_count: 5, llm_label: 6,
    };
    annotators.forEach(function (a, i) { colIndexMap[a.key] = 7 + i; });

    const $overlay = $('#datasetOverlay');

    const dataTable = $('.datatables-dataset').DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        ajax: {
            url: tableDataUrl,
            type: 'GET',
            dataSrc: 'data',
            data: function (d) {
                d.complete_only = $('#completeOnlyToggle').is(':checked') ? 1 : 0;
            }
        },
        columns: allColumns,
        order: [[3, 'desc']],
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        pageLength: 25,
        dom: '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"<"me-5 ms-n2"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-3"lB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: '', searchPlaceholder: 'Search records…',
            sLengthMenu: '_MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2"></span> Loading…',
            paginate: { first: '&laquo;', last: '&raquo;', next: '&rsaquo;', previous: '&lsaquo;' }
        }
    });

    dataTable.on('processing.dt', function (e, settings, processing) {
        processing ? $overlay.removeClass('d-none') : $overlay.addClass('d-none');
    });
    dataTable.on('draw.dt', function () { $overlay.addClass('d-none'); });

    // Initial visibility: hide cols that are unchecked by default
    ['updated_at', 'packages_count', 'llm_label'].forEach(function (k) {
        dataTable.column(colIndexMap[k]).visible(false);
    });
    annotators.forEach(function (a) {
        dataTable.column(colIndexMap[a.key]).visible(false);
    });

    // Complete annotation filter toggle
    $('#completeOnlyToggle').on('change', function () {
        dataTable.ajax.reload(null, true);
    });

    // Toggle columns
    $(document).on('change', '.col-toggle', function () {
        const idx = colIndexMap[$(this).val()];
        if (idx !== undefined) dataTable.column(idx).visible(this.checked);
    });

    // Select / Deselect all
    $('#checkAllCols').on('click', function () {
        $('.col-toggle').prop('checked', true).trigger('change');
    });
    $('#uncheckAllCols').on('click', function () {
        // Keep at least # column visible; hide all togglable columns
        $('.col-toggle').prop('checked', false).trigger('change');
    });

    // Export CSV with selected columns + current search
    $('#exportCsvBtn').on('click', function () {
        const selectedCols = $.map($('.col-toggle:checked'), function (el) { return el.value; });
        if (selectedCols.length === 0) {
            alert('Please select at least one column to export.');
            return;
        }
        const params = new URLSearchParams({ columns: selectedCols.join(',') });
        const sv = dataTable.search();
        if (sv) params.set('search', sv);
        window.location.href = exportUrl + '?' + params.toString();
    });
});
</script>
@endpush
