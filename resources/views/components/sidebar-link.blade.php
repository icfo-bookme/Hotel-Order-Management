@props(['active' => false, 'label' => '', 'nested' => false])

<a {{ $attributes->class([
    'sidebar-link relative flex items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white',
    'py-2.5' => $nested,
    'py-3' => !$nested,
    'bg-white text-blue-950 shadow-sm' => $active && !$nested,
    'bg-blue-400/15 text-white ring-1 ring-inset ring-blue-300/20' => $active && $nested,
    'text-blue-100 hover:bg-white/10 hover:text-white' => !$active,
]) }}
    aria-label="{{ $label }}" title="{{ $label }}"
    @if ($active) aria-current="page" @endif>
    @if ($active && $nested)
        <span class="absolute inset-y-2 left-0 w-0.5 rounded-full bg-blue-300" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</a>
