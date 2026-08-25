<?php

namespace App\Livewire\Portal;

use App\Models\Frego\Client;
use App\Models\Frego\Provider;
use App\Queries\BookingFilters;
use App\Queries\BookingQuery;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portal del cliente y del proveedor.
 *
 * En el sistema actual esto vive en una aplicación aparte que comparte la base;
 * aquí es una sección más, con la misma sesión y los mismos datos, pero acotada
 * a lo que le toca ver a quien entra.
 *
 * **La acotación no es cosmética.** Todas las consultas se filtran por el cliente
 * o el proveedor de la cuenta, que sale de la sesión y nunca de la petición: no
 * hay forma de pedir los documentos de otro cambiando un parámetro.
 */
class PortalHome extends Component
{
    use WithPagination;

    /** documentos | embarques */
    #[Url(as: 'ver', except: 'documentos')]
    public string $tab = 'documentos';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function paginationView(): string
    {
        return 'vendor.pagination.frego';
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function isClient(): bool
    {
        return auth()->user()?->portalClientId() !== null;
    }

    public function partyName(): string
    {
        $usuario = auth()->user();

        return $this->isClient()
            ? (string) ($usuario->client_id ? Client::find($usuario->client_id)?->fullName : '')
            : (string) ($usuario->provider_id ? Provider::find($usuario->provider_id)?->fullName : '');
    }

    /** Facturas del cliente, o costos del proveedor. */
    private function documents(): LengthAwarePaginator
    {
        $usuario = auth()->user();

        $filtros = TransactionFilters::make([
            'tran_number' => $this->search ?: null,
            'showCancelled' => 1,
        ]);

        if (($clienteId = $usuario->portalClientId()) !== null) {
            $filtros->customer = $clienteId;
            $filtros->type = [0];
        } else {
            $filtros->vendor = $usuario->portalProviderId();
            $filtros->type = [1, 2];
            $filtros->paymentMode = true;
        }

        return TransactionQuery::make($filtros)->paginate(25, $this->getPage());
    }

    /** Embarques del cliente. Un proveedor no tiene bookings propios. */
    private function bookings(): Collection
    {
        $clienteId = auth()->user()->portalClientId();

        if ($clienteId === null) {
            return collect();
        }

        $filtros = BookingFilters::make(['booking_number' => $this->search ?: null]);
        $filtros->client = $clienteId;

        return BookingQuery::make($filtros)->get(50);
    }

    public function render()
    {
        return view('livewire.portal.portal-home', [
            'documentos' => $this->tab === 'documentos' ? $this->documents() : null,
            'embarques' => $this->tab === 'embarques' ? $this->bookings() : collect(),
        ])->layout('components.portal-layout', [
            'title' => $this->isClient() ? 'Mis facturas y embarques' : 'Mis documentos',
        ]);
    }
}
