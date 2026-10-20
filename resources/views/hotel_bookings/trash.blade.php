<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Deleted Bookings</h1>
    </x-slot>

    <p class="mb-4 text-sm text-gray-600">Deleted bookings retain their rooms and history. Restore is blocked if the
        ticket already has a replacement or another confirmed booking.</p>
    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="booking-trash-table" data-page-loading class="display nowrap w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-blue-950 text-white">
                    <th>Booking ID</th>
                    <th>Sale ID</th>
                    <th>Customer</th>
                    <th>Hotel / Rooms</th>
                    <th>Deleted At</th>
                    <th>Deleted By</th>
                    <th>Reason</th>
                    <th class="no-export">Action</th>
                </tr>
            </thead>
        </table>
    </div>

    <x-modal id="restore-booking-modal" title="Restore Booking" formId="restore-booking-form" submitText="Restore">
        <form id="restore-booking-form" class="space-y-4 text-sm">
            <p id="restore-booking-description" class="text-gray-700"></p>
            <p id="restore-booking-error" role="alert" class="hidden text-red-700"></p>
        </form>
    </x-modal>

    @push('scripts')
        <script>
            $(document).ready(function initializeBookingTrash() {
                const $table = $('#booking-trash-table');
                let bookingId;
                $table.on('xhr.dt', handleTrashResponse);
                const table = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    order: [
                        [4, 'desc']
                    ],
                    ajax: {
                        url: @json(route('hotel-bookings.trash.data')),
                        timeout: 30000
                    },
                    columns: [{
                            data: 'id'
                        }, {
                            data: 'sales_id'
                        }, {
                            data: 'customer',
                            orderable: false
                        },
                        {
                            data: 'hotels',
                            orderable: false,
                            searchable: false
                        }, {
                            data: 'deleted_at'
                        },
                        {
                            data: 'deleted_by_name',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'delete_reason',
                            className: 'whitespace-normal break-words'
                        },
                        {
                            data: 'action',
                            orderable: false,
                            searchable: false
                        }
                    ],
                    initComplete: finishTrashLoading
                });
                $table.on('click', '[data-restore-booking]', openRestoreBooking);
                $('#restore-booking-form').on('submit', restoreBooking);
                $(document.defaultView).on('resize', adjustTrashColumns);

                function openRestoreBooking() {
                    bookingId = $(this).attr('data-restore-booking');
                    $('#restore-booking-description').text('Restore booking #' + bookingId +
                        ' and return it to the active list?');
                    $('#restore-booking-error').addClass('hidden');
                    openModal('restore-booking-modal', this);
                }

                function restoreBooking(event) {
                    event.preventDefault();
                    if ($('#restore-booking-modal').data('busy')) return;
                    setModalBusy('restore-booking-modal', true);
                    $('#restore-booking-error').addClass('hidden');
                    $.ajax({
                            url: @json(url('/hotel-bookings')) + '/' + bookingId + '/restore',
                            method: 'POST',
                            dataType: 'json',
                            timeout: 30000
                        })
                        .done(bookingRestored).fail(restoreFailed).always(finishRestore);
                }

                function bookingRestored(result) {
                    setModalBusy('restore-booking-modal', false);
                    closeModal('restore-booking-modal');
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                }

                function restoreFailed(xhr) {
                    const result = xhr.responseJSON;
                    $('#restore-booking-error').text(result && result.errors ? Object.values(result.errors).flat().join(
                            ' ') : result && result.message || 'Could not restore booking. Please try again.')
                        .removeClass('hidden');
                }

                function finishRestore() {
                    setModalBusy('restore-booking-modal', false);
                }

                function finishTrashLoading() {
                    pageLoaderReady($table);
                }

                function handleTrashResponse(event, settings, json) {
                    if (json === null) {
                        finishTrashLoading();
                        toastr.error('Could not load deleted bookings. Please reload.');
                        return true;
                    }
                }

                function adjustTrashColumns() {
                    table.columns.adjust();
                }
            });
        </script>
    @endpush
</x-app-layout>
