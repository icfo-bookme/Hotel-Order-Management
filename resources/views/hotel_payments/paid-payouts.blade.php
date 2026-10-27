<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Paid Payouts</h1>
    </x-slot>
    <div class="ticket-table-container min-w-0 overflow-x-auto">
        <table id="paid-payouts-table" data-page-loading class="display nowrap w-full table-auto border border-gray-300">
            <thead>
                <tr class="bg-blue-950 text-white">
                    <th>Payout ID</th>
                    <th>Customer</th>
                    <th>Hotel</th>
                    <th>Amount Paid</th>
                    <th>Paid At</th>
                    <th>Payment Account</th>
                    <th>Reference</th>
                    <th class="no-export">Payment Screenshot</th>
                    <th>Recorded By</th>
                    <th>Remark</th>
                </tr>
            </thead>
        </table>
    </div>
    <x-modal id="paid-payout-screenshot-modal" title="Payment Screenshot" maxWidth="4xl" :hideFooter="true">
        <div class="flex justify-end">
            <a id="paid-payout-screenshot-download" download title="Download screenshot" aria-label="Download screenshot"
                class="hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 3v12m0 0 4-4m-4 4-4-4M5 16v4a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-4" />
                </svg>
            </a>
        </div>
        <p id="paid-payout-screenshot-status" role="status" class="py-6 text-center text-sm text-gray-500">Loading
            screenshot...</p>
        <img id="paid-payout-screenshot-image" alt="Payment screenshot enlarged"
            class="mx-auto hidden max-h-[70dvh] max-w-full rounded-lg object-contain" />
    </x-modal>
    @push('scripts')
        <script>
            $(document).ready(function initializePaidPayouts() {
                const $table = $('#paid-payouts-table');
                $table.on('xhr.dt', handlePaidPayoutResponse);
                const table = $table.DataTable({
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,
                    order: [
                        [0, 'desc']
                    ],
                    ajax: {
                        url: @json(route('hotel-payouts.paid.data')),
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
                            data: 'amount'
                        }, {
                            data: 'paid_at'
                        },
                        {
                            data: 'payment_account',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'transaction_reference',
                            defaultContent: '-'
                        },
                        {
                            data: 'screenshot',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'recorded_by',
                            orderable: false,
                            searchable: false
                        },
                        {
                            data: 'remark',
                            defaultContent: '-'
                        },
                    ],
                    initComplete: finishPaidPayoutLoading,
                });
                $(document.defaultView).on('resize', adjustPaidPayoutColumns);
                $table.on('click', '[data-view-payout-screenshot]', openPaidPayoutScreenshot);
                $('#paid-payout-screenshot-modal').on('modal:closed', clearPaidPayoutScreenshot);

                function openPaidPayoutScreenshot() {
                    clearPaidPayoutScreenshot();
                    $('#paid-payout-screenshot-download').attr('href', $(this).attr('data-view-payout-screenshot'));
                    $('#paid-payout-screenshot-status').text('Loading screenshot...').removeClass('hidden');
                    $('#paid-payout-screenshot-image').one('load', showPaidPayoutScreenshot).one('error',
                        handlePaidScreenshotError).attr('src', $(this).attr('data-view-payout-screenshot'));
                    openModal('paid-payout-screenshot-modal', this);
                }

                function showPaidPayoutScreenshot() {
                    $('#paid-payout-screenshot-status').addClass('hidden');
                    $('#paid-payout-screenshot-image').removeClass('hidden');
                    $('#paid-payout-screenshot-download').removeClass('hidden');
                }

                function handlePaidScreenshotError() {
                    $('#paid-payout-screenshot-status').text('Could not load this screenshot. Please try again.')
                        .removeClass('hidden');
                }

                function clearPaidPayoutScreenshot() {
                    $('#paid-payout-screenshot-download').removeAttr('href').addClass('hidden');
                    $('#paid-payout-screenshot-image').off('load error').removeAttr('src').addClass('hidden');
                }

                function finishPaidPayoutLoading() {
                    pageLoaderReady($table);
                }

                function handlePaidPayoutResponse(event, settings, json) {
                    if (json === null) {
                        finishPaidPayoutLoading();
                        toastr.error('Could not load paid payouts. Please reload.');
                        return true;
                    }
                }

                function adjustPaidPayoutColumns() {
                    table.columns.adjust();
                }
            });
        </script>
    @endpush
</x-app-layout>
