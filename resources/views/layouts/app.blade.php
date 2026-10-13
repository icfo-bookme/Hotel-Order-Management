<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
    <!-- Datatables CSS CDN -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    @stack('styles')
</head>

<body class="font-sans antialiased">
    <div x-data="appNavigation" @keydown.escape.window="if (sidebarOpen) closeSidebar()"
        @resize.window.debounce.150ms="updateViewport()"
        :class="{ 'sidebar-collapsed': sidebarCollapsed, 'sidebar-open': sidebarOpen }"
        class="app-shell min-h-screen bg-gray-100">
        @include('layouts.navigation')

        <div class="app-content min-w-0" :inert="sidebarOpen && !desktop">
            <header class="app-header fixed top-0 right-0 left-0 z-20 flex h-16 min-w-0 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
                <button x-ref="menuToggle" type="button" @click="toggleSidebar()"
                    :aria-expanded="String(desktop ? !sidebarCollapsed : sidebarOpen)" aria-controls="app-sidebar"
                    :aria-label="desktop ? (sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar') : 'Open navigation'"
                    class="shrink-0 rounded-lg p-2 text-blue-950 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="truncate text-lg font-semibold text-gray-800">Hotel Order Management</h1>
                    @endisset
                </div>
                <span class="hidden max-w-48 truncate text-sm text-gray-500 sm:block">{{ Auth::user()->name }}</span>
            </header>

        <!-- Page Content -->
        <main id="main-content" class="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
        </div>
    </div>

    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Datatables JS CDN -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- Datatables Buttons -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    @stack('scripts')
</body>

</html>
