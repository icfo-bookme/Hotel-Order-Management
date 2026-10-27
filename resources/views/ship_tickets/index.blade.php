<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">
            {{ ucwords(str_replace('-', ' ', $status)) . ' Tickets' }}</h1>
    </x-slot>

    @if ($status === 'pending' || $status === 'feedbacked')
        @include('ship_tickets.partials.sell-modal')
    @endif

    @if (in_array($status, ['pending-sold', 'sold'], true))
        @include('ship_tickets.partials.reschedule-modal')
        <x-modal id="delete-booking-modal" title="Delete Booking" formId="delete-booking-form" submitText="Move to Trash">
            <form id="delete-booking-form" class="space-y-4 text-sm">
                <p id="delete-booking-description" class="font-medium text-gray-800"></p>
                <p class="text-gray-600">The booking will move to Trash and can be restored. Customer payment records
                    will be retained. Deletion is blocked only if a hotel payout has been recorded.</p>
                <label class="block font-medium">Reason
                    <textarea id="delete-booking-reason" required maxlength="1000" rows="3"
                        class="mt-1 w-full rounded-md border-gray-300"></textarea>
                </label>
                <p id="delete-booking-error" role="alert" class="hidden text-red-700"></p>
            </form>
        </x-modal>
        <x-modal id="verify-payments-modal" title="Verify Payments" formId="verify-payments-form"
            submitText="Yes, Verify">
            <form id="verify-payments-form" class="space-y-4">
                <p id="verify-payments-description" class="text-sm text-gray-700"></p>
                <p id="verify-payments-error" role="alert" class="hidden text-sm text-red-700"></p>
            </form>
        </x-modal>
        <x-modal id="reschedule-charges-modal" title="Reschedule Charges" :hideFooter="true">
            <div id="reschedule-charges-content" class="space-y-4 text-sm"></div>
        </x-modal>
        <x-modal id="payment-screenshot-modal" title="Payment Screenshot" maxWidth="4xl" :hideFooter="true">
            <p id="payment-screenshot-status" role="status" class="py-6 text-center text-sm text-gray-500">Loading
                screenshot...</p>
            <img id="payment-screenshot-image" alt="Payment screenshot enlarged"
                class="mx-auto hidden max-h-[70dvh] max-w-full rounded-lg object-contain" />
        </x-modal>
    @endif

    @if ($status === 'pending')
        <x-modal id="remarks-modal" title="Add Feedback" formId="remarks-form" submitId="save-remarks">
            <form id="remarks-form"><input type="hidden" id="ticket_id"><x-input-label for="remarks-text"
                    value="Feedback" />
                <textarea id="remarks-text" class="mt-2 w-full rounded-lg border-gray-300" rows="5" maxlength="1000" required
                    placeholder="Enter feedback..."></textarea>
            </form>
        </x-modal>
    @elseif ($status === 'feedbacked')
        <x-modal id="edit-feedback-modal" title="Edit Feedback" formId="edit-feedback-form" submitId="update-feedback"
            submitText="Update">
            <form id="edit-feedback-form"><input type="hidden" id="edit_ticket_id"><x-input-label
                    for="edit-feedback-text" value="Feedback" />
                <textarea id="edit-feedback-text" class="mt-2 w-full rounded-lg border-gray-300" rows="5" maxlength="1000"
                    required placeholder="Edit feedback..."></textarea>
            </form>
        </x-modal>
    @endif

    <div class="min-w-0">
        <div class="ticket-table-container min-w-0 overflow-x-auto">
            <table id="tickets-table" data-page-loading data-status="{{ $status }}"
                data-ajax-url="{{ route('ship-tickets.data', $status) }}"
                data-remarks-save-url="{{ route('remarks.save') }}"
                data-remarks-update-url="{{ route('remarks.update') }}"
                data-verify-payments-url="{{ route('hotel-sales.verify-payments', ['shipTicketSale' => '__SALE__']) }}"
                data-delete-booking-url="{{ route('hotel-bookings.destroy', ['hotelBooking' => '__BOOKING__']) }}"
                class="display nowrap w-full table-auto border border-gray-300">
                <thead>
                    <tr class="bg-blue-950 text-white">
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>WhatsApp</th>

                        @if ($status === 'pending')
                            <th>Journey Date</th>
                        @endif

                        @if ($status === 'feedbacked')
                            <th>Feedback</th>
                            <th>Feedback By</th>
                        @endif

                        @if (in_array($status, ['pending-sold', 'sold'], true))
                            <th>Hotel</th>
                            <th>Check-in / Check-out</th>
                            <th>Payment Details</th>
                            <th>Sold By</th>
                        @endif
                        <th class="text-center no-export">Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                const $table = $('#tickets-table');
                if (!$table.length) return;

                const status = $table.attr('data-status');
                const $modal = $('#sell-modal');
                const $form = $('#sell-form');
                const $rooms = $('#sell-rooms');
                const $payments = $('#sell-payments');
                const $error = $('#sell-error');
                let saleId;
                let verifySaleId;
                let deleteBookingId;
                $table.on('click', '[data-delete-booking]', openDeleteBooking);
                $('#delete-booking-form').on('submit', deleteHotelBooking);
                let saving = false;
                let hotelOptions = null;
                let hotelsLoading = false;

                const table = initializeTicketTable();
                @if (in_array($status, ['pending-sold', 'sold'], true))
                    @include('ship_tickets.partials.reschedule-script')
                @endif
                bindTableResize();
                if (status === 'pending' || status === 'feedbacked') bindFeedbackEvents();
                if (['pending-sold', 'sold'].includes(status)) {
                    bindScreenshotViewer();
                    bindPaymentVerification();
                }

                if ($modal.length) {
                    bindBookingEvents();
                    bindAttachmentEvents();
                }

                function disableBookingInputs() {
                    const states = [];
                    $form.find('input, select, textarea, button').each(function rememberInputState() {
                        states.push({
                            element: this,
                            disabled: $(this).prop('disabled')
                        });
                        $(this).prop('disabled', true);
                    });
                    return states;
                }

                function bindPaymentVerification() {
                    $table.on('click', '.btn-verify-payments', confirmSalePayments);
                    $('#verify-payments-form').on('submit', verifyPendingPayments);
                }

                function openDeleteBooking() {
                    deleteBookingId = $(this).attr('data-delete-booking');
                    $('#delete-booking-form').trigger('reset');
                    $('#delete-booking-description').text('Move booking #' + deleteBookingId + ' to Trash?');
                    $('#delete-booking-error').addClass('hidden');
                    openModal('delete-booking-modal', this);
                }

                function deleteHotelBooking(event) {
                    event.preventDefault();
                    if ($('#delete-booking-modal').data('busy')) return;
                    setModalBusy('delete-booking-modal', true);
                    $('#delete-booking-error').addClass('hidden');
                    $.ajax({
                        url: $table.attr('data-delete-booking-url').replace('__BOOKING__', deleteBookingId),
                        method: 'DELETE',
                        dataType: 'json',
                        timeout: 30000,
                        data: {
                            delete_reason: $('#delete-booking-reason').val()
                        }
                    }).done(handleBookingDeleted).fail(handleBookingDeleteFailed).always(finishBookingDelete);
                }

                function handleBookingDeleted(result) {
                    setModalBusy('delete-booking-modal', false);
                    closeModal('delete-booking-modal');
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                }

                function handleBookingDeleteFailed(xhr) {
                    const result = xhr.responseJSON;
                    $('#delete-booking-error').text(result && result.errors ? Object.values(result.errors).flat().join(
                            ' ') : result && result.message || 'Could not delete booking. Please try again.')
                        .removeClass('hidden');
                }

                function finishBookingDelete() {
                    setModalBusy('delete-booking-modal', false);
                }

                function confirmSalePayments() {
                    verifySaleId = table.row($(this).closest('tr')).data().id;
                    $('#verify-payments-description').text('Confirm that all pending payments for ticket #' +
                        verifySaleId + ' have been received. Rejected payments will stay rejected.');
                    $('#verify-payments-error').addClass('hidden');
                    openModal('verify-payments-modal', this);
                }

                function verifyPendingPayments(event) {
                    event.preventDefault();
                    if ($('#verify-payments-modal').data('busy')) return;
                    setModalBusy('verify-payments-modal', true);
                    $('#verify-payments-error').addClass('hidden');
                    $.ajax({
                        url: $table.attr('data-verify-payments-url').replace('__SALE__', verifySaleId),
                        method: 'POST',
                        data: {},
                        dataType: 'json',
                        timeout: 30000
                    }).done(handlePaymentReviewed).fail(handlePaymentReviewFailed).always(finishPaymentReview);
                }

                function handlePaymentReviewed(result) {
                    setModalBusy('verify-payments-modal', false);
                    closeModal('verify-payments-modal');
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                }

                function handlePaymentReviewFailed(xhr) {
                    $('#verify-payments-error').text(xhr.responseJSON && xhr.responseJSON.message ||
                        'Could not update payment. Please try again.').removeClass('hidden');
                }

                function finishPaymentReview() {
                    setModalBusy('verify-payments-modal', false);
                }

                function restoreBookingInputs(states) {
                    $.each(states, function restoreInputState(index, state) {
                        $(state.element).prop('disabled', state.disabled);
                    });
                }

                function getTableColumns() {
                    const columns = [{
                            data: 'id',
                        },
                        {
                            data: 'customer_name',
                        },
                        {
                            data: 'customer_mobile',
                        },
                        {
                            data: 'whatsapp',
                        },
                    ];
                    if (status === 'pending')
                        columns.push({
                            data: 'journey_date',
                        });
                    if (status === 'feedbacked')
                        columns.push({
                            data: 'remark_text',
                            className: 'whitespace-normal break-words',
                        }, {
                            data: 'feedback_by',
                        }, );
                    if (['pending-sold', 'sold'].includes(status))
                        columns.push({
                            data: 'hotels',
                            orderable: false,
                            searchable: false,
                        }, {
                            data: 'stay_dates',
                            orderable: false,
                            searchable: false,
                        }, {
                            data: 'payment_details',
                            orderable: false,
                            searchable: false,
                        }, {
                            data: 'sold_by',
                            orderable: false,
                            searchable: false,
                        }, );
                    columns.push({
                        data: 'action',
                        orderable: false,
                        searchable: false,
                    });

                    return columns;
                }

                function initializeTicketTable() {
                    $table.on('xhr.dt', function(event, settings, json) {
                        if (json === null) {
                            pageLoaderReady($table);
                            toastr.error('Could not load tickets. Please try again.');
                            return true;
                        }
                    });

                    return $table.DataTable({
                        processing: true,
                        serverSide: true,
                        scrollX: true,
                        autoWidth: false,
                        ajax: {
                            url: $table.attr('data-ajax-url'),
                            timeout: 30000,
                        },
                        columns: getTableColumns(),
                        initComplete: function() {
                            pageLoaderReady($table);
                        },
                    });
                }

                function bindTableResize() {
                    if (typeof ResizeObserver !== 'undefined' && $('.app-content').length) {
                        let timer;
                        new ResizeObserver(function() {
                            clearTimeout(timer);
                            timer = setTimeout(function() {
                                table.columns.adjust();
                            }, 250);
                        }).observe($('.app-content')[0]);
                    }
                }

                function bindScreenshotViewer() {
                    $table.on('click', '[data-view-payment-screenshot]', function() {
                        const $image = $('#payment-screenshot-image');
                        $('#payment-screenshot-status').text('Loading screenshot...').removeClass('hidden');
                        $image.off('load error').addClass('hidden').removeAttr('src');
                        $image
                            .one('load', function() {
                                $('#payment-screenshot-status').addClass('hidden');
                                $image.removeClass('hidden');
                            })
                            .one('error', function() {
                                $('#payment-screenshot-status')
                                    .text('Could not load this screenshot. Please try again.')
                                    .removeClass('hidden');
                            });
                        openModal('payment-screenshot-modal', this);
                        $image.attr('src', $(this).attr('data-view-payment-screenshot'));
                    });
                    $('#payment-screenshot-modal').on('modal:closed', function() {
                        $('#payment-screenshot-image').off('load error').removeAttr('src').addClass('hidden');
                    });
                }

                function bindFeedbackEvents() {
                    $table.on('click', '.btn-remarks, .btn-edit', function() {
                        const row = table.row($(this).closest('tr')).data(),
                            edit = $(this).hasClass('btn-edit');
                        $(edit ? '#edit_ticket_id' : '#ticket_id').val(row.id);
                        $(edit ? '#edit-feedback-text' : '#remarks-text').val(
                            edit ?
                            $('<textarea>')
                            .html(row.remark_text || '')
                            .val() :
                            '',
                        );
                        openModal(edit ? 'edit-feedback-modal' : 'remarks-modal', this);
                    });
                    $('#remarks-form, #edit-feedback-form').on('submit', function(event) {
                        event.preventDefault();
                        const edit = this.id === 'edit-feedback-form',
                            text = $(edit ? '#edit-feedback-text' : '#remarks-text')
                            .val()
                            .trim();
                        if (!text) {
                            toastr.error('Please write feedback.');
                            return;
                        }
                        setModalBusy(edit ? 'edit-feedback-modal' : 'remarks-modal', true);
                        $.post($table.attr(edit ? 'data-remarks-update-url' : 'data-remarks-save-url'), {
                                ticket_id: $(edit ? '#edit_ticket_id' : '#ticket_id').val(),
                                remark_text: text,
                            })
                            .done(function(result) {
                                toastr.success(result.message);
                                setModalBusy(edit ? 'edit-feedback-modal' : 'remarks-modal', false);
                                closeModal(edit ? 'edit-feedback-modal' : 'remarks-modal');
                                table.ajax.reload(null, false);
                            })
                            .fail(function(xhr) {
                                toastr.error(
                                    (xhr.responseJSON && xhr.responseJSON.message) ||
                                    'Could not save feedback.',
                                );
                            })
                            .always(function() {
                                setModalBusy(edit ? 'edit-feedback-modal' : 'remarks-modal', false);
                            });
                    });
                }

                function bindBookingEvents() {
                    $modal.on('change', '[data-room-select]', handleRoomChanged);
                    $modal.on('modal:closed', cancelPendingRoomRequests);
                    $modal.on('select2:open', focusDropdownSearch);
                    $modal.on('change', '[data-hotel-select]', handleHotelChanged);
                    $modal.on('click', '[data-retry-rooms]', retryRoomOptions);
                    $('#retry-hotel-list').on('click', loadHotelOptions);
                    $('#add-sell-room').on('click', function() {
                        addBookingRow('room');
                    });
                    $('#add-sell-payment').on('click', function() {
                        @can('payments.create')
                            addBookingRow('payment');
                        @endcan
                    });
                    $modal.on('input change', calculateBookingTotals);
                    $modal.on('click', '[data-remove-row]', function() {
                        if (!saving) {
                            clearPaymentScreenshot($(this).closest('[data-payment-row]'));
                            destroyHotelDropdowns($(this).closest('[data-room-row]'));
                            $(this).closest('[data-room-row], [data-payment-row]').remove();
                            calculateBookingTotals();
                        }
                    });

                    $table.on('click', '.btn-sell', openBookingModal);
                    $form.on('submit', submitHotelBooking);
                }

                function openBookingModal() {
                    saleId = table.row($(this).closest('tr')).data().id;
                    $form.trigger('reset');
                    destroyHotelDropdowns($rooms);
                    $rooms.empty();
                    $payments.empty();
                    $error.addClass('hidden');
                    $('#sell-customer').text('Ticket #' + saleId);
                    addBookingRow('room');
                    @can('payments.create')
                        addBookingRow('payment');
                    @endcan
                    openModal('sell-modal', this);
                    loadHotelOptions();
                    $rooms.find('input').first().trigger('focus');
                }

                function submitHotelBooking(event) {
                    event.preventDefault();
                    if (saving) return;
                    const payload = {
                        rooms: getRowsData($rooms),
                        payments: getRowsData($payments),
                        remark: $('#sell-remark').val(),
                        hotel_advances: getHotelAdvances(),
                    };
                    const allocated = payload.hotel_advances.reduce(sumAdvanceCents, 0n);
                    const received = payload.payments.reduce(sumPaymentCents, 0n);
                    if (allocated !== received) {
                        $error.text('Hotel advances must add up to the received payment amount.').removeClass('hidden');
                        return;
                    }
                    if (payload.rooms.some(isHotelMissing)) {
                        $error.text('Please select a hotel and room for every entry.').removeClass('hidden');
                        return;
                    }
                    if (
                        payload.rooms.some(function(room) {
                            return calculateRoomTotals(room).nights < 1;
                        })
                    ) {
                        $error.text('Check-out must be after check-in for every room.').removeClass('hidden');
                        return;
                    }
                    const invoiceTab = @json(auth()->user()->can('bookings.invoice')) ? document.defaultView.open('about:blank',
                        '_blank') : null;
                    if (invoiceTab) {
                        invoiceTab.opener = null;
                        invoiceTab.document.title = 'Preparing BookMe Invoice';
                        $(invoiceTab.document.body).text('Preparing your BookMe invoice...');
                    }
                    saving = true;
                    setModalBusy('sell-modal', true);
                    $error.addClass('hidden');
                    const disabledStates = disableBookingInputs();
                    const formData = buildBookingFormData(payload);
                    $.ajax({
                            url: $modal.attr('data-sell-url').replace('__SALE__', saleId),
                            method: 'POST',
                            contentType: false,
                            processData: false,
                            dataType: 'json',
                            timeout: 60000,
                            data: formData,
                        })
                        .done(function(result) {
                            saving = false;
                            setModalBusy('sell-modal', false);
                            closeModal('sell-modal');
                            toastr.success(result.message);
                            if (result.sheet_warning) toastr.warning(result.sheet_warning);
                            table.ajax.reload(null, false);
                            @can('bookings.invoice')
                                printBookingInvoice(result.invoice_url, invoiceTab);
                            @endcan
                        })
                        .fail(function(xhr) {
                            if (invoiceTab && !invoiceTab.closed) invoiceTab.close();
                            const result = xhr.responseJSON;
                            $error
                                .text(
                                    result && result.errors ?
                                    Object.values(result.errors).flat().join('\n') :
                                    (result && result.message) || 'Could not save this booking.',
                                )
                                .removeClass('hidden');
                            $modal.find('[data-modal-body]').scrollTop(0);
                        })
                        .always(function() {
                            saving = false;
                            setModalBusy('sell-modal', false);
                            restoreBookingInputs(disabledStates);
                        });
                }

                function printBookingInvoice(url, invoiceTab) {
                    const printUrl = url + '?print=1';
                    if (invoiceTab && !invoiceTab.closed) {
                        invoiceTab.location.replace(printUrl);
                    } else {
                        toastr.info(
                            'Invoice tab was blocked or closed. Use the invoice icon in Pending Sold or Sold to print.'
                        );
                    }
                }

                function buildBookingFormData(payload) {
                    const formData = new FormData();
                    formData.append('remark', payload.remark);
                    $.each(payload.hotel_advances, function appendHotelAdvance(index, advance) {
                        formData.append('hotel_advances[' + index + '][bookme_hotel_id]', advance
                            .bookme_hotel_id);
                        formData.append('hotel_advances[' + index + '][amount]', advance.amount);
                    });
                    $.each(payload.rooms, function(index, room) {
                        $.each(room, function(field, value) {
                            formData.append('rooms[' + index + '][' + field + ']', value);
                        });
                    });
                    $.each(payload.payments, function(index, payment) {
                        $.each(payment, function(field, value) {
                            formData.append('payments[' + index + '][' + field + ']', value);
                        });
                        const file = $payments
                            .children()
                            .eq(index)
                            .find('[data-payment-attachment]')
                            .prop('files')[0];
                        if (file) formData.append('payments[' + index + '][attachment]', file);
                    });
                    return formData;
                }

                function bindAttachmentEvents() {
                    $modal.on('change', '[data-payment-attachment]', function() {
                        const $input = $(this),
                            $row = $input.closest('[data-payment-row]');
                        const file = $input.prop('files')[0];
                        const oldUrl = $row.data('attachment-preview-url');
                        if (oldUrl) URL.revokeObjectURL(oldUrl);
                        $row.removeData('attachment-preview-url');
                        if (!file) {
                            clearPaymentScreenshot($row);
                            return;
                        }
                        if (
                            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
                            file.size > 5 * 1024 * 1024
                        ) {
                            clearPaymentScreenshot($row);
                            $error
                                .text('Choose a JPG, PNG or WebP screenshot up to 5 MB.')
                                .removeClass('hidden');
                            return;
                        }
                        const url = URL.createObjectURL(file);
                        $row.data('attachment-preview-url', url);
                        $row.find('[data-attachment-preview]')
                            .removeClass('hidden')
                            .find('img')
                            .attr('src', url);
                    });
                    $modal.on('click', '[data-remove-attachment]', function() {
                        clearPaymentScreenshot($(this).closest('[data-payment-row]'));
                    });
                    $modal.on('modal:closed', function() {
                        $payments.children().each(function() {
                            clearPaymentScreenshot($(this));
                        });
                    });
                }

                function clearPaymentScreenshot($row) {
                    const url = $row.data('attachment-preview-url');
                    if (url) URL.revokeObjectURL(url);
                    $row.removeData('attachment-preview-url');
                    $row.find('[data-payment-attachment]').val('');
                    $row.find('[data-attachment-preview]').addClass('hidden').find('img').removeAttr('src');
                }

                function getMinimumCheckoutDate(checkIn) {
                    if (!checkIn) return '';
                    const date = new Date(checkIn + 'T00:00:00Z');
                    if (!Number.isFinite(date.getTime())) return '';
                    date.setUTCDate(date.getUTCDate() + 1);
                    return date.toISOString().slice(0, 10);
                }

                function amountToCents(value) {
                    const match = String(value ?? '')
                        .trim()
                        .match(/^([0-9]+)(?:[.]([0-9]{0,2}))?$/);
                    return match ? BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0')) : 0n;
                }

                function formatMoney(value) {
                    const abs = value < 0n ? -value : value;
                    return (
                        (value < 0n ? '-' : '') + String(abs / 100n) + '.' + String(abs % 100n).padStart(2, '0')
                    );
                }

                function getRowValues($row) {
                    const data = {};
                    $row.find('[data-field]').each(function() {
                        data[$(this).attr('data-field')] = $(this).val();
                        if ($(this).is('[data-hotel-select]')) {
                            data.bookme_hotel_id = $(this).val() || '';
                            data.hotel_name = $(this).val() ? $(this).find(':selected').text() : '';
                        }
                    });
                    return data;
                }

                function getRowsData($container) {
                    const result = [];
                    $container.children().each(function() {
                        result.push(getRowValues($(this)));
                    });
                    return result;
                }

                function calculateRoomTotals(room) {
                    const difference =
                        Date.parse(room.check_out_date + 'T00:00:00Z') -
                        Date.parse(room.check_in_date + 'T00:00:00Z');
                    const nights = Number.isFinite(difference) ?
                        Math.max(0, Math.round(difference / 86400000)) :
                        0;
                    return {
                        nights,
                        sell: amountToCents(room.customer_price_per_night) * BigInt(nights),
                        partner: amountToCents(room.partner_price_per_night) * BigInt(nights),
                    };
                }

                function calculateBookingTotals() {
                    let sell = 0n,
                        partner = 0n,
                        received = 0n;
                    $rooms.children().each(function() {
                        const $row = $(this);
                        const minimum = getMinimumCheckoutDate($row.find('[data-field="check_in_date"]').val());
                        const $checkout = $row.find('[data-field="check_out_date"]');
                        if (minimum) {
                            $checkout.attr('min', minimum);
                            if ($checkout.val() && $checkout.val() < minimum) $checkout.val('');
                        } else {
                            $checkout.removeAttr('min');
                        }
                        const price = calculateRoomTotals(getRowValues($row));
                        sell += price.sell;
                        partner += price.partner;
                        $row.find('[data-room-nights]').text(price.nights);
                        $row.find('[data-room-sell]').text(formatMoney(price.sell));
                        $row.find('[data-room-partner]').text(formatMoney(price.partner));
                    });
                    $payments.find('[data-field="amount"]').each(function() {
                        received += amountToCents($(this).val());
                    });
                    $.each({
                            total: sell,
                            profit: sell - partner,
                            received,
                            due: sell - received,
                        },
                        function(key, value) {
                            $('#sell-' + key).text(formatMoney(value));
                        },
                    );
                    $rooms.find('[data-remove-row]').prop('disabled', $rooms.children().length === 1);
                    updateHotelAdvanceFields(received);
                }

                function updateHotelAdvanceFields(received) {
                    const hotels = {};
                    $rooms.find('[data-hotel-select]').each(function collectSelectedHotel() {
                        if ($(this).val()) hotels[$(this).val()] = $(this).find(':selected').text();
                    });
                    const $container = $('#sell-hotel-advances');
                    $container.children().each(function removeUnusedHotelAdvance() {
                        if (!hotels[$(this).attr('data-advance-hotel')]) $(this).remove();
                    });
                    const hotelCount = Object.keys(hotels).length;
                    const single = hotelCount === 1;
                    $('#sell-hotel-advance-section').toggleClass('hidden', hotelCount < 2);
                    $.each(hotels, function updateHotelAdvance(hotelId, name) {
                        let $row = $container.children().filter(function matchAdvanceHotel() {
                            return $(this).attr('data-advance-hotel') === hotelId;
                        });
                        if (!$row.length) {
                            $row = $('<label>', {
                                class: 'block text-sm font-medium text-gray-700',
                                'data-advance-hotel': hotelId
                            });
                            $('<span>').text(name).appendTo($row);
                            $('<input>', {
                                type: 'number',
                                min: '0',
                                max: '999999999999.99',
                                step: '0.01',
                                required: true,
                                value: '0.00',
                                'aria-label': name + ' advance',
                                class: 'mt-1 block w-full rounded-md border-gray-300 text-sm'
                            }).appendTo($row);
                            $container.append($row);
                        }
                        $row.find('input').prop('readOnly', single);
                        if (single) $row.find('input').val(formatMoney(received));
                    });
                    const allocated = getHotelAdvances().reduce(sumAdvanceCents, 0n);
                    $('#sell-advance-summary').text('Allocated: ' + formatMoney(allocated) + ' / Received: ' +
                            formatMoney(received))
                        .toggleClass('text-red-700', allocated !== received);
                }

                function sumAdvanceCents(total, advance) {
                    return total + amountToCents(advance.amount);
                }

                function sumPaymentCents(total, payment) {
                    return total + amountToCents(payment.amount);
                }

                function getHotelAdvances() {
                    const advances = [];
                    $('#sell-hotel-advances').children().each(function readHotelAdvance() {
                        advances.push({
                            bookme_hotel_id: $(this).attr('data-advance-hotel'),
                            amount: $(this).find('input').val()
                        });
                    });
                    return advances;
                }

                function addBookingRow(kind) {
                    const $container = kind === 'room' ? $rooms : $payments;
                    $container.append($('#sell-' + kind + '-template').html());
                    if (kind === 'room') {
                        initializeHotelDropdown.call($container.children().last().find('[data-hotel-select]')[0]);
                        initializeRoomDropdown.call($container.children().last().find('[data-room-select]')[0]);
                    }
                    if (kind === 'payment') {
                        const now = new Date();
                        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                        $container
                            .children()
                            .last()
                            .find('[data-field="paid_at"]')
                            .val(now.toISOString().slice(0, 16));
                    }
                    calculateBookingTotals();
                }

                function initializeHotelDropdown() {
                    const $select = $(this);
                    $select.select2({
                        data: hotelOptions || [],
                        placeholder: hotelsLoading ? 'Loading hotels...' : 'Search and select a hotel',
                        dropdownParent: $modal,
                        width: '100%',
                        language: {
                            noResults: hotelSearchEmpty
                        },
                    });
                    $select.prop('disabled', hotelOptions === null || saving);
                }

                function isHotelMissing(room) {
                    return !room.hotel_name || !room.room_name;
                }

                function hotelSearchEmpty() {
                    return 'No hotels found';
                }

                function destroyHotelDropdowns($container) {
                    $container.find('[data-room-row]').addBack('[data-room-row]').each(cancelBookingRequests);
                    $container.find('[data-hotel-select].select2-hidden-accessible').each(destroyHotelDropdown);
                    $container.find('[data-room-select].select2-hidden-accessible').each(destroyHotelDropdown);
                }

                function destroyHotelDropdown() {
                    $(this).select2('destroy');
                }

                function loadHotelOptions() {
                    if (hotelsLoading || hotelOptions !== null) return;
                    hotelsLoading = true;
                    $('#hotel-list-error').addClass('hidden');
                    refreshHotelDropdowns();
                    $.ajax({
                        url: $modal.attr('data-hotels-url'),
                        dataType: 'json',
                        timeout: 20000,
                    }).done(handleHotelsLoaded).fail(handleHotelsFailed).always(finishHotelsLoading);
                }

                function handleHotelsLoaded(response) {
                    hotelOptions = response.results;
                }

                function handleHotelsFailed() {
                    $('#hotel-list-error').removeClass('hidden');
                }

                function finishHotelsLoading() {
                    hotelsLoading = false;
                    refreshHotelDropdowns();
                }

                function refreshHotelDropdowns() {
                    destroyHotelDropdowns($rooms);
                    $rooms.find('[data-hotel-select]').each(initializeHotelDropdown);
                    $rooms.find('[data-room-select]').each(initializeRoomDropdown);
                }

                function initializeRoomDropdown() {
                    $(this).select2({
                        placeholder: $(this).find('option').first().text(),
                        dropdownParent: $modal,
                        width: '100%',
                    });
                }

                function focusDropdownSearch() {
                    $modal.find('.select2-container--open .select2-search__field').trigger('focus');
                }

                function cancelPendingRoomRequests() {
                    $rooms.children().each(cancelBookingRequests);
                    $modal.find('select.select2-hidden-accessible').select2('close');
                }

                function cancelRoomRequest() {
                    const request = $(this).data('room-request');
                    if (request) request.abort();
                    $(this).removeData('room-request');
                }

                function cancelBookingRequests() {
                    cancelRoomRequest.call(this);
                }

                function handleHotelChanged() {
                    const $row = $(this).closest('[data-room-row]');
                    const hotel = $(this).select2('data')[0];
                    $row.data('hotel-id', hotel && hotel.hotel_id);
                    loadRoomOptions($row);
                }

                function retryRoomOptions() {
                    loadRoomOptions($(this).closest('[data-room-row]'));
                }

                function setRoomOptions($row, options, placeholder, disabled) {
                    const $select = $row.find('[data-room-select]');
                    if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
                    $select.empty().append(new Option(placeholder, '', true, true));
                    $.each(options, function appendRoomOption(index, room) {
                        const $option = $(new Option(room.text, room.id));
                        $option.data('customer-price-per-night', room.customer_price_per_night);
                        $select.append($option);
                    });
                    $select.prop('disabled', disabled);
                    initializeRoomDropdown.call($select[0]);
                }

                function loadRoomOptions($row) {
                    cancelRoomRequest.call($row[0]);
                    $row.find('[data-room-error]').addClass('hidden');
                    $row.find('[data-field="customer_price_per_night"]').val('');
                    calculateBookingTotals();
                    const hotelId = $row.data('hotel-id');
                    setRoomOptions($row, [], hotelId ? 'Loading rooms...' : 'Select a hotel first', true);
                    if (!hotelId) return;
                    const request = $.ajax({
                        url: $form.attr('data-rooms-url').replace('__HOTEL__', hotelId),
                        dataType: 'json',
                        timeout: 20000,
                        context: $row[0],
                    }).done(handleRoomsLoaded).fail(handleRoomsFailed);
                    $row.data('room-request', request);
                }

                function handleRoomsLoaded(response) {
                    const rooms = response.results;
                    setRoomOptions($(this), rooms, rooms.length ? 'Search and select a room' : 'No rooms available', !
                        rooms.length);
                    $(this).removeData('room-request');
                }

                function handleRoomChanged() {
                    const price = $(this).find(':selected').data('customer-price-per-night');
                    const $row = $(this).closest('[data-room-row]');
                    $row.find('[data-field="customer_price_per_night"]').val(price ?? '');
                    calculateBookingTotals();
                }

                function handleRoomsFailed(xhr, status) {
                    if (status === 'abort') return;
                    setRoomOptions($(this), [], 'Rooms unavailable', true);
                    $(this).find('[data-room-error]').removeClass('hidden');
                    $(this).removeData('room-request');
                }

            });
        </script>
    @endpush
</x-app-layout>
