{{-- blade-formatter-disable --}}
const $bedForm = $('#extra-bed-form');
let bedRooms = [], bedSaving = false;
$table.on('click', '.btn-extra-beds', function() {
    const row = table.row($(this).closest('tr')).data();
    bedRooms = [];
    $bedForm[0].reset();
    $('#extra-bed-room, #extra-bed-preview').empty();
    $('#extra-bed-error').addClass('hidden');
    openModal('extra-bed-modal', this);
    setModalBusy('extra-bed-modal', true);
    $.getJSON(@json(url('/hotel/sales')) + '/' + row.id + '/rooms')
        .done(function(result) {
            bedRooms = result.rooms;
            $.each(bedRooms, function(index, room) {
                $('<option>', {value: room.id, text: room.hotel_name + ' / ' + room.room_name}).appendTo('#extra-bed-room');
            });
            selectBedRoom();
        })
        .fail(bedRequestFailed)
        .always(function() { setModalBusy('extra-bed-modal', false); });
});
function selectedBedRoom() {
    return bedRooms.find(function(room) { return String(room.id) === $('#extra-bed-room').val(); });
}
function selectBedRoom() {
    const room = selectedBedRoom();
    $('#extra-bed-error').addClass('hidden');
    if (!room) return;
    $.each(['extra_bed_count', 'extra_bed_customer_rate', 'extra_bed_partner_rate'], function(index, field) {
        $bedForm.find('[name="' + field + '"]').val(room[field]);
    });
    if (room.blocked) $('#extra-bed-error').text('Rooms cannot be changed after a hotel payout has been recorded.').removeClass('hidden');
    updateBedPreview();
}
function updateBedPreview() {
    const room = selectedBedRoom();
    if (!room) return;
    const values = Object.assign({}, room);
    $.each($bedForm.serializeArray(), function(index, field) { values[field.name] = field.value; });
    const totals = calculateRoomTotals(values);
    const sell = totals.sell + BigInt(room.customer_fees);
    const partner = totals.partner + BigInt(room.partner_fees);
    $('#extra-bed-preview').text('Nights: ' + totals.nights + ' | Room + extra beds: ' + formatMoney(sell) +
        ' | Hotel total: ' + formatMoney(partner) + ' | Profit: ' + formatMoney(sell - partner));
}
$('#extra-bed-room').on('change', selectBedRoom);
$bedForm.on('input change', updateBedPreview);
$bedForm.on('submit', function(event) {
    event.preventDefault();
    const room = selectedBedRoom();
    if (bedSaving || !room || room.blocked) return;
    bedSaving = true;
    setModalBusy('extra-bed-modal', true);
    $('#extra-bed-error').addClass('hidden');
    $.ajax({url: @json(url('/hotel-booking-rooms')) + '/' + room.id, method: 'PATCH', data: $bedForm.serialize(), dataType: 'json', timeout: 30000})
        .done(function(result) {
            setModalBusy('extra-bed-modal', false);
            closeModal('extra-bed-modal');
            toastr.success(result.message);
            if (result.sheet_warning) toastr.warning(result.sheet_warning);
            table.ajax.reload(null, false);
        })
        .fail(bedRequestFailed)
        .always(function() { bedSaving = false; setModalBusy('extra-bed-modal', false); });
});
function bedRequestFailed(xhr) {
    const result = xhr.responseJSON;
    $('#extra-bed-error').text(result && result.errors ? Object.values(result.errors).flat().join(' ') :
        (result && result.message) || 'Could not update extra beds. Please reopen the form and try again.').removeClass('hidden');
}
{{-- blade-formatter-enable --}}
