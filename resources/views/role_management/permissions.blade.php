<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Permissions</h1>
    </x-slot>
    <p class="mb-4 text-sm text-gray-600">Permissions are defined by application features. Assign them through Roles.</p>
    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="permissions-table" data-page-loading class="display nowrap w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-blue-950 text-white">
                    <th>ID</th>
                    <th>Group</th>
                    <th>Permission</th>
                    <th>Code</th>
                    <th>Roles</th>
                </tr>
            </thead>
        </table>
    </div>
    @push('scripts')
        <script>
            $(document).ready(function initializePermissions() {
                const $table = $('#permissions-table');
                $table.on('xhr.dt', handlePermissionsResponse);
                $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    order: [
                        [1, 'asc']
                    ],
                    ajax: {
                        url: @json(route('role-management.permissions.data')),
                        timeout: 30000
                    },
                    columns: [{
                        data: 'id'
                    }, {
                        data: 'group'
                    }, {
                        data: 'label',
                        orderable: false,
                        searchable: false
                    }, {
                        data: 'name'
                    }, {
                        data: 'roles_count',
                        searchable: false
                    }],
                    initComplete: finishPermissionsLoading
                });

                function finishPermissionsLoading() {
                    pageLoaderReady($table);
                }

                function handlePermissionsResponse(event, settings, json) {
                    if (json === null) {
                        finishPermissionsLoading();
                        toastr.error('Could not load permissions.');
                        return true;
                    }
                }
            });
        </script>
    @endpush
</x-app-layout>
