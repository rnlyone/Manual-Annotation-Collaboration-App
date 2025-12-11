<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-6">
        {{-- Breadcrumb --}}
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Home</a>
                    </li>
                    <li class="breadcrumb-item active">Settings</li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </nav>
        </div>
        {{-- /Breadcrumb --}}
        @php
            $isEditError = old('_method') === 'PUT';
        @endphp

        <!-- DataTable with Buttons -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">User Management</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">Create New
                    User</button>
            </div>
            <div id="userTableDiv" class="card-datatable table-responsive pt-0">
                <table id="userTable" class="datatables-basic table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- make a modal for new user creation --}}
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-edit-user">
            <div class="modal-content">
                <div class="modal-header bg-transparent">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5 px-sm-5 pt-50">
                    <form id="createUserForm" class="row g-3" method="POST" action="{{ route('users.store') }}">
                        @csrf
                        {{-- username --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="username">Username</label>
                            <input type="text" id="username" name="username"
                                class="form-control @if(!$isEditError) @error('username') is-invalid @enderror @endif" placeholder="johndoe" required
                                value="{{ $isEditError ? '' : old('username') }}" />
                            @if (! $isEditError)
                                @error('username')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name">Name</label>
                            <input type="text" id="name" name="name" class="form-control @if(!$isEditError) @error('name') is-invalid @enderror @endif"
                                placeholder="John Doe" required value="{{ $isEditError ? '' : old('name') }}" />
                            @if (! $isEditError)
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email"
                                class="form-control @if(!$isEditError) @error('email') is-invalid @enderror @endif"
                                placeholder="john.doe@example.com" required value="{{ $isEditError ? '' : old('email') }}" />
                            @if (! $isEditError)
                                @error('email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <select id="role" name="role" class="form-select @if(!$isEditError) @error('role') is-invalid @enderror @endif" required>
                                <option value="admin" {{ (!$isEditError && old('role') === 'admin') ? 'selected' : '' }}>Admin</option>
                                <option value="annotator" {{ (!$isEditError && old('role') === 'annotator') ? 'selected' : '' }}>Annotator</option>
                                <option value="validator" {{ (!$isEditError && old('role') === 'validator') ? 'selected' : '' }}>Validator</option>
                            </select>
                            @if (! $isEditError)
                                @error('role')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password"
                                class="form-control @if(!$isEditError) @error('password') is-invalid @enderror @endif" placeholder="********" required />
                            @if (! $isEditError)
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 text-center mt-2 pt-50">
                            <button type="submit" class="btn btn-primary me-1">Create User</button>
                            <button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                                aria-label="Close">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- edit modal --}}
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-edit-user">
            <div class="modal-content">
                <div class="modal-header bg-transparent">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pb-5 px-sm-5 pt-50">
                    <form id="editUserForm" class="row g-3" method="POST" action="">
                        @csrf
                        @method('PUT')
                        <input type="hidden" id="edit_user_id" name="user_id" value="{{ $isEditError ? old('user_id') : '' }}">
                        {{-- username --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="username"
                                class="form-control @if($isEditError) @error('username') is-invalid @enderror @endif"
                                placeholder="johndoe" required value="{{ $isEditError ? old('username') : '' }}" />
                            @if ($isEditError)
                                @error('username')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="edit_name">Name</label>
                            <input type="text" id="edit_name" name="name"
                                class="form-control @if($isEditError) @error('name') is-invalid @enderror @endif" placeholder="John Doe"
                                required value="{{ $isEditError ? old('name') : '' }}" />
                            @if ($isEditError)
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email"
                                class="form-control @if($isEditError) @error('email') is-invalid @enderror @endif"
                                placeholder="john.doe@example.com" required value="{{ $isEditError ? old('email') : '' }}" />
                            @if ($isEditError)
                                @error('email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="edit_role">Role</label>
                            <select id="edit_role" name="role" class="form-select @if($isEditError) @error('role') is-invalid @enderror @endif" required>
                                <option value="admin" {{ $isEditError && old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="annotator" {{ $isEditError && old('role') === 'annotator' ? 'selected' : '' }}>Annotator</option>
                                <option value="validator" {{ $isEditError && old('role') === 'validator' ? 'selected' : '' }}>Validator</option>
                            </select>
                            @if ($isEditError)
                                @error('role')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="edit_password">Password</label>
                            <input type="password" id="edit_password" name="password"
                                class="form-control @if($isEditError) @error('password') is-invalid @enderror @endif"
                                placeholder="********" />
                            @if ($isEditError)
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>
                        <div class="col-12 text-center mt-2 pt-50">
                            <button type="submit" class="btn btn-primary me-1">Update User</button>
                            <button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                                aria-label="Close">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Initialize DataTable
        const userDataEndpoint = '{{ route('users.data') }}';
        const editUserModalEl = document.getElementById('editUserModal');
        const editUserForm = document.getElementById('editUserForm');
        const editModal = editUserModalEl && window.bootstrap ? new bootstrap.Modal(editUserModalEl) : null;
        const createUserModalEl = document.getElementById('createUserModal');
        const createModal = createUserModalEl && window.bootstrap ? new bootstrap.Modal(createUserModalEl) : null;
        const flashSuccess = @json(session('success'));
        const flashError = @json(session('error'));
        const validationErrors = @json($errors->all());
        const lastMethod = @json(old('_method'));
        const lastEditedUserId = @json(old('user_id'));

        const table = $('#userTable').DataTable({
            ajax: userDataEndpoint,
            columns: [{
                    data: null,
                    defaultContent: '',
                    orderable: false
                },
                {
                    data: 'id'
                },
                {
                    data: 'username'
                },
                {
                    data: 'name'
                },
                {
                    data: 'email'
                },
                {
                    data: 'role'
                },
                {
                    data: 'created_at'
                },
                {
                    data: null,
                    render: function (data, type, row) {
                        return `
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-info edit-user-btn" data-user-id="${row.id}">
                                    Edit
                                </button>
                                <form method="POST" action="/users/${row.id}" onsubmit="return confirm('Are you sure?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        `;
                    },
                    orderable: false
                }
            ],
            order: [
                [1, 'asc']
            ]
        });

        if (flashSuccess) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: flashSuccess,
                confirmButtonText: 'OK'
            });
        } else if (flashError) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: flashError,
                confirmButtonText: 'Dismiss'
            });
        } else if (Array.isArray(validationErrors) && validationErrors.length) {
            const editContext = lastMethod === 'PUT' && lastEditedUserId;

            if (editContext) {
                if (editUserForm) {
                    editUserForm.action = `/users/${lastEditedUserId}`;
                }
                if (editModal) {
                    editModal.show();
                }
            } else if (createModal) {
                createModal.show();
            }

            Swal.fire({
                icon: 'error',
                title: editContext ? 'Unable to update user' : 'Unable to create user',
                html: `<ul class="text-start mb-0">${validationErrors.map(err => `<li>${err}</li>`).join('')}</ul>`,
                confirmButtonText: 'Review form'
            });
        }

        $('#userTable').on('click', '.edit-user-btn', function () {
            const userId = this.getAttribute('data-user-id');
            if (!userId) {
                return;
            }

            if (editUserForm) {
                editUserForm.reset();
                editUserForm.action = `/users/${userId}`;
            }
            const editUserIdInput = document.getElementById('edit_user_id');
            if (editUserIdInput) {
                editUserIdInput.value = userId;
            }

            fetch(`${userDataEndpoint}?id=${userId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Failed to load user data');
                    }
                    return response.json();
                })
                .then(({ data }) => {
                    if (!data) {
                        throw new Error('No user data returned');
                    }

                    document.getElementById('edit_username').value = data.username || '';
                    document.getElementById('edit_name').value = data.name || '';
                    document.getElementById('edit_email').value = data.email || '';
                    document.getElementById('edit_role').value = data.role || 'annotator';
                    document.getElementById('edit_password').value = '';

                    if (editModal) {
                        editModal.show();
                    }
                })
                .catch((error) => {
                    console.error(error);
                    alert('Unable to load user details. Please try again.');
                });
        });
    });

</script>
