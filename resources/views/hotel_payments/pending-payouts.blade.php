<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Pending Payouts</h1>
    </x-slot>

    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="pending-payouts-table" data-page-loading class="display nowrap w-full table-auto border border-gray-300">
            <thead><tr class="bg-blue-950 text-white">
                <th>ID</th><th>Customer</th><th>Hotel</th>
                <th>Payment Details</th><th>Payout Amount</th><th>Sold By</th><th>Hotel Payment Method</th><th class="no-export">Action</th>
            </tr></thead>
        </table>
    </div>

    <x-modal id="payout-screenshot-modal" title="Payment Screenshot" maxWidth="4xl" :hideFooter="true">
        <p id="payout-screenshot-status" role="status" class="py-6 text-center text-sm text-gray-500">Loading screenshot...</p>
        <img id="payout-screenshot-image" alt="Payment screenshot enlarged" class="mx-auto hidden max-h-[70dvh] max-w-full rounded-lg object-contain" />
    </x-modal>

    @push('scripts')
        <script>
            $(document).ready(function initializePendingPayouts() {
                const $table = $('#pending-payouts-table');
                const $image = $('#payout-screenshot-image');
                const $status = $('#payout-screenshot-status');
                const submissions = {};
                $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
                $table.on('xhr.dt', handlePayoutResponse);
                const table = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    ajax: { url: @json(route('hotel-payouts.pending.data')), timeout: 30000 },
                    columns: [
                        { data: 'id' }, { data: 'customer_name', orderable: false },
                        { data: 'hotels', orderable: false, searchable: false },
                        { data: 'payment_details', orderable: false, searchable: false },
                        { data: 'payout_amount', orderable: false, searchable: false, className: 'font-semibold' },
                        { data: 'sold_by', orderable: false, searchable: false },
                        { data: 'hotel_payment_accounts', orderable: false, searchable: false },
                        { data: 'action', orderable: false, searchable: false },
                    ],
                    initComplete: finishPayoutLoading,
                });
                $table.on('click', '[data-view-payment-screenshot]', openPayoutScreenshot);
                $('#payout-screenshot-modal').on('modal:closed', clearPayoutScreenshot);
                $(document.defaultView).on('resize', adjustPayoutColumns);
                $table.on('click', '[data-payout]', confirmHotelPayout);
                $table.on('click', '[data-set-advances]', openAdvanceAllocation);

                function allowDialogClose() { return !Swal.isLoading(); }

                function confirmHotelPayout() {
                    const row = table.row($(this).closest('tr')).data();
                    const balance = row.balance;
                    const account = balance.account;
                    if (balance.reason || !account) { toastr.error(balance.reason || 'No payment account selected.'); return; }
                    const $content = $('<div>', { class: 'space-y-3 text-left text-sm' });
                    $('<p>').text(row.hotel_name + ' — Booking #' + row.hotel_booking_id).appendTo($content);
                    $('<p>').text([account.payment_method, account.bank_name, account.account_name, account.account_number].filter(Boolean).join(' · ')).appendTo($content);
                    $('<p>').text('Payout total: ' + balance.payout_amount + ' | Already paid to hotel: ' + balance.paid + ' | Remaining: ' + balance.remaining).appendTo($content);
                    const $amount = $('<input>', { type: 'number', min: '0.01', max: balance.available, step: '0.01', value: balance.available, 'aria-label': 'Payout amount', class: 'w-full rounded-md border-gray-300' }).appendTo($content);
                    const $reference = $('<input>', { type: 'text', maxlength: 255, placeholder: 'Transaction reference (optional)', 'aria-label': 'Transaction reference', class: 'w-full rounded-md border-gray-300' }).appendTo($content);
                    $('<p>', { class: 'text-xs text-gray-500' }).text('Confirm only after sending the money. This records the payment; it does not transfer money.').appendTo($content);
                    submissions[row.id] ||= crypto.randomUUID();
                    Swal.fire({ title: 'Record hotel payout?', html: $content[0], showCancelButton: true, confirmButtonText: 'Confirm Payout', showLoaderOnConfirm: true, allowOutsideClick: allowDialogClose, allowEscapeKey: allowDialogClose, preConfirm: saveHotelPayout });

                    function saveHotelPayout() {
                        if (!$amount[0].checkValidity() || !$amount.val()) { Swal.showValidationMessage('Enter a valid amount within the remaining payout.'); return false; }
                        return $.ajax({
                            url: @json(url('/hotel-payouts')) + '/' + row.id,
                            method: 'POST', dataType: 'json', timeout: 30000,
                            data: { submission_id: submissions[row.id], amount: $amount.val(), transaction_reference: $reference.val() },
                        }).then(handlePayoutSaved, handlePayoutFailed);
                    }
                    function handlePayoutSaved(response) {
                        delete submissions[row.id];
                        toastr.success(response.message); table.ajax.reload(null, false); return response;
                    }
                }

                function handlePayoutFailed(xhr) {
                    const result = xhr.responseJSON;
                    const message = result && result.errors ? Object.values(result.errors).flat().join(' ') : result && result.message || 'Could not save. Retry with the same amount, or reload to check the latest balance.';
                    Swal.showValidationMessage($('<div>').text(message).html());
                    return false;
                }

                function openAdvanceAllocation() {
                    const row = table.row($(this).closest('tr')).data();
                    const $content = $('<div>', { class: 'space-y-3 text-left text-sm' });
                    $('<p>').text('Allocate the received payment across hotels. The amounts must add up to the booking received total.').appendTo($content);
                    $.each(row.allocations, appendAllocationInput);
                    Swal.fire({ title: 'Hotel advances', html: $content[0], showCancelButton: true, confirmButtonText: 'Save Advances', showLoaderOnConfirm: true, allowOutsideClick: allowDialogClose, allowEscapeKey: allowDialogClose, preConfirm: saveAdvances });

                    function appendAllocationInput(index, allocation) {
                        const $label = $('<label>', { class: 'block' }).text(allocation.hotel_name).appendTo($content);
                        $('<input>', { type: 'number', min: '0', step: '0.01', value: allocation.amount, 'data-allocation-hotel': allocation.bookme_hotel_id, class: 'mt-1 w-full rounded-md border-gray-300' }).appendTo($label);
                    }
                    function saveAdvances() {
                        const allocations = [];
                        let valid = true;
                        $content.find('[data-allocation-hotel]').each(readAllocation);
                        if (!valid) { Swal.showValidationMessage('Enter a valid advance for each hotel.'); return false; }
                        return $.ajax({ url: @json(url('/hotel-bookings')) + '/' + row.hotel_booking_id + '/advances', method: 'PATCH', dataType: 'json', timeout: 30000, data: { hotel_advances: allocations } }).then(handleAdvancesSaved, handlePayoutFailed);
                        function readAllocation() {
                            valid = valid && this.checkValidity() && $(this).val() !== '';
                            allocations.push({ bookme_hotel_id: $(this).attr('data-allocation-hotel'), amount: $(this).val() });
                        }
                    }
                    function handleAdvancesSaved(response) { toastr.success(response.message); table.ajax.reload(null, false); return response; }
                }


                function finishPayoutLoading() { pageLoaderReady($table); }
                function handlePayoutResponse(event, settings, json) {
                    if (json === null) {
                        finishPayoutLoading();
                        toastr.error('Could not load pending payouts. Please reload.');
                        return true;
                    }
                }
                function adjustPayoutColumns() { table.columns.adjust(); }
                function openPayoutScreenshot() {
                    clearPayoutScreenshot();
                    $status.text('Loading screenshot...').removeClass('hidden');
                    $image.one('load', showPayoutScreenshot).one('error', handleScreenshotError);
                    openModal('payout-screenshot-modal', this);
                    $image.attr('src', $(this).attr('data-view-payment-screenshot'));
                }
                function showPayoutScreenshot() { $status.addClass('hidden'); $image.removeClass('hidden'); }
                function handleScreenshotError() { $status.text('Could not load this screenshot. Please try again.'); }
                function clearPayoutScreenshot() { $image.off('load error').removeAttr('src').addClass('hidden'); }
            });
        </script>
    @endpush
</x-app-layout>
