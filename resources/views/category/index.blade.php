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
                    <li class="breadcrumb-item active">Category</li>
                </ol>
            </nav>
        </div>
    </div>
    {{-- /Breadcrumb --}}

    {{-- Category Table --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Category List</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#createCategoryModal">
                    <i class="ri-add-line me-1"></i> Add Category
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="categoryTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{-- /Category Table --}}

</div>

{{-- Create Category Modal --}}
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createCategoryForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create_category_id" class="form-label">Category ID</label>
                        <input type="text" class="form-control" id="create_category_id" name="id">
                    </div>
                    <div class="mb-3">
                        <label for="create_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="create_category_name" name="name" required>
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

{{-- Edit Category Modal --}}
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_category_id_hidden" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Category ID</label>
                        <input type="text" class="form-control" id="edit_category_id" name="id" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="name" required>
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
    var table = $('#categoryTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: '{{ route("categories.data") }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            {
                data: null,
                render: function (data, type, row) {
                    const rowData = encodeURIComponent(JSON.stringify({ id: row.id, name: row.name }));
                    return `
                        <div class="d-flex gap-2">
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

    // Create Category
    $('#createCategoryForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: '{{ route("categories.store") }}',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                $('#createCategoryModal').modal('hide');
                $('#createCategoryForm')[0].reset();
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Category created successfully',
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

    // Edit Category - Show Modal
    $(document).on('click', '.edit-btn', function() {
        const rowDataAttr = $(this).data('row');
        const row = rowDataAttr ? JSON.parse(decodeURIComponent(rowDataAttr)) : null;

        if (!row) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to load category data.' });
            return;
        }

        $('#edit_category_id_hidden').val(row.id);
        $('#edit_category_id').val(row.id);
        $('#edit_category_name').val(row.name);
        $('#editCategoryModal').modal('show');
    });

    // Update Category
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        var id = $('#edit_category_id_hidden').val();
        $.ajax({
            url: '{{ route("categories.update", ":id") }}'.replace(':id', id),
            method: 'PUT',
            data: $(this).serialize(),
            success: function(response) {
                $('#editCategoryModal').modal('hide');
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Category updated successfully',
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

    // Delete Category
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
                    url: '{{ route("categories.destroy", ":id") }}'.replace(':id', id),
                    method: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        table.ajax.reload();
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Category has been deleted.',
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
