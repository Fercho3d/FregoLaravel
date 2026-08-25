@use('App\Support\PaymentStatus')

@php
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
    $esCliente = $this->isClient();
@endphp

<div class="space-y-4">

    <header>
        <h2 class="text-lg font-semibold text-ink">
            {{ $esCliente ? 'Mis facturas y embarques' : 'Mis documentos' }}
        </h2>
        <p class="text-sm text-ink-muted">{{ $this->partyName() ?: '—' }}</p>
    </header>

    {{-- Pestañas: un proveedor no tiene embarques propios --}}
    @if ($esCliente)
        <nav class="flex flex-wrap items-center gap-1 rounded-xl border border-line bg-panel p-1 text-sm">
            @foreach (['documentos' => 'Facturas', 'embarques' => 'Embarques'] as $clave => $etiqueta)
                <button type="button" wire:click="$set('tab', '{{ $clave }}')"
                        class="rounded-lg px-4 py-2 font-medium transition
                               {{ $tab === $clave ? 'bg-accent-500 text-white' : 'text-ink-muted hover:bg-raised hover:text-ink' }}">
                    {{ $etiqueta }}
                </button>
            @endforeach
        </nav>
    @endif

    <label class="block">
        <span class="sr-only">Buscar</span>
        <input type="search" wire:model.live.debounce.400ms="search" value="{{ $search }}"
               class="field-input py-2 text-sm"
               placeholder="{{ $tab === 'embarques' ? 'Buscar por número de booking…' : 'Buscar por número de documento…' }}">
    </label>

    <div class="relative rounded-xl border border-line bg-panel">
        <div wire:loading.delay class="absolute inset-0 z-20 rounded-xl bg-panel/75 text-center backdrop-blur-[1px]">
            <span class="mt-14 inline-flex items-center gap-2 rounded-full border border-line bg-panel px-4 py-2 text-sm text-ink-muted shadow-lg">
                <x-spinner class="h-4 w-4 text-brand" /> Actualizando…
            </span>
        </div>

        @if ($tab === 'embarques')
            <ul class="divide-y divide-line">
                @forelse ($embarques as $embarque)
                    <li class="space-y-1.5 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-ink">{{ trim((string) $embarque->booking_number) ?: 'Sin número' }}</p>
                            <span class="text-xs tabular-nums text-ink-muted">
                                {{ number_format((float) $embarque->total_completed, 0) }}% completado
                            </span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-raised">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, (float) $embarque->total_completed) }}%"></div>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 pt-1 text-xs sm:grid-cols-4">
                            @foreach ([
                                ['Buque', $embarque->vessel_name],
                                ['Destino', $embarque->discharge_name],
                                ['Carga', $fecha($embarque->loading_EDT)],
                                ['Arribo', $fecha($embarque->dicharge_ETA)],
                            ] as [$etiqueta, $valor])
                                <div>
                                    <dt class="text-ink-faint">{{ $etiqueta }}</dt>
                                    <dd class="truncate text-ink-soft">{{ $valor ?: '—' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </li>
                @empty
                    <li class="px-4 py-12 text-center text-sm text-ink-faint">No hay embarques que mostrar.</li>
                @endforelse
            </ul>
        @else
            <ul class="divide-y divide-line">
                @forelse ($documentos as $documento)
                    @php $estado = PaymentStatus::for($documento); @endphp
                    <li class="space-y-2 p-4 {{ $documento->cancelled ? 'opacity-60' : '' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $documento->tran_number ?: 'Sin número' }}</p>
                                <p class="text-xs text-ink-muted">
                                    Booking {{ trim((string) $documento->booking_number) ?: '—' }} · {{ $fecha($documento->tran_date) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($documento->cancelled)
                                    <span class="badge badge-danger">Cancelada</span>
                                @endif
                                <span class="{{ $estado->classes() }}">{{ $estado->label() }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="text-sm font-semibold tabular-nums text-ink">
                                {{ $money($documento->total_natural_amount) }} {{ $documento->currency }}
                            </span>

                            <div class="flex flex-wrap gap-2">
                                @foreach ([['pdf', $documento->pdf_attach], ['xml', $documento->xml_attach]] as [$tipo, $archivo])
                                    @if ($archivo)
                                        <a href="{{ route('portal.file', [$documento->transc_id, $tipo]) }}" target="_blank"
                                           class="btn-ghost !px-3 !py-1 text-xs uppercase">{{ $tipo }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-4 py-12 text-center text-sm text-ink-faint">No hay documentos que mostrar.</li>
                @endforelse
            </ul>
        @endif
    </div>

    @if ($tab !== 'embarques' && $documentos !== null)
        <div>{{ $documentos->links() }}</div>
    @endif
</div>
