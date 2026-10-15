@props(['id', 'title', 'active' => false])

<section data-sidebar-group data-open="{{ $active ? 'true' : 'false' }}" class="mt-3">
    <button type="button" aria-controls="{{ $id }}" aria-label="{{ $title }}" title="{{ $title }}"
        data-sidebar-group-toggle aria-expanded="{{ $active ? 'true' : 'false' }}"
        @class([
            'sidebar-link group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white',
            'bg-white/5 text-white ring-1 ring-inset ring-white/10' => $active,
            'text-blue-100 hover:bg-white/10 hover:text-white' => ! $active,
        ])>
        <span @class(['flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors', 'bg-blue-400/15 text-blue-200' => $active, 'bg-white/5 text-blue-200 group-hover:bg-white/10' => ! $active])>{{ $icon }}</span>
        <span class="sidebar-label min-w-0 flex-1 text-left">{{ $title }}</span>
        <svg data-sidebar-chevron class="sidebar-label h-4 w-4 shrink-0 text-blue-300 transition-transform duration-200 motion-reduce:transition-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
        </svg>
    </button>
    <div id="{{ $id }}" data-sidebar-submenu class="grid {{ $active ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0' }} transition-[grid-template-rows,opacity] duration-200 ease-in-out motion-reduce:transition-none">
        <div class="min-h-0 overflow-hidden">
            <div class="ml-7 mt-2 space-y-1 border-l border-blue-300/15 pb-1 pl-3">
                {{ $slot }}
            </div>
        </div>
    </div>
</section>
