<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Roles</h1>
    </x-slot>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">Assign permissions to roles. Super Admin is protected and has all permissions.
        </p>
        @can('roles.create')
            <button id="add-role" type="button" class="rounded-lg bg-blue-950 px-4 py-2 text-sm font-semibold text-white">Add
                Role</button>
        @endcan
    </div>
    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="roles-table" data-page-loading class="display nowrap w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-blue-950 text-white">
                    <th>ID</th>
                    <th>Role</th>
                    <th>Users</th>
                    <th>Permissions</th>
                    <th class="no-export">Action</th>
                </tr>
            </thead>
        </table>
    </div>
    <x-modal id="role-modal" title="Role" formId="role-form" maxWidth="4xl">
        <form id="role-form" class="space-y-4">
            <p id="role-error" role="alert" class="hidden whitespace-pre-line text-sm text-red-700"></p>
            <label class="block text-sm font-medium">Role Name<input name="name" required maxlength="100"
                    class="mt-1 w-full rounded-md border-gray-300" /></label>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($groups as $group => $permissions)
                    <fieldset class="rounded-lg border border-gray-200 p-4">
                        <legend class="px-1 text-sm font-semibold">{{ $group }}</legend>
                        <label class="mb-3 flex items-center gap-2 text-xs font-medium text-blue-700"><input
                                type="checkbox" data-permission-group /> Select All</label>
                        @foreach ($permissions as $permission)
                            <label class="mb-2 flex items-start gap-2 text-sm text-gray-700"><input type="checkbox"
                                    name="permissions[]" value="{{ $permission->id }}" class="mt-1"
                                    @cannot($permission->name) disabled @endcannot />{{ config('access.permissions')[$group][$permission->name] ?? $permission->name }}</label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
        </form>
    </x-modal>
    <x-modal id="delete-role-modal" title="Delete Role" formId="delete-role-form" submitText="Delete">
        <form id="delete-role-form" class="space-y-3">
            <p id="delete-role-description" class="text-sm text-gray-700"></p>
            <p id="delete-role-error" role="alert" class="hidden text-sm text-red-700"></p>
        </form>
    </x-modal>
    @push('scripts')
        <script>
            $(document).ready(function initializeRoles() {
                const $table = $('#roles-table'),
                    $form = $('#role-form');
                let editingId = null,
                    deleteId = null;
                $table.on('xhr.dt', handleRolesResponse);
                const table = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    ajax: {
                        url: @json(route('role-management.roles.data')),
                        timeout: 30000
                    },
                    columns: [{
                        data: 'id'
                    }, {
                        data: 'name'
                    }, {
                        data: 'users_count',
                        searchable: false
                    }, {
                        data: 'permissions_count',
                        searchable: false
                    }, {
                        data: 'action',
                        orderable: false,
                        searchable: false
                    }],
                    initComplete: finishRolesLoading
                });
                $('#add-role').on('click', openNewRole);
                $table.on('click', '[data-edit-roles]', openEditRole);
                $table.on('click', '[data-delete-role]', openDeleteRole);
                $form.on('submit', saveRole).on('change', '[data-permission-group]', togglePermissionGroup).on('change',
                    '[name="permissions[]"]', updatePermissionGroups);
                $('#delete-role-form').on('submit', deleteRole);

                function resetRole() {
                    $form.trigger('reset');
                    $('#role-error').addClass('hidden');
                }

                function openNewRole() {
                    editingId = null;
                    resetRole();
                    $('#role-modal-title').text('Add Role');
                    openModal('role-modal', this);
                }

                function openEditRole() {
                    editingId = $(this).attr('data-edit-roles');
                    resetRole();
                    $('#role-modal-title').text('Edit Role');
                    openModal('role-modal', this);
                    setModalBusy('role-modal', true);
                    $.getJSON(@json(url('/role-management/roles')) + '/' + editingId).done(fillRole).fail(roleSaveFailed)
                        .always(finishRoleSave);
                }

                function fillRole(result) {
                    $form.find('[name="name"]').val(result.data.name);
                    const ids = result.data.permissions.map(function permissionId(p) {
                        return String(p.id);
                    });
                    $form.find('[name="permissions[]"]').each(function checkPermission() {
                        $(this).prop('checked', ids.includes($(this).val()));
                    });
                    updatePermissionGroups();
                }

                function togglePermissionGroup() {
                    $(this).closest('fieldset').find('[name="permissions[]"]:enabled').prop('checked', $(this).prop(
                        'checked'));
                    updatePermissionGroups();
                }

                function updatePermissionGroups() {
                    $form.find('fieldset').each(function updateGroup() {
                        const $items = $(this).find('[name="permissions[]"]:enabled'),
                            count = $items.filter(':checked').length;
                        $(this).find('[data-permission-group]').prop('checked', $items.length > 0 && count ===
                            $items.length).prop('indeterminate', count > 0 && count < $items.length);
                    });
                }

                function saveRole(event) {
                    event.preventDefault();
                    if ($('#role-modal').data('busy')) return;
                    const permissions = $form.find('[name="permissions[]"]:checked').map(function checkedPermission() {
                        return Number($(this).val());
                    }).get();
                    setModalBusy('role-modal', true);
                    $('#role-error').addClass('hidden');
                    $.ajax({
                        url: @json(url('/role-management/roles')) + (editingId ? '/' + editingId : ''),
                        method: editingId ? 'PUT' : 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            name: $form.find('[name="name"]').val(),
                            permissions
                        }),
                        dataType: 'json',
                        timeout: 30000
                    }).done(roleSaved).fail(roleSaveFailed).always(finishRoleSave);
                }

                function roleSaved(result) {
                    setModalBusy('role-modal', false);
                    closeModal('role-modal');
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                }

                function showError(selector, xhr) {
                    const r = xhr.responseJSON;
                    $(selector).text(r && r.errors ? Object.values(r.errors).flat().join('\n') : r && r.message ||
                        'Could not save role.').removeClass('hidden');
                }

                function roleSaveFailed(xhr) {
                    showError('#role-error', xhr);
                }

                function finishRoleSave() {
                    setModalBusy('role-modal', false);
                }

                function openDeleteRole() {
                    deleteId = $(this).attr('data-delete-role');
                    $('#delete-role-description').text('Delete this unassigned role?');
                    $('#delete-role-error').addClass('hidden');
                    openModal('delete-role-modal', this);
                }

                function deleteRole(event) {
                    event.preventDefault();
                    if ($('#delete-role-modal').data('busy')) return;
                    setModalBusy('delete-role-modal', true);
                    $.ajax({
                        url: @json(url('/role-management/roles')) + '/' + deleteId,
                        method: 'DELETE',
                        dataType: 'json',
                        timeout: 30000
                    }).done(roleDeleted).fail(deleteRoleFailed).always(finishRoleDelete);
                }

                function roleDeleted(result) {
                    setModalBusy('delete-role-modal', false);
                    closeModal('delete-role-modal');
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                }

                function deleteRoleFailed(xhr) {
                    showError('#delete-role-error', xhr);
                }

                function finishRoleDelete() {
                    setModalBusy('delete-role-modal', false);
                }

                function finishRolesLoading() {
                    pageLoaderReady($table);
                }

                function handleRolesResponse(event, settings, json) {
                    if (json === null) {
                        finishRolesLoading();
                        toastr.error('Could not load roles.');
                        return true;
                    }
                }
            });
        </script>
    @endpush
</x-app-layout>
