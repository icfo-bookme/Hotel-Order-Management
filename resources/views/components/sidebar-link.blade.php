@props(['active' => false, 'label' => ''])

<a {{ $attributes->class([
    'sidebar-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-white',
    'bg-white text-blue-950 shadow-sm' => $active,
    'text-blue-100 hover:bg-white/10 hover:text-white' => ! $active,
]) }} aria-label="{{ $label }}" title="{{ $label }}" @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
