@php
    $money = fn ($v) => number_format((float) $v, 2);
    $esVenta = $this->isSale();
    $esAdmin = auth()->user()?->isAdmin() ?? false;
@endphp

<div class="space-y-4">

    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-ink">Servicios y precios</h2>
            <p class="text-sm text-ink-muted">
                El precio pactado con cada tercero. Un servicio en <strong>0</strong> es precio abierto:
                se captura a mano al agregar el concepto.
            </p>
        </div>

        @if ($esAdmin)
            <button type="button" wire:click="create" class="btn-accent !px-3 !py-1.5 text-xs">Agregar</button>
        @endif
    </header>

    {{-- Filtros --}}
    <div class="card p-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="block">
                <span class="field-label text-xs">Tipo</span>
                <select wire:model.live="type" class="field-input mt-1 py-1.5 text-sm">
                    @foreach (['1' => 'De venta (cliente)', '2' => 'De compra (proveedor)'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected((string) $valor === $type)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">{{ $esVenta ? 'Cliente' : 'Proveedor' }}</span>
                <select wire:model.live="partyId" class="field-input mt-1 py-1.5 text-sm">
                    <option value="">Todos</option>
                    @foreach ($terceros as $id => $nombre)
                        <option value="{{ $id }}" @selected((string) $id === $partyId)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="field-label text-xs">Descripción</span>
                <input type="search" wire:model.live.debounce.300ms="search" value="{{ $search }}"
                       class="field-input mt-1 py-1.5 text-sm" placeholder="Buscar…">
            </label>
        </div>
    </div>

    {{-- Formulario --}}
    @if ($editing !== null)
        <form wire:submit="save" class="card space-y-4 p-5">
            <p class="text-sm font-medium text-ink">{{ $editing === 0 ? 'Nuevo servicio' : 'Editar servicio' }}</p>

            @include('partials.validation-errors')

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block lg:col-span-2">
                    <span class="field-label">Descripción</span>
                    <input type="text" wire:model="form.description" value="{{ $form['description'] ?? '' }}" class="field-input mt-1.5" required>
                    @error('form.description') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">{{ $esVenta ? 'Cliente' : 'Proveedor' }}</span>
                    <select wire:model="form.party_id" class="field-input mt-1.5" required>
                        <option value="">Selecciona</option>
                        @foreach ($terceros as $id => $nombre)
                            <option value="{{ $id }}" @selected((string) $id === (string) ($form['party_id'] ?? ''))>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('form.party_id') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">Tipo de cargo</span>
                    <select wire:model="form.charge_type_id" class="field-input mt-1.5" required>
                        <option value="">Selecciona</option>
                        @foreach ($tiposDeCargo as $id => $nombre)
                            <option value="{{ $id }}" @selected((string) $id === (string) ($form['charge_type_id'] ?? ''))>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('form.charge_type_id') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="field-label">
                        Precio <span class="font-normal text-ink-faint">(0 = abierto)</span>
                    </span>
                    <input type="number" step="0.0001" min="0" wire:model="form.price" value="{{ $form['price'] ?? '' }}"
                           class="field-input mt-1.5 text-right tabular-nums" required>
                    @error('form.price') <span class="mt-1 block text-xs text-brand">{{ $message }}</span> @enderror
                </label>

                <label class="flex items-end gap-2 pb-2.5 text-sm text-ink-soft">
                    <input type="checkbox" wire:model="form.active" @checked($form['active'] ?? false)
                           class="h-4 w-4 rounded border-line bg-panel text-accent-500 focus:ring-accent-500">
                    Activo
                </label>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-line pt-4">
                <button type="button" wire:click="cancel" class="btn-ghost !px-3 !py-1.5 text-xs">Cancelar</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-accent !px-3 !py-1.5 text-xs">
                    <x-spinner wire:loading wire:target="save" class="h-3.5 w-3.5" />
                    Guardar
                </button>
            </div>
        </form>
    @endif

    {{-- Listado --}}
    <div class="relative rounded-xl border border-line bg-panel">
        <div wire:loading.delay class="absolute inset-0 z-20 rounded-xl bg-panel/75 text-center backdrop-blur-[1px]">
            <span class="mt-14 inline-flex items-center gap-2 rounded-full border border-line bg-panel px-4 py-2 text-sm text-ink-muted shadow-lg">
                <x-spinner class="h-4 w-4 text-brand" /> Actualizando…
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Descripción</th>
                        <th class="px-4 py-2.5 text-left font-semibold">{{ $esVenta ? 'Cliente' : 'Proveedor' }}</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Tipo de cargo</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Precio</th>
                        <th class="px-4 py-2.5 text-left font-semibold">Estado</th>
                        @if ($esAdmin)
                            <th class="px-4 py-2.5 text-right font-semibold"><span class="sr-only">Acciones</span></th>
                        @endif
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @forelse ($servicios as $servicio)
                        <tr class="transition hover:bg-raised {{ $servicio->active ? '' : 'opacity-60' }}">
                            <td class="max-w-[22rem] truncate px-4 py-2 text-ink" title="{{ $servicio->description }}">
                                {{ $servicio->description ?: '—' }}
                            </td>
                            <td class="max-w-[14rem] truncate px-4 py-2 text-ink-muted">
                                {{ ($esVenta ? $servicio->client_name : $servicio->provider_name) ?: '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-ink-muted">{{ $servicio->charge_type_name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-ink-soft">
                                @if ((float) $servicio->price === 0.0)
                                    <span class="badge badge-warn">Abierto</span>
                                @else
                                    {{ $money($servicio->price) }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <span class="badge {{ $servicio->active ? 'badge-ok' : 'badge-neutral' }}">
                                    {{ $servicio->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            @if ($esAdmin)
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <div class="flex justify-end gap-3 text-xs">
                                        <button type="button" wire:click="edit({{ $servicio->service_id }})" class="text-brand hover:underline">Editar</button>
                                        <button type="button" wire:click="toggleActive({{ $servicio->service_id }})"
                                                class="text-ink-muted transition hover:text-brand">
                                            {{ $servicio->active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $esAdmin ? 6 : 5 }}" class="px-4 py-12 text-center text-ink-faint">
                                No hay servicios con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>{{ $servicios->links() }}</div>
</div>
