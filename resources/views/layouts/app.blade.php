<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">

    @stack('styles')
</head>

<body class="font-sans antialiased">
    <div id="app-shell" class="app-shell min-h-screen bg-gray-100">
        @include('layouts.navigation')

        <div class="app-content min-w-0">
            <header
                class="app-header fixed top-0 right-0 left-0 z-20 flex h-16 min-w-0 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
                <button id="sidebar-toggle" type="button" aria-expanded="true" aria-controls="app-sidebar"
                    aria-label="Toggle sidebar"
                    class="shrink-0 rounded-lg p-2 text-blue-950 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="truncate text-lg font-semibold text-gray-800">Hotel Order Management</h1>
                    @endisset
                </div>
                <x-user-dropdown />
            </header>

            <main id="main-content" aria-busy="true" class="relative isolate min-h-[calc(100dvh-4rem)] min-w-0">
                @include('components.global-loader')
                <div id="page-content" inert class="min-w-0 px-4 py-6 opacity-0 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    @stack('modals')

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
        function tableExportTitle() {
            return $('.app-header h1').text().trim() || document.title;
        }

        function formatTableExport(value) {
            const $content = $('<div>').html(String(value ?? ''));
            $content.find('br').replaceWith('\n');
            $content.find('p').append('\n');
            const text = $content.text().trim();
            return /^[=+@-]/.test(text) && !/^-?\d+(\.\d+)?$/.test(text) ? "'" + text : text;
        }

        const tableExportOptions = {
            columns: ':visible:not(.no-export)',
            format: {
                body: formatTableExport
            }
        };

        $.extend(true, $.fn.dataTable.defaults, {
            dom: '<"dt-toolbar"<"dt-toolbar-length"l><"dt-toolbar-buttons"B><"dt-toolbar-search"f>>rt<"dt-footer"ip>',
            pageLength: 10,
            searchDelay: 400,
            buttons: [{
                    extend: 'copyHtml5',
                    title: tableExportTitle,
                    exportOptions: tableExportOptions
                },
                {
                    extend: 'csvHtml5',
                    title: tableExportTitle,
                    exportOptions: tableExportOptions
                },
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    title: tableExportTitle,
                    exportOptions: tableExportOptions
                },
                {
                    extend: 'pdfHtml5',
                    title: tableExportTitle,
                    orientation: 'landscape',
                    pageSize: 'A3',
                    exportOptions: tableExportOptions
                },
                {
                    extend: 'print',
                    title: tableExportTitle,
                    exportOptions: tableExportOptions
                },
                {
                    extend: 'colvis',
                    text: 'Columns',
                    columns: ':not(.no-export)'
                }
            ]
        });
    </script>
    <script>
        const pageLoader = {
            finished: false,
            timeoutId: null,
            update() {
                if (!this.finished && !$('#page-content [data-page-loading][id]').length) this.finish();
            },
            finish(message = '') {
                if (this.finished) return;
                this.finished = true;
                clearTimeout(this.timeoutId);
                $('#page-content').removeClass('opacity-0').prop('inert', false);
                $('#main-content').attr('aria-busy', 'false');
                $('#global-loader').remove();
                if (message) $('<p>', {
                    role: 'alert',
                    class: 'mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700',
                    text: message
                }).prependTo('#page-content');
            },
            ready(element) {
                $(element).removeAttr('data-page-loading');
                this.update();
            }
        };

        function openModal(id, trigger = null) {
            $.appModal.open(id, trigger);
        }

        function closeModal(id) {
            $.appModal.close(id);
        }

        function setModalBusy(id, busy) {
            $.appModal.busy(id, busy);
        }

        function pageLoaderReady(element) {
            pageLoader.ready(element);
        }

        $(document).ready(function() {
            pageLoader.timeoutId = setTimeout(function() {
                pageLoader.finish('Page data could not finish loading. Please reload to try again.');
            }, 35000);
            pageLoader.update();
        });
        $(document.defaultView).on('pageshow', function() {
            pageLoader.update();
        });
    </script>
    @stack('scripts')
</body>

</html>
