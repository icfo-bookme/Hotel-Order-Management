<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Users</h1>
    </x-slot>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">Manage staff accounts, roles and account status.</p>
        @can('users.create') @can('users.assign-role')
        <button id="add-user" type="button" class="rounded-lg bg-blue-950 px-4 py-2 text-sm font-semibold text-white">Add
            User</button>
        @endcan @endcan
    </div>
    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="users-table" data-page-loading class="display nowrap w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-blue-950 text-white">
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th class="no-export">Action</th>
                </tr>
            </thead>
        </table>
    </div>
    <x-modal id="managed-user-modal" title="User" formId="managed-user-form" maxWidth="2xl">
        <form id="managed-user-form" class="grid gap-4 sm:grid-cols-2">
            <p id="user-error" role="alert" class="hidden whitespace-pre-line text-sm text-red-700 sm:col-span-2"></p>
            <label class="text-sm font-medium">Name<input name="name" required maxlength="255"
                    class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="text-sm font-medium">Email<input name="email" type="email" required maxlength="255"
                    class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="text-sm font-medium">Password<input name="password" type="password" minlength="8"
                    autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300" /><span
                    class="mt-1 block text-xs text-gray-500">On edit, leave empty to keep the current
                    password.</span></label>
            <label class="text-sm font-medium">Confirm Password<input name="password_confirmation" type="password"
                    minlength="8" autocomplete="new-password" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="text-sm font-medium">Role<select name="role_id" required
                    class="mt-1 w-full rounded-md border-gray-300" @cannot('users.assign-role') disabled @endcannot>
                    <option value="">Select role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select></label>
            <label class="flex items-center gap-2 text-sm font-medium"><input name="is_active" type="checkbox"
                    checked /> Active</label>
        </form>
    </x-modal>
    @push('scripts')
        <script>
            $(document).ready(function initializeUsers() {
                const $form = $('#managed-user-form'),
                    $error = $('#user-error');
                let editingId = null;
                const $table = $('#users-table');
                $table.on('xhr.dt', handleUsersResponse);
                const table = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    ajax: {
                        url: @json(route('role-management.users.data')),
                        timeout: 30000
                    },
                    columns: [{
                        data: 'id'
                    }, {
                        data: 'name'
                    }, {
                        data: 'email'
                    }, {
                        data: 'role_name',
                        orderable: false
                    }, {
                        data: 'is_active',
                        render: renderUserStatus
                    }, {
                        data: 'created_at'
                    }, {
                        data: 'action',
                        orderable: false,
                        searchable: false
                    }],
                    initComplete: finishUsersLoading
                });
                $('#add-user').on('click', openNewUser);
                $table.on('click', '[data-edit-users]', openEditUser);
                $form.on('submit', saveUser);

                function renderUserStatus(value, type) {
                    return type === 'display' ? (value ? 'Active' : 'Inactive') : value;
                }

                function prepareUserForm() {
                    $form.trigger('reset');
                    $error.addClass('hidden');
                    $form.find('[name="is_active"]').prop('disabled', false);
                    $form.find('[name="password"], [name="password_confirmation"]').prop('required', editingId ===
                        null);
                }

                function openNewUser() {
                    editingId = null;
                    prepareUserForm();
                    $('#managed-user-modal-title').text('Add User');
                    openModal('managed-user-modal', this);
                }

                function openEditUser() {
                    const row = table.row($(this).closest('tr')).data();
                    editingId = row.id;
                    prepareUserForm();
                    $.each(['name', 'email', 'role_id'], function fillUserField(index, name) {
                        $form.find('[name="' + name + '"]').val(name === 'role_id' ? row[name] : $('<textarea>')
                            .html(row[name]).val());
                    });
                    $form.find('[name="is_active"]').prop('checked', row.is_active).prop('disabled', row.id ===
                        @json(auth()->id()));
                    $('#managed-user-modal-title').text('Edit User');
                    openModal('managed-user-modal', this);
                }

                function saveUser(event) {
                    event.preventDefault();
                    if ($('#managed-user-modal').data('busy')) return;
                    const data = {
                        name: $form.find('[name="name"]').val(),
                        email: $form.find('[name="email"]').val(),
                        role_id: $form.find('[name="role_id"]').val(),
                        is_active: $form.find('[name="is_active"]').prop('checked'),
                        password: $form.find('[name="password"]').val(),
                        password_confirmation: $form.find('[name="password_confirmation"]').val()
                    };
                    $error.addClass('hidden');
                    setModalBusy('managed-user-modal', true);
                    $.ajax({
                        url: @json(url('/role-management/users')) + (editingId ? '/' + editingId : ''),
                        method: editingId ? 'PUT' : 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        dataType: 'json',
                        timeout: 30000
                    }).done(userSaved).fail(userSaveFailed).always(finishUserSave);
                }

                function userSaved(result) {
                    setModalBusy('managed-user-modal', false);
                    closeModal('managed-user-modal');
                    $form.find('[type="password"]').val('');
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                }

                function userSaveFailed(xhr) {
                    const r = xhr.responseJSON;
                    $error.text(r && r.errors ? Object.values(r.errors).flat().join('\n') : r && r.message ||
                        'Could not save user.').removeClass('hidden');
                }

                function finishUserSave() {
                    setModalBusy('managed-user-modal', false);
                }

                function finishUsersLoading() {
                    pageLoaderReady($table);
                }

                function handleUsersResponse(event, settings, json) {
                    if (json === null) {
                        finishUsersLoading();
                        toastr.error('Could not load users.');
                        return true;
                    }
                }
            });
        </script>
    @endpush
</x-app-layout>
