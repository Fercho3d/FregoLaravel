@use('App\Support\PaymentStatus')

@php
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);

    // Cuántos filtros trae puestos: se muestra en el botón cuando el panel está
    // plegado, para que no se pierda de vista que la lista viene acotada.
    $activos = collect([$tranNumber, $bookingNumber, $appliedTo, $dates, $companyId, $accountId, $paid])
        ->filter(fn ($v) => filled($v))
        ->count() + ($showCancelled !== '0' ? 1 : 0);
    $columns = [
        ['booking', 'Booking', 'text-left'],
        ['tran_date', 'Fecha', 'text-left'],
        ['tran_number', 'Número', 'text-left'],
        [null, 'Aplicado a', 'text-left'],
        [null, 'Compañía', 'text-left'],
        [null, 'Ccy', 'text-left'],
        [null, 'Importe', 'text-right'],
        [null, 'TC', 'text-right'],
        [null, 'Sub 0 %', 'text-right'],
        [null, 'Sub 16 %', 'text-right'],
        [null, 'IVA 16 %', 'text-right'],
        [null, 'Ret. IVA', 'text-right'],
        [null, 'Total', 'text-right'],
        [null, 'Pagado', 'text-right'],
        [null, 'Estado', 'text-left'],
        ['seal', 'CFDI', 'text-left'],
    ];
@endphp

<div class="space-y-4">

    {{-- Pestañas: navegación sin recarga completa --}}
    <nav class="flex flex-wrap items-center gap-1 rounded-xl border border-line bg-panel p-1 text-sm">
        @foreach ([
            ['invoice', 'Facturas', route('transactions.invoice')],
            ['bill', 'Costos', route('transactions.bill')],
            ['all', 'Todas', route('transactions.all')],
        ] as [$key, $label, $href])
            <a href="{{ $href }}" wire:navigate
               class="rounded-lg px-4 py-2 font-medium transition {{ $screen === $key ? 'bg-accent-500 text-white' : 'text-ink-muted hover:bg-raised hover:text-ink' }}">
                {{ $label }}
            </a>
        @endforeach

        @if ($booking)
            <span class="rounded-lg bg-raised px-4 py-2 font-medium text-ink">
                Booking {{ trim($booking->booking_number) }}
            </span>

            {{-- El alta necesita saber a qué booking pertenece; por eso solo se
                 ofrece desde esta pantalla. --}}
            @foreach ([['factura', 'Nueva factura'], ['costo', 'Nuevo costo']] as [$tipo, $etiqueta])
                <a href="{{ route('transactions.create', ['booking' => $booking->booking_id, 'tipo' => $tipo]) }}"
                   wire:navigate class="btn-ghost px-3 py-1.5 text-xs">{{ $etiqueta }}</a>
            @endforeach
        @endif

        <span class="ml-auto px-3 text-xs text-ink-faint" title="Tiempo de la consulta que alimenta esta tabla">
            {{ number_format($rows->total()) }} registros · consulta en {{ $queryMs }} ms
        </span>
    </nav>

    {{-- Filtros. Se pliegan en pantallas angostas para que la tabla quede a la
         vista sin tener que bajar; en pantallas anchas arrancan abiertos. --}}
    <div class="rounded-xl border border-line bg-panel"
         x-data="{ abierto: window.innerWidth >= 1024 }">

        <button type="button" x-on:click="abierto = !abierto"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-ink-muted transition hover:text-ink">
            <svg class="h-4 w-4 shrink-0 transition-transform" :class="abierto && 'rotate-90'"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            <span>Filtros</span>
            @if ($activos)
                <span class="rounded-full bg-accent-500/20 px-2 py-0.5 text-[11px] font-semibold text-brand">{{ $activos }}</span>
            @endif
            <span class="ml-auto text-xs font-normal text-ink-faint" x-text="abierto ? 'Ocultar' : 'Mostrar'"></span>
        </button>

        <div x-show="abierto" x-cloak class="grid gap-3 border-t border-line p-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="field-label text-xs">Número</span>
                <input type="text" wire:model.live.debounce.400ms="tranNumber" class="field-input mt-1 py-1.5 text-sm" placeholder="F-1234">
            </label>

            <label class="block">
                <span class="field-label text-xs">Booking</span>
                <input type="text" wire:model.live.debounce.400ms="bookingNumber" class="field-input mt-1 py-1.5 text-sm" placeholder="MEX…">
            </label>

            <label class="block">
                <span class="field-label text-xs">Cliente o proveedor</span>
                <input type="text" wire:model.live.debounce.400ms="appliedTo" class="field-input mt-1 py-1.5 text-sm" placeholder="Nombre">
            </label>

            <label class="block">
                <span class="field-label text-xs">Fechas <span class="text-ink-faint">(dd/mm/aaaa - dd/mm/aaaa)</span></span>
                <input type="text" wire:model.live.debounce.600ms="dates" class="field-input mt-1 py-1.5 text-sm" placeholder="01/01/2025 - 31/12/2025">
            </label>

            <label class="block">
                <span class="field-label text-xs">Compañía</span>
                <select wire:model.live="companyId" class="field-input mt-1 py-1.5 text-sm">
                    <option value="">Todas</option>
                    @foreach ($companies as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">Divisa</span>
                <select wire:model.live="accountId" class="field-input mt-1 py-1.5 text-sm">
                    <option value="">Todas</option>
                    @foreach ($currencies as $id => $prefix)
                        <option value="{{ $id }}">{{ $prefix }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">Estado de pago</span>
                <select wire:model.live="paid" class="field-input mt-1 py-1.5 text-sm">
                    <option value="">Todas</option>
                    <option value="0">Sin pagar</option>
                    <option value="2">Parciales</option>
                    <option value="1">Pagadas</option>
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">Canceladas</span>
                <select wire:model.live="showCancelled" class="field-input mt-1 py-1.5 text-sm">
                    <option value="0">Solo vigentes</option>
                    <option value="1">Vigentes y canceladas</option>
                    <option value="2">Solo canceladas</option>
                </select>
            </label>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <button type="button" wire:click="clearFilters" class="btn-ghost !py-1.5 !px-3 text-xs">Limpiar filtros</button>
                <button type="button" wire:click="calculateTotals" class="btn-ghost !py-1.5 !px-3 text-xs">
                    Sumar todo el filtro
                </button>
                <label class="ml-auto flex items-center gap-2 text-xs text-ink-muted">
                    Por página
                    <select wire:model.live="perPage" class="field-input !w-auto py-1 text-xs">
                        @foreach ([25, 50, 100, 200] as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    {{-- Resultados. Tabla completa desde `md`; en móvil, tarjetas con los
         campos que de verdad se consultan de pie frente a un contenedor. --}}
    <div class="relative rounded-xl border border-line bg-panel">

        {{-- Velo de carga. Con `delay` para que un filtro rápido no parpadee.
             OJO: el modificador de display (`.flex`, `.block`…) NO se puede
             combinar con `.delay` — Livewire solo genera la regla que oculta el
             elemento para los modificadores sueltos, y el velo se quedaría
             visible tapando la tabla hasta que arrancara su JavaScript. Al estar
             posicionado en absoluto, el navegador ya lo trata como bloque, así
             que basta con centrar el aviso con `text-center`. --}}
        <div wire:loading.delay
             class="absolute inset-0 z-20 rounded-xl bg-panel/75 text-center backdrop-blur-[1px]">
            <span class="mt-14 inline-flex items-center gap-2 rounded-full border border-line bg-panel px-4 py-2 text-sm text-ink-muted shadow-lg">
                <x-spinner class="h-4 w-4 text-brand" />
                Actualizando…
            </span>
        </div>

        {{-- Tarjetas (móvil) --}}
        <ul class="divide-y divide-line md:hidden">
            @forelse ($rows as $row)
                @php $status = PaymentStatus::for($row); @endphp
                <li class="space-y-2 p-4 {{ $row->cancelled ? 'opacity-50' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('transactions.booking', $row->booking) }}" wire:navigate
                               class="font-semibold text-brand hover:underline">
                                {{ trim((string) $row->booking_number) ?: '—' }}
                            </a>
                            <a href="{{ route('transactions.show', $row->transc_id) }}" wire:navigate
                               class="block truncate text-sm text-ink hover:underline">
                                {{ $row->tran_number ?: 'Sin número' }}
                            </a>
                        </div>
                        <span class="{{ $status->classes() }} shrink-0">{{ $status->label() }}</span>
                    </div>

                    <p class="truncate text-sm text-ink-muted">{{ $row->customerName ?: ($row->vendorName ?: '—') }}</p>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div class="flex justify-between gap-2">
                            <dt class="text-ink-faint">Fecha</dt>
                            <dd class="text-ink-soft">{{ $row->tran_date ? \Illuminate\Support\Carbon::parse($row->tran_date)->format('d/m/Y') : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-ink-faint">Moneda</dt>
                            <dd class="text-ink-soft">{{ $row->currency ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-ink-faint">Total</dt>
                            <dd class="font-semibold tabular-nums {{ (float) $row->total_amount < 0 ? 'text-brand' : 'text-ink' }}">{{ $money($row->total_amount) }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-ink-faint">Pagado</dt>
                            <dd class="tabular-nums text-ink-soft">{{ $money($row->tran_paid_amount) }}</dd>
                        </div>
                    </dl>
                </li>
            @empty
                <li class="px-4 py-12 text-center text-sm text-ink-faint">No hay transacciones con estos filtros.</li>
            @endforelse
        </ul>

        {{-- Tabla (desde md) --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line bg-panel text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        @foreach ($columns as [$sortKey, $label, $align])
                            <th class="whitespace-nowrap px-3 py-2.5 font-semibold {{ $align }}">
                                @if ($sortKey)
                                    <button type="button" wire:click="sortBy('{{ $sortKey }}')" class="inline-flex items-center gap-1 transition hover:text-ink">
                                        {{ $label }}
                                        @if ($sort === $sortKey)
                                            <span class="text-brand">{{ $direction === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </button>
                                @else
                                    {{ $label }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        @php $status = PaymentStatus::for($row); @endphp
                        <tr class="transition hover:bg-raised {{ $row->cancelled ? 'opacity-50' : '' }}">
                            <td class="whitespace-nowrap px-3 py-2">
                                <a href="{{ route('transactions.booking', $row->booking) }}" wire:navigate
                                   class="text-brand hover:underline">{{ trim((string) $row->booking_number) ?: '—' }}</a>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink-muted">
                                {{ $row->tran_date ? \Illuminate\Support\Carbon::parse($row->tran_date)->format('d/m/Y') : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <a href="{{ route('transactions.show', $row->transc_id) }}" wire:navigate
                                   class="text-ink hover:text-brand hover:underline">{{ $row->tran_number ?: 'Ver' }}</a>
                            </td>
                            <td class="max-w-[16rem] truncate px-3 py-2 text-ink-muted" title="{{ $row->customerName ?: $row->vendorName }}">
                                {{ $row->customerName ?: ($row->vendorName ?: '—') }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink-muted">{{ $row->companyName ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-ink-muted">{{ $row->currency ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-soft">{{ $money($row->amount_original) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-faint">
                                {{ $row->exchange_value === null ? '—' : number_format((float) $row->exchange_value, 4) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-muted">{{ $money($row->sub_0_mxn) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-muted">{{ $money($row->sub_16_mxn) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-muted">{{ $money($row->tax_16_mxn) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-muted">{{ $money($row->tax_ret_mxn) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums {{ (float) $row->total_amount < 0 ? 'text-brand' : 'text-ink' }}">
                                {{ $money($row->total_amount) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-ink-muted">{{ $money($row->tran_paid_amount) }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span class="{{ $status->classes() }}">{{ $status->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-[11px] text-ink-faint" title="{{ $row->seal }}">
                                {{ $row->seal ? \Illuminate\Support\Str::limit($row->seal, 8, '…') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-3 py-12 text-center text-ink-faint">
                                No hay transacciones con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($totals)
                    <tfoot class="border-t border-line bg-panel text-sm font-semibold">
                        <tr>
                            <td colspan="6" class="px-3 py-2.5 text-ink-muted">Total del filtro completo</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-ink">{{ $money($totals['amount_original']) }}</td>
                            <td></td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-ink-soft">{{ $money($totals['sub_0_mxn']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-ink-soft">{{ $money($totals['sub_16_mxn']) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-ink-soft">{{ $money($totals['tax_16_mxn']) }}</td>
                            <td></td>
                            <td class="px-3 py-2.5 text-right tabular-nums text-ink">{{ $money($totals['total_amount']) }}</td>
                            <td colspan="3" class="px-3 py-2.5 text-right text-ink-muted">
                                Por cobrar/pagar: {{ $money($totals['left_to_pay']) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Totales del filtro, en móvil: el `tfoot` de la tabla no se ve ahí. --}}
    @if ($totals)
        <div class="card space-y-1.5 p-4 text-sm md:hidden">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-faint">Total del filtro completo</p>
            <div class="flex justify-between gap-2">
                <span class="text-ink-muted">Total</span>
                <span class="font-semibold tabular-nums text-ink">{{ $money($totals['total_amount']) }}</span>
            </div>
            <div class="flex justify-between gap-2">
                <span class="text-ink-muted">Por cobrar/pagar</span>
                <span class="tabular-nums text-ink-soft">{{ $money($totals['left_to_pay']) }}</span>
            </div>
        </div>
    @endif

    <div>{{ $rows->links() }}</div>
</div>
