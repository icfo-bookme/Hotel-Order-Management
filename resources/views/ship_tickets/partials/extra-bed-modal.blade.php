<x-modal id="extra-bed-modal" title="Extra Beds" formId="extra-bed-form" submitText="Save Extra Beds">
    <form id="extra-bed-form" class="space-y-4 text-sm">
        <p>Rates are per bed, per night, for the entire room stay. Set the count to zero for no extra beds.</p>
        <p id="extra-bed-error" role="alert" class="hidden rounded-lg bg-red-50 p-3 text-red-700"></p>
        <label class="block font-medium">Hotel / Room
            <select id="extra-bed-room" required class="mt-1 w-full rounded-md border-gray-300"></select>
        </label>
        <label class="block font-medium">Extra Beds
            <input name="extra_bed_count" type="number" min="0" max="50" step="1" value="0" required class="mt-1 w-full rounded-md border-gray-300" />
        </label>
        @foreach (['extra_bed_customer_rate' => 'Customer Rate / Bed / Night', 'extra_bed_partner_rate' => 'Hotel Rate / Bed / Night'] as $field => $label)
            <label class="block font-medium">{{ $label }}
                <input name="{{ $field }}" type="number" min="0" max="9999999999.99" step="0.01" value="0" required class="mt-1 w-full rounded-md border-gray-300" />
            </label>
        @endforeach
        <p id="extra-bed-preview" role="status" class="rounded-lg bg-gray-50 p-3"></p>
    </form>
</x-modal>
