<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Sales &amp; Payments Report</h1>
    </x-slot>

    <div class="mb-5 rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
        <form id="report-filters" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm font-medium text-gray-700">Booking Date From
                <x-text-input id="report-date-from" name="date_from" type="date" required :value="$filters['date_from']" class="mt-1 block w-full text-sm" />
            </label>
            <label class="text-sm font-medium text-gray-700">Booking Date To
                <x-text-input id="report-date-to" name="date_to" type="date" required :value="$filters['date_to']" class="mt-1 block w-full text-sm" />
            </label>
            <label class="text-sm font-medium text-gray-700">Hotel
                <select name="hotel_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">All hotels</option>
                    @foreach ($hotels as $hotel)
                        <option value="{{ $hotel->bookme_hotel_id }}" @selected((string) ($filters['hotel_id'] ?? '') === (string) $hotel->bookme_hotel_id)>{{ $hotel->hotel_name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-3">
                <button type="submit" class="rounded-lg bg-blue-950 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900">Apply Filters</button>
                <a href="{{ route('reports.hotel-sales') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
            </div>
        </form>
        <p class="mt-4 text-xs leading-5 text-gray-500">Only sold bookings whose customer payments are all verified are included, filtered by booking date (Bangladesh time). Each row shows the full booking, including all its hotels. The hotel filter selects bookings containing that hotel. Payment and payout totals include all entries for the selected bookings; Received and Due use verified payments only.</p>
        <p id="report-error" role="alert" class="mt-3 hidden text-sm text-red-700"></p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['bookings' => 'Bookings', 'sell' => 'Customer Total', 'partner' => 'Partner Total', 'profit' => 'B2B Profit', 'received' => 'Verified Received', 'due' => 'Due / Hotel Received', 'paid' => 'Paid to Hotels'] as $key => $label)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-xs font-medium text-gray-500">{{ $label }}</p>
                <p data-report-total="{{ $key }}" class="mt-2 text-2xl font-semibold tabular-nums text-gray-900">—</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-gray-900">Booking Details</h2>
            <a id="report-export" href="{{ route('reports.hotel-sales.export', $filters) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Export All CSV</a>
        </div>
        <p class="mb-3 text-xs text-gray-500">Table export buttons use the current page. Export All CSV includes every matching booking.</p>
        <div class="ticket-table-container min-w-0 overflow-x-auto">
            <table id="hotel-report-table" data-page-loading class="display nowrap w-full text-sm">
                <thead><tr>
                    <th>Booking ID</th><th>Sale ID</th><th>Booking Date</th><th>Customer</th><th>Hotel / Rooms</th>
                    <th>Customer Total</th><th>Partner Total</th><th>B2B Profit</th><th>Verified Received</th>
                    <th>Due / Hotel Received</th><th>Paid to Hotels</th><th>Sold By</th>
                </tr></thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function initializeHotelReport() {
                const $table = $('#hotel-report-table');
                const $filters = $('#report-filters');
                const $error = $('#report-error');
                let appliedFilters = $filters.serializeArray();
                $table.on('xhr.dt', handleReportResponse);
                const table = $table.DataTable({
                    processing: true, serverSide: true, scrollX: true, autoWidth: false,
                    search: { search: @json($filters['q'] ?? '') },
                    language: { searchPlaceholder: 'Customer, mobile or WhatsApp' },
                    order: [[0, 'desc']],
                    ajax: { url: @json(route('reports.hotel-sales.data')), timeout: 30000, data: addReportFilters },
                    columns: [
                        { data: 'id', name: 'hotel_bookings.id', searchable: false }, { data: 'sales_id', name: 'hotel_bookings.sales_id', searchable: false },
                        { data: 'created_at', name: 'hotel_bookings.created_at', searchable: false },
                        { data: 'customer', orderable: false }, { data: 'hotels', orderable: false, searchable: false },
                        { data: 'sell', orderable: false, searchable: false }, { data: 'partner', orderable: false, searchable: false },
                        { data: 'profit', orderable: false, searchable: false }, { data: 'verified', orderable: false, searchable: false },
                        { data: 'due', orderable: false, searchable: false },
                        { data: 'payout', orderable: false, searchable: false }, { data: 'sold_by', orderable: false, searchable: false },
                    ],
                    initComplete: finishReportLoading,
                });
                $filters.on('submit', applyReportFilters);
                $('#report-export').on('click', prepareReportExport);
                $('#report-date-from').on('change', updateReportDateMinimum);
                $(document.defaultView).on('resize', adjustReportColumns);
                updateReportDateMinimum();

                function addReportFilters(data) {
                    $.each(appliedFilters, function appendReportFilter(index, field) { data[field.name] = field.value; });
                }
                function applyReportFilters(event) {
                    event.preventDefault();
                    appliedFilters = $filters.serializeArray();
                    $error.addClass('hidden');
                    table.ajax.reload();
                }
                function prepareReportExport() {
                    $(this).attr('href', @json(route('reports.hotel-sales.export')) + '?' + $.param([...appliedFilters, { name: 'q', value: table.search() }]));
                }
                function updateReportDateMinimum() { $('#report-date-to').attr('min', $('#report-date-from').val()); }
                function finishReportLoading() { pageLoaderReady($table); }
                function adjustReportColumns() { table.columns.adjust(); }
                function handleReportResponse(event, settings, json, xhr) {
                    if (json === null) {
                        finishReportLoading();
                        $('[data-report-total]').text('—');
                        const result = xhr.responseJSON;
                        $error.text(result && result.errors ? Object.values(result.errors).flat().join(' ') : 'Could not load this report. Please try again.').removeClass('hidden');
                        return true;
                    }
                    if (Number(json.draw) !== settings.iDraw) return;
                    $error.addClass('hidden');
                    $.each(json.summary, updateReportTotal);
                }
                function updateReportTotal(key, value) { $('[data-report-total="' + key + '"]').text(value); }
            });
        </script>
    @endpush
</x-app-layout>
