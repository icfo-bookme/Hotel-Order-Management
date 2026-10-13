<div x-cloak x-show="sidebarOpen" x-transition.opacity @click="closeSidebar()" class="fixed inset-0 z-30 bg-gray-950/50 lg:hidden" aria-hidden="true"></div>
<aside x-ref="sidebar" @keydown.tab="trapFocus($event)" id="app-sidebar" aria-label="Main navigation" :inert="!desktop && !sidebarOpen" class="fixed inset-y-0 left-0 z-40 app-sidebar flex flex-col bg-blue-950 text-white">
    <div class="sidebar-brand flex h-16 shrink-0 items-center justify-between gap-2 overflow-hidden border-b border-white/10 px-4">
        <a href="{{ route('dashboard') }}" aria-label="Hotel Order Management dashboard" class="flex min-w-0 items-center gap-3 rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-white" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 21V3h14v18M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1m-5 10v-6h4v6" /></svg>
            </span>
            <span class="sidebar-label min-w-0 truncate text-sm font-semibold leading-5">Hotel Order<br><span class="font-normal text-blue-200">Management</span></span>
        </a>
        <button x-ref="sidebarClose" type="button" @click="closeSidebar()" class="shrink-0 rounded-md p-2 text-blue-200 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white lg:hidden" aria-label="Close navigation">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M6 18 18 6" /></svg>
        </button>
    </div>
    <nav class="sidebar-nav flex-1 overflow-y-auto px-4 py-6">
        <p class="sidebar-heading mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-blue-300">Workspace</p>
        <div class="space-y-2">
            <x-sidebar-link label="Dashboard" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 10 9-7 9 7v10H15v-7H9v7H3z" /></svg>
                <span class="sidebar-label">Dashboard</span>
            </x-sidebar-link>
            <x-sidebar-link label="Pending" :href="route('ship-tickets.index', 'pending')" :active="request()->routeIs('ship-tickets.index') && strtolower((string) request()->route('status')) === 'pending'">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M12 7v5l3 2" /></svg>
                <span class="sidebar-label">Pending</span>
            </x-sidebar-link>
            <x-sidebar-link label="Feedbacked" :href="route('ship-tickets.index', 'feedbacked')" :active="request()->routeIs('ship-tickets.index') && strtolower((string) request()->route('status')) === 'feedbacked'">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 0 1-9 9H3v-9a9 9 0 0 1 18 0ZM8 12l3 3 5-6" /></svg>
                <span class="sidebar-label">Feedbacked</span>
            </x-sidebar-link>
        </div>
        <p class="sidebar-heading mb-3 mt-8 px-3 text-xs font-semibold uppercase tracking-wider text-blue-300">Account</p>
        <x-sidebar-link label="Profile" :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><circle cx="12" cy="8" r="4" stroke-width="1.8" /><path stroke-linecap="round" stroke-width="1.8" d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg>
            <span class="sidebar-label">Profile</span>
        </x-sidebar-link>
    </nav>
    <div class="sidebar-footer shrink-0 border-t border-white/10 p-4">
        <div class="sidebar-user mb-4 px-3">
            <p class="truncate text-sm font-semibold">{{ Auth::user()->name }}</p>
            <p class="mt-1 truncate text-xs text-blue-200">{{ Auth::user()->email }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="Log Out" title="Log Out" class="sidebar-logout flex w-full items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-blue-100 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H5v14h4m5-14 7 7-7 7m-5-7h12" /></svg>
                <span class="sidebar-label">{{ __('Log Out') }}</span>
            </button>
        </form>
    </div>
</aside>
