@php
    // Cada grupo de campos: etiqueta, propiedad, tipo y opciones.
    $grupos = [
        'Identificación' => [
            ['Número de booking', 'bookingNumber', 'text', null, true],
            ['HB', 'hb', 'text', null, false],
            ['Referencia del cliente', 'customerReference', 'text', null, false],
            ['Cliente', 'clientId', 'select', $clientes, true],
            ['Tipo de booking', 'bookingType', 'text', null, false],
        ],
        'Transporte' => [
            ['Buque', 'vesselId', 'select', $buques, true],
            ['Naviera', 'carrierId', 'select', $navieras, false],
            ['Transportista', 'transportId', 'select', $transportistas, false],
            ['Agente aduanal', 'brokerId', 'select', $agentes, false],
        ],
        'Ruta' => [
            ['Lugar de recolección', 'pickupPlace', 'select', $lugares, true],
            ['Puerto de carga', 'loadingPort', 'select', $puertosCarga, true],
            ['Fecha de carga', 'loadingDate', 'date', null, true],
            ['Puerto de descarga', 'dischargePort', 'select', $puertosDescarga, true],
            ['Fecha de arribo', 'arrivalDate', 'date', null, true],
            ['Destino final', 'finalDestination', 'select', $destinos, false],
        ],
        'Carga' => [
            ['Tipo de contenedor', 'containerType', 'select', $tiposContenedor, false],
            ['Mercancía', 'commodity', 'text', null, false],
            ['Temperatura', 'setPoint', 'text', null, false],
        ],
    ];
@endphp

<div class="mx-auto max-w-4xl space-y-4">

    <a href="{{ $bookingId ? route('operations.bookings.show', $bookingId) : route('operations.bookings') }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-ink">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        {{ $bookingId ? 'Volver al booking' : 'Volver a bookings' }}
    </a>

    <form wire:submit="save" class="card space-y-6 p-5 sm:p-6">
        <header>
            <h2 class="text-lg font-semibold text-ink">{{ $bookingId ? 'Editar booking' : 'Nuevo booking' }}</h2>
            @if ($bookingId)
                <p class="mt-0.5 text-sm text-ink-muted">{{ $bookingNumber }}</p>
            @endif
        </header>

        @if ($locked)
            <p class="flex items-start gap-2 rounded-lg border border-line bg-raised px-3 py-2 text-xs text-ink-muted">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="5" y="11" width="14" height="9" rx="2"/><path stroke-linecap="round" d="M8 11V8a4 4 0 0 1 8 0v3"/>
                </svg>
                <span>{{ __('Este booking está cerrado: operación lo dio por terminado y su facturación quedó fija.') }}</span>
            </p>
        @endif

        @include('partials.validation-errors')

        @foreach ($grupos as $titulo => $campos)
            <fieldset class="space-y-4" @disabled($locked)>
                <legend class="text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ $titulo }}</legend>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($campos as [$etiqueta, $propiedad, $tipo, $opciones, $obligatorio])
                        <label class="block">
                            <span class="field-label">
                                {{ $etiqueta }}
                                @if ($obligatorio) <span class="text-brand">*</span> @endif
                            </span>

                            @if ($tipo === 'select')
                                <select wire:model="{{ $propiedad }}" @disabled($locked)
                                        class="field-input mt-1.5" @required($obligatorio)>
                                    <option value="">{{ $obligatorio ? 'Selecciona' : 'Sin especificar' }}</option>
                                    @foreach ($opciones as $id => $nombre)
                                        <option value="{{ $id }}" @selected((string) $id === (string) $$propiedad)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $tipo }}" wire:model="{{ $propiedad }}" value="{{ $$propiedad }}"
                                       @disabled($locked) class="field-input mt-1.5" @required($obligatorio)>
                            @endif

                            @error($propiedad) <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                        </label>

                        {{-- Alta rápida de buque: cambian de nombre y de servicio seguido,
                             y detener la captura para ir al catálogo no tiene sentido. --}}
                        @if ($propiedad === 'vesselId')
                            <label class="block">
                                <span class="field-label">
                                    {{ __('…o un buque nuevo') }}
                                    <span class="font-normal text-ink-faint">{{ __('(se da de alta al guardar)') }}</span>
                                </span>
                                <input type="text" wire:model.live="newVessel" value="{{ $newVessel }}"
                                       @disabled($locked) class="field-input mt-1.5" placeholder="{{ __('Nombre del buque') }}">
                                @error('newVessel') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                            </label>
                        @endif
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <fieldset class="space-y-2" @disabled($locked)>
            <legend class="text-xs font-semibold uppercase tracking-wide text-ink-faint">{{ __('Notas') }}</legend>
            <textarea wire:model="remarks" rows="3" @disabled($locked) class="field-input">{{ $remarks }}</textarea>
        </fieldset>

        <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-line pt-4">
            <a href="{{ $bookingId ? route('operations.bookings.show', $bookingId) : route('operations.bookings') }}"
               wire:navigate class="btn-ghost">{{ __('Cancelar') }}</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($locked) class="btn-accent">
                <x-spinner wire:loading wire:target="save" class="h-4 w-4" />
                {{ $bookingId ? 'Guardar cambios' : 'Crear booking' }}
            </button>
        </footer>
    </form>
</div>
