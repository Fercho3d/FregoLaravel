@props(['class' => 'h-8'])

{{-- Wordmark FregoCargo: "Frego" + chevron rojo. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-baseline font-extrabold tracking-tight ' . $class]) }}>
    <span class="text-white">Frego</span><span class="text-accent-500">&rsaquo;</span>
</span>
