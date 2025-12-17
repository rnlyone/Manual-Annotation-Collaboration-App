<div class="container-xxl flex-grow-1 container-p-y">


    <div class="row g-6">
        {{-- Breadcrumb --}}
        <div class="col-12 mb-4">
            <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Home</a>
                    </li>
                    <li class="breadcrumb-item active">Settings</li>
                    <li class="breadcrumb-item active">Data</li>
                </ol>
            </nav>
        </div>
        {{-- /Breadcrumb --}}

        {{-- Data Table Card --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Data Management</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bx bx-plus me-1"></i>Add Data
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#addManuallyModal"><i class="bx bx-edit-alt me-2"></i>Add Manually</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#importCsvModal"><i class="bx bx-spreadsheet me-2"></i>Import CSV</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-datatable table-responsive position-relative">
                    <div id="dataTableOverlay" class="datatable-overlay d-flex flex-column align-items-center justify-content-center text-center" style="position:absolute; inset:0; background:rgba(255,255,255,0.92); z-index:5;">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mb-0 text-muted">Loading latest data...</p>
                    </div>
                    <table class="datatables-data table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ID</th>
                                <th>Content</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        {{-- /Data Table Card --}}

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
    const $table = $('.datatables-data');
    const $overlay = $('#dataTableOverlay');

    const dataTable = $table.DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        ajax: {
            url: "{{ route('data.data') }}",
            type: 'GET',
            dataSrc: 'data'
        },
        columns: [
            //index non sortable
            { data: null, name: 'index', render: function (data, type, row, meta) { return meta.row + 1; } },
            { data: 'id', name: 'id' },
            { data: 'content', name: 'content' },
            { data: 'created_at', name: 'created_at' },
            { data: 'updated_at', name: 'updated_at' },
                            {
                    data: null,
                    render: function (data, type, row) {
                        return `
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-info edit-user-btn" data-user-id="${row.id}">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-user-btn" data-user-id="${row.id}">
                                    Delete
                                </button>
                            </div>
                        `;
                    },
                    orderable: false
                }
        ],
        order: [[3, 'desc']],
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        pageLength: 25,
        dom: '<"card-header d-flex border-top rounded-0 flex-wrap pb-md-0 pb-4"<"me-5 ms-n2"f><"d-flex justify-content-start justify-content-md-end align-items-baseline"<"dt-action-buttons d-flex align-items-start align-items-md-center justify-content-sm-center gap-3"lB>>>t<"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
            search: "",
            searchPlaceholder: "Search Data",
            sLengthMenu: "_MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            processing: '<span class="spinner-border spinner-border-sm align-middle me-2"></span> Loading... ',
            paginate: {
                first: "&laquo;",
                last: "&raquo;",
                next: "&rsaquo;",
                previous: "&lsaquo;"
            }
        }
    });

    dataTable.on('processing.dt', function(e, settings, processing) {
        if (processing) {
            $overlay.removeClass('d-none');
        } else {
            $overlay.addClass('d-none');
        }
    });

    dataTable.on('draw.dt', function() {
        $overlay.addClass('d-none');
    });

    // Add ajax edit modal functionality here
    $(document).on('click', '.edit-user-btn', function() {
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

                // Reload DataTable
                dataTable.ajax.reload(null, false);
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
    $(document).on('click', '.delete-user-btn', function() {
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
                        dataTable.ajax.reload(null, false);
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
                dataTable.ajax.reload(null, false);

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
                dataTable.ajax.reload(null, false);
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
                if (headers.length) {o
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
