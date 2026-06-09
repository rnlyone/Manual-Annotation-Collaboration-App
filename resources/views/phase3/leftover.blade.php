<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-6">

        {{-- Breadcrumb --}}
        <div class="col-12 mb-2">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('phase3.insights') }}">Phase 3 Insights</a></li>
                    <li class="breadcrumb-item active">Leftover Data</li>
                </ol>
            </nav>
        </div>

        {{-- ── STAT CARDS ──────────────────────────────────────────── --}}
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-4">
                    <div class="avatar avatar-lg flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="ti ti-database-off ti-md"></i>
                        </span>
                    </div>
                    <div>
                        <p class="mb-0 text-muted small">Total Leftover</p>
                        <h4 class="mb-0 fw-bold">{{ number_format($contentdata['totalLeftover']) }}</h4>
                        <small class="text-muted">Phase 3 items not yet assigned to annotators</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-header pb-1">
                    <h6 class="mb-0"><i class="ti ti-users me-2"></i>By Annotator Count</h6>
                </div>
                <div class="card-body pt-2">
                    @forelse($contentdata['byAnnotatorCount'] as $cnt => $total)
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small text-muted">{{ $cnt }} annotator{{ $cnt !== 1 ? 's' : '' }}</span>
                            <span class="badge bg-label-{{ $cnt === 0 ? 'danger' : ($cnt === 1 ? 'warning' : 'info') }} ms-2">
                                {{ number_format($total) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No annotator-role users found.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-header pb-1">
                    <h6 class="mb-0"><i class="ti ti-info-circle me-2"></i>About Leftover Data</h6>
                </div>
                <div class="card-body pt-2">
                    <p class="small text-muted mb-0">
                        These are Phase 3 data items that have been identified for re-annotation
                        but are <strong>not yet fully annotated</strong> by all
                        <strong>{{ $contentdata['annotatorRoleCount'] }} annotator{{ $contentdata['annotatorRoleCount'] !== 1 ? 's' : '' }}</strong>.
                        Select the items below and create a new <em>Phase 3</em> package so they can be assigned to annotators.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── FILTER & ACTION BAR ──────────────────────────────── --}}
        <div class="col-12">
            <div class="card border-0 bg-label-secondary">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap justify-content-between mb-2">
                        {{-- Sub-filter by annotator count --}}
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="small fw-semibold text-muted me-1">Annotators:</span>
                            <button class="btn btn-sm btn-label-secondary annotator-filter-btn active" data-count="">All</button>
                            @foreach($contentdata['byAnnotatorCount'] as $cnt => $total)
                            <button class="btn btn-sm btn-label-{{ $cnt === 0 ? 'danger' : ($cnt === 1 ? 'warning' : 'info') }} annotator-filter-btn" data-count="{{ $cnt }}">
                                {{ $cnt }} annotator{{ $cnt !== 1 ? 's' : '' }}
                                <span class="badge bg-white text-dark ms-1">{{ $total }}</span>
                            </button>
                            @endforeach
                        </div>
                        {{-- Selection actions --}}
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button class="btn btn-sm btn-label-primary" id="selectAllVisibleBtn">Select Visible</button>
                            <button class="btn btn-sm btn-label-primary" id="selectAllMatchingBtn">Select All Matching</button>
                            <button class="btn btn-sm btn-label-secondary" id="deselectAllBtn">Deselect All</button>
                            <span class="badge bg-primary ms-1" id="selectedCountBadge">0 selected</span>
                        </div>
                    </div>
                    {{-- Disagree with LLM filters --}}
                    <div class="d-flex align-items-center gap-3 flex-wrap border-top pt-2 mt-1">
                        <span class="small fw-semibold text-muted me-1">Disagree with LLM:</span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input disagree-filter-toggle" type="checkbox" id="disagree1Toggle" role="switch" data-param="disagree_1">
                            <label class="form-check-label small" for="disagree1Toggle">
                                1 annotator disagrees
                                <span class="badge bg-label-warning ms-1">{{ $contentdata['disagreeStats'][1] ?? 0 }}</span>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input disagree-filter-toggle" type="checkbox" id="disagree2Toggle" role="switch" data-param="disagree_2">
                            <label class="form-check-label small" for="disagree2Toggle">
                                2 annotators disagree
                                <span class="badge bg-label-danger ms-1">{{ $contentdata['disagreeStats'][2] ?? 0 }}</span>
                            </label>
                        </div>
                        <span class="text-muted small">
                            <i class="ti ti-info-circle me-1"></i>Compares human label (Normal vs non-Normal) against LLM label.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── DATATABLE ────────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Leftover Phase 3 Data</h5>
                        <small class="text-muted">Select items, then create a new package to assign to annotators.</small>
                    </div>
                    <button class="btn btn-success" id="createPackageBtn" disabled>
                        <i class="ti ti-package me-1"></i>
                        Create Package
                        <span id="createPackageBtnCount" class="badge bg-white text-dark ms-1 d-none">0</span>
                    </button>
                </div>
                <div class="card-datatable table-responsive position-relative">
                    <div id="leftoverOverlay"
                        class="datatable-overlay d-flex flex-column align-items-center justify-content-center text-center"
                        style="position:absolute; inset:0; background:rgba(255,255,255,0.92); z-index:5;">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mb-0 text-muted">Loading leftover data...</p>
                    </div>
                    <table class="datatables-leftover table table-hover w-100">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectPageCheckbox" class="form-check-input"></th>
                                <th>#</th>
                                <th>ID</th>
                                <th>Content</th>
                                <th>Annotators</th>
                                <th>LLM Disagree</th>
                                <th>Done By</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ── CREATE PACKAGE MODAL ──────────────────────────────────────── --}}
<div class="modal fade" id="createPackageModal" tabindex="-1" aria-labelledby="createPackageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createPackageModalLabel">
                    <i class="ti ti-package me-2"></i>Create Leftover Package
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    A new <strong>Phase 3</strong> package will be created with the selected data items.
                    After creation you will be redirected to the package page to assign annotators.
                </p>
                <div class="mb-3">
                    <label for="packageNameInput" class="form-label fw-semibold">Package Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="packageNameInput" value="leftover datas"
                        placeholder="e.g. leftover datas" maxlength="255">
                    <div class="invalid-feedback" id="packageNameError"></div>
                </div>
                <div class="alert alert-primary py-2 mb-0 small">
                    <i class="ti ti-info-circle me-1"></i>
                    <span id="modalSelectedCount">0</span> data item(s) will be added to this package.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmCreatePackageBtn">
                    <span id="createPackageSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    <i class="ti ti-check me-1" id="createPackageCheckIcon"></i>
                    Create Package
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
    const tableUrl         = @json($contentdata['tableUrl']);
    const createPackageUrl = @json($contentdata['createPackageUrl']);
    const allLeftoverIds   = @json($contentdata['allLeftoverIds']);

    let currentAnnotatorFilter = '';
    let selectedIds = new Set();

    // ── DataTable ──────────────────────────────────────────────────────────
    const dataTable = $('.datatables-leftover').DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        ajax: {
            url: tableUrl,
            type: 'GET',
            dataSrc: 'data',
            data: function (d) {
                d.annotator_count_filter = currentAnnotatorFilter;
                d.disagree_1 = $('#disagree1Toggle').is(':checked') ? 1 : 0;
                d.disagree_2 = $('#disagree2Toggle').is(':checked') ? 1 : 0;
            }
        },
        columns: [
            {
                data: null, orderable: false, searchable: false, className: 'dt-checkboxes',
                render: function (data) {
                    const checked = selectedIds.has(data.id) ? 'checked' : '';
                    return '<input type="checkbox" class="form-check-input row-checkbox" data-id="' + $('<div>').text(data.id).html() + '" ' + checked + '>';
                }
            },
            {
                data: null, orderable: false, searchable: false,
                render: function (data, type, row, meta) { return meta.row + 1; }
            },
            { data: 'id', name: 'id' },
            { data: 'content', name: 'content' },
            {
                data: 'annotator_count', name: 'annotator_count', orderable: false, searchable: false,
                render: function (val) {
                    const colorMap = { 0: 'danger', 1: 'warning', 2: 'info' };
                    const color = colorMap[val] || 'secondary';
                    return '<span class="badge bg-label-' + color + '">' + val + ' / {{ $contentdata['annotatorRoleCount'] }}</span>';
                }
            },
            {
                data: 'llm_disagree_count', name: 'llm_disagree_count', orderable: false, searchable: false,
                render: function (val) {
                    if (val === 0 || val === null) return '<span class="text-muted">—</span>';
                    const color = val >= 2 ? 'danger' : 'warning';
                    return '<span class="badge bg-label-' + color + '">' + val + ' disagree</span>';
                }
            },
            {
                data: 'annotators', name: 'annotators', orderable: false, searchable: false,
                render: function (val) {
                    if (!val || val.length === 0) return '<span class="text-muted">—</span>';
                    return val.map(function (name) {
                        return '<span class="badge bg-label-secondary me-1">' + $('<div>').text(name).html() + '</span>';
                    }).join('');
                }
            },
            { data: 'created_at', name: 'created_at' },
        ],
        order: [[6, 'desc']],
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        pageLength: 25,
        dom: '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"<"me-5 ms-n2"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-3"l>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: '', searchPlaceholder: 'Search…',
            sLengthMenu: '_MENU_',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2"></span> Loading…',
            paginate: { first: '&laquo;', last: '&raquo;', next: '&rsaquo;', previous: '&lsaquo;' },
            emptyTable: 'No leftover Phase 3 data found.',
            zeroRecords: 'No matching records found.',
        }
    });

    const $overlay = $('#leftoverOverlay');
    dataTable.on('processing.dt', function (e, settings, processing) {
        processing ? $overlay.removeClass('d-none') : $overlay.addClass('d-none');
    });
    dataTable.on('draw.dt', function () { $overlay.addClass('d-none'); });

    // ── Selection helpers ─────────────────────────────────────────────────
    function updateSelectionUI() {
        const count = selectedIds.size;
        $('#selectedCountBadge').text(count + ' selected');
        if (count > 0) {
            $('#createPackageBtn').prop('disabled', false);
            $('#createPackageBtnCount').text(count).removeClass('d-none');
        } else {
            $('#createPackageBtn').prop('disabled', true);
            $('#createPackageBtnCount').addClass('d-none');
        }
        // Sync visible checkboxes
        $('.row-checkbox').each(function () {
            $(this).prop('checked', selectedIds.has($(this).data('id')));
        });
        // Sync page checkbox
        const visibleIds = getVisibleIds();
        const allChecked = visibleIds.length > 0 && visibleIds.every(id => selectedIds.has(id));
        $('#selectPageCheckbox').prop('checked', allChecked).prop('indeterminate', !allChecked && visibleIds.some(id => selectedIds.has(id)));
    }

    function getVisibleIds() {
        return $('.row-checkbox').map(function () { return $(this).data('id'); }).get();
    }

    // Row checkbox click
    $(document).on('change', '.row-checkbox', function () {
        const id = $(this).data('id');
        this.checked ? selectedIds.add(id) : selectedIds.delete(id);
        updateSelectionUI();
    });

    // Page header checkbox
    $('#selectPageCheckbox').on('change', function () {
        const checked = this.checked;
        getVisibleIds().forEach(id => checked ? selectedIds.add(id) : selectedIds.delete(id));
        updateSelectionUI();
    });

    // After each draw, re-sync checkboxes
    dataTable.on('draw.dt', function () {
        updateSelectionUI();
    });

    // Select all visible rows
    $('#selectAllVisibleBtn').on('click', function () {
        getVisibleIds().forEach(id => selectedIds.add(id));
        updateSelectionUI();
    });

    // Select all matching current filter (uses pre-loaded IDs from server)
    $('#selectAllMatchingBtn').on('click', function () {
        const disagree1 = $('#disagree1Toggle').is(':checked') ? 1 : 0;
        const disagree2 = $('#disagree2Toggle').is(':checked') ? 1 : 0;
        const hasAnyFilter = currentAnnotatorFilter !== '' || disagree1 || disagree2;

        if (!hasAnyFilter) {
            // No filters active — use the pre-loaded full list
            allLeftoverIds.forEach(id => selectedIds.add(id));
            updateSelectionUI();
        } else {
            // Fetch all matching IDs from the server with all active filters
            $.getJSON(tableUrl, {
                draw: 0,
                start: 0,
                length: 99999,
                annotator_count_filter: currentAnnotatorFilter,
                disagree_1: disagree1,
                disagree_2: disagree2,
            }, function (res) {
                (res.data || []).forEach(function (row) { selectedIds.add(row.id); });
                updateSelectionUI();
            });
        }
    });

    // Deselect all
    $('#deselectAllBtn').on('click', function () {
        selectedIds.clear();
        updateSelectionUI();
    });

    // ── Annotator count filter buttons ────────────────────────────────────
    $(document).on('click', '.annotator-filter-btn', function () {
        $('.annotator-filter-btn').removeClass('active');
        $(this).addClass('active');
        currentAnnotatorFilter = $(this).data('count').toString();
        dataTable.ajax.reload(null, true);
    });

    // ── Disagree with LLM filter toggles ─────────────────────────────────
    $('.disagree-filter-toggle').on('change', function () {
        dataTable.ajax.reload(null, true);
    });

    // ── Create Package modal ──────────────────────────────────────────────
    $('#createPackageBtn').on('click', function () {
        $('#modalSelectedCount').text(selectedIds.size);
        $('#packageNameInput').removeClass('is-invalid');
        $('#packageNameError').text('');
        const modal = new bootstrap.Modal(document.getElementById('createPackageModal'));
        modal.show();
    });

    $('#confirmCreatePackageBtn').on('click', function () {
        const packageName = $('#packageNameInput').val().trim();
        if (!packageName) {
            $('#packageNameInput').addClass('is-invalid');
            $('#packageNameError').text('Package name is required.');
            return;
        }
        if (selectedIds.size === 0) {
            Swal.fire({ icon: 'warning', title: 'No items selected', text: 'Please select at least one data item.' });
            return;
        }

        $('#createPackageSpinner').removeClass('d-none');
        $('#createPackageCheckIcon').addClass('d-none');
        $('#confirmCreatePackageBtn').prop('disabled', true);

        $.ajax({
            url: createPackageUrl,
            method: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: JSON.stringify({
                package_name: packageName,
                data_ids: Array.from(selectedIds),
            }),
            success: function (res) {
                bootstrap.Modal.getInstance(document.getElementById('createPackageModal')).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Package Created',
                    text: res.message,
                    confirmButtonText: 'Assign Annotators',
                    showCancelButton: true,
                    cancelButtonText: 'Stay Here',
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = res.assign_url;
                    } else {
                        selectedIds.clear();
                        dataTable.ajax.reload(null, true);
                    }
                });
            },
            error: function (xhr) {
                $('#createPackageSpinner').addClass('d-none');
                $('#createPackageCheckIcon').removeClass('d-none');
                $('#confirmCreatePackageBtn').prop('disabled', false);
                const msg = xhr.responseJSON?.errors?.package_name?.[0]
                    || xhr.responseJSON?.message
                    || 'Failed to create package.';
                if (xhr.responseJSON?.errors?.package_name) {
                    $('#packageNameInput').addClass('is-invalid');
                    $('#packageNameError').text(xhr.responseJSON.errors.package_name[0]);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: msg });
                }
            }
        });
    });

    // Reset spinner state when modal closes
    document.getElementById('createPackageModal').addEventListener('hidden.bs.modal', function () {
        $('#createPackageSpinner').addClass('d-none');
        $('#createPackageCheckIcon').removeClass('d-none');
        $('#confirmCreatePackageBtn').prop('disabled', false);
    });
});
</script>
@endpush
