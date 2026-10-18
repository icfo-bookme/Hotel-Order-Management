const $rescheduleForm = $('#reschedule-form');
let rescheduleRooms = [], rescheduleSaving = false, rescheduleRequest = null;
$table.on('click', '.btn-reschedule', openReschedule);
$table.on('click', '[data-reschedule-charges]', showRescheduleCharges);
$('#reschedule-room').on('change', selectRescheduleRoom);
$('#reschedule-add-payment').on('change', toggleReschedulePayment);
$rescheduleForm.on('input change', updateReschedulePreview);
$rescheduleForm.on('submit', saveReschedule);

function showRescheduleError(message) {
    $('#reschedule-error').text(message).removeClass('hidden');
}
function showRescheduleCharges() {
    const charges = JSON.parse($(this).attr('data-reschedule-charges'));
    const $content = $('#reschedule-charges-content').empty();
    $.each(charges, function appendRescheduleCharge(index, charge) {
        const $entry = $('<div>', { class: 'space-y-2 rounded-lg border border-gray-200 p-3' }).appendTo($content);
        $.each({ 'Customer Fee': charge.customer_fee, 'Partner Fee': charge.partner_fee, 'Previous Dates': charge.previous_dates, 'Current Dates': charge.current_dates }, function appendChargeField(label, value) {
            const $line = $('<p>', { class: 'text-gray-600' }).text(label + ': ').appendTo($entry);
            $('<strong>', { class: 'text-gray-900', text: value }).appendTo($line);
        });
    });
    openModal('reschedule-charges-modal', this);
}
function openReschedule() {
    const row = table.row($(this).closest('tr')).data();
    $('#reschedule-history').closest('details').prop('open', false);
    if (rescheduleRequest) rescheduleRequest.abort();
    $rescheduleForm.trigger('reset');
    toggleReschedulePayment();
    $('#reschedule-error').addClass('hidden');
    $('#reschedule-room').empty();
    $('#reschedule-current, #reschedule-preview, #reschedule-history').empty();
    rescheduleRooms = [];
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $rescheduleForm.find('[name="payment[paid_at]"]').val(now.toISOString().slice(0, 16));
    openModal('reschedule-modal', this);
    setModalBusy('reschedule-modal', true);
    rescheduleRequest = $.getJSON(@json(url('/hotel/sales')) + '/' + row.id + '/reschedules')
        .done(loadRescheduleRooms).fail(handleRescheduleError).always(finishRescheduleRequest);
}
function loadRescheduleRooms(result) {
    rescheduleRooms = result.rooms;
    $.each(rescheduleRooms, function appendRescheduleRoom(index, room) {
        $('<option>', { value: room.id, text: room.hotel_name + ' / ' + room.room_name }).appendTo('#reschedule-room');
    });
    selectRescheduleRoom();
    if (!rescheduleRooms.length) showRescheduleError('No confirmed rooms are available.');
}
function selectedRescheduleRoom() {
    return rescheduleRooms.find(function matchRescheduleRoom(room) { return String(room.id) === $('#reschedule-room').val(); });
}
function selectRescheduleRoom() {
    const room = selectedRescheduleRoom();
    if (!room) return;
    $('#reschedule-error').addClass('hidden');
    $('#reschedule-current').text('Current stay: ' + room.check_in_date + ' / ' + room.check_out_date + ' | Customer: ' + room.customer_sell + ' | Partner: ' + room.partner_share);
    $rescheduleForm.find('[name="new_check_in_date"]').val(room.check_in_date);
    $rescheduleForm.find('[name="new_check_out_date"]').val(room.check_out_date);
    $rescheduleForm.find('[name="customer_fee"], [name="partner_fee"]').val('0');
    $('#reschedule-history').empty();
    $.each(room.history, function appendRescheduleHistory(index, history) {
        $('<p>', { class: 'rounded bg-gray-50 p-2', text: history.from + ' ? ' + history.to + ' | Customer fee: ' + history.customer_fee + ' | Partner fee: ' + history.partner_fee + ' | ' + history.reason + ' | ' + (history.by || '-') }).appendTo('#reschedule-history');
    });
    if (!room.history.length) $('#reschedule-history').text('No reschedule history.');
    if (room.blocked) showRescheduleError('A hotel payout has already been recorded. A payout adjustment is required before rescheduling.');
    updateReschedulePreview();
}
function toggleReschedulePayment() {
    const enabled = $('#reschedule-add-payment').prop('checked');
    $('#reschedule-payment').prop('disabled', !enabled).toggleClass('hidden', !enabled);
}
function updateReschedulePreview() {
    const room = selectedRescheduleRoom();
    if (!room) return;
    const checkIn = $rescheduleForm.find('[name="new_check_in_date"]').val();
    const $checkout = $rescheduleForm.find('[name="new_check_out_date"]');
    $checkout.attr('min', getMinimumCheckoutDate(checkIn));
    const days = (Date.parse($checkout.val() + 'T00:00:00Z') - Date.parse(checkIn + 'T00:00:00Z')) / 86400000;
    if (!Number.isFinite(days) || days < 1) { $('#reschedule-preview').text('Check-out must be after check-in.'); return; }
    const sell = amountToCents(room.customer_price_per_night) * BigInt(days) + BigInt(room.customer_fees) + amountToCents($rescheduleForm.find('[name="customer_fee"]').val());
    const partner = amountToCents(room.partner_price_per_night) * BigInt(days) + BigInt(room.partner_fees) + amountToCents($rescheduleForm.find('[name="partner_fee"]').val());
    $('#reschedule-preview').text('New room totals ? Nights: ' + days + ' | Customer: ' + formatMoney(sell) + ' | Partner: ' + formatMoney(partner) + ' | B2B Profit: ' + formatMoney(sell - partner));
}
function saveReschedule(event) {
    event.preventDefault();
    const room = selectedRescheduleRoom();
    if (rescheduleSaving || !room) return;
    if (room.blocked) { showRescheduleError('Cannot reschedule after a hotel payout.'); return; }
    const payload = new FormData($rescheduleForm[0]);
    payload.append('old_check_in_date', room.check_in_date);
    payload.append('old_check_out_date', room.check_out_date);
    rescheduleSaving = true;
    setModalBusy('reschedule-modal', true);
    $('#reschedule-error').addClass('hidden');
    $.ajax({
        url: @json(url('/hotel-booking-rooms')) + '/' + room.id + '/reschedule',
        method: 'POST', data: payload, processData: false, contentType: false, dataType: 'json', timeout: 30000
    }).done(rescheduleSaved).fail(handleRescheduleError).always(finishRescheduleSave);
}
function rescheduleSaved(result) {
    setModalBusy('reschedule-modal', false);
    closeModal('reschedule-modal');
    toastr.success(result.message);
    table.ajax.reload(null, false);
}
function handleRescheduleError(xhr, status) {
    if (status === 'abort') return;
    const result = xhr.responseJSON;
    showRescheduleError(result && result.errors ? Object.values(result.errors).flat().join('\n') : result && result.message || 'Could not complete reschedule. Please try again.');
}
function finishRescheduleRequest() { setModalBusy('reschedule-modal', false); }
function finishRescheduleSave() { rescheduleSaving = false; setModalBusy('reschedule-modal', false); }
