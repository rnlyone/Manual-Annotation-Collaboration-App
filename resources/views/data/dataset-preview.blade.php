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
        {{-- /Breadcrumb --}}

        {{-- Stats Card (Data Pivot) --}}
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
                        <h4 class="mb-0 fw-bold" id="totalCountDisplay">{{ number_format($contentdata['totalCount']) }}</h4>
                        <small class="text-muted">Entries in Data Management</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Column Customizer --}}
        <div class="col-12 col-md-8">
            <div class="card h-100">
                <div class="card-header pb-2">
                    <h6 class="mb-0"><i class="ti ti-columns me-2"></i>Customize Columns</h6>
                    <small class="text-muted">Select which columns to display in the preview and include in CSV export.</small>
                </div>
                <div class="card-body pt-2">
                    <div class="d-flex flex-wrap gap-3" id="columnToggles">
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
                            <input class="form-check-input col-toggle" type="checkbox" id="col-updated_at" value="updated_at" checked>
                            <label class="form-check-label" for="col-updated_at">Updated At</label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input col-toggle" type="checkbox" id="col-packages_count" value="packages_count">
                            <label class="form-check-label" for="col-packages_count">Package Count</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DataTable Preview Card --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Dataset Preview</h5>
                        <small class="text-muted">Live preview with selected columns. Export downloads all records matching the current search.</small>
                    </div>
                    <button type="button" id="exportCsvBtn" class="btn btn-success">
                        <i class="ti ti-download me-1"></i>Export CSV
                    </button>
                </div>
                <div class="card-datatable table-responsive position-relative">
                    <div id="datasetOverlay" class="datatable-overlay d-flex flex-column align-items-center justify-content-center text-center"
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
                                <th class="col-th" data-col="id">ID</th>
                                <th class="col-th" data-col="content">Content</th>
                                <th class="col-th" data-col="created_at">Created At</th>
                                <th class="col-th" data-col="updated_at">Updated At</th>
                                <th class="col-th" data-col="packages_count">Package Count</th>
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
    const exportUrl = @json($contentdata['exportUrl']);

    // Column index map: key = value attr, index in DataTable columns array
    const colIndexMap = {
        id: 1,
        content: 2,
        created_at: 3,
        updated_at: 4,
        packages_count: 5,
    };

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
                d.include_packages_count = 1;
            }
        },
        columns: [
            {
                data: null,
                name: 'index',
                orderable: false,
                searchable: false,
                render: function (data, type, row, meta) { return meta.row + 1; }
            },
            { data: 'id',             name: 'id' },
            { data: 'content',        name: 'content' },
            { data: 'created_at',     name: 'created_at' },
            { data: 'updated_at',     name: 'updated_at' },
            { data: 'packages_count', name: 'packages_count', orderable: false, searchable: false },
        ],
        order: [[3, 'desc']],
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        pageLength: 25,
        dom: '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"<"me-5 ms-n2"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-3"lB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: '',
            searchPlaceholder: 'Search records...',
            sLengthMenu: '_MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2"></span> Loading... ',
            paginate: { first: '&laquo;', last: '&raquo;', next: '&rsaquo;', previous: '&lsaquo;' }
        }
    });

    dataTable.on('processing.dt', function (e, settings, processing) {
        if (processing) $overlay.removeClass('d-none');
        else $overlay.addClass('d-none');
    });

    dataTable.on('draw.dt', function () {
        $overlay.addClass('d-none');
    });

    // Apply initial column visibility: packages_count hidden by default
    dataTable.column(colIndexMap['packages_count']).visible(false);

    // Column toggle checkboxes
    $('.col-toggle').on('change', function () {
        const col = $(this).val();
        const idx = colIndexMap[col];
        if (idx !== undefined) {
            dataTable.column(idx).visible(this.checked);
        }
    });

    // Export CSV: collect selected columns + current search value
    $('#exportCsvBtn').on('click', function () {
        const selectedCols = [];
        $('.col-toggle:checked').each(function () {
            selectedCols.push($(this).val());
        });

        if (selectedCols.length === 0) {
            alert('Please select at least one column to export.');
            return;
        }

        const searchVal = dataTable.search();
        const params = new URLSearchParams({
            columns: selectedCols.join(','),
        });
        if (searchVal) {
            params.set('search', searchVal);
        }

        window.location.href = exportUrl + '?' + params.toString();
    });
});
</script>
@endpush
