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
                    <li class="breadcrumb-item active">Package</li>
                </ol>
            </nav>
        </div>
    </div>
    {{-- /Breadcrumb --}}

    {{-- Package Table --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Package List</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#createPackageModal">
                    <i class="ri-add-line me-1"></i> Add Package
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="packageTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Data Count</th>
                                <th>User Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{-- /Package Table --}}

</div>

{{-- Create Package Modal --}}
<div class="modal fade" id="createPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createPackageForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create_package_id" class="form-label">Package ID</label>
                        <input type="text" class="form-control" id="create_package_id" name="id">
                    </div>
                    <div class="mb-3">
                        <label for="create_package_name" class="form-label">Package Name</label>
                        <input type="text" class="form-control" id="create_package_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Package Modal --}}
<div class="modal fade" id="editPackageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPackageForm">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_package_id_hidden" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_package_id" class="form-label">Package ID</label>
                        <input type="text" class="form-control" id="edit_package_id" name="id" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_package_name" class="form-label">Package Name</label>
                        <input type="text" class="form-control" id="edit_package_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#packageTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: '{{ route("packages.data") }}',
        columns: [
            // index number
            {
                data: null,
                render: function (data, type, row, meta) {
                    return meta.row + 1;
                },
                orderable: false
            },
            { data: 'name', name: 'name' },
            {
                data: 'data_count',
                name: 'data_count',
                render: function(data) {
                    return data ?? 0;
                }
            },
            {
                data: 'user_count',
                name: 'user_count',
                render: function(data) {
                    return data ?? 0;
                }
            },
            {
                data: null,
                render: function (data, type, row) {
                    const rowData = encodeURIComponent(JSON.stringify({ id: row.id, name: row.name }));
                    return `
                        <div class="d-flex gap-2">
                            <a href="{{ route('packages.edit', ':id') }}" class="btn btn-sm btn-primary assign-package-btn" data-row="${rowData}" onclick="event.preventDefault(); window.location.href='{{ route('packages.edit', ':id') }}'.replace(':id', ${row.id});">
                                Assign Package
                            </a>
                            <button type="button" class="btn btn-sm btn-info edit-btn" data-row="${rowData}">
                                Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="${row.id}">
                                Delete
                            </button>
                        </div>
                    `;
                },
                orderable: false
            }
        ]
    });

    // Create Package
    $('#createPackageForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '{{ route("packages.store") }}',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#createPackageModal').modal('hide');
                $('#createPackageForm')[0].reset();
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Package created successfully',
                    timer: 1500
                });
            },
            error: function(xhr) {
                let errorMessage = 'Something went wrong';
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
            }
        });
    });

    // Edit Package - Show Modal
    $(document).on('click', '.edit-btn', function() {
        const rowDataAttr = $(this).data('row');
        const row = rowDataAttr ? JSON.parse(decodeURIComponent(rowDataAttr)) : null;

        if (!row) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to load package data.' });
            return;
        }

        $('#edit_package_id_hidden').val(row.id);
        $('#edit_package_id').val(row.id);
        $('#edit_package_name').val(row.name);
        $('#editPackageModal').modal('show');
    });

    // Update Package
    $('#editPackageForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#edit_package_id_hidden').val();
        $.ajax({
            url: '{{ route("packages.update", ":id") }}'.replace(':id', id),
            method: 'PUT',
            data: $(this).serialize(),
            success: function(response) {
                $('#editPackageModal').modal('hide');
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Package updated successfully',
                    timer: 1500
                });
            },
            error: function(xhr) {
                let errorMessage = 'Something went wrong';
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
            }
        });
    });

    // Delete Package
    $(document).on('click', '.delete-btn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("packages.destroy", ":id") }}'.replace(':id', id),
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        table.ajax.reload();
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Package has been deleted.',
                            timer: 1500
                        });
                    },
                    error: function(xhr) {
                        let errorMessage = 'Something went wrong';
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
                    }
                });
            }
        });
    });
});
</script>
@endpush
