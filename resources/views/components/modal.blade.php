@props([
    'id' => null,
    'name' => null,
    'title' => null,
    'show' => false,
    'maxWidth' => '2xl',
    'submitText' => 'Save',
    'formId' => null,
    'submitId' => null,
    'hideFooter' => false,
])
@php
    $modalId = $id ?? $name;
    $width = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '4xl' => 'max-w-4xl',
    ][$maxWidth];
@endphp
@push('modals')
    <div id="{{ $modalId }}" data-modal="{{ $modalId }}" data-show="{{ $show ? 'true' : 'false' }}" role="dialog"
        aria-modal="true" aria-labelledby="{{ $modalId }}-title" aria-hidden="{{ $show ? 'false' : 'true' }}"
        tabindex="-1" @if (!$show) hidden @endif
        {{ $attributes->except('focusable')->class(['fixed inset-0 z-[60] items-center justify-center bg-gray-900/50 p-4', 'hidden' => !$show, 'flex' => $show]) }}>
        <div class="flex max-h-[90dvh] w-full {{ $width }} flex-col overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex shrink-0 items-center justify-between gap-4 border-b border-gray-200 px-5 py-4">
                <div class="min-w-0">
                    <h2 id="{{ $modalId }}-title" class="text-lg font-semibold text-gray-900">
                        {{ $title ?? ucwords(str_replace('-', ' ', $modalId)) }}</h2>{{ $subtitle ?? '' }}
                </div>
                <button type="button" data-close-modal="{{ $modalId }}" title="Close" aria-label="Close modal"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600"><svg
                        class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M6 18 18 6" />
                    </svg></button>
            </div>
            <div data-modal-body class="min-h-0 flex-1 overflow-y-auto p-5">{{ $slot }}</div>
            @unless ($hideFooter || !$formId)
                <div class="flex shrink-0 justify-end gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4">
                    <button type="button" data-close-modal="{{ $modalId }}"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                    <button type="submit" @if ($formId) form="{{ $formId }}" @endif
                        @if ($submitId) id="{{ $submitId }}" @endif data-submit-btn
                        data-saving-text="Saving..."
                        class="rounded-lg bg-blue-950 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:cursor-wait disabled:opacity-50">{{ $submitText }}</button>
                </div>
            @endunless
        </div>
    </div>
@endpush
