<?php

namespace App\Livewire\Operations;

use App\Models\Frego\Booking;
use App\Models\Frego\Client;
use App\Models\Frego\Provider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Alta y edición de un booking.
 *
 * Es el formulario más grande del sistema. En Yii2 vivía repartido en pestañas
 * de una pantalla de 376 líneas; aquí se agrupa por lo que significa cada cosa:
 * identificación, transporte, ruta, carga y notas.
 *
 * Un booking **cerrado** (`locked`) no se edita: es la señal de que operación ya
 * lo dio por terminado y su facturación quedó fija.
 */
class BookingForm extends Component
{
    public ?int $bookingId = null;

    public bool $locked = false;

    // --- Identificación ---
    public string $bookingNumber = '';

    public ?string $hb = null;

    public ?string $customerReference = null;

    public string $clientId = '';

    public ?string $bookingType = null;

    // --- Transporte ---
    public string $vesselId = '';

    /** Nombre de un buque que aún no está en el catálogo. */
    public string $newVessel = '';

    public ?string $carrierId = null;

    public ?string $transportId = null;

    public ?string $brokerId = null;

    // --- Ruta ---
    public string $loadingPort = '';

    public string $loadingDate = '';

    public string $dischargePort = '';

    public string $arrivalDate = '';

    public string $pickupPlace = '';

    public ?string $finalDestination = null;

    // --- Carga ---
    public ?string $containerType = null;

    public ?string $commodity = null;

    public ?string $setPoint = null;

    public ?string $remarks = null;

    public function mount(?int $booking = null): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        if ($booking === null) {
            $this->loadingDate = now()->toDateString();
            $this->arrivalDate = now()->addWeeks(3)->toDateString();

            return;
        }

        $modelo = Booking::findOrFail($booking);

        $this->bookingId = $modelo->booking_id;
        $this->locked = (bool) $modelo->locked;
        $this->bookingNumber = (string) $modelo->booking_number;
        $this->hb = $modelo->HB;
        $this->customerReference = $modelo->customer_reference;
        $this->clientId = (string) $modelo->client;
        $this->bookingType = $this->asOption($modelo->booking_type);
        $this->vesselId = (string) $modelo->vessel;
        $this->carrierId = $this->asOption($modelo->carrier_id);
        $this->transportId = $this->asOption($modelo->transport_id);
        $this->brokerId = $this->asOption($modelo->custom_brocker_id);
        $this->loadingPort = (string) $modelo->loading_port;
        $this->loadingDate = $modelo->loading_EDT?->toDateString() ?? '';
        $this->dischargePort = (string) $modelo->dicharge_port_id;
        $this->arrivalDate = $modelo->dicharge_ETA?->toDateString() ?? '';
        $this->pickupPlace = (string) $modelo->pick_up_place_id;
        $this->finalDestination = $this->asOption($modelo->final_destination_id);
        $this->containerType = $this->asOption($modelo->container_type);
        $this->commodity = $modelo->commodity;
        $this->setPoint = $modelo->set_point;
        $this->remarks = $modelo->remarks;
    }

    private function asOption(mixed $valor): ?string
    {
        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
        abort_if($this->locked, 422, 'Este booking está cerrado y no se puede editar.');

        $this->blanksToNull();

        $datos = $this->validate([
            'bookingNumber' => ['required', 'string', 'max:128'],
            'hb' => ['nullable', 'string', 'max:64'],
            'customerReference' => ['nullable', 'string', 'max:64'],
            'clientId' => ['required', Rule::exists('client', 'client_id')],
            'bookingType' => ['nullable', 'string', 'max:25'],
            'vesselId' => [Rule::requiredIf(blank($this->newVessel)), 'nullable', Rule::exists('vessel', 'vessel_id')],
            'newVessel' => ['nullable', 'string', 'max:100'],
            'carrierId' => ['nullable', Rule::exists('provider', 'provider_id')],
            'transportId' => ['nullable', Rule::exists('provider', 'provider_id')],
            'brokerId' => ['nullable', Rule::exists('provider', 'provider_id')],
            'loadingPort' => ['required', Rule::exists('loading_ports', 'port_id')],
            'loadingDate' => ['required', 'date'],
            'dischargePort' => ['required', Rule::exists('dicharge_port', 'dicharge_port_id')],
            'arrivalDate' => ['required', 'date', 'after_or_equal:loadingDate'],
            'pickupPlace' => ['required', Rule::exists('pickup_place', 'pick_id')],
            'finalDestination' => ['nullable', Rule::exists('final_destination', 'final_destination_id')],
            'containerType' => ['nullable', Rule::exists('container_types', 'contType_id')],
            'commodity' => ['nullable', 'string', 'max:50'],
            'setPoint' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
        ], attributes: $this->etiquetas());

        $modelo = $this->bookingId === null ? new Booking : Booking::findOrFail($this->bookingId);

        $modelo->forceFill([
            'booking_number' => $datos['bookingNumber'],
            'HB' => $datos['hb'],
            'customer_reference' => $datos['customerReference'],
            'client' => (int) $datos['clientId'],
            'booking_type' => $datos['bookingType'],
            'vessel' => $this->resolveVessel(),
            'carrier_id' => $this->entero($datos['carrierId']),
            'transport_id' => $this->entero($datos['transportId']),
            'custom_brocker_id' => $this->entero($datos['brokerId']),
            'loading_port' => (int) $datos['loadingPort'],
            'loading_EDT' => $datos['loadingDate'],
            'dicharge_port_id' => (int) $datos['dischargePort'],
            'dicharge_ETA' => $datos['arrivalDate'],
            'pick_up_place_id' => (int) $datos['pickupPlace'],
            'final_destination_id' => $this->entero($datos['finalDestination']),
            'container_type' => $this->entero($datos['containerType']),
            'commodity' => $datos['commodity'],
            'set_point' => $datos['setPoint'],
            'remarks' => $datos['remarks'],
            'modified_by' => auth()->id(),
        ]);

        if ($this->bookingId === null) {
            // Nace como booking real y no como borrador: el listado del sistema
            // original solo enseña `is_draft = 0` y `mode = 10`.
            $modelo->forceFill([
                'is_draft' => 0,
                'mode' => Booking::MODE_BOOKING,
                'locked' => 0,
                'created_by' => auth()->id(),
            ]);
        }

        $modelo->save();

        session()->flash('status', $this->bookingId === null ? 'Booking creado.' : 'Booking actualizado.');
        $this->redirectRoute('operations.bookings.show', $modelo->booking_id, navigate: true);
    }

    /**
     * Buque elegido, dando de alta el catálogo si el usuario escribió uno nuevo.
     *
     * El original permite lo mismo: los buques cambian de nombre y de servicio
     * seguido, y detener la captura para ir al catálogo no tiene sentido.
     */
    private function resolveVessel(): int
    {
        if (blank($this->newVessel)) {
            return (int) $this->vesselId;
        }

        $existente = DB::table('vessel')->where('vessel_name', trim($this->newVessel))->value('vessel_id');

        return (int) ($existente ?? DB::table('vessel')->insertGetId(['vessel_name' => trim($this->newVessel)], 'vessel_id'));
    }

    private function entero(?string $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }

    /** Los selectores vacíos llegan como cadena vacía; para validar conviene null. */
    private function blanksToNull(): void
    {
        foreach ([
            'hb', 'customerReference', 'bookingType', 'carrierId', 'transportId',
            'brokerId', 'finalDestination', 'containerType', 'commodity', 'setPoint', 'remarks',
        ] as $campo) {
            if (trim((string) $this->{$campo}) === '') {
                $this->{$campo} = null;
            }
        }
    }

    /** @return array<string, string> */
    private function etiquetas(): array
    {
        return [
            'bookingNumber' => 'número de booking',
            'hb' => 'HB',
            'customerReference' => 'referencia del cliente',
            'clientId' => 'cliente',
            'vesselId' => 'buque',
            'newVessel' => 'buque nuevo',
            'carrierId' => 'naviera',
            'transportId' => 'transportista',
            'brokerId' => 'agente aduanal',
            'loadingPort' => 'puerto de carga',
            'loadingDate' => 'fecha de carga',
            'dischargePort' => 'puerto de descarga',
            'arrivalDate' => 'fecha de arribo',
            'pickupPlace' => 'lugar de recolección',
            'finalDestination' => 'destino final',
            'containerType' => 'tipo de contenedor',
            'commodity' => 'mercancía',
            'setPoint' => 'temperatura',
        ];
    }

    public function render()
    {
        return view('livewire.operations.booking-form', [
            'clientes' => Client::options(),
            'buques' => DB::table('vessel')->orderBy('vessel_name')->pluck('vessel_name', 'vessel_id')->all(),
            'navieras' => Provider::optionsByType(Provider::TYPE_CARRIER),
            'transportistas' => Provider::optionsByType(Provider::TYPE_TRANSPORT),
            'agentes' => Provider::optionsByType(Provider::TYPE_BROKER),
            'puertosCarga' => DB::table('loading_ports')->where('deleted', 0)->orderBy('port_name')->pluck('port_name', 'port_id')->all(),
            'puertosDescarga' => DB::table('dicharge_port')->where('deleted', 0)->orderBy('name')->pluck('name', 'dicharge_port_id')->all(),
            'lugares' => DB::table('pickup_place')->orderBy('name')->pluck('name', 'pick_id')->all(),
            'destinos' => DB::table('final_destination')->where('deleted', 0)->orderBy('name')->pluck('name', 'final_destination_id')->all(),
            'tiposContenedor' => DB::table('container_types')->orderBy('container_name')->pluck('container_name', 'contType_id')->all(),
        ])->layout('components.app-layout', [
            'title' => $this->bookingId === null ? 'Nuevo booking' : 'Editar booking',
        ]);
    }
}
