{{-- blade-formatter-disable --}}
function openEditBooking() {
    openBookingModal.call(this);
    editBookingId = $(this).attr('data-edit-booking');
    $('#sell-modal-title').text('Edit Booking #' + editBookingId);
    $('#submit-sell').text('Update Booking').data('original-label', 'Update Booking');
    $('#sell-customer').text('Loading booking...');
    setModalBusy('sell-modal', true);
    const requestedId = editBookingId;
    editRequest = $.getJSON($modal.attr('data-edit-url').replace('__BOOKING__', requestedId))
        .done(function(result) {
            if (editBookingId === requestedId) fillEditBooking(result.data);
        })
        .fail(function(xhr, status) {
            if (status !== 'abort') $error.text(xhr.responseJSON?.message || 'Could not load booking. Close and try again.').removeClass('hidden');
        })
        .always(function() {
            if (editBookingId !== requestedId) return;
            editRequest = null;
            setModalBusy('sell-modal', false);
        });
}

function fillEditBooking(booking) {
    editVersion = booking.version;
    saleId = booking.sales_id;
    $('#sell-customer').text(booking.sales_id ? 'Ticket #' + booking.sales_id : 'Hotel Booking #' + booking.id);
    $('#sell-customer-name').val(booking.customer_name);
    $('#sell-customer-mobile').val(booking.customer_mobile);
    $('#sell-whatsapp').val(booking.whatsapp || '');
    $('#sell-reservation-number').val(booking.reservation_number || '');
    $('#sell-remark').val(booking.remark || '');
    destroyHotelDropdowns($rooms);
    $rooms.empty();
    $payments.children().each(function() { clearPaymentScreenshot($(this)); });
    $payments.empty();
    $('#sell-hotel-advances').empty();
    $.each(booking.rooms, function(index, room) {
        addBookingRow('room');
        const $row = $rooms.children().last().attr('data-record-id', room.id);
        const $hotel = $row.find('[data-hotel-select]');
        if (!$hotel.find('option').filter(function() { return this.value === String(room.bookme_hotel_id); }).length) {
            $hotel.append(new Option(room.hotel_name, room.bookme_hotel_id));
        }
        $hotel.val(String(room.bookme_hotel_id)).prop('disabled', false).trigger('change.select2');
        $row.data('hotel-id', room.bookme_hotel_id);
        $row.data('edit-room-options', true);
        setRoomOptions($row, [{id: room.room_name, text: room.room_name}], 'Search and select a room', false);
        $row.find('[data-room-select]').val(room.room_name).trigger('change.select2');
        $.each(room, function(field, value) {
            if (['hotel_name', 'room_name'].includes(field)) return;
            if (field === 'check_in_date' || field === 'check_out_date') value = String(value).slice(0, 10);
            $row.find('[data-field="' + field + '"]').val(value);
        });
        const fees = (room.reschedules || []).reduce(function(total, item) {
            return {customer: total.customer + amountToCents(item.customer_fee), partner: total.partner + amountToCents(item.partner_fee)};
        }, {customer: 0n, partner: 0n});
        $row.data('customer-fees', formatMoney(fees.customer)).data('partner-fees', formatMoney(fees.partner));
        if (fees.customer || fees.partner) $('<p>', {class: 'mt-2 text-xs text-gray-500', text: 'Existing reschedule fees included: Customer ' + formatMoney(fees.customer) + ' / Hotel ' + formatMoney(fees.partner)}).appendTo($row);
    });
    $('#sell-payments-section').removeClass('hidden');
    $.each(booking.payments, function(index, payment) {
        addBookingRow('payment');
        const $row = $payments.children().last().attr('data-record-id', payment.id);
        const $method = $row.find('[data-field="payment_method"]');
        if (!$method.find('option').filter(function() { return this.value === payment.payment_method; }).length) {
            $method.append(new Option(payment.payment_method, payment.payment_method));
        }
        $row.find('[data-field="paid_at"]').attr('step', '1');
        $.each(payment, function(field, value) {
            if (field === 'paid_at' && value) {
                const date = new Date(value);
                date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
                value = date.toISOString().slice(0, 19);
            }
            $row.find('[data-field="' + field + '"]').val(value);
        });
        const locked = Boolean(payment.refunded_room_id) || !@json(auth()->user()->can('payments.update'));
        if (payment.refunded_room_id) $row.find('[data-field="amount"]').attr('min', '-9999999999.99');
        $row.find('input, select, button').prop('disabled', locked);
        $('<p>', {class: 'mt-2 text-xs text-gray-500', text: payment.refunded_room_id ? 'Recorded refund — preserved in booking history.' : 'Payment #' + payment.id + ' — ' + payment.status + '. Changes require verification again.'}).appendTo($row);
        if (payment.attachment_path) {
            $('<span>', {class: 'mt-2 block text-xs text-gray-600', text: 'Existing screenshot saved.'}).appendTo($row);
            @can('payments.screenshot')
                $('<a>', {href: @json(url('/hotel-booking-payments')) + '/' + payment.id + '/attachment', target: '_blank', rel: 'noopener', class: 'mt-1 block text-xs text-blue-700 underline', text: 'View existing screenshot'}).appendTo($row);
            @endcan
            if (!locked) $('<label>', {class: 'mt-2 block text-xs text-gray-600', text: ' Remove existing screenshot'}).prepend($('<input>', {type: 'checkbox', 'data-remove-existing-attachment': ''})).appendTo($row);
        }
    });
    calculateBookingTotals();
    $.each(booking.advances, function(index, advance) {
        $('#sell-hotel-advances').children().filter(function() { return $(this).attr('data-advance-hotel') === String(advance.bookme_hotel_id); }).find('input').val(advance.advance_amount);
    });
    calculateBookingTotals();
}
