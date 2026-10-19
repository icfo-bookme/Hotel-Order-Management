<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">{{ $title }}</h1>
    </x-slot>

    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">{{ $title }}</h2>
        <p class="mt-2 text-sm text-gray-500">{{ $title }} management has not been configured yet.</p>
    </div>
</x-app-layout>
