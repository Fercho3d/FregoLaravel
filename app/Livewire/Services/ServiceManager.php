<?php

namespace App\Livewire\Services;

use App\Models\Frego\Client;
use App\Models\Frego\Provider;
use App\Models\Frego\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Servicios contratados: el precio pactado con cada cliente y con cada proveedor.
 *
 * Es el catálogo del que salen los conceptos de una transacción, y por eso no
 * cabía entre los catálogos planos: cada servicio pertenece a un cliente **o** a
 * un proveedor, y a un tipo de cargo que decide su IVA.
 *
 * Un servicio con **precio 0 es un precio abierto**: al capturar el concepto se
 * escribe a mano. Cualquier otro precio queda fijo.
 */
class ServiceManager extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** 1 = de venta (cliente), 2 = de compra (proveedor). */
    #[Url(as: 'tipo', except: '1')]
    public string $type = '1';

    #[Url(as: 'tercero', except: '')]
    public string $partyId = '';

    public ?int $editing = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function paginationView(): string
    {
        return 'vendor.pagination.frego';
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }

        if ($property === 'type') {
            $this->partyId = '';
        }
    }

    public function isSale(): bool
    {
        return (int) $this->type === Service::TYPE_CLIENT;
    }

    /** @return array<int, string> */
    public function parties(): array
    {
        return $this->isSale() ? Client::options() : Provider::options();
    }

    // ------------------------------------------------------------ Edición

    public function create(): void
    {
        $this->assertAdmin();

        $this->editing = 0;
        $this->form = [
            'description' => '',
            'price' => '0',
            'charge_type_id' => '',
            'party_id' => $this->partyId,
            'active' => true,
        ];
        $this->resetErrorBag();
    }

    public function edit(int $id): void
    {
        $this->assertAdmin();

        $servicio = Service::findOrFail($id);

        $this->editing = $id;
        $this->form = [
            'description' => (string) $servicio->description,
            'price' => (string) ($servicio->price ?? 0),
            'charge_type_id' => (string) $servicio->charge_type_id,
            'party_id' => (string) ($servicio->client_id ?: $servicio->provider_id),
            'active' => (bool) $servicio->active,
        ];
        $this->type = (string) $servicio->type;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'form']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->assertAdmin();

        $datos = $this->validate([
            'form.description' => ['required', 'string', 'max:255'],
            'form.price' => ['required', 'numeric', 'min:0'],
            'form.charge_type_id' => ['required', Rule::exists('charge_type', 'charge_type_id')],
            'form.party_id' => [
                'required',
                $this->isSale()
                    ? Rule::exists('client', 'client_id')
                    : Rule::exists('provider', 'provider_id'),
            ],
        ], attributes: [
            'form.description' => 'descripción',
            'form.price' => 'precio',
            'form.charge_type_id' => 'tipo de cargo',
            'form.party_id' => $this->isSale() ? 'cliente' : 'proveedor',
        ])['form'];

        $valores = [
            'description' => $datos['description'],
            'price' => (float) $datos['price'],
            'charge_type_id' => (int) $datos['charge_type_id'],
            'type' => (int) $this->type,
            'client_id' => $this->isSale() ? (int) $datos['party_id'] : null,
            'provider_id' => $this->isSale() ? null : (int) $datos['party_id'],
            'active' => ($this->form['active'] ?? false) ? 1 : 0,
            'modified_by' => auth()->id(),
        ];

        $this->editing === 0
            ? DB::table('service')->insert($valores + ['created_by' => auth()->id(), 'created_at' => now()])
            : DB::table('service')->where('service_id', $this->editing)->update($valores);

        session()->flash('status', $this->editing === 0 ? 'Servicio creado.' : 'Servicio actualizado.');
        $this->cancel();
    }

    /** Se desactiva, no se borra: hay conceptos históricos que lo referencian. */
    public function toggleActive(int $id): void
    {
        $this->assertAdmin();

        $servicio = Service::findOrFail($id);
        $servicio->forceFill(['active' => $servicio->active ? 0 : 1])->save();
    }

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    public function render()
    {
        $servicios = DB::table('service as s')
            ->leftJoin('charge_type as ct', 'ct.charge_type_id', '=', 's.charge_type_id')
            ->leftJoin('client as c', 'c.client_id', '=', 's.client_id')
            ->leftJoin('provider as p', 'p.provider_id', '=', 's.provider_id')
            ->where('s.type', (int) $this->type)
            ->when($this->partyId !== '', fn ($q) => $this->isSale()
                ? $q->where('s.client_id', (int) $this->partyId)
                : $q->where('s.provider_id', (int) $this->partyId))
            ->when($this->search !== '', fn ($q) => $q->where('s.description', 'like', '%'.$this->search.'%'))
            ->orderBy('s.description')
            ->paginate(25, [
                's.service_id', 's.description', 's.price', 's.active',
                'ct.charge_type_name', 'c.fullName as client_name', 'p.fullName as provider_name',
            ], 'page', $this->getPage());

        return view('livewire.services.service-manager', [
            'servicios' => $servicios,
            'terceros' => $this->parties(),
            'tiposDeCargo' => DB::table('charge_type')->where('deleted', 0)
                ->orderBy('charge_type_name')->pluck('charge_type_name', 'charge_type_id')->all(),
        ])->layout('components.app-layout', ['title' => 'Servicios y precios']);
    }
}
