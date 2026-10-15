<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Hotel Payment Accounts</h1>
    </x-slot>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    @endpush
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">Manage accounts used to pay hotels their partner share.</p>
        <button id="add-account" type="button" class="rounded-lg bg-blue-950 px-4 py-2 text-sm font-semibold text-white">+ Add Account</button>
    </div>
    <div class="ticket-table-container overflow-x-auto rounded-lg bg-white p-4">
        <table id="accounts-table" data-page-loading class="display nowrap w-full">
            <thead><tr><th>ID</th><th>Hotel</th><th>Method</th><th>Account Name</th><th>Account Number</th><th>Bank</th><th>Branch</th><th>Routing Number</th><th>Default</th><th>Status</th><th>Action</th></tr></thead>
        </table>
    </div>
    <x-modal id="account-modal" title="Hotel Payment Account" formId="account-form" submitId="save-account" maxWidth="2xl">
        <form id="account-form" class="space-y-4">
            <p id="account-error" role="alert" class="hidden whitespace-pre-line rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
            <div>
                <label for="account-hotel" class="block text-sm font-medium text-gray-700">Hotel</label>
                <select id="account-hotel" required class="mt-1 w-full rounded-md border-gray-300"><option value="">Search and select a hotel</option></select>
                <button id="retry-account-hotels" type="button" class="mt-2 hidden text-sm font-semibold text-blue-700 underline">Retry loading hotels</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">Payment Method<select id="account-method" name="payment_method" required class="mt-1 block w-full rounded-md border-gray-300 text-sm"><option value="bank">Bank</option><option value="bkash">bKash</option><option value="nagad">Nagad</option></select></label>
                <label class="text-sm font-medium">Account Name<x-text-input name="account_name" required maxlength="255" class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-medium sm:col-span-2">Account / Mobile Number<x-text-input name="account_number" required maxlength="100" class="mt-1 block w-full text-sm" /></label>
            </div>
            <div id="account-bank-fields" class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">Bank Name<x-text-input name="bank_name" maxlength="255" class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-medium">Branch Name<x-text-input name="branch_name" maxlength="255" class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-medium">Routing Number<x-text-input name="routing_number" maxlength="50" class="mt-1 block w-full text-sm" /></label>
            </div>
            <div class="flex flex-wrap gap-5 text-sm">
                <label class="flex items-center gap-2"><input id="account-default" type="checkbox" class="rounded border-gray-300">Default for this hotel</label>
                <label class="flex items-center gap-2"><input id="account-active" type="checkbox" checked class="rounded border-gray-300">Active</label>
            </div>
        </form>
    </x-modal>
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
        <script>
            $(document).ready(function initializeHotelAccounts() {
                const $table = $('#accounts-table'), $modal = $('#account-modal'), $form = $('#account-form');
                const $hotel = $('#account-hotel'), $error = $('#account-error');
                let editingId = null, hotels = null, loadingHotels = false, saving = false;

                $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
                $table.on('xhr.dt', handleTableResponse);
                const table = $table.DataTable({
                    serverSide: true, processing: true, scrollX: true, autoWidth: false,
                    ajax: { url: @json(route('hotel-payment-accounts.data')), timeout: 30000 },
                    columns: [
                        { data: 'id' }, { data: 'hotel_name', render: $.fn.dataTable.render.text() },
                        { data: 'payment_method', render: renderPaymentMethod },
                        { data: 'account_name', render: $.fn.dataTable.render.text() },
                        { data: 'account_number', render: $.fn.dataTable.render.text() },
                        { data: 'bank_name', defaultContent: '-', render: $.fn.dataTable.render.text() },
                        { data: 'branch_name', defaultContent: '-', render: $.fn.dataTable.render.text() },
                        { data: 'routing_number', defaultContent: '-', render: $.fn.dataTable.render.text() },
                        { data: 'is_default', render: renderDefault }, { data: 'is_active', render: renderActive },
                        { data: null, orderable: false, searchable: false, render: renderAccountAction },
                    ], initComplete: finishTableLoading,
                });
                $hotel.select2({ dropdownParent: $modal, width: '100%', placeholder: 'Search and select a hotel' });
                $('#add-account').on('click', openNewAccount);
                $table.on('click', '[data-edit-account]', openEditAccount);
                $('#account-method').on('change', toggleBankFields);
                $('#retry-account-hotels').on('click', loadHotels);
                $modal.on('select2:open', focusHotelSearch).on('modal:closed', closeHotelSearch);
                $form.on('submit', saveAccount);

                function renderPaymentMethod(value) { return { bank: 'Bank', bkash: 'bKash', nagad: 'Nagad' }[value] || '-'; }
                function renderDefault(value) { return value ? 'Default' : '-'; }
                function renderActive(value) { return value ? 'Active' : 'Inactive'; }
                function renderAccountAction() {
                    return '<button type="button" data-edit-account title="Edit Account" aria-label="Edit Account" class="rounded-lg bg-blue-50 p-2 text-blue-700"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15z" /></svg></button>';
                }
                function finishTableLoading() { pageLoaderReady($table); }
                function handleTableResponse(event, settings, json) {
                    if (json === null) { finishTableLoading(); toastr.error('Could not load accounts. Please reload.'); return true; }
                }
                function focusHotelSearch() { $modal.find('.select2-search__field').trigger('focus'); }
                function closeHotelSearch() { $hotel.select2('close'); }
                function resetAccountForm() {
                    $form.trigger('reset');
                    $error.empty().addClass('hidden');
                    $('#retry-account-hotels').addClass('hidden');
                    $hotel.empty().append(new Option('Search and select a hotel', '', true, true));
                }
                function openNewAccount() {
                    editingId = null;
                    resetAccountForm(); populateHotels(); toggleBankFields();
                    openModal('account-modal', this); loadHotels();
                }
                function openEditAccount() {
                    const row = table.row($(this).closest('tr')).data();
                    editingId = row.id;
                    resetAccountForm();
                    $hotel.append(new Option(row.hotel_name, row.bookme_hotel_id, true, true)).prop('disabled', true).trigger('change');
                    $.each(row, fillAccountField);
                    $('#account-default').prop('checked', row.is_default);
                    $('#account-active').prop('checked', row.is_active);
                    toggleBankFields(); openModal('account-modal', this);
                }
                function fillAccountField(field, value) {
                    $form.find('[name]').filter(function matchField() { return this.name === field; }).val(value ?? '');
                }
                function toggleBankFields() {
                    const bank = $('#account-method').val() === 'bank';
                    $('#account-bank-fields').toggleClass('hidden', !bank).find('input').prop('disabled', !bank);
                    $form.find('[name="bank_name"]').prop('required', bank);
                }
                function populateHotels() {
                    if (editingId || saving) return;
                    $hotel.empty().append(new Option(loadingHotels ? 'Loading hotels...' : 'Search and select a hotel', '', true, true));
                    $.each(hotels || [], appendHotelOption);
                    $hotel.prop('disabled', hotels === null).trigger('change');
                }
                function appendHotelOption(index, hotel) { $hotel.append(new Option(hotel.text, hotel.id)); }
                function loadHotels() {
                    if (hotels !== null || loadingHotels || editingId) return;
                    loadingHotels = true;
                    $('#retry-account-hotels').addClass('hidden'); $error.addClass('hidden'); populateHotels();
                    $.ajax({ url: @json(route('hotel-options.index')), dataType: 'json', timeout: 20000 })
                        .done(handleHotelsLoaded).fail(handleHotelsFailed).always(finishHotelsLoading);
                }
                function handleHotelsLoaded(response) { hotels = response.results; }
                function handleHotelsFailed() {
                    if (editingId) return;
                    $error.text('Could not load hotels. Please try again.').removeClass('hidden');
                    $('#retry-account-hotels').removeClass('hidden');
                }
                function finishHotelsLoading() { loadingHotels = false; populateHotels(); }
                function saveAccount(event) {
                    event.preventDefault();
                    if (saving) return;
                    if (!editingId && !$hotel.val()) { $error.text('Please select a hotel.').removeClass('hidden'); return; }
                    const data = {};
                    $.each($form.serializeArray(), collectAccountField);
                    function collectAccountField(index, field) { data[field.name] = field.value; }
                    if (!editingId) data.bookme_hotel_id = $hotel.val();
                    data.is_default = $('#account-default').prop('checked') ? 1 : 0;
                    data.is_active = $('#account-active').prop('checked') ? 1 : 0;
                    saving = true; setModalBusy('account-modal', true); $error.addClass('hidden');
                    const states = [];
                    $form.find('input, select, button').each(function lockAccountInput() {
                        states.push({ element: this, disabled: $(this).prop('disabled') }); $(this).prop('disabled', true);
                    });
                    $.ajax({
                        url: editingId ? @json(url('/hotel-payment-accounts')) + '/' + editingId : @json(route('hotel-payment-accounts.store')),
                        method: editingId ? 'PUT' : 'POST', data, dataType: 'json', timeout: 30000,
                    }).done(handleAccountSaved).fail(handleAccountFailed).always(restoreAccountForm);
                    function restoreAccountForm() {
                        $.each(states, function restoreAccountInput(index, state) { $(state.element).prop('disabled', state.disabled); });
                        saving = false; setModalBusy('account-modal', false);
                    }
                }
                function handleAccountSaved(response) {
                    setModalBusy('account-modal', false); closeModal('account-modal');
                    toastr.success(response.message); table.ajax.reload(null, false);
                }
                function handleAccountFailed(xhr) {
                    const response = xhr.responseJSON;
                    $error.text(response && response.errors ? Object.values(response.errors).flat().join('\n') : response && response.message || 'Could not save this account. Please try again.').removeClass('hidden');
                }
            });
        </script>
    @endpush
</x-app-layout>
