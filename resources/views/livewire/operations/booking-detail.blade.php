@use('App\Support\PaymentStatus')

@php
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $avance = (float) $booking->total_completed;
    $marcadas = collect($checklist)->filter()->count();
@endphp

<div class="mx-auto max-w-6xl space-y-4">

    <a href="{{ route('operations.bookings') }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-ink">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Volver a bookings
    </a>

    {{-- Encabezado --}}
    <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Booking</p>
                <h2 class="mt-0.5 truncate text-2xl font-semibold text-ink">
                    {{ trim((string) $booking->booking_number) ?: 'Sin número' }}
                </h2>
                <p class="mt-1 truncate text-sm text-ink-muted">{{ $booking->client_name ?: '—' }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($booking->locked)
                    <span class="badge badge-neutral">Cerrado</span>
                @endif
                @if (auth()->user()?->isAdmin() && ! $booking->locked)
                    <a href="{{ route('operations.bookings.edit', $booking->booking_id) }}" wire:navigate class="btn-ghost px-3 py-1.5 text-xs">
                        Editar
                    </a>
                    <a href="{{ route('operations.bookings.generate', $booking->booking_id) }}" wire:navigate
                       title="Propone la factura y los costos a partir de los servicios contratados para esta ruta"
                       class="btn-ghost px-3 py-1.5 text-xs">
                        Generar facturación
                    </a>
                    <button type="button" wire:click="lock"
                            wire:confirm="Al cerrarlo ya no se podrán tocar sus contenedores ni sus documentos. ¿Continuar?"
                            class="btn-ghost px-3 py-1.5 text-xs">Cerrar booking</button>
                @endif
                @if ($booking->locked && auth()->user()?->isSuperAdmin())
                    <button type="button" wire:click="unlock"
                            wire:confirm="Reabrir permite volver a tocar importes ya conciliados. ¿Continuar?"
                            class="btn-ghost px-3 py-1.5 text-xs text-brand">Reabrir</button>
                @endif
                <a href="{{ route('operations.bookings.pdf', $booking->booking_id) }}" target="_blank"
                   class="btn-ghost px-3 py-1.5 text-xs">Confirmación PDF</a>
                @if (auth()->user()?->isAdmin())
                    <button type="button" wire:click="sendConfirmation"
                            wire:confirm="Se le mandará al cliente la confirmación en PDF. ¿Continuar?"
                            wire:loading.attr="disabled" wire:target="sendConfirmation"
                            class="btn-ghost px-3 py-1.5 text-xs">
                        <x-spinner wire:loading wire:target="sendConfirmation" class="h-3.5 w-3.5" />
                        Enviar al cliente
                    </button>
                @endif
                <a href="{{ route('transactions.booking', $booking->booking_id) }}" wire:navigate class="btn-ghost px-3 py-1.5 text-xs">
                    Ver facturación
                </a>
            </div>
        </div>

        {{-- Avance --}}
        <div class="mt-5 border-t border-line pt-4">
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="text-ink-muted">Avance de la lista de verificación</span>
                <span class="font-semibold tabular-nums text-ink">
                    {{ number_format($avance, 0) }}%
                    <span class="font-normal text-ink-faint">({{ $marcadas }} de {{ count($checklist) }})</span>
                </span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-raised">
                <div class="h-full rounded-full {{ $avance >= 90 ? 'bg-emerald-500' : ($avance >= 50 ? 'bg-amber-500' : 'bg-accent-500') }}"
                     style="width: {{ min(100, $avance) }}%"></div>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-line pt-5 text-sm sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([
                ['Buque', $booking->vessel_name],
                ['Puerto de carga', trim((string) $booking->port_name)],
                ['Puerto de descarga', $booking->discharge_name],
                ['Lugar de recolección', $booking->pickup_name],
                ['Recolección', $fecha($booking->pickup_date)],
                ['Instrucciones (SI)', $fecha($booking->SI_date)],
                ['Carga estimada', $fecha($booking->loading_EDT)],
                ['Arribo estimado', $fecha($booking->dicharge_ETA)],
                ['Mercancía', $booking->commodity],
                ['Temperatura', $booking->set_point],
                ['Referencia del cliente', $booking->customer_reference],
                ['Creado por', $booking->creator],
            ] as [$etiqueta, $valor])
                <div class="min-w-0">
                    <dt class="text-xs uppercase tracking-wide text-ink-faint">{{ $etiqueta }}</dt>
                    <dd class="mt-0.5 truncate text-ink" title="{{ $valor }}">{{ $valor ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Contenedores --}}
    <section class="card overflow-hidden">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            <h3 class="text-sm font-semibold text-ink">Contenedores</h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-ink-faint">{{ $contenedores->count() }}</span>
                @if (auth()->user()?->isAdmin() && ! $booking->locked)
                    <button type="button" wire:click="addContainer" class="btn-ghost px-3 py-1.5 text-xs">Agregar</button>
                @endif
            </div>
        </header>

        @if ($editingContainer)
            <form wire:submit="saveContainer" class="space-y-4 border-b border-line bg-raised/60 p-5">
                <p class="text-sm font-medium text-ink">{{ $containerId ? 'Editar contenedor' : 'Nuevo contenedor' }}</p>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Número', 'containerNumber', 'text'],
                        ['Sello', 'containerSeal', 'text'],
                        ['Cantidad', 'containerQuantity', 'number'],
                        ['Mercancía', 'containerCommodity', 'text'],
                        ['Recolección', 'containerPickup', 'date'],
                    ] as [$etiqueta, $propiedad, $tipo])
                        <label class="block">
                            <span class="field-label">{{ $etiqueta }}</span>
                            <input type="{{ $tipo }}" @if ($tipo === 'number') min="1" @endif
                                   wire:model="{{ $propiedad }}" value="{{ $$propiedad }}" class="field-input mt-1.5">
                            @error($propiedad) <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                        </label>
                    @endforeach

                    <label class="block">
                        <span class="field-label">Tipo</span>
                        <select wire:model="containerType" class="field-input mt-1.5">
                            <option value="">Sin especificar</option>
                            @foreach ($tiposContenedor as $id => $nombre)
                                <option value="{{ $id }}" @selected((string) $id === $containerType)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        @error('containerType') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" wire:click="cancelContainerEdit" class="btn-ghost !px-3 !py-1.5 text-xs">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveContainer" class="btn-accent !px-3 !py-1.5 text-xs">
                        <x-spinner wire:loading wire:target="saveContainer" class="h-3.5 w-3.5" />
                        Guardar
                    </button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Número</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Sello</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Tipo</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Cantidad</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Mercancía</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Recolección</th>
                        @if (auth()->user()?->isAdmin() && ! $booking->locked)
                            <th class="px-4 py-2.5 text-right font-semibold"><span class="sr-only">Acciones</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($contenedores as $c)
                        <tr class="transition hover:bg-raised">
                            <td class="whitespace-nowrap px-4 py-2 text-ink">{{ $c->number ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-ink-muted">{{ $c->seal ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-ink-muted">{{ $c->container_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-ink-muted">{{ $c->quantity ?: '—' }}</td>
                            <td class="max-w-[16rem] truncate px-4 py-2 text-ink-muted">{{ $c->comodity ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-ink-muted">{{ $fecha($c->pick_up_date) }}</td>
                            @if (auth()->user()?->isAdmin() && ! $booking->locked)
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <div class="flex justify-end gap-3 text-xs">
                                        <button type="button" wire:click="editContainer({{ $c->container_ID }})" class="text-brand hover:underline">Editar</button>
                                        <button type="button" wire:click="deleteContainer({{ $c->container_ID }})"
                                                wire:confirm="¿Quitar este contenedor del booking?"
                                                class="text-ink-muted transition hover:text-brand">Quitar</button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->isAdmin() && ! $booking->locked ? 7 : 6 }}" class="px-4 py-10 text-center text-ink-faint">
                                Este booking no tiene contenedores.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Facturación --}}
    <section class="card overflow-hidden">
        <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
            <h3 class="text-sm font-semibold text-ink">Facturación</h3>
            <span class="text-xs text-ink-faint">{{ $transacciones->count() }} transacciones</span>
        </header>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Documento</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Aplicado a</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Fecha</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Total</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($transacciones as $t)
                        @php $estado = PaymentStatus::for($t); @endphp
                        <tr class="transition hover:bg-raised {{ $t->cancelled ? 'opacity-50' : '' }}">
                            <td class="whitespace-nowrap px-4 py-2">
                                <a href="{{ route('transactions.show', $t->transc_id) }}" wire:navigate
                                   class="text-brand hover:underline">{{ $t->tran_number ?: 'Ver' }}</a>
                            </td>
                            <td class="max-w-[16rem] truncate px-4 py-2 text-ink-muted">{{ $t->customerName ?: ($t->vendorName ?: '—') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-ink-muted">{{ $fecha($t->tran_date) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-semibold tabular-nums {{ (float) $t->total_amount < 0 ? 'text-brand' : 'text-ink' }}">
                                {{ $money($t->total_amount) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2"><span class="{{ $estado->classes() }}">{{ $estado->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-ink-faint">Este booking no tiene facturación.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Documentos --}}
    @if ($documentos !== [])
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between gap-3 border-b border-line px-5 py-3">
                <h3 class="text-sm font-semibold text-ink">Documentos</h3>
                <span class="text-xs text-ink-faint">
                    {{ collect($documentos)->sum(fn ($d) => count($d->files)) }} archivos
                </span>
            </header>

            <p class="border-b border-line px-5 py-2 text-xs text-ink-faint">
                Cada cliente pide los suyos: esta lista sale de los campos configurados para
                {{ $booking->client_name ?: 'este cliente' }}.
            </p>

            @error('upload') <p class="alert-danger m-5">{{ $message }}</p> @enderror

            <ul class="divide-y divide-line">
                @foreach ($documentos as $campo)
                    <li class="space-y-2 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-medium text-ink">{{ $campo->label }}</p>

                            @if (auth()->user()?->isAdmin() && ! $booking->locked)
                                @if ($uploadField === $campo->field_id)
                                    <span class="flex items-center gap-2">
                                        <input type="file" wire:model="upload"
                                               class="block w-full text-xs text-ink-muted file:mr-3 file:rounded-lg file:border-0 file:bg-raised file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-ink hover:file:bg-line">
                                        <span wire:loading wire:target="upload" class="inline-flex items-center gap-1.5 text-xs text-ink-muted">
                                            <x-spinner class="h-3 w-3" /> Subiendo…
                                        </span>
                                    </span>
                                @else
                                    <button type="button" wire:click="chooseField({{ $campo->field_id }})"
                                            class="btn-ghost !px-3 !py-1 text-xs">Adjuntar</button>
                                @endif
                            @endif
                        </div>

                        @if ($campo->files === [])
                            <p class="text-xs text-ink-faint">Sin documentos.</p>
                        @else
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($campo->files as $archivo)
                                    <li class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-1.5 text-xs">
                                        <a href="{{ route('operations.bookings.file', [$booking->booking_id, urlencode($archivo)]) }}"
                                           class="max-w-[16rem] truncate text-brand hover:underline" title="{{ $archivo }}">
                                            {{ $archivo }}
                                        </a>
                                        @if (auth()->user()?->isAdmin() && ! $booking->locked)
                                            <button type="button" wire:click="removeFile({{ $campo->field_id }}, @js($archivo))"
                                                    wire:confirm="¿Quitar este documento del booking?"
                                                    class="text-ink-faint transition hover:text-brand" aria-label="Quitar">×</button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Lista de verificación --}}
    @if ($checklist !== [])
        <section class="card p-5 sm:p-6">
            <h3 class="text-sm font-semibold text-ink">Lista de verificación</h3>

            <ul class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($checklist as $etiqueta => $marcada)
                    <li class="flex items-center justify-between gap-3 border-b border-line/60 py-1.5 text-sm">
                        <span class="flex min-w-0 items-center gap-2">
                            @if ($marcada)
                                <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @else
                                <svg class="h-4 w-4 shrink-0 text-ink-faint" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <circle cx="12" cy="12" r="8"/>
                                </svg>
                            @endif
                            <span class="truncate {{ $marcada ? 'text-ink' : 'text-ink-faint' }}">{{ $etiqueta }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-ink-faint">{{ $marcada ? $fecha($marcada) : '' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
