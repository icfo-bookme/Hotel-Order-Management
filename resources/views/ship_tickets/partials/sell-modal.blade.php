<x-modal id="sell-modal" title="Book Hotel" maxWidth="4xl" formId="sell-form" submitId="submit-sell" submitText="Sold"
    data-sell-url="{{ route('hotel-sales.sell', ['shipTicketSale' => '__SALE__']) }}"
    data-hotels-url="{{ route('hotel-options.index') }}">
    <x-slot name="subtitle">
        <p id="sell-customer" class="mt-1 text-sm text-gray-500"></p>
    </x-slot>
    <form id="sell-form" class="space-y-5"
        data-rooms-url="{{ route('hotel-options.rooms', ['hotelId' => '__HOTEL__']) }}">
        <p id="sell-error" role="alert"
            class="hidden whitespace-pre-line rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Rooms</h3><button type="button" id="add-sell-room"
                    class="text-sm font-semibold text-blue-700">+ Add Room</button>
            </div>
            <div id="sell-rooms" class="space-y-3"></div>
            <div id="hotel-list-error" role="alert" class="mt-2 hidden text-sm text-red-700">
                Hotel list could not be loaded.
                <button id="retry-hotel-list" type="button" class="font-semibold underline">Try again</button>
            </div>
        </section>
        @can('payments.create')
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Payments</h3>
                        <p class="text-xs text-gray-500">Optional. Add each payment separately.</p>
                    </div><button type="button" id="add-sell-payment" class="text-sm font-semibold text-blue-700">+ Add
                        Payment</button>
                </div>
                <div id="sell-payments" class="space-y-3"></div>
            </section>
        @endcan
        <div><x-input-label for="sell-remark" value="Remark" />
            <textarea id="sell-remark" name="remark" rows="2" maxlength="5000"
                class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
        </div>
        <section id="sell-hotel-advance-section" class="hidden rounded-lg border border-gray-200 p-4">
            <h3 class="font-semibold text-gray-900">Advance Received by Hotel</h3>
            <p class="mt-1 text-xs text-gray-500">Split the received payment across hotels. The total must match
                Received.</p>
            <div id="sell-hotel-advances" class="mt-3 grid gap-3 sm:grid-cols-2"></div>
            <p id="sell-advance-summary" aria-live="polite" class="mt-2 text-xs text-gray-600"></p>
        </section>
        <div class="grid grid-cols-2 gap-3 rounded-lg bg-gray-50 p-4 text-sm sm:grid-cols-4">
            <div>Total Sell<strong id="sell-total" class="block text-lg">0.00</strong></div>
            <div>Profit<strong id="sell-profit" class="block text-lg">0.00</strong></div>
            <div>Received<strong id="sell-received" class="block text-lg">0.00</strong></div>
            <div>Due<strong id="sell-due" class="block text-lg">0.00</strong></div>
        </div>
    </form>
    <template id="sell-room-template">
        <div data-room-row class="rounded-lg border border-gray-200 p-4">
            <div class="mb-3 flex justify-between"><span class="text-sm font-semibold">Room</span><button type="button"
                    data-remove-row class="text-xs font-semibold text-red-600">Remove</button></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block min-w-0 text-sm font-medium text-gray-700">Hotel Name
                    <select data-field="hotel_name" data-hotel-select required aria-label="Hotel Name"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Search and select a hotel</option>
                    </select>
                </label>
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700">Room Name
                        <select data-field="room_name" data-room-select required disabled aria-label="Room Name"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select a hotel first</option>
                        </select>
                    </label>
                    <p data-room-error role="alert" class="mt-1 hidden text-xs text-red-700">Could not load rooms.
                        <button type="button" data-retry-rooms class="font-semibold underline">Try again</button>
                    </p>
                </div>
                @foreach (['check_in_date' => 'Check-in', 'check_out_date' => 'Check-out', 'customer_price_per_night' => 'Customer Price / Night', 'partner_price_per_night' => 'Partner Price / Night'] as $field => $label)
                    <label class="block text-sm font-medium text-gray-700">{{ $label }}
                        <x-text-input data-field="{{ $field }}" class="mt-1 block w-full text-sm" required
                            type="{{ str_ends_with($field, '_date') ? 'date' : (str_ends_with($field, '_per_night') ? 'number' : 'text') }}"
                            :attributes="new \Illuminate\View\ComponentAttributeBag(
                                str_ends_with($field, '_per_night')
                                    ? ['min' => '0', 'max' => '9999999999.99', 'step' => '0.01']
                                    : (str_ends_with($field, '_name')
                                        ? ['maxlength' => '255']
                                        : []),
                            )" />
                    </label>
                @endforeach
                <label class="block text-sm font-medium text-gray-700">Extra Beds
                    <x-text-input data-field="extra_bed_count" type="number" min="0" max="50" step="1" value="0" required class="mt-1 block w-full text-sm" />
                </label>
                @foreach (['extra_bed_customer_rate' => 'Extra Bed Customer Rate / Night', 'extra_bed_partner_rate' => 'Extra Bed Hotel Rate / Night'] as $field => $label)
                    <label class="block text-sm font-medium text-gray-700">{{ $label }}
                        <x-text-input data-field="{{ $field }}" type="number" min="0" max="9999999999.99" step="0.01" value="0" required class="mt-1 block w-full text-sm" />
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500">Extra bed rates are per bed, per night, for the entire stay. Totals below include extra beds.</p>
            <div class="mt-3 flex flex-wrap gap-4 rounded bg-gray-50 p-3 text-sm text-gray-600" aria-live="polite">
                <span>Nights: <strong data-room-nights>0</strong></span>
                <span>Customer Total: <strong data-room-sell>0.00</strong></span>
                <span>Partner Total: <strong data-room-partner>0.00</strong></span>
            </div>
        </div>
    </template>
    <template id="sell-payment-template">
        <div data-payment-row class="rounded-lg border border-gray-200 p-4">
            <div class="mb-3 flex justify-between"><span class="text-sm font-semibold">Payment</span><button
                    type="button" data-remove-row class="text-xs font-semibold text-red-600">Remove</button></div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="text-sm font-medium">Amount<x-text-input data-field="amount" type="number"
                        min="0.01" max="9999999999.99" step="0.01" required
                        class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-medium">Other Fee<x-text-input data-field="other_fee" type="number"
                        min="0" max="9999999999.99" step="0.01" value="0.00" required
                        class="mt-1 block w-full text-sm" /><span
                        class="mt-1 block text-xs font-normal text-gray-500">Enter the payment fee manually, if
                        any.</span></label>
                <label class="text-sm font-medium">Payment Method<select data-field="payment_method" required
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        <option value="">Select method</option>
                        @foreach (['cash' => 'Cash', 'bank' => 'Bank', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select></label>
                <label class="text-sm font-medium">Paid At<x-text-input data-field="paid_at" type="datetime-local"
                        required class="mt-1 block w-full text-sm" /></label>
                <label class="text-sm font-medium">Transaction Reference<x-text-input
                        data-field="transaction_reference" maxlength="255"
                        class="mt-1 block w-full text-sm" /></label>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium">Payment Screenshot<input type="file"
                            data-payment-attachment accept="image/jpeg,image/png,image/webp"
                            class="mt-1 block w-full rounded-lg border border-gray-300 p-2 text-sm" /></label>
                    <p class="mt-1 text-xs text-gray-500">Optional. JPG, PNG or WebP, up to 5 MB.</p>
                    <div data-attachment-preview class="mt-2 hidden"><img alt="Payment screenshot preview"
                            class="max-h-40 rounded-lg border border-gray-200" /><button type="button"
                            data-remove-attachment class="mt-1 text-xs font-semibold text-red-600">Remove
                            Screenshot</button></div>
                </div>
            </div>
        </div>
    </template>

</x-modal>
