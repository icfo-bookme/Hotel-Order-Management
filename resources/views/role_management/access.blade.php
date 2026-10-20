<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-semibold text-gray-800">Your Access</h1>
    </x-slot>
    <div class="rounded-xl border border-gray-200 bg-white p-6">
        <h2 class="font-semibold text-gray-900">Welcome, {{ auth()->user()->name }}</h2>
        <p class="mt-2 text-sm text-gray-600">Use the sidebar to open the pages assigned to your role. Contact your
            administrator if you need more access.</p>
    </div>
</x-app-layout>
