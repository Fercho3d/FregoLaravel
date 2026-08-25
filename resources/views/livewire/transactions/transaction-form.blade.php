@use('App\Models\Frego\Transaction')

@php
    $bloqueada = $this->isLocked();
    $esFactura = $this->isInvoice();
    $numeroEditable = $this->numberIsEditable();
    // Con el documento bloqueado, la fecha sigue abierta para el super
    // administrador: es lo que en el original hacía el lápiz de `modify-date`.
    $fechaBloqueada = $bloqueada && ! $dateIsEditable;
@endphp

<div class="mx-auto max-w-3xl space-y-4">

    <a href="{{ route('transactions.booking', $bookingId) }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-sm text-ink-muted transition hover:text-ink">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Transacciones del booking {{ trim((string) ($booking?->booking_number ?? '')) }}
    </a>

    {{-- Los campos llevan `value` y `@selected` explícitos además de `wire:model`:
         Livewire rellena los valores al arrancar su JavaScript, y sin esto el
         formulario se pinta vacío hasta que eso ocurre. --}}
    <form wire:submit="save" class="card space-y-5 p-5 sm:p-6">
        <header>
            <h2 class="text-lg font-semibold text-ink">{{ $this->title() }}</h2>
            <p class="mt-0.5 text-sm text-ink-muted">
                Booking {{ trim((string) ($booking?->booking_number ?? '—')) }}
            </p>
        </header>

        @if ($bloqueada)
            <p class="flex items-start gap-2 rounded-lg border border-line bg-raised px-3 py-2 text-xs text-ink-muted">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="5" y="11" width="14" height="9" rx="2"/><path stroke-linecap="round" d="M8 11V8a4 4 0 0 1 8 0v3"/>
                </svg>
                <span>
                    {{ $lockReason }}
                    Solo puedes cambiar la compañía emisora{{ $dateIsEditable ? ' y la fecha' : '' }}.
                </span>
            </p>
        @endif

        @include('partials.validation-errors')

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="field-label">Fecha</span>
                <input type="date" wire:model="tranDate" value="{{ $tranDate }}"
                       @disabled($fechaBloqueada) class="field-input mt-1.5" required>
                @error('tranDate') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">
                    Número
                    @unless ($numeroEditable)
                        <span class="font-normal text-ink-faint">(lo asigna el consecutivo)</span>
                    @endunless
                </span>
                <input type="text" wire:model="tranNumber" value="{{ $tranNumber }}" maxlength="128"
                       @disabled($bloqueada || ! $numeroEditable) class="field-input mt-1.5">
                @error('tranNumber') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="field-label">Moneda</span>
                <select wire:model="accountId" @disabled($bloqueada) class="field-input mt-1.5" required>
                    <option value="">Selecciona la moneda</option>
                    @foreach ($currencies as $id => $prefijo)
                        <option value="{{ $id }}" @selected((string) $id === $accountId)>{{ $prefijo }}</option>
                    @endforeach
                </select>
                @error('accountId') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
            </label>

            @if ($esFactura)
                <label class="block">
                    <span class="field-label">Tipo de factura</span>
                    <select wire:model.live="invoiceType" @disabled($bloqueada) class="field-input mt-1.5">
                        @foreach ([
                            Transaction::INVOICE_TYPE_NORMAL => 'Normal',
                            Transaction::INVOICE_TYPE_HISTORY => 'Histórica',
                            Transaction::INVOICE_TYPE_CREDIT => 'Nota de crédito',
                        ] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected((string) $valor === $invoiceType)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('invoiceType') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                </label>
            @endif
        </div>

        <label class="block">
            <span class="field-label">{{ $esFactura ? 'Cliente' : 'Proveedor' }}</span>
            <select wire:model="{{ $esFactura ? 'customerId' : 'vendorId' }}" @disabled($bloqueada)
                    class="field-input mt-1.5" required>
                <option value="">Selecciona {{ $esFactura ? 'el cliente' : 'el proveedor' }}</option>
                @php $seleccionado = $esFactura ? $customerId : $vendorId; @endphp
                @foreach ($esFactura ? $clients : $providers as $id => $nombre)
                    <option value="{{ $id }}" @selected((string) $id === $seleccionado)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error($esFactura ? 'customerId' : 'vendorId')
                <span class="mt-1 block text-xs text-brand">{{ $message }}</span>
            @enderror
        </label>

        {{-- La compañía emisora se edita siempre, aunque el resto esté bloqueado:
             es el dato que hay que poder corregir después de timbrar. --}}
        <label class="block">
            <span class="field-label">Compañía emisora</span>
            <select wire:model="companyId" class="field-input mt-1.5">
                <option value="">Sin compañía</option>
                @foreach ($companies as $id => $nombre)
                    <option value="{{ $id }}" @selected((string) $id === $companyId)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error('companyId') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
        </label>

        @if ($esFactura && (int) $invoiceType === Transaction::INVOICE_TYPE_HISTORY)
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="field-label">Sello CFDI</span>
                    <input type="text" wire:model="seal" value="{{ $seal }}" maxlength="128" @disabled($bloqueada) class="field-input mt-1.5">
                    <span class="mt-1 block text-xs text-ink-faint">Déjalo vacío para mantener la factura abierta.</span>
                    @error('seal') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                </label>

                @if (filled($newSeal))
                    <label class="block">
                        <span class="field-label">Sello nuevo</span>
                        <input type="text" wire:model="newSeal" value="{{ $newSeal }}" maxlength="128" @disabled($bloqueada) class="field-input mt-1.5">
                        @error('newSeal') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                    </label>
                @endif
            </div>
        @endif

        <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-line pt-4">
            <a href="{{ $transactionId ? route('transactions.show', $transactionId) : route('transactions.booking', $bookingId) }}"
               wire:navigate class="btn-ghost">Cancelar</a>

            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-accent">
                <x-spinner wire:loading wire:target="save" class="h-4 w-4" />
                <span wire:loading.remove wire:target="save">{{ $transactionId ? 'Guardar cambios' : 'Crear transacción' }}</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
        </footer>
    </form>
</div>
