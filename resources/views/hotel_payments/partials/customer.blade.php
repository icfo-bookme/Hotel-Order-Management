<div class="min-w-44 whitespace-normal text-sm">
    <p class="font-semibold text-gray-800">{{ $sale->customer_name }}</p>
    <p class="mt-2 text-gray-700"><span class="text-gray-500">Mobile:</span> {{ $sale->customer_mobile ?: '-' }}</p>
    <p class="mt-1 text-gray-700"><span class="text-gray-500">WhatsApp:</span> {{ $sale->whatsapp ?: '-' }}</p>
</div>
