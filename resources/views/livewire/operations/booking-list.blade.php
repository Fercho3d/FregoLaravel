@php
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';

    // El color del avance: lo que va tarde tiene que saltar a la vista.
    $tonoAvance = fn (float $pct) => match (true) {
        $pct >= 90 => 'bg-emerald-500',
        $pct >= 50 => 'bg-amber-500',
        default => 'bg-accent-500',
    };
@endphp

<div class="space-y-4">

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink">{{ $mode === '9' ? 'Cotizaciones' : 'Bookings' }}</h2>
            <p class="text-sm text-ink-muted">
                Embarques y el avance de su lista de verificación.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-ink-faint">
                {{ number_format($filas->total()) }} {{ $mode === '9' ? 'cotizaciones' : 'bookings' }} · consulta en {{ $queryMs }} ms
            </span>
            @if (auth()->user()?->isAdmin())
                <a href="{{ route('operations.bookings.create') }}" wire:navigate class="btn-accent !px-3 !py-1.5 text-xs">
                    Nuevo booking
                </a>
            @endif
        </div>
    </header>

    {{-- Filtros --}}
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="field-label text-xs">Booking</span>
                <input type="text" wire:model.live.debounce.400ms="bookingNumber" value="{{ $bookingNumber }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="MEX…">
            </label>

            <label class="block">
                <span class="field-label text-xs">Cliente</span>
                <input type="text" wire:model.live.debounce.400ms="clientName" value="{{ $clientName }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="Nombre">
            </label>

            <label class="block">
                <span class="field-label text-xs">Buque</span>
                <input type="text" wire:model.live.debounce.400ms="vesselName" value="{{ $vesselName }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="Nombre">
            </label>

            <label class="block">
                <span class="field-label text-xs">Mercancía</span>
                <input type="text" wire:model.live.debounce.400ms="commodity" value="{{ $commodity }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="Descripción">
            </label>

            <label class="block">
                <span class="field-label text-xs">Recolección <span class="text-ink-faint">(rango)</span></span>
                <input type="text" wire:model.live.debounce.600ms="dates" value="{{ $dates }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="01/01/2025 - 31/12/2025">
            </label>

            <label class="block">
                <span class="field-label text-xs">Carga <span class="text-ink-faint">(rango)</span></span>
                <input type="text" wire:model.live.debounce.600ms="loadingDates" value="{{ $loadingDates }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="01/01/2025 - 31/12/2025">
            </label>

            <label class="block">
                <span class="field-label text-xs">Tipo</span>
                <select wire:model.live="mode" class="field-input mt-1 py-1.5 text-sm">
                    @foreach (['10' => 'Bookings', '9' => 'Cotizaciones'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected((string) $valor === $mode)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">Estado</span>
                <select wire:model.live="onlyLocked" class="field-input mt-1 py-1.5 text-sm">
                    @foreach (['0' => 'Todos', '1' => 'Solo cerrados'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected((string) $valor === $onlyLocked)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-3 flex flex-wrap items-end gap-2">
            <button type="button" wire:click="clearFilters" class="btn-ghost !px-3 !py-1.5 text-xs">Limpiar filtros</button>
            <label class="ml-auto flex items-center gap-2 text-xs text-ink-muted">
                Por página
                <select wire:model.live="perPage" class="field-input !w-auto py-1 text-xs">
                    @foreach ([25, 50, 100] as $n)
                        <option value="{{ $n }}" @selected($n === $perPage)>{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>

    {{-- Resultados --}}
    <div class="relative rounded-xl border border-line bg-panel">
        <div wire:loading.delay class="absolute inset-0 z-20 rounded-xl bg-panel/75 text-center backdrop-blur-[1px]">
            <span class="mt-14 inline-flex items-center gap-2 rounded-full border border-line bg-panel px-4 py-2 text-sm text-ink-muted shadow-lg">
                <x-spinner class="h-4 w-4 text-brand" />
                Actualizando…
            </span>
        </div>

        {{-- Tarjetas en móvil --}}
        <ul class="divide-y divide-line md:hidden">
            @forelse ($filas as $fila)
                <li class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('operations.bookings.show', $fila->booking_id) }}" wire:navigate
                               class="font-semibold text-brand hover:underline">{{ trim((string) $fila->booking_number) ?: 'Sin número' }}</a>
                            <p class="truncate text-sm text-ink-muted">{{ $fila->client_name ?: '—' }}</p>
                        </div>
                        @if ($fila->locked)
                            <span class="badge badge-neutral shrink-0">Cerrado</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-raised">
                            <div class="h-full rounded-full {{ $tonoAvance((float) $fila->total_completed) }}"
                                 style="width: {{ min(100, (float) $fila->total_completed) }}%"></div>
                        </div>
                        <span class="shrink-0 text-xs tabular-nums text-ink-muted">{{ number_format((float) $fila->total_completed, 0) }}%</span>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-ink-faint">Buque</dt>
                            <dd class="truncate text-ink-soft">{{ $fila->vessel_name ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-ink-faint">Carga</dt>
                            <dd class="text-ink-soft">{{ $fecha($fila->loading_EDT) }}</dd>
                        </div>
                    </dl>
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-ink-faint">No hay embarques con estos filtros.</li>
            @endforelse
        </ul>

        {{-- Tabla desde md --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line bg-panel text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-3 py-2.5 text-left font-semibold">Booking</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Cliente</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Buque</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Origen</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Destino</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Recolección</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Carga</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Arribo</th>
                        <th class="w-40 px-3 py-2.5 text-left font-semibold">Avance</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($filas as $fila)
                        <tr class="transition hover:bg-raised">
                            <td class="whitespace-nowrap px-3 py-2">
                                <a href="{{ route('operations.bookings.show', $fila->booking_id) }}" wire:navigate
                                   class="font-medium text-brand hover:underline">{{ trim((string) $fila->booking_number) ?: '—' }}</a>
                                @if ($fila->locked)
                                    <span class="ml-1.5 badge badge-neutral">Cerrado</span>
                                @endif
                            </td>
                            <td class="max-w-[14rem] truncate px-3 py-2 text-ink-muted" title="{{ $fila->client_name }}">{{ $fila->client_name ?: '—' }}</td>
                            <td class="max-w-[12rem] truncate px-3 py-2 text-ink-muted" title="{{ $fila->vessel_name }}">{{ $fila->vessel_name ?: '—' }}</td>
                            <td class="max-w-[10rem] truncate px-3 py-2 text-ink-muted">{{ trim((string) $fila->port_name) ?: '—' }}</td>
                            <td class="max-w-[10rem] truncate px-3 py-2 text-ink-muted">{{ $fila->discharge_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink-muted">{{ $fecha($fila->pickup_date) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink-muted">{{ $fecha($fila->loading_EDT) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink-muted">{{ $fecha($fila->dicharge_ETA) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-raised">
                                        <div class="h-full rounded-full {{ $tonoAvance((float) $fila->total_completed) }}"
                                             style="width: {{ min(100, (float) $fila->total_completed) }}%"></div>
                                    </div>
                                    <span class="shrink-0 text-xs tabular-nums text-ink-muted">{{ number_format((float) $fila->total_completed, 0) }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-12 text-center text-ink-faint">No hay embarques con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $filas->links() }}</div>
</div>
