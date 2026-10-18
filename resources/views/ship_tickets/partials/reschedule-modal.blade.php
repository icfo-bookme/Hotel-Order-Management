<x-modal id="reschedule-modal" title="Reschedule Room" formId="reschedule-form" submitText="Confirm Reschedule" maxWidth="4xl">
    <form id="reschedule-form" class="space-y-4">
        <p class="text-sm text-gray-600">Confirm availability and the existing nightly rates with the hotel before saving. Reschedule one room at a time.</p>
        <p id="reschedule-error" role="alert" class="hidden whitespace-pre-line rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
        <label class="block text-sm font-medium">Hotel / Room<select id="reschedule-room" required class="mt-1 w-full rounded-md border-gray-300"></select></label>
        <p id="reschedule-current" class="text-sm text-gray-600"></p>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium">New Check-in<input name="new_check_in_date" type="date" required  value="" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">New Check-out<input name="new_check_out_date" type="date" required  value="" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">Additional Customer Fee<input name="customer_fee" type="number" required min="0" max="9999999999.99" step="0.01" value="0" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">Additional Partner Fee<input name="partner_fee" type="number" required min="0" max="9999999999.99" step="0.01" value="0" class="mt-1 w-full rounded-md border-gray-300" /></label>
        </div>
        <p id="reschedule-preview" class="rounded-lg bg-gray-50 p-3 text-sm" role="status"></p>
        <label class="block text-sm font-medium">Reason<textarea name="reason" required maxlength="5000" rows="2" class="mt-1 w-full rounded-md border-gray-300"></textarea></label>
        <label class="flex items-center gap-2 text-sm font-medium"><input id="reschedule-add-payment" type="checkbox" /> Record additional payment</label>
        <fieldset id="reschedule-payment" disabled class="hidden grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium">Received Amount<input name="payment[amount]" type="number" required min="0.01" step="0.01" value=""  class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">Other Fee<input name="payment[other_fee]" type="number"  min="0" step="0.01" value="0"  class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">Paid At<input name="payment[paid_at]" type="datetime-local" required   class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">Transaction Reference<input name="payment[transaction_reference]" type="text"   maxlength="255" class="mt-1 w-full rounded-md border-gray-300" /></label>
            <label class="block text-sm font-medium">Payment Screenshot<input name="payment[attachment]" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm" /></label>
            <label class="block text-sm font-medium">Payment Method<select name="payment[payment_method]" required class="mt-1 w-full rounded-md border-gray-300"><option value="cash">Cash</option><option value="bank">Bank</option><option value="bkash">bKash</option><option value="nagad">Nagad</option><option value="card">Card</option><option value="other">Other</option></select></label>
            <p class="text-xs text-gray-500 sm:col-span-2">This payment will be pending verification and allocated to the selected room's hotel. Other fee is excluded from the advance.</p>
        </fieldset>
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" required class="mt-1" /> Availability and rates have been confirmed with the hotel.</label>
        <details class="rounded-lg border border-gray-200 p-3"><summary class="cursor-pointer text-sm font-medium">Reschedule History</summary><div id="reschedule-history" class="mt-2 space-y-2 text-sm"></div></details>
    </form>
</x-modal>
