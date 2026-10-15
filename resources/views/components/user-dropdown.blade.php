<div class="relative shrink-0" id="user-dropdown">
    <button type="button" id="user-dropdown-toggle" aria-expanded="false" aria-controls="user-dropdown-menu"
        class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">
        <span class="max-w-24 truncate sm:max-w-48">{{ Auth::user()->name }}</span>
        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
    </button>
    <div id="user-dropdown-menu" hidden class="absolute right-0 z-50 mt-2 hidden w-56 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
        <div class="border-b border-gray-100 px-4 py-3">
            <p class="truncate text-sm font-medium text-gray-800">{{ Auth::user()->name }}</p>
            <p class="mt-1 truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:bg-gray-100">{{ __('Profile') }}</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 focus:bg-gray-100">{{ __('Log Out') }}</button>
        </form>
    </div>
</div>
