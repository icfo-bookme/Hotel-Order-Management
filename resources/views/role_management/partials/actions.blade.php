<div class="flex items-center justify-center gap-2">
    @if ($editable)
        <button type="button" data-edit-{{ $section }}="{{ $item->id }}" title="Edit" aria-label="Edit"
            class="rounded-lg bg-blue-50 p-2 text-blue-700 hover:bg-blue-100"><svg class="h-4 w-4" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                    d="m16 4 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15z" />
            </svg></button>
    @endif
    @if ($deletable)
        <button type="button" data-delete-role="{{ $item->id }}" title="Delete Role" aria-label="Delete Role"
            class="rounded-lg bg-red-50 p-2 text-red-700 hover:bg-red-100"><svg class="h-4 w-4" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-width="1.8" d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15" />
            </svg></button>
    @endif
    @if (!$editable && !$deletable)
        <span class="text-gray-400">?</span>
    @endif
</div>
