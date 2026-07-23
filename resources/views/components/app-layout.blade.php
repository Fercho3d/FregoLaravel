@props(['title' => 'Panel'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name', 'FregoCargo') }}</title>
    <style>[x-cloak]{display:none!important}</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-frego-950 text-frego-100 antialiased">
<div class="flex min-h-full" x-data="{ sidebar: false }">
    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-64 transform border-r border-frego-800 bg-frego-900 transition-transform lg:translate-x-0"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-16 items-center gap-2 border-b border-frego-800 px-5">
            @include('partials.logo', ['class' => 'text-xl'])
            <span class="text-[10px] font-semibold uppercase tracking-widest text-frego-500">Cargo</span>
        </div>
        <nav class="flex flex-col gap-0.5 p-3 text-sm">
            @php
                $nav = [
                    ['Panel', route('dashboard'), true],
                    ['Catálogos', '#', false],
                    ['Operación', '#', false],
                    ['Facturación', '#', false],
                    ['Pagos y bancos', '#', false],
                    ['Reportes', '#', false],
                    ['Portal', '#', false],
                ];
            @endphp
            @foreach ($nav as [$label, $href, $active])
                <a href="{{ $href }}"
                   class="rounded-lg px-3 py-2 font-medium transition {{ $active ? 'bg-frego-800 text-white' : 'text-frego-400 hover:bg-frego-800/60 hover:text-frego-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </aside>

    {{-- Overlay móvil --}}
    <div x-show="sidebar" x-cloak class="fixed inset-0 z-30 bg-black/60 lg:hidden" x-on:click="sidebar = false"></div>

    {{-- Columna principal --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-frego-800 bg-frego-950/80 px-4 backdrop-blur sm:px-6">
            <button class="rounded-lg p-2 text-frego-400 hover:bg-frego-800 lg:hidden" x-on:click="sidebar = true" aria-label="Menú">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-sm font-semibold text-frego-200">{{ $title }}</h1>

            <div class="relative" x-data="{ open: false }">
                <button x-on:click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-frego-200 hover:bg-frego-800">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-accent-500 text-xs font-bold text-white">
                        {{ strtoupper(mb_substr(auth()->user()->name ?? auth()->user()->username ?? 'U', 0, 1)) }}
                    </span>
                    <span class="hidden sm:inline">{{ auth()->user()->name ?? auth()->user()->username }}</span>
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false"
                     class="absolute right-0 mt-2 w-52 rounded-xl border border-frego-800 bg-frego-900 p-1.5 shadow-xl">
                    <a href="{{ route('security.show') }}" class="block rounded-lg px-3 py-2 text-sm text-frego-300 hover:bg-frego-800 hover:text-white">Seguridad y 2FA</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-accent-400 hover:bg-frego-800">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @include('partials.session-status')
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
