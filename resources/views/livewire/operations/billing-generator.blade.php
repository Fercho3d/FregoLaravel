@php
    $money = fn ($v) => number_format((float) $v, 2);
    $divisa = fn ($id) => $divisas[$id] ?? '—';
    $hoy = $this->date();
@endphp

<div class="mx-auto max-w-6xl space-y-4">

    <a href="{{ route('operations.bookings.show', $booking->booking_id) }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-ink">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Volver al booking
    </a>

    {{-- Encabezado --}}
    <section class="card p-5 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Generar factura y costos</p>
        <h2 class="mt-0.5 text-2xl font-semibold text-ink">
            {{ trim((string) $booking->booking_number) ?: 'Booking '.$booking->booking_id }}
        </h2>
        <p class="mt-1 text-sm text-ink-muted">{{ $cliente ?: 'Sin cliente' }}</p>

        <p class="mt-4 max-w-3xl text-sm text-ink-muted">
            Estos son los servicios contratados que empatan con la ruta del booking y con los contenedores
            que lleva. Nada se escribe hasta que confirmes: revisa los renglones, quita los que no
            correspondan y abajo verás exactamente qué documentos van a quedar, con fecha
            {{ $hoy->format('d/m/Y') }}.
        </p>

        @if ($existentes->isNotEmpty())
            <div class="alert-warn mt-4">
                Este booking ya tiene {{ $existentes->count() }}
                {{ $existentes->count() === 1 ? 'transacción' : 'transacciones' }} sin cancelar
                ({{ $existentes->map(fn ($t) => $t->tran_number ?: '#'.$t->transc_id)->join(', ') }}).
                Generar otra vez las duplica.
            </div>
        @endif
    </section>

    {{-- Un bloque por documento posible --}}
    @foreach ($bloques as $bloque)
        @php
            $renglones = $this->visible($bloque);
            $documentos = $plan->forBlock($bloque);
            $tercero = $bloque->isBill() ? ($proveedores[$bloque->value] ?? null) : $cliente;
        @endphp

        <section class="card overflow-hidden">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-ink">{{ $bloque->label() }}</h3>
                    <p class="truncate text-xs text-ink-faint">{{ $tercero ?: 'Sin asignar en el booking' }}</p>
                </div>

                @if ($renglones !== [])
                    <div class="flex items-center gap-3 text-xs">
                        <button type="button" wire:click="selectAll('{{ $bloque->value }}')" class="text-brand hover:underline">Marcar todo</button>
                        <button type="button" wire:click="clearBlock('{{ $bloque->value }}')" class="text-ink-muted transition hover:text-brand">Quitar todo</button>
                    </div>
                @endif
            </header>

            @if ($renglones === [])
                <p class="px-5 py-8 text-center text-sm text-ink-faint">
                    @if ($bloque->isBill() && $tercero === null)
                        El booking no tiene {{ $bloque->party() }}: no hay nada que costear.
                    @else
                        Ningún servicio contratado empata con esta ruta.
                    @endif
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold"><span class="sr-only">Incluir</span></th>
                                <th class="px-4 py-2.5 text-left font-semibold">Servicio</th>
                                <th class="px-4 py-2.5 text-left font-semibold">Vigencia</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Precio</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Cantidad</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($renglones as $renglon)
                                @php $vigente = $renglon->isCurrentOn($hoy); @endphp
                                <tr class="transition hover:bg-raised {{ $vigente ? '' : 'opacity-70' }}">
                                    <td class="px-4 py-2.5 align-top">
                                        <input type="checkbox" wire:model.live="selected" value="{{ $renglon->key() }}"
                                               class="mt-0.5 h-4 w-4 rounded border-line text-accent-500 focus:ring-accent-500/40">
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <p class="text-ink">{{ $renglon->lineDescription() }}</p>
                                        <p class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-ink-faint">
                                            <span>{{ $divisa($renglon->accountId) }}</span>
                                            @if ($renglon->documents > 1)
                                                <span class="badge badge-neutral">{{ $renglon->documents }} costos, uno por contenedor</span>
                                            @endif
                                            @if ($renglon->priceType === null)
                                                <span class="badge badge-danger">Sin tipo de precio</span>
                                            @endif
                                            @if ($renglon->quantity <= 0)
                                                <span class="badge badge-danger">Cantidad 0</span>
                                            @endif
                                            @if ($renglon->price <= 0)
                                                <span class="badge badge-warn">Precio abierto</span>
                                            @endif
                                        </p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-xs">
                                        @if ($vigente)
                                            <span class="badge badge-ok">Vigente</span>
                                        @else
                                            <span class="badge badge-warn">Fuera de vigencia</span>
                                        @endif
                                        <span class="mt-1 block text-ink-faint">
                                            {{ $renglon->startDate ?: '—' }} → {{ $renglon->endDate ?: '—' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink-muted">{{ $money($renglon->price) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink-muted">{{ $money($renglon->quantity) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums font-medium text-ink">{{ $money($renglon->amount()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <footer class="border-t border-line px-5 py-3 text-xs text-ink-muted">
                    @if ($documentos === [])
                        Sin renglones marcados: no se creará ningún documento.
                    @else
                        @php $copias = array_sum(array_map(fn ($d) => $d->copies, $documentos)); @endphp
                        Quedará{{ $copias === 1 ? '' : 'n' }} <span class="font-semibold text-ink">{{ $copias }}</span>
                        {{ $copias === 1 ? 'documento' : 'documentos' }}:
                        @foreach ($plan->totalsByAccount($bloque) as $cuenta => $total)
                            <span class="ml-1 tabular-nums text-ink">{{ $divisa($cuenta) }} {{ $money($total) }}</span>
                        @endforeach
                    @endif
                </footer>
            @endif
        </section>
    @endforeach

    {{-- Precios fuera de vigencia --}}
    @if ($this->hiddenCount() > 0 || $showExpired)
        <div class="text-center text-sm">
            <button type="button" wire:click="$toggle('showExpired')" class="text-ink-muted underline transition hover:text-brand">
                @if ($showExpired)
                    Ocultar los precios fuera de vigencia
                @else
                    Ver {{ $this->hiddenCount() }} {{ $this->hiddenCount() === 1 ? 'precio' : 'precios' }} fuera de vigencia
                @endif
            </button>
        </div>
    @endif

    {{-- Confirmación --}}
    <section class="card p-5 sm:p-6">
        @error('plan') <div class="alert-danger mb-4">{{ $message }}</div> @enderror

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="text-sm">
                @if ($plan->isEmpty())
                    <p class="text-ink-muted">No hay ningún renglón marcado.</p>
                @else
                    <p class="text-ink">
                        <span class="font-semibold">{{ $plan->documentCount() }}</span>
                        {{ $plan->documentCount() === 1 ? 'documento' : 'documentos' }} con
                        <span class="font-semibold">{{ $plan->lineCount() }}</span>
                        {{ $plan->lineCount() === 1 ? 'concepto' : 'conceptos' }}.
                    </p>
                    <p class="mt-0.5 text-xs text-ink-faint">
                        Las facturas al cliente toman folio consecutivo al crearse.
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('operations.bookings.show', $booking->booking_id) }}" wire:navigate class="btn-ghost px-4 py-2 text-sm">Cancelar</a>
                <button type="button" wire:click="generate" @disabled($plan->isEmpty())
                        wire:confirm="Se van a crear {{ $plan->documentCount() }} documentos con sus conceptos. ¿Continuar?"
                        wire:loading.attr="disabled" wire:target="generate" class="btn-accent px-4 py-2 text-sm">
                    <x-spinner wire:loading wire:target="generate" class="h-4 w-4" />
                    Generar
                </button>
            </div>
        </div>
    </section>
</div>
