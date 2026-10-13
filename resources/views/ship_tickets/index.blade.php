<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">{{ ucfirst($status) }} Tickets</h1>
    </x-slot>

    <!-- ✅ Loader -->
    <div id="loader" class="fixed inset-0 z-50 hidden flex items-center justify-center
            ">

        <div class="flex flex-col items-center space-y-3">
            <div
                class="w-10 h-10 border-4 border-blue-600 border-t-transparent
                    rounded-full animate-spin">
            </div>

            <p class="text-sm text-gray-900 tracking-wide">
                Processing...
            </p>
        </div>
    </div>


    <!-- =========================
        ADD FEEDBACK MODAL
    ========================== -->
    <div id="remarks-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="mx-4 max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-4 sm:p-6">
            <h2 class="text-xl font-semibold mb-4">Add Feedback</h2>

            <input type="hidden" id="ticket_id">

            <textarea id="remarks-text" class="w-full border rounded px-3 py-2" rows="5" placeholder="Enter feedback..."></textarea>

            <div class="mt-4 flex justify-end">
                <button id="close-modal" class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded mr-2">
                    Cancel
                </button>
                <button id="save-remarks" class="bg-blue-600 hover:bg-blue-800 text-white px-4 py-2 rounded">
                    Save
                </button>
            </div>
        </div>
    </div>

    <!-- =========================
        EDIT FEEDBACK MODAL
    ========================== -->
    <div id="edit-feedback-modal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="mx-4 max-h-[90dvh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-4 sm:p-6">
            <h2 class="text-xl font-semibold mb-4">Edit Feedback</h2>

            <input type="hidden" id="edit_ticket_id">

            <textarea id="edit-feedback-text" class="w-full border rounded px-3 py-2" rows="5" placeholder="Edit feedback..."></textarea>

            <div class="mt-4 flex justify-end">
                <button id="close-edit-modal" class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded mr-2">
                    Cancel
                </button>
                <button id="update-feedback" class="bg-yellow-600 hover:bg-yellow-800 text-white px-4 py-2 rounded">
                    Update
                </button>
            </div>
        </div>
    </div>

    <!-- =========================
        DATATABLE
    ========================== -->
    <div class="min-w-0">
        <div class="ticket-table-container min-w-0 overflow-x-auto">
            <table id="tickets-table" class="display nowrap w-full table-auto border border-gray-300">
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

                        <th class="text-center">Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {

                /* =========================
                    CSRF
                ========================== */
                $.ajaxSetup({
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                    },
                });

                /* =========================
                    DATATABLE INIT
                ========================== */
                let table = $('#tickets-table').DataTable({
                    serverSide: true,
                    scrollX: true,
                    autoWidth: false,

                    ajax: {
                        url: "{{ route('ship-tickets.data', $status) }}",
                        beforeSend: () => $('#loader').removeClass('hidden'),
                        complete: () => $('#loader').addClass('hidden')
                    },

                    columns: [{
                            data: 'id'
                        },
                        {
                            data: 'customer_name'
                        },
                        {
                            data: 'customer_mobile'
                        },
                        {
                            data: 'whatsapp'
                        },

                        @if ($status === 'pending')
                            {
                                data: 'journey_date'
                            },
                        @elseif ($status === 'feedbacked') {
                                data: 'remark_text',
                                render: function(data) {
                                    return `
                                <div class="max-w-[320px] whitespace-normal break-words">
                                    ${data ?? ''}
                                </div>
                            `;
                                }
                            }, {
                                data: 'feedback_by'
                            },
                        @endif

                        {
                            data: 'action',
                            orderable: false,
                            searchable: false
                        }
                    ]
                });

                const content = document.querySelector('.app-content');
                if (content && window.ResizeObserver) {
                    let resizeTimer;
                    const observer = new ResizeObserver(() => {
                        clearTimeout(resizeTimer);
                        resizeTimer = setTimeout(() => table.columns.adjust(), 250);
                    });
                    observer.observe(content);
                }

                /* =========================
                    ADD FEEDBACK
                ========================== */
                $('#tickets-table tbody').on('click', '.btn-remarks', function() {
                    let data = table.row($(this).closest('tr')).data();
                    $('#ticket_id').val(data.id);
                    $('#remarks-text').val('');
                    $('#remarks-modal').removeClass('hidden');
                });

                $('#close-modal').click(() => $('#remarks-modal').addClass('hidden'));

                $('#remarks-modal').click(function(e) {
                    if ($(e.target).is(this)) $(this).addClass('hidden');
                });

                $('#save-remarks').click(function() {
                    let ticketId = $('#ticket_id').val();
                    let remark = $('#remarks-text').val().trim();

                    if (!remark) {
                        alert('Please write feedback!');
                        return;
                    }

                    $.post("{{ route('remarks.save') }}", {
                        ticket_id: ticketId,
                        remark_text: remark
                    }, function(res) {
                        toastr.success(res.message || 'Saved');
                        $('#remarks-modal').addClass('hidden');
                        table.ajax.reload(null, false);
                    });
                });

                $('#tickets-table tbody').on('click', '.btn-edit', function() {
                    let data = table.row($(this).closest('tr')).data();

                    $('#edit_ticket_id').val(data.id);
                    $('#edit-feedback-text').val(data.remark_text);

                    $('#edit-feedback-modal').removeClass('hidden');
                });

                $('#close-edit-modal').click(() => $('#edit-feedback-modal').addClass('hidden'));

                $('#edit-feedback-modal').click(function(e) {
                    if ($(e.target).is(this)) $(this).addClass('hidden');
                });

                $('#update-feedback').click(function() {
                    let ticketId = $('#edit_ticket_id').val();
                    let feedback = $('#edit-feedback-text').val().trim();

                    if (!feedback) {
                        alert('Feedback cannot be empty!');
                        return;
                    }

                    $.post("{{ route('remarks.update') }}", {
                        ticket_id: ticketId,
                        remark_text: feedback
                    }, function(res) {
                        toastr.success(res.message || 'Updated');
                        $('#edit-feedback-modal').addClass('hidden');
                        table.ajax.reload(null, false);
                    });
                });

            });
        </script>
    @endpush

</x-app-layout>
