<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">{{ ucwords(str_replace('-', ' ', $status)) }} Tickets</h1>
    </x-slot>

    @if ($status === 'pending' || $status === 'feedbacked')
        @include('ship_tickets.partials.sell-modal')
    @endif

    @if (in_array($status, ['pending-sold', 'sold'], true))
        @include('ship_tickets.partials.reschedule-modal')
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
            <form id="edit-feedback-form"><input type="hidden" id="edit_ticket_id"><x-input-label for="edit-feedback-text"
                    value="Feedback" />
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
            $(document).ready(function () {
                const $table = $('#tickets-table');
                if (!$table.length) return;

                const status = $table.attr('data-status');
                const $modal = $('#sell-modal');
                const $form = $('#sell-form');
                const $rooms = $('#sell-rooms');
                const $payments = $('#sell-payments');
                const $error = $('#sell-error');
                let saleId;
                let saving = false;
                let hotelOptions = null;
                let hotelsLoading = false;

                configureAjax();
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
                        states.push({ element: this, disabled: $(this).prop('disabled') });
                        $(this).prop('disabled', true);
                    });
                    return states;
                }

                function bindPaymentVerification() {
                    $table.on('click', '.btn-verify-payments', confirmSalePayments);
                }

                function confirmSalePayments() {
                    const $button = $(this);
                    const row = table.row($button.closest('tr')).data();
                    Swal.fire({
                        title: 'Verify payments?',
                        text: 'Confirm that all pending payments for ticket #' + row.id + ' have been received. Rejected payments will stay rejected.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, verify',
                        showLoaderOnConfirm: true,
                        allowOutsideClick: preventCloseWhileVerifying,
                        preConfirm: verifyPendingPayments,
                    });

                    function verifyPendingPayments() {
                        return sendPaymentReview($table.attr('data-verify-payments-url').replace('__SALE__', row.id), 'POST', {});
                    }
                }

                function preventCloseWhileVerifying() {
                    return !Swal.isLoading();
                }

                function sendPaymentReview(url, method, data) {
                    return $.ajax({ url, method, data, dataType: 'json', timeout: 30000 })
                        .then(handlePaymentReviewed, handlePaymentReviewFailed);
                }

                function handlePaymentReviewed(result) {
                    toastr.success(result.message);
                    table.ajax.reload(null, false);
                    return result;
                }

                function handlePaymentReviewFailed(xhr) {
                    Swal.showValidationMessage(xhr.responseJSON && xhr.responseJSON.message || 'Could not update payment. Please try again.');
                    return false;
                }

                function restoreBookingInputs(states) {
                    $.each(states, function restoreInputState(index, state) {
                        $(state.element).prop('disabled', state.disabled);
                    });
                }

                function configureAjax() {
                    $.ajaxSetup({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        },
                    });
                }

                function getTableColumns() {
                    const columns = [
                        {
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
                        columns.push(
                            {
                                data: 'remark_text',
                                className: 'whitespace-normal break-words',
                            },
                            {
                                data: 'feedback_by',
                            },
                        );
                    if (['pending-sold', 'sold'].includes(status))
                        columns.push(
                            {
                                data: 'hotels',
                                orderable: false,
                                searchable: false,
                            },
                            {
                                data: 'stay_dates',
                                orderable: false,
                                searchable: false,
                            },
                            {
                                data: 'payment_details',
                                orderable: false,
                                searchable: false,
                            },
                            {
                                data: 'sold_by',
                                orderable: false,
                                searchable: false,
                            },
                        );
                    columns.push({
                        data: 'action',
                        orderable: false,
                        searchable: false,
                    });

                    return columns;
                }

                function initializeTicketTable() {
                    $table.on('xhr.dt', function (event, settings, json) {
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
                        initComplete: function () {
                            pageLoaderReady($table);
                        },
                    });
                }

                function bindTableResize() {
                    if (typeof ResizeObserver !== 'undefined' && $('.app-content').length) {
                        let timer;
                        new ResizeObserver(function () {
                            clearTimeout(timer);
                            timer = setTimeout(function () {
                                table.columns.adjust();
                            }, 250);
                        }).observe($('.app-content')[0]);
                    }
                }

                function bindScreenshotViewer() {
                    $table.on('click', '[data-view-payment-screenshot]', function () {
                        const $image = $('#payment-screenshot-image');
                        $('#payment-screenshot-status').text('Loading screenshot...').removeClass('hidden');
                        $image.off('load error').addClass('hidden').removeAttr('src');
                        $image
                            .one('load', function () {
                                $('#payment-screenshot-status').addClass('hidden');
                                $image.removeClass('hidden');
                            })
                            .one('error', function () {
                                $('#payment-screenshot-status')
                                    .text('Could not load this screenshot. Please try again.')
                                    .removeClass('hidden');
                            });
                        openModal('payment-screenshot-modal', this);
                        $image.attr('src', $(this).attr('data-view-payment-screenshot'));
                    });
                    $('#payment-screenshot-modal').on('modal:closed', function () {
                        $('#payment-screenshot-image').off('load error').removeAttr('src').addClass('hidden');
                    });
                }

                function bindFeedbackEvents() {
                    $table.on('click', '.btn-remarks, .btn-edit', function () {
                        const row = table.row($(this).closest('tr')).data(),
                            edit = $(this).hasClass('btn-edit');
                        $(edit ? '#edit_ticket_id' : '#ticket_id').val(row.id);
                        $(edit ? '#edit-feedback-text' : '#remarks-text').val(
                            edit
                                ? $('<textarea>')
                                      .html(row.remark_text || '')
                                      .val()
                                : '',
                        );
                        openModal(edit ? 'edit-feedback-modal' : 'remarks-modal', this);
                    });
                    $('#remarks-form, #edit-feedback-form').on('submit', function (event) {
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
                            .done(function (result) {
                                toastr.success(result.message);
                                setModalBusy(edit ? 'edit-feedback-modal' : 'remarks-modal', false);
                                closeModal(edit ? 'edit-feedback-modal' : 'remarks-modal');
                                table.ajax.reload(null, false);
                            })
                            .fail(function (xhr) {
                                toastr.error(
                                    (xhr.responseJSON && xhr.responseJSON.message) ||
                                        'Could not save feedback.',
                                );
                            })
                            .always(function () {
                                setModalBusy(edit ? 'edit-feedback-modal' : 'remarks-modal', false);
                            });
                    });
                }

                function bindBookingEvents() {
                    $modal.on('change', '[data-hotel-account]', syncHotelAccountSelection);
                    $modal.on('click', '[data-retry-hotel-accounts]', retryHotelAccounts);
                    $modal.on('change', '[data-room-select]', handleRoomChanged);
                    $modal.on('modal:closed', cancelPendingRoomRequests);
                    $modal.on('select2:open', focusDropdownSearch);
                    $modal.on('change', '[data-hotel-select]', handleHotelChanged);
                    $modal.on('click', '[data-retry-rooms]', retryRoomOptions);
                    $('#retry-hotel-list').on('click', loadHotelOptions);
                    $('#add-sell-room').on('click', function () {
                        addBookingRow('room');
                    });
                    $('#add-sell-payment').on('click', function () {
                        addBookingRow('payment');
                    });
                    $modal.on('input change', calculateBookingTotals);
                    $modal.on('click', '[data-remove-row]', function () {
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
                    addBookingRow('payment');
                    openModal('sell-modal', this);
                    loadHotelOptions();
                    $rooms.find('input').first().trigger('focus');
                }

                function submitHotelBooking(event) {
                    event.preventDefault();
                    if (saving) return;
                    if ($rooms.children().filter(hasUnloadedHotelAccounts).length) {
                        $error.text('Please wait for hotel payment accounts to load, or retry if loading failed.').removeClass('hidden');
                        return;
                    }
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
                    if (payload.rooms.some(isHotelAccountMissing)) {
                        $error.text('Please select at least one payout payment method for each hotel. Add a payment method in Hotel Payment Accounts if none is available.').removeClass('hidden');
                        return;
                    }
                    if (
                        payload.rooms.some(function (room) {
                            return calculateRoomTotals(room).nights < 1;
                        })
                    ) {
                        $error.text('Check-out must be after check-in for every room.').removeClass('hidden');
                        return;
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
                        timeout: 30000,
                        data: formData,
                    })
                        .done(function (result) {
                            saving = false;
                            setModalBusy('sell-modal', false);
                            closeModal('sell-modal');
                            toastr.success(result.message);
                            table.ajax.reload(null, false);
                        })
                        .fail(function (xhr) {
                            const result = xhr.responseJSON;
                            $error
                                .text(
                                    result && result.errors
                                        ? Object.values(result.errors).flat().join('\n')
                                        : (result && result.message) || 'Could not save this booking.',
                                )
                                .removeClass('hidden');
                            $modal.find('[data-modal-body]').scrollTop(0);
                        })
                        .always(function () {
                            saving = false;
                            setModalBusy('sell-modal', false);
                            restoreBookingInputs(disabledStates);
                        });
                }

                function buildBookingFormData(payload) {
                    const formData = new FormData();
                    formData.append('remark', payload.remark);
                    $.each(payload.hotel_advances, function appendHotelAdvance(index, advance) {
                        formData.append('hotel_advances[' + index + '][bookme_hotel_id]', advance.bookme_hotel_id);
                        formData.append('hotel_advances[' + index + '][amount]', advance.amount);
                    });
                    $.each(payload.rooms, function (index, room) {
                        $.each(room, function (field, value) {
                            formData.append('rooms[' + index + '][' + field + ']', value);
                        });
                    });
                    $.each(payload.payments, function (index, payment) {
                        $.each(payment, function (field, value) {
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
                    $modal.on('change', '[data-payment-attachment]', function () {
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
                    $modal.on('click', '[data-remove-attachment]', function () {
                        clearPaymentScreenshot($(this).closest('[data-payment-row]'));
                    });
                    $modal.on('modal:closed', function () {
                        $payments.children().each(function () {
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
                    $row.find('[data-field]').each(function () {
                        data[$(this).attr('data-field')] = $(this).val();
                        if ($(this).is('[data-hotel-select]')) {
                            data.bookme_hotel_id = $(this).val() || '';
                            data.hotel_name = $(this).val() ? $(this).find(':selected').text() : '';
                        }
                        if ($(this).is('[data-hotel-account]')) data.hotel_payment_account_id = $(this).val() || '';
                    });
                    return data;
                }

                function getRowsData($container) {
                    const result = [];
                    $container.children().each(function () {
                        result.push(getRowValues($(this)));
                    });
                    return result;
                }

                function calculateRoomTotals(room) {
                    const difference =
                        Date.parse(room.check_out_date + 'T00:00:00Z') -
                        Date.parse(room.check_in_date + 'T00:00:00Z');
                    const nights = Number.isFinite(difference)
                        ? Math.max(0, Math.round(difference / 86400000))
                        : 0;
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
                    $rooms.children().each(function () {
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
                    $payments.find('[data-field="amount"]').each(function () {
                        received += amountToCents($(this).val());
                    });
                    $.each(
                        {
                            total: sell,
                            profit: sell - partner,
                            received,
                            due: sell - received,
                        },
                        function (key, value) {
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
                        let $row = $container.children().filter(function matchAdvanceHotel() { return $(this).attr('data-advance-hotel') === hotelId; });
                        if (!$row.length) {
                            $row = $('<label>', { class: 'block text-sm font-medium text-gray-700', 'data-advance-hotel': hotelId });
                            $('<span>').text(name).appendTo($row);
                            $('<input>', { type: 'number', min: '0', max: '999999999999.99', step: '0.01', required: true, value: '0.00', 'aria-label': name + ' advance', class: 'mt-1 block w-full rounded-md border-gray-300 text-sm' }).appendTo($row);
                            $container.append($row);
                        }
                        $row.find('input').prop('readOnly', single);
                        if (single) $row.find('input').val(formatMoney(received));
                    });
                    const allocated = getHotelAdvances().reduce(sumAdvanceCents, 0n);
                    $('#sell-advance-summary').text('Allocated: ' + formatMoney(allocated) + ' / Received: ' + formatMoney(received))
                        .toggleClass('text-red-700', allocated !== received);
                }

                function sumAdvanceCents(total, advance) { return total + amountToCents(advance.amount); }
                function sumPaymentCents(total, payment) { return total + amountToCents(payment.amount); }

                function getHotelAdvances() {
                    const advances = [];
                    $('#sell-hotel-advances').children().each(function readHotelAdvance() {
                        advances.push({ bookme_hotel_id: $(this).attr('data-advance-hotel'), amount: $(this).find('input').val() });
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
                        language: { noResults: hotelSearchEmpty },
                    });
                    $select.prop('disabled', hotelOptions === null || saving);
                }

                function isHotelMissing(room) {
                    return !room.hotel_name || !room.room_name;
                }

                function isHotelAccountMissing(room) {
                    return !room.hotel_payment_account_id;
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
                    const accountRequest = $(this).data('account-request');
                    if (accountRequest) accountRequest.abort();
                    $(this).removeData('account-request');
                }

                function handleHotelChanged() {
                    const $row = $(this).closest('[data-room-row]');
                    const hotel = $(this).select2('data')[0];
                    $row.data('hotel-id', hotel && hotel.hotel_id);
                    loadRoomOptions($row);
                    loadHotelAccounts($row);
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
                    setRoomOptions($(this), rooms, rooms.length ? 'Search and select a room' : 'No rooms available', !rooms.length);
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

                function retryHotelAccounts() {
                    loadHotelAccounts($(this).closest('[data-room-row]'));
                }

                function loadHotelAccounts($row) {
                    $row.data('accounts-ready', false);
                    const previous = $row.data('account-request');
                    if (previous) previous.abort();
                    const hotelId = $row.data('hotel-id');
                    const $select = $row.find('[data-hotel-account]');
                    $select.empty().append(new Option(hotelId ? 'Loading accounts...' : 'Select a hotel first', '')).prop('disabled', true).prop('required', false);
                    $row.find('[data-retry-hotel-accounts]').addClass('hidden');
                    $row.find('[data-account-status]').text(hotelId ? 'Loading hotel payment accounts...' : 'Select a hotel first.');
                    if (!hotelId) return;
                    const request = $.ajax({
                        url: $form.attr('data-accounts-url').replace('__HOTEL__', hotelId),
                        dataType: 'json', timeout: 20000, context: $row[0],
                    }).done(handleHotelAccountsLoaded).fail(handleHotelAccountsFailed);
                    $row.data('account-request', request);
                }

                function handleHotelAccountsLoaded(response) {
                    const $row = $(this), $select = $row.find('[data-hotel-account]');
                    const accounts = response.results;
                    $row.removeData('account-request');
                    $row.data('accounts-ready', true);
                    $select.empty().append(new Option(accounts.length ? 'Select a payment account' : 'No payment account added', ''));
                    let selected = '';
                    $.each(accounts, function appendPaymentAccount(index, account) {
                        const method = { bank: 'Bank', bkash: 'bKash', nagad: 'Nagad' }[account.payment_method] || account.payment_method;
                        const label = [method, account.bank_name, account.account_name, account.account_number].filter(Boolean).join(' · ') + (account.is_default ? ' (Default)' : '');
                        $select.append(new Option(label, account.id));
                        if (account.is_default) selected = String(account.id);
                    });
                    $rooms.children().not(this).each(function reuseHotelAccount() {
                        if (String($(this).data('hotel-id')) === String($row.data('hotel-id'))) {
                            const value = $(this).find('[data-hotel-account]').val();
                            if (value) selected = value;
                        }
                    });
                    $select.val(selected).prop('disabled', !accounts.length || saving).prop('required', !!accounts.length);
                    $row.find('[data-account-status]').text(accounts.length ? 'Select a payment method. This account will be used for all rooms of this hotel in this booking.' : 'No payout payment method added for this hotel. Please add one in Hotel Payment Accounts before selling.');
                }

                function handleHotelAccountsFailed(xhr, status) {
                    if (status === 'abort') return;
                    $(this).removeData('account-request');
                    $(this).find('[data-hotel-account]').empty().append(new Option('Accounts unavailable', ''));
                    $(this).find('[data-account-status]').text('Could not load payment accounts. Please retry before saving.');
                    $(this).find('[data-retry-hotel-accounts]').removeClass('hidden');
                }

                function syncHotelAccountSelection() {
                    const hotelId = $(this).closest('[data-room-row]').data('hotel-id');
                    const value = $(this).val();
                    $rooms.children().each(function syncMatchingHotel() {
                        if (String($(this).data('hotel-id')) === String(hotelId)) $(this).find('[data-hotel-account]').val(value);
                    });
                }

                function hasUnloadedHotelAccounts() {
                    return $(this).data('hotel-id') && !$(this).data('accounts-ready');
                }
            });
        </script>
    @endpush
</x-app-layout>
