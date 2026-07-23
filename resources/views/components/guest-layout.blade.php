@props(['title' => 'Acceso'])
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
    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-md">
            <div class="flex flex-col items-center gap-2">
                <a href="/" class="text-3xl">@include('partials.logo', ['class' => 'text-3xl'])</a>
                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-frego-500">Freight Global Operator</p>
            </div>

            <div class="mt-8 rounded-2xl border border-frego-800 bg-frego-900/70 p-6 shadow-xl shadow-black/40 sm:p-8">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs text-frego-600">
                &copy; {{ date('Y') }} FregoCargo — Sistema interno confidencial
            </p>
        </div>
    </div>
    @livewireScripts
</body>
</html>
