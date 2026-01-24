<div class="w-[90%] mx-auto mt-6 flex gap-3">

    {{-- Pending --}}
    <a href="{{ url('/hotel/pending') }}"
       class="px-6 py-2 rounded-md font-semibold text-white
       {{ request()->is('hotel/pending') ? 'bg-blue-950' : 'bg-gray-600 hover:bg-gray-700' }}">
        Pending
    </a>

    {{-- Feedbacked --}}
    <a href="{{ url('/hotel/feedbacked') }}"
       class="px-6 py-2 rounded-md font-semibold text-white
       {{ request()->is('hotel/feedbacked') ? 'bg-blue-950' : 'bg-gray-600 hover:bg-gray-700' }}">
        Feedbacked
    </a>

    {{-- Booked --}}
    <a href="{{ url('/hotel/booked') }}"
       class="px-6 py-2 rounded-md font-semibold text-white
       {{ request()->is('hotel/booked') ? 'bg-blue-950' : 'bg-gray-600 hover:bg-gray-700' }}">
        Booked
    </a>

</div>
