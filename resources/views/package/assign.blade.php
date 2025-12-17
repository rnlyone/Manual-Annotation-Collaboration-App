@push('styles')
<style>
    .dt-buttons {
        display: none !important;
    }

    .dataTables_wrapper .row:last-child {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .dataTables_wrapper .row:last-child > div {
        display: flex;
        align-items: center;
    }

    .dataTables_wrapper .row:last-child > div:first-child {
        justify-content: flex-start;
    }

    .dataTables_wrapper .row:last-child > div:last-child {
        justify-content: flex-end;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        float: none !important;
        margin: 0;
    }

    .dataTables_wrapper .dataTables_paginate .pagination {
        margin-bottom: 0;
    }

    #selectedDataTable tbody tr.unsaved-row {
        background-color: rgba(255, 193, 7, 0.12);
    }

    .random-select-row {
        gap: 0.75rem;
    }

    .random-select-row .form-check {
        margin-bottom: 0;
    }

    .random-select-control {
        min-width: 220px;
    }

    .random-select-control .input-group-text {
        font-weight: 600;
    }

    .random-select-control small {
        font-size: 0.75rem;
    }

    .table-header-action-btn {
        padding: 0.15rem 0.5rem;
        font-size: 0.75rem;
        line-height: 1.1;
    }

    @media (max-width: 991.98px) {
        .random-select-row {
            flex-direction: column;
            align-items: flex-start !important;
        }

        .random-select-control {
            width: 100%;
        }
    }

    @media (max-width: 767.98px) {
        .dataTables_wrapper .row:last-child > div {
            justify-content: center;
            text-align: center;
        }

        .dataTables_wrapper .dataTables_paginate {
            justify-content: center;
        }
    }
</style>
@endpush

<div class="container-xxl flex-grow-1 container-p-y">


    <div class="row g-6">
        {{-- Breadcrumb --}}
        <div class="col-12 mb-4">
            <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Home</a>
                    </li>
                    <li class="breadcrumb-item">Settings</li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('packages.index') }}">Package</a>
                    </li>
                    <li class="breadcrumb-item active">Assign</li>
                </ol>
            </nav>
        </div>
        {{-- /Breadcrumb --}}

        {{-- Data Tables --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Assign Data to Package</h5>
                        <small class="text-muted">Package: {{ $package->name }} (ID: {{ $package->id }})</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        {{-- back button to packages --}}
                        <a href="{{ route('packages.index') }}" class="btn btn-secondary">
                            <i class="bx bx-arrow-back me-1"></i> Back to Packages
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-plus me-1"></i>Add Data
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#addManuallyModal"><i class="bx bx-edit-alt me-2"></i>Add Manually</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#importCsvModal"><i class="bx bx-spreadsheet me-2"></i>Import CSV</a></li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-success d-flex align-items-center" id="saveAssignmentsButton">
                            <i class="bx bx-save me-1"></i>
                            <span id="saveAssignmentsButtonLabel">Save Selection</span>
                            <span class="badge bg-white text-success border ms-2" id="selectedCountBadge">{{ count($assignedDataIds ?? []) }}</span>
                        </button>
                        <button type="button" class="btn btn-warning d-flex align-items-center d-none" id="saveDeselectionsButton">
                            <i class="bx bx-eraser me-1"></i>
                            <span id="saveDeselectionsButtonLabel">Save Deselections</span>
                            <span class="badge bg-white text-warning border ms-2" id="saveDeselectionsBadge">0</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Unselected Data</h6>
                                        <small class="text-muted">Choose data to add into this package</small>
                                    </div>
                                    <div class="d-flex random-select-row flex-wrap justify-content-lg-end align-items-start">
                                        <div class="form-check" style="margin-right: 3%">
                                            <input class="form-check-input" type="checkbox" id="onlyUnassignedCheckbox" checked>
                                            <label class="form-check-label" for="onlyUnassignedCheckbox">Only unassigned data</label>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllFilteredButton">
                                            <i class="bx bx-check-double me-1"></i>Select all filtered
                                        </button>
                                        <div class="random-select-control">
                                            <label for="randomSelectCount" class="form-label text-muted small mb-1">Random picker</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Qty</span>
                                                <input type="number" min="1" class="form-control" id="randomSelectCount" placeholder="e.g. 10">
                                                <button type="button" class="btn btn-outline-primary" id="randomSelectButton">
                                                    <i class="bx bx-shuffle me-1"></i>Pick
                                                </button>
                                            </div>
                                            <small class="text-muted">Respects current filters.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-datatable table-responsive">
                                    <table class="table table-hover" id="unselectedDataTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 70px;" class="text-center">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm table-header-action-btn" id="selectCurrentPageButton" title="Select rows on this page">
                                                        <i class="bx bx-list-check"></i>
                                                    </button>
                                                </th>
                                                <th style="width: 60px;">#</th>
                                                <th>Content</th>
                                                <th style="width: 90px;">Actions</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card border shadow-none h-100">
                                <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">Selected Data</h6>
                                        <small class="text-muted">Currently assigned (unsaved changes included)</small>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="deselectFilteredSelectedButton">
                                            Deselect all filtered
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllSelectedButton">
                                            Deselect all
                                        </button>
                                    </div>
                                </div>
                                <div class="card-datatable table-responsive">
                                    <table class="table table-hover" id="selectedDataTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;" class="text-center">
                                                    <input type="checkbox" class="form-check-input" id="selectedHeaderCheckbox" checked>
                                                </th>
                                                <th style="width: 60px;">#</th>
                                                <th>Content</th>
                                                <th style="width: 110px;">Packages</th>
                                                <th style="width: 90px;">Actions</th>
                                                <th class="d-none">State</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- /Data Tables --}}

        {{-- Assign Users --}}
        <div class="col-12">
            <div class="card mt-4">
                <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Assign User to Package</h5>
                        <small class="text-muted">Toggle users who should have access to this package</small>
                    </div>
                    <button type="button" class="btn btn-primary d-flex align-items-center d-none" id="saveUserAssignmentsButton">
                        <i class="bx bx-save me-1"></i>
                        <span id="saveUserAssignmentsLabel">Save User Assignments</span>
                        <span class="badge bg-white text-primary border ms-2" id="saveUserAssignmentsBadge">0</span>
                    </button>
                </div>
                <div class="card-body">
                    <div class="card-datatable table-responsive">
                        <table class="table table-hover" id="packageUsersTable">
                            <thead>
                                <tr>
                                    <th style="width: 60px;" class="text-center">Assign</th>
                                    <th style="width: 80px;">ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th style="width: 120px;">Role</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        {{-- Add Manually Modal --}}
        <div class="modal fade" id="addManuallyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Data Manually</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addManuallyForm">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="id" class="form-label">ID</label>
                                <input type="text" class="form-control" id="id" name="id" placeholder="If you not fill it, system will automatically add the ID">
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                                <textarea class="form-control" id="content" name="content" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                            <button id="saveButton" type="button" class="btn btn-primary">Save</button>
                            <button id="saveAndAddAnotherButton" type="button" class="btn btn-primary">Save and Add Another</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {{-- /Add Manually Modal --}}

        {{-- Import CSV Modal --}}
        <div class="modal fade" id="importCsvModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Import CSV File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="importCsvForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="csvFile" class="form-label">Upload CSV File</label>
                                <input type="file" class="form-control" id="csvFile" name="file" accept=".csv" required>
                                <div class="form-text">Accepted format: .csv</div>
                            </div>

                            <div id="csvColumnMapping" style="display: none;">
                                <hr class="my-4">
                                <h6 class="mb-3">Map CSV Columns</h6>

                                <div class="row mb-3 g-3">
                                    <div class="col-md-4">
                                        <label for="idColumnSelect" class="form-label">ID Column</label>
                                        <select class="form-select" id="idColumnSelect" name="id_column">
                                            <option value="">Select ID Column (Optional)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="contentColumnSelect" class="form-label">Content Column</label>
                                        <select class="form-select" id="contentColumnSelect" name="content_column" required>
                                            <option value="">Select Content Column</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="createdAtColumnSelect" class="form-label">Created At Column</label>
                                        <select class="form-select" id="createdAtColumnSelect" name="created_at_column">
                                            <option value="">Select Created At Column (Optional)</option>
                                        </select>
                                    </div>
                                </div>
                                <h6 class="mb-2">Data Preview</h6>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" id="csvPreviewTable">
                                        <thead>
                                            <tr id="csvPreviewHeader"></tr>
                                        </thead>
                                        <tbody id="csvPreviewBody"></tbody>
                                    </table>
                                </div>

                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="skipDuplicateIds" name="skip_duplicates" checked>
                                    <label class="form-check-label" for="skipDuplicateIds">Skip rows when ID already exists</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="csvUploadButton">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {{-- /Import CSV Modal --}}

        {{-- Edit Data Modal --}}
        <div class="modal fade" id="editDataModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="editDataForm">
                        <div class="modal-body">
                            <input type="hidden" id="edit_id" name="id">
                            <div class="mb-3">
                                <label for="edit_content" class="form-label">Content</label>
                                <textarea class="form-control" id="edit_content" name="content" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                            <button id="updateButton" type="button" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {{-- /Edit Data Modal --}}

    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
<script>
$(document).ready(function() {
    $.fn.DataTable.ext.pager.numbers_length = 5;
    const token = '{{ csrf_token() }}';
    const initialSelectedRows = @json($assignedDataDetails ?? []);
    const initialUserAssignments = @json($assignedUserIds ?? []);

    const selectedRowsMap = new Map();
    const lastSavedSelection = new Set();
    const pendingDeselections = new Set();
    const userAssignmentsSet = new Set((initialUserAssignments || []).map(String));
    const lastSavedUserAssignments = new Set(userAssignmentsSet);
    initialSelectedRows.forEach(row => {
        if (row && row.id) {
            const id = String(row.id);
            lastSavedSelection.add(id);
            selectedRowsMap.set(id, {
                id,
                content: row.content ?? '',
                packages_count: row.packages_count ?? 0,
                unsaved: false,
            });
        }
    });

    const selectedData = new Set(selectedRowsMap.keys());
    const $selectedCountBadge = $('#selectedCountBadge');
    const $saveAssignmentsButton = $('#saveAssignmentsButton');
    const $saveAssignmentsButtonLabel = $('#saveAssignmentsButtonLabel');
    const $saveDeselectionsButton = $('#saveDeselectionsButton');
    const $saveDeselectionsButtonLabel = $('#saveDeselectionsButtonLabel');
    const $saveDeselectionsBadge = $('#saveDeselectionsBadge');
    const $deselectFilteredSelectedButton = $('#deselectFilteredSelectedButton');
    const $deselectAllSelectedButton = $('#deselectAllSelectedButton');
    const $onlyUnassignedCheckbox = $('#onlyUnassignedCheckbox');
    const $selectAllFilteredButton = $('#selectAllFilteredButton');
    const $saveUserAssignmentsButton = $('#saveUserAssignmentsButton');
    const $saveUserAssignmentsLabel = $('#saveUserAssignmentsLabel');
    const $saveUserAssignmentsBadge = $('#saveUserAssignmentsBadge');
    const $selectedHeaderCheckbox = $('#selectedHeaderCheckbox');
    const $randomSelectInput = $('#randomSelectCount');
    const $randomSelectButton = $('#randomSelectButton');
    const $selectCurrentPageButton = $('#selectCurrentPageButton');
    const randomSelectButtonDefaultHtml = $randomSelectButton.html();
    const selectAllFilteredButtonDefaultHtml = $selectAllFilteredButton.html();

    const formatContent = (content = '') => {
        const trimmed = content ?? '';
        return trimmed.length > 100 ? `${trimmed.substring(0, 100)}...` : trimmed;
    };

    const selectedTableData = () => {
        return Array.from(selectedRowsMap.values()).sort((a, b) => {
            if (!!a.unsaved !== !!b.unsaved) {
                return a.unsaved ? 1 : -1;
            }
            return (a.content || '').localeCompare(b.content || '');
        });
    };

    const updateDeselectionButton = () => {
        const count = pendingDeselections.size;
        $saveDeselectionsBadge.text(count);
        if (count > 0) {
            $saveDeselectionsButton.removeClass('d-none').prop('disabled', false).removeClass('opacity-75');
            $saveDeselectionsButtonLabel.text('Save Deselections');
        } else {
            $saveDeselectionsButton.addClass('d-none').prop('disabled', false).removeClass('opacity-75');
            $saveDeselectionsButtonLabel.text('Save Deselections');
        }
    };

    const computeUserAssignmentDiff = () => {
        let diff = 0;
        userAssignmentsSet.forEach(id => {
            if (!lastSavedUserAssignments.has(id)) {
                diff += 1;
            }
        });
        lastSavedUserAssignments.forEach(id => {
            if (!userAssignmentsSet.has(id)) {
                diff += 1;
            }
        });
        return diff;
    };

    const updateUserAssignmentsButtonState = () => {
        const diff = computeUserAssignmentDiff();
        $saveUserAssignmentsBadge.text(diff);
        if (diff > 0) {
            $saveUserAssignmentsButton.removeClass('d-none').prop('disabled', false).removeClass('opacity-75');
            $saveUserAssignmentsLabel.text('Save User Assignments');
        } else {
            $saveUserAssignmentsButton.addClass('d-none').prop('disabled', false).removeClass('opacity-75');
            $saveUserAssignmentsLabel.text('Save User Assignments');
        }
    };

    const updateSelectedCountBadge = () => {
        $selectedCountBadge.text(selectedData.size);
    };

    const syncSelectedHeaderCheckbox = () => {
        const checkboxes = $selectedTableEl.find('.selected-select-checkbox');
        if (!checkboxes.length) {
            $selectedHeaderCheckbox.prop('checked', false);
            return;
        }
        const checked = checkboxes.filter(':checked');
        $selectedHeaderCheckbox.prop('checked', checked.length === checkboxes.length);
    };

    let selectedTable;
    let shouldFocusUnsavedRows = false;

    function focusSelectedTableToBottom() {
        if (!selectedTable) {
            return;
        }
        const pageInfo = selectedTable.page.info();
        if (!pageInfo || !pageInfo.pages) {
            return;
        }
        selectedTable.page(pageInfo.pages - 1).draw('page');
    }

    const refreshSelectedTable = () => {
        if (!selectedTable) {
            return;
        }
        const data = selectedTableData();
        selectedTable.clear();
        selectedTable.rows.add(data);
        selectedTable.draw(false);
        if (shouldFocusUnsavedRows) {
            focusSelectedTableToBottom();
            shouldFocusUnsavedRows = false;
        }
    };

    const addSelectedRow = (rowData, options = {}) => {
        if (!rowData || !rowData.id) {
            return false;
        }

        const silent = !!options.silent;
        const id = String(rowData.id);
        const alreadySelected = selectedData.has(id);
        selectedData.add(id);
        const isUnsaved = !lastSavedSelection.has(id);
        selectedRowsMap.set(id, {
            id,
            content: formatContent(rowData.content ?? ''),
            packages_count: rowData.packages_count ?? 0,
            unsaved: isUnsaved,
        });
        if (isUnsaved) {
            shouldFocusUnsavedRows = true;
        }
        if (pendingDeselections.has(id)) {
            pendingDeselections.delete(id);
            updateDeselectionButton();
        }
        if (!silent) {
            refreshSelectedTable();
            updateSelectedCountBadge();
        }
        return !alreadySelected;
    };

    const removeSelectedRow = (id, options = {}) => {
        const stringId = String(id);
        const skipTracking = !!options.skipDeselectionTracking;
        selectedData.delete(stringId);
        selectedRowsMap.delete(stringId);
        refreshSelectedTable();
        updateSelectedCountBadge();
        if (!skipTracking) {
            if (lastSavedSelection.has(stringId)) {
                pendingDeselections.add(stringId);
            } else {
                pendingDeselections.delete(stringId);
            }
            updateDeselectionButton();
        }
    };

    const setRandomSelectButtonLoading = (isLoading) => {
        if (isLoading) {
            $randomSelectButton.prop('disabled', true).addClass('opacity-75').html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Picking');
            return;
        }
        $randomSelectButton.prop('disabled', false).removeClass('opacity-75').html(randomSelectButtonDefaultHtml);
    };

    const setSelectAllFilteredButtonLoading = (isLoading) => {
        if (isLoading) {
            $selectAllFilteredButton.prop('disabled', true).addClass('opacity-75').html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Selecting');
            return;
        }
        $selectAllFilteredButton.prop('disabled', false).removeClass('opacity-75').html(selectAllFilteredButtonDefaultHtml);
    };

    const pickRandomItems = (pool, count) => {
        const working = pool.slice();
        for (let i = working.length - 1; i > 0; i -= 1) {
            const j = Math.floor(Math.random() * (i + 1));
            [working[i], working[j]] = [working[j], working[i]];
        }
        return working.slice(0, count);
    };

    const requestRandomSelection = (desiredCount) => {
        setRandomSelectButtonLoading(true);

        $.ajax({
            url: "{{ route('data.allIds') }}",
            type: 'POST',
            data: {
                _token: token,
                search: unselectedTable.search(),
                only_unassigned: $onlyUnassignedCheckbox.is(':checked') ? 1 : 0,
                include_packages_count: 1,
                exclude_ids_json: JSON.stringify(Array.from(selectedData)),
            }
        })
        .done(function(response) {
            const pool = (response.data || []).filter(item => item && item.id && !selectedData.has(String(item.id)));
            if (!pool.length) {
                Swal.fire({
                    icon: 'info',
                    title: 'No data available',
                    text: 'Nothing left to pick with the current filters.',
                });
                return;
            }

            const sampleSize = Math.min(desiredCount, pool.length);
            const randomItems = pickRandomItems(pool, sampleSize);
            randomItems.forEach(item => addSelectedRow(item, { silent: true }));
            refreshSelectedTable();
            updateSelectedCountBadge();
            syncSelectedHeaderCheckbox();
            unselectedTable.ajax.reload(null, false);

            const shortageNote = pool.length < desiredCount ? ` Only ${pool.length} item${pool.length === 1 ? '' : 's'} matched.` : '';
            Swal.fire({
                icon: 'success',
                title: 'Random selection applied',
                text: `Added ${sampleSize} random row${sampleSize === 1 ? '' : 's'}.${shortageNote}`,
                timer: 2000,
                showConfirmButton: false
            });
        })
        .fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Random pick failed',
                text: 'Unable to fetch data for random selection. Please try again.',
            });
        })
        .always(function() {
            setRandomSelectButtonLoading(false);
        });
    };

    $randomSelectInput.on('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $randomSelectButton.trigger('click');
        }
    });

    $randomSelectButton.on('click', function() {
        if ($randomSelectButton.prop('disabled')) {
            return;
        }

        const desiredCount = parseInt($randomSelectInput.val(), 10);
        if (!Number.isFinite(desiredCount) || desiredCount <= 0) {
            Swal.fire({
                icon: 'info',
                title: 'Enter a number',
                text: 'Please provide how many random rows you want to add.',
            });
            $randomSelectInput.focus();
            return;
        }

        requestRandomSelection(desiredCount);
    });

    const $unselectedTableEl = $('#unselectedDataTable');
    const $selectedTableEl = $('#selectedDataTable');
    const $packageUsersTable = $('#packageUsersTable');

    const unselectedTable = $unselectedTableEl.DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        ajax: {
            url: "{{ route('data.data') }}",
            type: 'POST',
            data: function(d) {
                d._token = token;
                d.only_unassigned = $onlyUnassignedCheckbox.is(':checked') ? 1 : 0;
                d.include_packages_count = 1;
                d.selected_ids_json = JSON.stringify(Array.from(selectedData));
            },
            dataSrc: 'data'
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data) {
                    const disabled = selectedData.has(String(data.id)) ? 'checked disabled' : '';
                    return `<input type="checkbox" class="form-check-input unselected-select-checkbox" value="${data.id}" ${disabled}>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row, meta) {
                    return meta.settings._iDisplayStart + meta.row + 1;
                }
            },
            { data: 'content', name: 'content' },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    return `
                        <div class="d-flex gap-1 justify-content-center">
                            <button type="button" class="btn btn-sm btn-icon btn-primary edit-data-btn" data-user-id="${row.id}" title="Edit">
                                <i class="tf-icons ti ti-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-icon btn-danger delete-data-btn" data-user-id="${row.id}" title="Delete">
                                <i class="tf-icons ti ti-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[2, 'asc']],
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        pageLength: 25,
            dom: '<"card-header border-top rounded-0 flex-wrap pb-md-0 pb-4"<"flex-grow-1 me-4"f><"d-flex justify-content-end align-items-baseline"<"dt-action-buttons d-flex align-items-start gap-3"l>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: "",
            searchPlaceholder: "Search Data",
            sLengthMenu: "_MENU_",
            info: "_START_ to _END_ of _TOTAL_",
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2"></span> Loading... ',
            paginate: {
                first: "&laquo;",
                last: "&raquo;",
                next: "&rsaquo;",
                previous: "&lsaquo;"
            }
        }
    });

    selectedTable = $selectedTableEl.DataTable({
        data: selectedTableData(),
        paging: true,
        searching: true,
        ordering: true,
        orderFixed: {
            pre: [[5, 'asc']]
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data) {
                    return `<input type="checkbox" class="form-check-input selected-select-checkbox" value="${data.id}" checked>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row, meta) {
                    return meta.row + 1;
                }
            },
            { data: 'content', name: 'content', orderable: true },
            {
                data: 'packages_count',
                name: 'packages_count',
                orderable: true,
                render: function(data) {
                    return data ?? 0;
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    const unsavedBadge = row.unsaved ? '<span class="badge bg-warning text-dark mt-1 w-100">Unsaved</span>' : '';
                    return `
                        <div class="d-flex flex-column gap-1 align-items-center">
                            <div class="d-flex gap-1 justify-content-center w-100">
                                <button type="button" class="btn btn-sm btn-icon btn-primary edit-data-btn" data-user-id="${row.id}" title="Edit">
                                    <i class="tf-icons ti ti-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-icon btn-danger delete-data-btn" data-user-id="${row.id}" title="Delete">
                                    <i class="tf-icons ti ti-trash"></i>
                                </button>
                            </div>
                            ${unsavedBadge}
                        </div>
                    `;
                }
            },
            {
                data: 'unsaved',
                visible: false,
                searchable: false,
                render: function(data) {
                    return data ? 1 : 0;
                }
            }
        ],
        order: [[5, 'asc'], [2, 'asc']],
        rowCallback: function(row, data) {
            $(row).toggleClass('unsaved-row', !!data.unsaved);
        },
        dom: '<"card-header border-top rounded-0 flex-wrap pb-md-0 pb-4"<"flex-grow-1 me-4"f><"d-flex justify-content-end align-items-baseline"<"dt-action-buttons d-flex align-items-start gap-3"l>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: "",
            searchPlaceholder: "Search Selected",
            sLengthMenu: "_MENU_",
            info: "_START_ to _END_ of _TOTAL_",
            paginate: {
                first: "&laquo;",
                last: "&raquo;",
                next: "&rsaquo;",
                previous: "&lsaquo;"
            }
        }
    });

    const userAssignmentsTable = $packageUsersTable.DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('users.data') }}",
            type: 'GET',
            dataSrc: 'data'
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(data) {
                    const id = String(data.id);
                    const checked = userAssignmentsSet.has(id) ? 'checked' : '';
                    return `<input type="checkbox" class="form-check-input user-assign-checkbox" value="${id}" ${checked}>`;
                }
            },
            {
                data: 'id',
                orderable: true,
                render: function(data) {
                    return data ?? '-';
                }
            },
            {
                data: 'name',
                orderable: true,
                render: function(data, type, row) {
                    const displayName = row.name ?? '—';
                    const username = row.username ? `<span class="text-muted small">${row.username}</span>` : '';
                    return `<div class="d-flex flex-column"><span class="fw-semibold">${displayName}</span>${username}</div>`;
                }
            },
            {
                data: 'email',
                orderable: true,
                render: function(data) {
                    return data ?? '—';
                }
            },
            {
                data: 'role',
                orderable: true,
                render: function(data) {
                    return data ?? '—';
                }
            }
        ],
        order: [[2, 'asc']],
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pageLength: 10,
        dom: '<"card-header border-top rounded-0 flex-wrap pb-md-0 pb-4"<"flex-grow-1 me-4"f><"d-flex justify-content-end align-items-baseline"<"dt-action-buttons d-flex align-items-start gap-3"l>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: "",
            searchPlaceholder: "Search Users",
            sLengthMenu: "_MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            paginate: {
                first: "&laquo;",
                last: "&raquo;",
                next: "&rsaquo;",
                previous: "&lsaquo;"
            }
        }
    });

    $selectedTableEl.on('draw.dt', function() {
        syncSelectedHeaderCheckbox();
    });

    $packageUsersTable.on('change', '.user-assign-checkbox', function() {
        const userId = String($(this).val());
        if ($(this).is(':checked')) {
            userAssignmentsSet.add(userId);
        } else {
            userAssignmentsSet.delete(userId);
        }
        updateUserAssignmentsButtonState();
    });

    updateSelectedCountBadge();
    syncSelectedHeaderCheckbox();
    updateDeselectionButton();
    updateUserAssignmentsButtonState();

    $unselectedTableEl.on('change', '.unselected-select-checkbox', function() {
        const $checkbox = $(this);
        const dataId = $checkbox.val();
        const rowData = unselectedTable.row($checkbox.closest('tr')).data();
        if ($checkbox.is(':checked')) {
            addSelectedRow(rowData);
        } else {
            removeSelectedRow(dataId);
        }

        unselectedTable.ajax.reload(null, false);
    });

    $selectedTableEl.on('change', '.selected-select-checkbox', function() {
        const $checkbox = $(this);
        const dataId = $checkbox.val();
        if (!$checkbox.is(':checked')) {
            removeSelectedRow(dataId);
            unselectedTable.ajax.reload(null, false);
        }
    });

    $selectCurrentPageButton.on('click', function() {
        const rows = unselectedTable.rows({ page: 'current' }).data().toArray();
        if (!rows.length) {
            Swal.fire({
                icon: 'info',
                title: 'Nothing to select',
                text: 'No rows are visible on this page.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        let addedCount = 0;
        rows.forEach(row => {
            if (row && row.id && !selectedData.has(String(row.id))) {
                addSelectedRow(row, { silent: true });
                addedCount += 1;
            }
        });

        if (!addedCount) {
            Swal.fire({
                icon: 'info',
                title: 'All set',
                text: 'Every row on this page is already selected.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        refreshSelectedTable();
        updateSelectedCountBadge();
        syncSelectedHeaderCheckbox();
        unselectedTable.ajax.reload(null, false);

        Swal.fire({
            icon: 'success',
            title: 'Page selected',
            text: `Added ${addedCount} row${addedCount === 1 ? '' : 's'} from the current page.`,
            timer: 1500,
            showConfirmButton: false
        });
    });

    $selectedHeaderCheckbox.on('change', function() {
        const isChecked = $(this).is(':checked');
        if (isChecked) {
            $selectedTableEl.find('.selected-select-checkbox').prop('checked', true);
            syncSelectedHeaderCheckbox();
            return;
        }

        const rows = selectedTable.rows({ page: 'current' }).data().toArray();
        if (!rows.length) {
            syncSelectedHeaderCheckbox();
            return;
        }

        rows.forEach(row => {
            removeSelectedRow(row.id);
        });
        unselectedTable.ajax.reload(null, false);
    });

    $onlyUnassignedCheckbox.on('change', function() {
        unselectedTable.ajax.reload();
    });

    $selectAllFilteredButton.on('click', function() {
        if ($selectAllFilteredButton.prop('disabled')) {
            return;
        }

        setSelectAllFilteredButtonLoading(true);

        $.ajax({
            url: "{{ route('data.allIds') }}",
            type: 'POST',
            data: {
                _token: token,
                search: unselectedTable.search(),
                only_unassigned: $onlyUnassignedCheckbox.is(':checked') ? 1 : 0,
                include_packages_count: 1,
            }
        })
        .done(function(response) {
            const dataset = response.data || [];
            if (!dataset.length) {
                Swal.fire({
                    icon: 'info',
                    title: 'Nothing to select',
                    text: 'No rows match the current filters.',
                    timer: 1500,
                    showConfirmButton: false
                });
                return;
            }
            let addedCount = 0;
            dataset.forEach(item => {
                if (!item || !item.id) {
                    return;
                }
                const wasAdded = addSelectedRow(item, { silent: true });
                if (wasAdded) {
                    addedCount += 1;
                }
            });

            refreshSelectedTable();
            updateSelectedCountBadge();
            syncSelectedHeaderCheckbox();
            unselectedTable.ajax.reload(null, false);

            if (!addedCount) {
                Swal.fire({
                    icon: 'info',
                    title: 'Already selected',
                    text: 'All filtered rows are already in the selection.',
                    timer: 1500,
                    showConfirmButton: false
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Selection updated',
                text: `Added ${addedCount} filtered row${addedCount === 1 ? '' : 's'}.`,
                timer: 1500,
                showConfirmButton: false
            });
        })
        .fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Unable to select all rows. Please try again.'
            });
        })
        .always(function() {
            setSelectAllFilteredButtonLoading(false);
        });
    });

    $('#saveAssignmentsButton').on('click', function() {
        if ($saveAssignmentsButton.prop('disabled')) {
            return;
        }

        $saveAssignmentsButton.prop('disabled', true).addClass('opacity-75');
        $saveAssignmentsButtonLabel.text('Saving...');

        const payloadIds = Array.from(selectedData);
        const chunkSize = 800;
        const chunkTotal = payloadIds.length ? Math.ceil(payloadIds.length / chunkSize) : 1;

        const requests = [];
        if (payloadIds.length) {
            for (let i = 0; i < payloadIds.length; i += chunkSize) {
                const chunk = payloadIds.slice(i, i + chunkSize);
                const chunkIndex = Math.floor(i / chunkSize);
                requests.push($.ajax({
                    url: "{{ route('packages.assignData', $package) }}",
                    method: 'POST',
                    data: {
                        _token: token,
                        chunk_index: chunkIndex,
                        chunk_total: chunkTotal,
                        data_ids: chunk
                    }
                }));
            }
        } else {
            requests.push($.ajax({
                url: "{{ route('packages.assignData', $package) }}",
                method: 'POST',
                data: {
                    _token: token,
                    data_ids: [],
                    chunk_index: 0,
                    chunk_total: 1
                }
            }));
        }

        $.when.apply($, requests)
            .done(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Selection saved successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });

                lastSavedSelection.clear();
                selectedRowsMap.forEach((entry, id) => {
                    entry.unsaved = false;
                    selectedRowsMap.set(id, entry);
                    lastSavedSelection.add(id);
                });
                refreshSelectedTable();
            })
            .fail(function(xhr) {
                let errorMessage = 'Failed to save selection';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: errorMessage
                });
            })
            .always(function() {
                $saveAssignmentsButton.prop('disabled', false).removeClass('opacity-75');
                $saveAssignmentsButtonLabel.text('Save Selection');
            });
    });

    $('#saveDeselectionsButton').on('click', function() {
        if ($saveDeselectionsButton.prop('disabled')) {
            return;
        }

        const idsBeingSaved = Array.from(pendingDeselections);
        if (!idsBeingSaved.length) {
            updateDeselectionButton();
            return;
        }

        $saveDeselectionsButton.prop('disabled', true).addClass('opacity-75');
        $saveDeselectionsButtonLabel.text('Saving...');

        $.ajax({
            url: "{{ route('packages.unassignData', $package) }}",
            method: 'POST',
            data: {
                _token: token,
                data_ids: idsBeingSaved
            }
        })
        .done(function(response) {
            Swal.fire({
                icon: 'success',
                title: 'Deselections Saved',
                text: response.message || 'Deselections saved successfully.',
                timer: 2000,
                showConfirmButton: false
            });

            idsBeingSaved.forEach(id => {
                pendingDeselections.delete(String(id));
                lastSavedSelection.delete(String(id));
            });
            updateDeselectionButton();
            unselectedTable.ajax.reload(null, false);
        })
        .fail(function(xhr) {
            let errorMessage = 'Failed to save deselections';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        })
        .always(function() {
            $saveDeselectionsButton.prop('disabled', false).removeClass('opacity-75');
            $saveDeselectionsButtonLabel.text('Save Deselections');
            updateDeselectionButton();
        });
    });

    $saveUserAssignmentsButton.on('click', function() {
        if ($saveUserAssignmentsButton.prop('disabled')) {
            return;
        }

        const payload = Array.from(userAssignmentsSet);
        $saveUserAssignmentsButton.prop('disabled', true).addClass('opacity-75');
        $saveUserAssignmentsLabel.text('Saving...');

        $.ajax({
            url: "{{ route('packages.assignUsers', $package) }}",
            method: 'POST',
            data: {
                _token: token,
                user_ids: payload
            }
        })
        .done(function(response) {
            Swal.fire({
                icon: 'success',
                title: 'User Assignments Saved',
                text: response.message || 'User assignments saved successfully.',
                timer: 2000,
                showConfirmButton: false
            });

            lastSavedUserAssignments.clear();
            userAssignmentsSet.forEach(id => lastSavedUserAssignments.add(id));
            updateUserAssignmentsButtonState();
        })
        .fail(function(xhr) {
            let errorMessage = 'Failed to save user assignments';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMessage
            });
        })
        .always(function() {
            $saveUserAssignmentsButton.prop('disabled', false).removeClass('opacity-75');
            $saveUserAssignmentsLabel.text('Save User Assignments');
            updateUserAssignmentsButtonState();
        });
    });

    $deselectFilteredSelectedButton.on('click', function() {
        if (!selectedTable) {
            return;
        }

        const filteredRows = selectedTable.rows({ search: 'applied' }).data().toArray();
        if (!filteredRows.length) {
            Swal.fire({
                icon: 'info',
                title: 'Nothing to deselect',
                text: 'No rows match the current filter.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        let removedCount = 0;
        filteredRows.forEach(row => {
            const id = String(row.id);
            if (selectedRowsMap.has(id)) {
                selectedRowsMap.delete(id);
                selectedData.delete(id);
                if (lastSavedSelection.has(id)) {
                    pendingDeselections.add(id);
                } else {
                    pendingDeselections.delete(id);
                }
                removedCount += 1;
            }
        });

        if (!removedCount) {
            Swal.fire({
                icon: 'info',
                title: 'Nothing to deselect',
                text: 'Filtered rows are already cleared.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        refreshSelectedTable();
        updateSelectedCountBadge();
        updateDeselectionButton();
        syncSelectedHeaderCheckbox();
        unselectedTable.ajax.reload(null, false);

        Swal.fire({
            icon: 'success',
            title: 'Deselected',
            text: `Removed ${removedCount} filtered row${removedCount === 1 ? '' : 's'}.`,
            timer: 1500,
            showConfirmButton: false
        });
    });

    $deselectAllSelectedButton.on('click', function() {
        if (!$deselectAllSelectedButton.length) {
            return;
        }

        if (!selectedData.size) {
            Swal.fire({
                icon: 'info',
                title: 'No selections',
                text: 'There are no rows to deselect.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        const previousSelectionIds = Array.from(selectedData);
        selectedData.clear();
        selectedRowsMap.clear();

        previousSelectionIds.forEach(id => {
            if (lastSavedSelection.has(id)) {
                pendingDeselections.add(id);
            }
        });
        updateDeselectionButton();
        refreshSelectedTable();
        updateSelectedCountBadge();
        syncSelectedHeaderCheckbox();
        unselectedTable.ajax.reload();

        Swal.fire({
            icon: 'success',
            title: 'Selections cleared',
            text: `Removed ${previousSelectionIds.length} row${previousSelectionIds.length === 1 ? '' : 's'} from the selection.`,
            timer: 1500,
            showConfirmButton: false
        });
    });

    // Add ajax edit modal functionality here
    $(document).on('click', '.edit-data-btn', function() {
        const userId = $(this).data('user-id');
        // Fetch user data via AJAX
        $.ajax({
            url: `/data/${userId}`,
            type: 'GET',
            success: function(response) {
                // Populate the edit form fields
                $('#edit_id').val(response.id);
                $('#edit_content').val(response.content);
                // Show the edit modal
                $('#editDataModal').modal('show');
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to fetch data.'
                });
            }
        });
    });

    // Handle Update button
    $('#updateButton').on('click', function(e) {
        e.preventDefault();
        const dataId = $('#edit_id').val();
        const formData = {
            content: $('#edit_content').val(),
            _token: '{{ csrf_token() }}',
            _method: 'PUT'
        };

        $.ajax({
            url: `/data/${dataId}`,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('#updateButton').prop('disabled', true);
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message || 'Data updated successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Close modal
                $('#editDataModal').modal('hide');

                // Sync tables
                unselectedTable.ajax.reload(null, false);

                const previewContent = formatContent($('#edit_content').val());
                if (selectedRowsMap.has(String(dataId))) {
                    const entry = selectedRowsMap.get(String(dataId));
                    entry.content = previewContent;
                    selectedRowsMap.set(String(dataId), entry);
                    refreshSelectedTable();
                }
            },
            error: function(xhr) {
                let errorMessage = 'Failed to update data';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: errorMessage
                });
            },
            complete: function() {
                $('#updateButton').prop('disabled', false);
            }
        });
    });

    // Handle delete with SweetAlert2
    $(document).on('click', '.delete-data-btn', function() {
        const userId = $(this).data('user-id');

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/data/${userId}`,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Data has been deleted.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        removeSelectedRow(userId, { skipDeselectionTracking: true });
                        lastSavedSelection.delete(String(userId));
                        unselectedTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Failed to delete data.'
                        });
                    }
                });
            }
        });
    });

    // Handle Save button
    $('#saveButton').on('click', function(e) {
        e.preventDefault();
        submitForm(true);
    });

    // Handle Save and Add Another button
    $('#saveAndAddAnotherButton').on('click', function(e) {
        e.preventDefault();
        submitForm(false);
    });

    // Function to submit the form
    function submitForm(closeModal) {
        const formData = {
            id: $('#id').val(),
            content: $('#content').val(),
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: "{{ route('data.store') }}",
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('#saveButton, #saveAndAddAnotherButton').prop('disabled', true);
            },
            success: function(response) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });

                // Reset form
                $('#addManuallyForm')[0].reset();

                // Reload DataTable
                unselectedTable.ajax.reload(null, false);

                // Close modal if Save button was clicked
                if (closeModal) {
                    $('#addManuallyModal').modal('hide');
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: errorMessage
                });
            },
            complete: function() {
                $('#saveButton, #saveAndAddAnotherButton').prop('disabled', false);
            }
        });
    }

    // Handle CSV import form
    $('#importCsvForm').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = $('#csvFile')[0];
        const idColumn = $('#idColumnSelect').val();
        const contentColumn = $('#contentColumnSelect').val();
        const createdAtColumn = $('#createdAtColumnSelect').val();
        const skipDuplicates = $('#skipDuplicateIds').is(':checked');

        // Validate content column is selected
        if (!contentColumn) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Please select a content column'
            });
            return;
        }

        // Validate file is selected
        if (!fileInput.files[0]) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Please select a CSV file'
            });
            return;
        }

        formData.append('file', fileInput.files[0]);
        formData.append('content_column', contentColumn);
        if (idColumn) {
            formData.append('id_column', idColumn);
        }
        if (createdAtColumn) {
            formData.append('created_at_column', createdAtColumn);
        }
        formData.append('skip_duplicates', skipDuplicates ? 1 : 0);
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: "{{ route('data.addbycsv') }}",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#csvUploadButton').prop('disabled', true).text('Uploading...');
            },
            success: function(response) {
                let message = response.message;
                if (response.errors && response.errors.length > 0) {
                    message += '<br><br><strong>Errors:</strong><br>' + response.errors.join('<br>');
                }

                Swal.fire({
                    icon: response.errors && response.errors.length > 0 ? 'warning' : 'success',
                    title: 'Import Complete!',
                    html: message,
                    confirmButtonText: 'OK'
                });

                // Reset form and hide mapping
                $('#importCsvForm')[0].reset();
                $('#csvColumnMapping').hide();
                $('#skipDuplicateIds').prop('checked', true);

                // Close modal
                $('#importCsvModal').modal('hide');

                // Reload DataTable
                unselectedTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                let errorMessage = 'Failed to import CSV file';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: errorMessage
                });
            },
            complete: function() {
                $('#csvUploadButton').prop('disabled', false).text('Upload');
            }
        });
    });

    // CSV file selection and column mapping functionality
    $('#csvFile').on('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        Papa.parse(file, {
            skipEmptyLines: 'greedy',
            complete: function(results) {
                const rows = results.data;
                if (!rows.length) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'CSV file appears to be empty.' });
                    return;
                }

                const headers = rows[0];
                if (headers.length) {
                    headers[0] = headers[0].replace(/^\uFEFF/, '');
                }
                const idSelect = $('#idColumnSelect');
                const contentSelect = $('#contentColumnSelect');
                const createdAtSelect = $('#createdAtColumnSelect');

                idSelect.empty().append('<option value="">Select ID Column (Optional)</option>');
                contentSelect.empty().append('<option value="">Select Content Column</option>');
                createdAtSelect.empty().append('<option value="">Select Created At Column (Optional)</option>');

                headers.forEach((header, idx) => {
                    const option = `<option value="${idx}">${header}</option>`;
                    idSelect.append(option);
                    contentSelect.append(option);
                    createdAtSelect.append(option);
                });

                $('#csvPreviewHeader').empty();
                headers.forEach(header => {
                    $('#csvPreviewHeader').append(`<th>${header}</th>`);
                });

                $('#csvPreviewBody').empty();
                const previewRows = rows.slice(1, 6);
                previewRows.forEach(columns => {
                    let rowHtml = '<tr>';
                    headers.forEach((_, idx) => {
                        let cell = columns[idx] ?? '';
                        if (cell && cell.length > 256) {
                            cell = cell.substring(0, 256) + '...';
                        }
                        rowHtml += `<td>${cell}</td>`;
                    });
                    rowHtml += '</tr>';
                    $('#csvPreviewBody').append(rowHtml);
                });

                $('#csvColumnMapping').show();
            },
            error: function(error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Failed to parse CSV file.' });
            }
        });
    });

});
</script>
@endpush
