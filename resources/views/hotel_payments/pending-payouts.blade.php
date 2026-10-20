<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Pending Payouts</h1>
    </x-slot>

    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="pending-payouts-table" data-page-loading
            class="display nowrap w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-blue-950 text-white">
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Hotel</th>
                    <th>Payment Details</th>
                    <th>Payout Amount</th>
                    <th>Sold By</th>
                    <th>Hotel Payment Method</th>
                    <th class="no-export">Action</th>
                </tr>
            </thead>
        </table>
    </div>

    <x-modal id="payout-screenshot-modal" title="Payment Screenshot" maxWidth="4xl" :hideFooter="true">
        <p id="payout-screenshot-status" role="status" class="py-6 text-center text-sm text-gray-500">Loading
            screenshot...</p>
        <img id="payout-screenshot-image" alt="Payment screenshot enlarged"
            class="mx-auto hidden max-h-[70dvh] max-w-full rounded-lg object-contain" />
    </x-modal>

    <x-modal id="record-payout-modal" title="Record Hotel Payout" formId="record-payout-form"
        submitText="Confirm Payout">
        <form id="record-payout-form" class="space-y-4 text-sm">
            <p id="record-payout-hotel" class="font-semibold text-gray-800"></p>
            <p id="record-payout-balance" class="text-gray-600"></p>
            <p id="record-payout-error" role="alert" class="hidden text-red-700"></p>
            <label class="block font-medium">Payment Account<select id="record-payout-account" required
                    class="mt-1 w-full rounded-md border-gray-300"></select></label>
            <label class="block font-medium">Payout Amount<input id="record-payout-amount" type="number" required
                    min="0.01" step="0.01" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block font-medium">Transaction Reference<input id="record-payout-reference" type="text"
                    maxlength="255" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block font-medium">Payment Screenshot<input id="record-payout-attachment" type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2" /><span
                    class="mt-1 block text-xs font-normal text-gray-500">Optional. JPG, PNG or WebP, up to 5
                    MB.</span></label>
            <div id="record-payout-preview" class="hidden"><img alt="Payment screenshot preview"
                    class="max-h-40 rounded-lg border border-gray-200" /><button id="remove-payout-attachment"
                    type="button" class="mt-1 text-xs font-semibold text-red-600">Remove Screenshot</button></div>
            <p class="text-xs text-gray-500">Confirm only after sending the money. This records the payment; it does not
                transfer money.</p>
        </form>
    </x-modal>

    <x-modal id="hotel-advances-modal" title="Set Hotel Advances" formId="hotel-advances-form"
        submitText="Save Advances">
        <form id="hotel-advances-form" class="space-y-4 text-sm">
            <p class="text-gray-600">Allocate the received payment across hotels. The amounts must add up to the booking
                received total.</p>
            <p id="hotel-advances-error" role="alert" class="hidden text-red-700"></p>
            <div id="hotel-advance-inputs" class="space-y-3"></div>
        </form>
    </x-modal>

    @push('scripts')
        <script>
            $(document).ready(function initializePendingPayouts() {
                const $table = $('#pending-payouts-table');
                const $image = $('#payout-screenshot-image');
                const $status = $('#payout-screenshot-status');
                const submissions = {};
                let payoutRow, advanceRow;
                let attachmentPreviewUrl;
                $table.on('xhr.dt', handlePayoutResponse);
                const table = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    ajax: {
                        url: @json(route('hotel-payouts.pending.data')),
                        timeout: 30000
                    },
                    columns: [{
                            data: 'id'
                        }, {
                            data: 'customer_name',
                            orderable: false
                        },
                        {
                            data: 'hotels',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'payment_details',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'payout_amount',
                            orderable: false,
                            searchable: false,
                            className: 'font-semibold'
                        },
                        {
                            data: 'sold_by',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'hotel_payment_accounts',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'action',
                            orderable: false,
                            searchable: false
                        },
                    ],
                    initComplete: finishPayoutLoading,
                });
                $table.on('click', '[data-view-payment-screenshot]', openPayoutScreenshot);
                $('#payout-screenshot-modal').on('modal:closed', clearPayoutScreenshot);
                $(document.defaultView).on('resize', adjustPayoutColumns);
                $table.on('click', '[data-payout]', confirmHotelPayout);
                $table.on('click', '[data-set-advances]', openAdvanceAllocation);
                $('#record-payout-form').on('submit', saveHotelPayout);
                $('#record-payout-attachment').on('change', previewPayoutAttachment);
                $('#remove-payout-attachment').on('click', clearPayoutAttachment);
                $('#record-payout-modal').on('modal:closed', clearPayoutAttachment);
                $('#hotel-advances-form').on('submit', saveAdvances);

                function confirmHotelPayout() {
                    payoutRow = table.row($(this).closest('tr')).data();
                    const balance = payoutRow.balance;
                    if (balance.reason || !balance.accounts.length) {
                        toastr.error(balance.reason || 'No active payment account available.');
                        return;
                    }
                    $('#record-payout-form').trigger('reset');
                    clearPayoutAttachment();
                    $('#record-payout-error').addClass('hidden');
                    $('#record-payout-hotel').text(payoutRow.hotel_name + ' ? Booking #' + payoutRow.hotel_booking_id);
                    $('#record-payout-balance').text('Payout total: ' + balance.payout_amount + ' | Already paid: ' +
                        balance.paid + ' | Remaining: ' + balance.remaining);
                    const $account = $('#record-payout-account').empty();
                    $('<option>', {
                        value: '',
                        text: 'Select payout payment account'
                    }).appendTo($account);
                    $.each(balance.accounts, function appendPayoutAccount(index, account) {
                        $('<option>', {
                            value: account.id,
                            text: [account.payment_method, account.bank_name, account.account_name,
                                account.account_number
                            ].filter(Boolean).join(' ? ')
                        }).appendTo($account);
                    });
                    $('#record-payout-amount').attr('max', balance.available).val(balance.available);
                    submissions[payoutRow.id] ||= crypto.randomUUID();
                    openModal('record-payout-modal', this);
                }

                function saveHotelPayout(event) {
                    event.preventDefault();
                    if ($('#record-payout-modal').data('busy')) return;
                    const data = {
                        submission_id: submissions[payoutRow.id],
                        hotel_payment_account_id: $('#record-payout-account').val(),
                        amount: $('#record-payout-amount').val(),
                        transaction_reference: $('#record-payout-reference').val()
                    };
                    const formData = new FormData();
                    $.each(data, function appendPayoutField(key, value) {
                        formData.append(key, value);
                    });
                    const file = $('#record-payout-attachment').prop('files')[0];
                    if (file) formData.append('attachment', file);
                    setModalBusy('record-payout-modal', true);
                    $('#record-payout-error').addClass('hidden');
                    $.ajax({
                            url: @json(url('/hotel-payouts')) + '/' + payoutRow.id,
                            method: 'POST',
                            dataType: 'json',
                            timeout: 30000,
                            data: formData,
                            contentType: false,
                            processData: false
                        })
                        .done(handlePayoutSaved).fail(handlePayoutFailed).always(finishPayoutSave);
                }

                function handlePayoutSaved(response) {
                    delete submissions[payoutRow.id];
                    setModalBusy('record-payout-modal', false);
                    closeModal('record-payout-modal');
                    toastr.success(response.message);
                    table.ajax.reload(null, false);
                }

                function handlePayoutFailed(xhr) {
                    showSaveError('#record-payout-error', xhr);
                }

                function finishPayoutSave() {
                    setModalBusy('record-payout-modal', false);
                }

                function clearPayoutAttachment() {
                    if (attachmentPreviewUrl) URL.revokeObjectURL(attachmentPreviewUrl);
                    attachmentPreviewUrl = null;
                    $('#record-payout-attachment').val('');
                    $('#record-payout-preview').addClass('hidden').find('img').removeAttr('src');
                }

                function previewPayoutAttachment() {
                    const file = $(this).prop('files')[0];
                    if (attachmentPreviewUrl) URL.revokeObjectURL(attachmentPreviewUrl);
                    attachmentPreviewUrl = null;
                    $('#record-payout-preview').addClass('hidden').find('img').removeAttr('src');
                    if (!file) return;
                    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                        clearPayoutAttachment();
                        $('#record-payout-error').text('Choose a JPG, PNG or WebP screenshot up to 5 MB.').removeClass(
                            'hidden');
                        return;
                    }
                    $('#record-payout-error').addClass('hidden');
                    attachmentPreviewUrl = URL.createObjectURL(file);
                    $('#record-payout-preview').removeClass('hidden').find('img').attr('src', attachmentPreviewUrl);
                }

                function showSaveError(selector, xhr) {
                    const result = xhr.responseJSON;
                    const message = result && result.errors ? Object.values(result.errors).flat().join(' ') : result &&
                        result.message || 'Could not save. Please try again.';
                    $(selector).text(message).removeClass('hidden');
                }

                function openAdvanceAllocation() {
                    advanceRow = table.row($(this).closest('tr')).data();
                    $('#hotel-advances-error').addClass('hidden');
                    const $content = $('#hotel-advance-inputs').empty();
                    $.each(advanceRow.allocations, function appendAllocationInput(index, allocation) {
                        const $label = $('<label>', {
                            class: 'block font-medium'
                        }).text(allocation.hotel_name).appendTo($content);
                        $('<input>', {
                            type: 'number',
                            required: true,
                            min: '0',
                            step: '0.01',
                            value: allocation.amount,
                            'data-allocation-hotel': allocation.bookme_hotel_id,
                            class: 'mt-1 w-full rounded-md border-gray-300'
                        }).appendTo($label);
                    });
                    openModal('hotel-advances-modal', this);
                }

                function saveAdvances(event) {
                    event.preventDefault();
                    if ($('#hotel-advances-modal').data('busy')) return;
                    const allocations = [];
                    $('#hotel-advance-inputs [data-allocation-hotel]').each(function readAllocation() {
                        allocations.push({
                            bookme_hotel_id: $(this).attr('data-allocation-hotel'),
                            amount: $(this).val()
                        });
                    });
                    setModalBusy('hotel-advances-modal', true);
                    $('#hotel-advances-error').addClass('hidden');
                    $.ajax({
                            url: @json(url('/hotel-bookings')) + '/' + advanceRow.hotel_booking_id + '/advances',
                            method: 'PATCH',
                            dataType: 'json',
                            timeout: 30000,
                            data: {
                                hotel_advances: allocations
                            }
                        })
                        .done(handleAdvancesSaved).fail(handleAdvancesFailed).always(finishAdvanceSave);
                }

                function handleAdvancesSaved(response) {
                    setModalBusy('hotel-advances-modal', false);
                    closeModal('hotel-advances-modal');
                    toastr.success(response.message);
                    table.ajax.reload(null, false);
                }

                function handleAdvancesFailed(xhr) {
                    showSaveError('#hotel-advances-error', xhr);
                }

                function finishAdvanceSave() {
                    setModalBusy('hotel-advances-modal', false);
                }

                function finishPayoutLoading() {
                    pageLoaderReady($table);
                }

                function handlePayoutResponse(event, settings, json) {
                    if (json === null) {
                        finishPayoutLoading();
                        toastr.error('Could not load pending payouts. Please reload.');
                        return true;
                    }
                }

                function adjustPayoutColumns() {
                    table.columns.adjust();
                }

                function openPayoutScreenshot() {
                    clearPayoutScreenshot();
                    $status.text('Loading screenshot...').removeClass('hidden');
                    $image.one('load', showPayoutScreenshot).one('error', handleScreenshotError);
                    openModal('payout-screenshot-modal', this);
                    $image.attr('src', $(this).attr('data-view-payment-screenshot'));
                }

                function showPayoutScreenshot() {
                    $status.addClass('hidden');
                    $image.removeClass('hidden');
                }

                function handleScreenshotError() {
                    $status.text('Could not load this screenshot. Please try again.');
                }

                function clearPayoutScreenshot() {
                    $image.off('load error').removeAttr('src').addClass('hidden');
                }
            });
        </script>
    @endpush
</x-app-layout>
