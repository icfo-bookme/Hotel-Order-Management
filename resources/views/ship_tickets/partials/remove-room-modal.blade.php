<x-modal id="remove-room-modal" title="Remove / Refund Room" formId="remove-room-form" submitText="Remove Room">
    <form id="remove-room-form" class="space-y-4 text-sm">
        <p>Remove one room and keep the remaining rooms. Enter only money already refunded or recovered.</p>
        <p id="remove-room-error" role="alert" class="hidden whitespace-pre-line rounded-lg bg-red-50 p-3 text-red-700"></p>
        <label class="block font-medium">Hotel / Room
            <select id="remove-room-select" required class="mt-1 w-full rounded-md border-gray-300"></select>
        </label>
        <p id="remove-room-details" class="rounded-lg bg-gray-50 p-3"></p>
        <label class="block font-medium">Refund Paid to Customer
            <input name="refund_amount" type="number" min="0" max="9999999999.99" step="0.01" required value="0"
                @disabled(!auth()->user()->can('payments.create')) class="mt-1 w-full rounded-md border-gray-300" />
        </label>
        <label class="block font-medium">Customer Refund Method
            <select name="payment_method" class="mt-1 w-full rounded-md border-gray-300">
                <option value="cash">Cash</option><option value="bank">Bank</option><option value="bkash">bKash</option>
                <option value="nagad">Nagad</option><option value="card">Card</option><option value="other">Other</option>
            </select>
        </label>
        <label class="block font-medium">Refund Received from Hotel
            <input name="hotel_refund_amount" type="number" min="0" max="9999999999.99" step="0.01" required value="0"
                @disabled(!auth()->user()->can('payouts.create')) class="mt-1 w-full rounded-md border-gray-300" />
        </label>
        <p class="text-xs text-gray-500">Hotel refunds reverse the original payout account. Leave zero if no money was returned.</p>
        <p id="remove-room-preview" role="status" class="rounded-lg bg-blue-50 p-3"></p>
        <label class="block font-medium">Transaction Reference
            <input name="transaction_reference" maxlength="255" class="mt-1 w-full rounded-md border-gray-300" />
        </label>
        <label class="block font-medium">Reason
            <textarea name="reason" required maxlength="1000" rows="2" class="mt-1 w-full rounded-md border-gray-300"></textarea>
        </label>
        <label class="flex items-start gap-2"><input name="confirmed" value="1" type="checkbox" required class="mt-1" />
            I confirm the room cancellation with the hotel and the refund amounts entered above.</label>
    </form>
</x-modal>
