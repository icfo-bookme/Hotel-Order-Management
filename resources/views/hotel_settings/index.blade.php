<x-app-layout>
    <x-slot name="header">
        <h1 class="truncate text-lg font-semibold text-gray-800 sm:text-xl">Hotel Settings</h1>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status'))
            <p role="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800">
                <p>Please correct the following:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route($settings->exists ? 'hotel-settings.update' : 'hotel-settings.store') }}"
            enctype="multipart/form-data" class="space-y-6">
            @csrf
            @if ($settings->exists)
                @method('PUT')
            @endif

            <section class="space-y-5 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Invoice Branding</h2>
                <div>
                    <x-input-label for="invoice_title" value="Invoice Title" />
                    <x-text-input id="invoice_title" name="invoice_title" :value="old('invoice_title', $settings->invoice_title)" maxlength="255" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('invoice_title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="logo" value="Invoice Logo" />
                    @if ($settings->logo_path)
                        <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="Current invoice logo"
                            class="my-3 max-h-24 max-w-full rounded border border-gray-200 object-contain p-2" />
                        <label class="mb-3 flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo', false)) class="rounded border-gray-300 text-blue-950" />
                            Remove current logo
                        </label>
                    @endif
                    <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-700" />
                    <p class="mt-2 text-xs text-gray-500">JPG, PNG or WebP, up to 5 MB. Upload a new logo to replace the current one.</p>
                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                </div>
                @foreach (['show_invoice_title' => 'Show invoice title', 'show_logo' => 'Show logo'] as $field => $label)
                    <label class="flex items-center gap-3 text-sm text-gray-700">
                        <input type="hidden" name="{{ $field }}" value="0" />
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings->$field)) class="rounded border-gray-300 text-blue-950 focus:ring-blue-600" />
                        {{ $label }}
                    </label>
                @endforeach
            </section>

            <section class="space-y-5 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Company Details</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach (['company_name' => ['Company Name', 'text', 255], 'company_phone' => ['Phone', 'tel', 50], 'company_email' => ['Email', 'email', 255]] as $field => [$label, $type, $limit])
                        <div>
                            <x-input-label :for="$field" :value="$label" />
                            <x-text-input :id="$field" :name="$field" :type="$type" :maxlength="$limit" :value="old($field, $settings->$field)" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                    @endforeach
                </div>
                <div>
                    <x-input-label for="company_address" value="Address" />
                    <textarea id="company_address" name="company_address" rows="3" maxlength="5000"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('company_address', $settings->company_address) }}</textarea>
                </div>
                <label class="flex items-center gap-3 text-sm text-gray-700">
                    <input type="hidden" name="show_company_details" value="0" />
                    <input type="checkbox" name="show_company_details" value="1" @checked(old('show_company_details', $settings->show_company_details)) class="rounded border-gray-300 text-blue-950 focus:ring-blue-600" />
                    Show company details
                </label>
            </section>

            <section class="space-y-5 rounded-xl bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Footer &amp; Terms</h2>
                @foreach (['footer_text' => ['Footer Text', 'show_footer', 5000], 'terms_and_conditions' => ['Terms and Conditions', 'show_terms', 10000]] as $field => [$label, $toggle, $limit])
                    <div>
                        <x-input-label :for="$field" :value="$label" />
                        <textarea id="{{ $field }}" name="{{ $field }}" rows="4" maxlength="{{ $limit }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old($field, $settings->$field) }}</textarea>
                        <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        <label class="mt-3 flex items-center gap-3 text-sm text-gray-700">
                            <input type="hidden" name="{{ $toggle }}" value="0" />
                            <input type="checkbox" name="{{ $toggle }}" value="1" @checked(old($toggle, $settings->$toggle)) class="rounded border-gray-300 text-blue-950 focus:ring-blue-600" />
                            Show {{ strtolower($label) }}
                        </label>
                    </div>
                @endforeach
            </section>

            <div class="flex justify-end">
                <x-primary-button>Save Settings</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
