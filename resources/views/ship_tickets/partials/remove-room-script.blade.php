{{-- blade-formatter-disable --}}
const $removeRoomForm = $('#remove-room-form');
let removableRooms = [], removeRoomSaving = false, removeRoomRequest = null;
$table.on('click', '.btn-remove-room', function() {
    const row = table.row($(this).closest('tr')).data();
    if (removeRoomRequest) removeRoomRequest.abort();
    $removeRoomForm[0].reset();
    removableRooms = [];
    $('#remove-room-select, #remove-room-details, #remove-room-preview').empty();
    $('#remove-room-error').addClass('hidden');
    openModal('remove-room-modal', this);
    setModalBusy('remove-room-modal', true);
    removeRoomRequest = $.getJSON(@json(url('/hotel/sales')) + '/' + row.id + '/removable-rooms')
        .done(function(result) {
            removableRooms = result.rooms;
            $.each(removableRooms, function(index, room) {
                $('<option>', {value: room.id, text: room.hotel_name + ' / ' + room.room_name}).appendTo('#remove-room-select');
            });
            selectRemovableRoom();
            if (!removableRooms.length) showRemoveRoomError('No rooms are available.');
        })
        .fail(removeRoomFailed)
        .always(function() { setModalBusy('remove-room-modal', false); });
});
function selectedRemovableRoom() {
    return removableRooms.find(function(room) { return String(room.id) === $('#remove-room-select').val(); });
}
function showRemoveRoomError(message) {
    $('#remove-room-error').text(message).removeClass('hidden');
}
function selectRemovableRoom() {
    const room = selectedRemovableRoom();
    if (!room) return;
    $('#remove-room-error').addClass('hidden');
    $('#remove-room-details').text('Room total: ' + room.customer_sell + ' | Partner total: ' + room.partner_share +
        ' | Hotel advance: ' + room.advance + ' | Paid to hotel: ' + room.hotel_paid);
    $removeRoomForm.find('[name="refund_amount"]').val(room.suggested_refund);
    $removeRoomForm.find('[name="hotel_refund_amount"]').val('0');
    if (room.last_room) showRemoveRoomError('Keep at least one room in the booking. This option removes an extra room.');
    updateRemoveRoomPreview();
}
function updateRemoveRoomPreview() {
    const room = selectedRemovableRoom();
    if (!room) return;
    const refund = amountToCents($removeRoomForm.find('[name="refund_amount"]').val());
    const recovery = amountToCents($removeRoomForm.find('[name="hotel_refund_amount"]').val());
    const advance = amountToCents(room.advance) - refund;
    const profit = amountToCents(room.remaining_sell) - amountToCents(room.remaining_partner);
    const target = advance - profit;
    const paid = amountToCents(room.hotel_paid);
    const minimum = paid > (target > 0n ? target : 0n) ? paid - (target > 0n ? target : 0n) : 0n;
    $('#remove-room-preview').text('Remaining hotel total: ' + room.remaining_sell + ' | Updated advance: ' + formatMoney(advance) +
        ' | Net paid to hotel: ' + formatMoney(paid - recovery) + ' | Minimum hotel refund required: ' + formatMoney(minimum));
}
$('#remove-room-select').on('change', selectRemovableRoom);
$removeRoomForm.on('input change', updateRemoveRoomPreview);
$removeRoomForm.on('submit', function(event) {
    event.preventDefault();
    const room = selectedRemovableRoom();
    if (removeRoomSaving || !room || room.last_room) return;
    const payload = {
        refund_amount: $removeRoomForm.find('[name="refund_amount"]').val() || '0',
        hotel_refund_amount: $removeRoomForm.find('[name="hotel_refund_amount"]').val() || '0',
        payment_method: $removeRoomForm.find('[name="payment_method"]').val(),
        transaction_reference: $removeRoomForm.find('[name="transaction_reference"]').val(),
        reason: $removeRoomForm.find('[name="reason"]').val(),
        confirmed: $removeRoomForm.find('[name="confirmed"]').prop('checked') ? 1 : 0,
    };
    removeRoomSaving = true;
    $('#remove-room-error').addClass('hidden');
    setModalBusy('remove-room-modal', true);
    $.ajax({url: @json(url('/hotel-booking-rooms')) + '/' + room.id + '/remove', method: 'POST', data: payload, dataType: 'json', timeout: 30000})
        .done(function(result) {
            setModalBusy('remove-room-modal', false);
            closeModal('remove-room-modal');
            toastr.success(result.message);
            if (result.sheet_warning) toastr.warning(result.sheet_warning);
            table.ajax.reload(null, false);
        })
        .fail(removeRoomFailed)
        .always(function() { removeRoomSaving = false; setModalBusy('remove-room-modal', false); });
});
function removeRoomFailed(xhr, status) {
    if (status === 'abort') return;
    const result = xhr.responseJSON;
    showRemoveRoomError(result && result.errors ? Object.values(result.errors).flat().join('\n') :
        (result && result.message) || 'Could not remove the room. Reopen the form to check its current status.');
}
{{-- blade-formatter-enable --}}
