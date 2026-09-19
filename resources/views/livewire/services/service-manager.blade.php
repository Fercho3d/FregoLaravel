@php
    $money = fn ($v) => number_format((float) $v, 2);
    $esVenta = $this->isSale();
    $esAdmin = auth()->user()?->isAdmin() ?? false;
@endphp

<div class="space-y-4">

    @if ($volver !== '')
        <a href="{{ $volver }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-ink">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ __('Volver') }}
        </a>
    @endif

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink">{{ __('Servicios y precios') }}</h2>
            <p class="text-sm text-ink-muted">
                {!! __('El precio pactado con cada tercero. Un servicio en <strong>0</strong> es precio abierto: se captura a mano al agregar el concepto.') !!}
            </p>
        </div>

        @if ($esAdmin)
            <a href="{{ route('parties.services.create', array_filter(['tipo' => $type, 'tercero' => $partyId, 'volver' => $this->currentUrl()])) }}" wire:navigate
               class="btn-accent !px-3 !py-1.5 text-xs">{{ __('Agregar') }}</a>
        @endif
    </header>

    {{-- Filtros --}}
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="block">
                <span class="field-label text-xs">{{ __('Tipo') }}</span>
                <select wire:model.live="type" class="field-input mt-1 py-1.5 text-sm">
                    @foreach (['1' => __('De venta (cliente)'), '2' => __('De compra (proveedor)')] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected((string) $valor === $type)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">{{ $esVenta ? __('Cliente') : __('Proveedor') }}</span>
                <select wire:model.live="partyId" class="field-input mt-1 py-1.5 text-sm">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach ($terceros as $id => $nombre)
                        <option value="{{ $id }}" @selected((string) $id === $partyId)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">{{ __('Descripción') }}</span>
                <input type="search" wire:model.live.debounce.300ms="search" value="{{ $search }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="{{ __('Buscar…') }}">
            </label>
        </div>
    </div>

    {{-- Listado --}}
    <div class="relative rounded-xl border border-line bg-panel">
        <div wire:loading.delay class="absolute inset-0 z-20 rounded-xl bg-panel/75 text-center backdrop-blur-[1px]">
            <span class="mt-14 inline-flex items-center gap-2 rounded-full border border-line bg-panel px-4 py-2 text-sm text-ink-muted shadow-lg">
                <x-spinner class="h-4 w-4 text-brand" /> {{ __('Actualizando…') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">{{ __('Descripción') }}</th>
                        <th class="px-4 py-2.5 text-left font-semibold">{{ $esVenta ? __('Cliente') : __('Proveedor') }}</th>
                        <th class="px-4 py-2.5 text-left font-semibold">{{ __('Tipo de cargo') }}</th>
                        <th class="px-4 py-2.5 text-right font-semibold">{{ __('Precio') }}</th>
                        <th class="px-4 py-2.5 text-left font-semibold">{{ __('Estado') }}</th>
                        @if ($esAdmin)
                            <th class="px-4 py-2.5 text-right font-semibold"><span class="sr-only">{{ __('Acciones') }}</span></th>
                        @endif
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($servicios as $servicio)
                        <tr class="transition hover:bg-raised {{ $servicio->active ? '' : 'opacity-60' }}">
                            <td class="max-w-[22rem] px-4 py-2">
                                <p class="truncate text-ink" title="{{ $servicio->description }}">{{ $servicio->description ?: '—' }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-faint">
                                    <span>{{ $servicio->currency ?: '—' }}</span>
                                    @if ($servicio->auto_include)
                                        <span class="badge badge-neutral">{{ __('Auto-incluible') }}</span>
                                        @if ($servicio->end_date)
                                            <span>vigente hasta {{ $servicio->end_date }}</span>
                                        @endif
                                    @endif
                                </p>
                            </td>
                            <td class="max-w-[14rem] truncate px-4 py-2 text-ink-muted">
                                {{ ($esVenta ? $servicio->client_name : $servicio->provider_name) ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-ink-muted">{{ $servicio->charge_type_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-ink-soft">
                                @if ((float) $servicio->price === 0.0)
                                    <span class="badge badge-warn">{{ __('Abierto') }}</span>
                                @else
                                    {{ $money($servicio->price) }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <span class="badge {{ $servicio->active ? 'badge-ok' : 'badge-neutral' }}">
                                    {{ $servicio->active ? __('Activo') : __('Inactivo') }}
                                </span>
                            </td>
                            @if ($esAdmin)
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <div class="flex justify-end gap-3 text-xs">
                                        <a href="{{ route('parties.services.edit', [$servicio->service_id, 'volver' => $this->currentUrl()]) }}" wire:navigate class="text-brand hover:underline">{{ __('Editar') }}</a>
                                        <button type="button" wire:click="toggleActive({{ $servicio->service_id }})"
                                                class="text-ink-muted transition hover:text-brand">
                                            {{ $servicio->active ? __('Desactivar') : __('Activar') }}
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $esAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-ink-faint">
                                {{ __('No hay servicios con estos filtros.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $servicios->links() }}</div>
</div>
