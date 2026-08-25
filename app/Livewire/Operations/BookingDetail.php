<?php

namespace App\Livewire\Operations;

use App\Models\Frego\Booking;
use App\Queries\BookingFilters;
use App\Queries\BookingQuery;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use App\Support\BookingFiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detalle de un booking: la ruta, los contenedores, su facturación y el avance
 * de la lista de verificación.
 *
 * Equivale a `actionView` del `BookingController` de Yii2, que repartía lo mismo
 * en varias pestañas.
 */
class BookingDetail extends Component
{
    use WithFileUploads;

    public int $bookingId;

    // --- Formulario de contenedor ---
    public bool $editingContainer = false;

    public ?int $containerId = null;

    public string $containerNumber = '';

    public string $containerSeal = '';

    public string $containerType = '';

    public string $containerQuantity = '1';

    public string $containerCommodity = '';

    public string $containerPickup = '';

    // --- Documentos del booking ---
    /** Campo al que se está subiendo, para no mezclar los archivos. */
    public ?int $uploadField = null;

    public $upload = null;

    public function mount(int $booking): void
    {
        $this->bookingId = $booking;
    }

    /** El encabezado sale del mismo motor que el listado, para que el avance cuadre. */
    private function header(): object
    {
        foreach ([10, 9] as $modo) {
            $filtros = BookingFilters::make([]);
            $filtros->mode = $modo;

            $fila = BookingQuery::make($filtros)->query()
                ->where('b.booking_id', $this->bookingId)
                ->first();

            if ($fila !== null) {
                return $fila;
            }
        }

        throw new NotFoundHttpException('No existe el booking '.$this->bookingId);
    }

    /** @return Collection<int, object> */
    private function containers(): Collection
    {
        return collect(
            DB::table('containers as c')
                ->leftJoin('container_types as ct', 'ct.contType_id', '=', 'c.container_type')
                ->where('c.booking', $this->bookingId)
                ->orderBy('c.container_ID')
                ->get([
                    'c.container_ID', 'c.number', 'c.seal', 'c.quantity', 'c.comodity',
                    'c.pick_up_date', 'ct.container_name',
                ])
        );
    }

    /** Facturas y costos del booking, con la misma aritmética que el módulo. */
    private function transactions(): Collection
    {
        $filtros = TransactionFilters::make(['booking' => $this->bookingId, 'showCancelled' => 1]);

        return TransactionQuery::make($filtros)->get();
    }

    /**
     * Las casillas de la lista de verificación con la fecha en que se marcaron.
     *
     * @return array<string, string|null>
     */
    private function checklist(): array
    {
        $fila = DB::table('check_list')->where('booking', $this->bookingId)->first();

        if ($fila === null) {
            return [];
        }

        $etiquetas = [
            'booking_number' => 'Número de booking', 'pickup_date' => 'Fecha de recolección',
            'modality' => 'Modalidad', 'doc_cut_of' => 'Corte documental', 'SI_date' => 'Instrucciones de embarque',
            'cleared' => 'Despacho aduanal', 'departure' => 'Zarpe', 'bl_payment' => 'Pago del BL',
            'swb' => 'SWB', 'vessel' => 'Buque', 'number' => 'Número', 'client' => 'Cliente',
            'loading_port' => 'Puerto de carga', 'loading_EDT' => 'Fecha de carga',
            'dicharge_port' => 'Puerto de descarga', 'container_type' => 'Tipo de contenedor',
            'commodity' => 'Mercancía', 'set_point' => 'Temperatura', 'dicharge_ETA' => 'Arribo estimado',
            'vacuum_maneuver' => 'Maniobra de vacío', 'draf_client' => 'Draft del cliente',
            'gated_IN' => 'Gate in', 'gated_out' => 'Gate out', 'delivered' => 'Entregado',
            'insurance' => 'Seguro', 'corrected_draft' => 'Draft corregido', 'vgm' => 'VGM',
        ];

        return collect($etiquetas)
            ->mapWithKeys(fn (string $etiqueta, string $campo) => [
                $etiqueta => $fila->{$campo.'_chk_date'} ?? null,
            ])
            ->all();
    }

    // ------------------------------------------------------- Contenedores

    /** Un booking cerrado ya no recibe movimientos de carga. */
    private function assertEditable(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
        abort_if((bool) $this->header()->locked, 403, 'Este booking está cerrado.');
    }

    public function addContainer(): void
    {
        $this->assertEditable();
        $this->resetContainerForm();
        $this->editingContainer = true;
    }

    public function editContainer(int $container): void
    {
        $this->assertEditable();

        $fila = DB::table('containers')
            ->where('booking', $this->bookingId)
            ->where('container_ID', $container)
            ->first();

        abort_if($fila === null, 404);

        $this->containerId = (int) $fila->container_ID;
        $this->containerNumber = (string) $fila->number;
        $this->containerSeal = (string) $fila->seal;
        $this->containerType = $this->asOption($fila->container_type);
        $this->containerQuantity = (string) ($fila->quantity ?? 1);
        $this->containerCommodity = (string) $fila->comodity;
        $this->containerPickup = $fila->pick_up_date
            ? Carbon::parse($fila->pick_up_date)->toDateString()
            : '';
        $this->editingContainer = true;
        $this->resetErrorBag();
    }

    public function saveContainer(): void
    {
        $this->assertEditable();

        $datos = $this->validate([
            'containerNumber' => ['nullable', 'string', 'max:50'],
            'containerSeal' => ['nullable', 'string', 'max:50'],
            'containerType' => ['nullable', Rule::exists('container_types', 'contType_id')],
            'containerQuantity' => ['required', 'integer', 'min:1'],
            'containerCommodity' => ['nullable', 'string', 'max:25'],
            'containerPickup' => ['nullable', 'date'],
        ], attributes: [
            'containerNumber' => 'número',
            'containerSeal' => 'sello',
            'containerType' => 'tipo',
            'containerQuantity' => 'cantidad',
            'containerCommodity' => 'mercancía',
            'containerPickup' => 'fecha de recolección',
        ]);

        $valores = [
            'booking' => $this->bookingId,
            'number' => $datos['containerNumber'] ?: null,
            'seal' => $datos['containerSeal'] ?: null,
            'container_type' => $datos['containerType'] === '' ? null : (int) $datos['containerType'],
            'quantity' => (int) $datos['containerQuantity'],
            'comodity' => $datos['containerCommodity'] ?: null,
            'pick_up_date' => $datos['containerPickup'] ?: null,
            'modified_by' => auth()->id(),
        ];

        if ($this->containerId === null) {
            DB::table('containers')->insert($valores + ['created_by' => auth()->id(), 'created_at' => now()]);
        } else {
            DB::table('containers')->where('container_ID', $this->containerId)->update($valores);
        }

        $this->resetContainerForm();
    }

    public function deleteContainer(int $container): void
    {
        $this->assertEditable();

        DB::table('containers')
            ->where('booking', $this->bookingId)
            ->where('container_ID', $container)
            ->delete();

        $this->resetContainerForm();
    }

    public function cancelContainerEdit(): void
    {
        $this->resetContainerForm();
    }

    private function resetContainerForm(): void
    {
        $this->reset([
            'editingContainer', 'containerId', 'containerNumber', 'containerSeal',
            'containerType', 'containerCommodity', 'containerPickup',
        ]);
        $this->containerQuantity = '1';
        $this->resetErrorBag();
    }

    private function asOption(mixed $valor): string
    {
        return $valor === null ? '' : (string) $valor;
    }

    // ------------------------------------------------------------ Cierre

    /**
     * Cierra el booking: operación lo da por terminado y su facturación queda
     * fija. Se puede reabrir, pero es una decisión consciente.
     */
    public function lock(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        Booking::whereKey($this->bookingId)->update(['locked' => 1, 'modified_by' => auth()->id()]);

        $this->headerCache = null;
        session()->flash('status', 'Booking cerrado.');
    }

    public function unlock(): void
    {
        // Reabrir permite volver a tocar importes ya conciliados, así que es
        // exclusivo del super administrador.
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        Booking::whereKey($this->bookingId)->update(['locked' => 0, 'modified_by' => auth()->id()]);

        $this->headerCache = null;
        session()->flash('status', 'Booking reabierto.');
    }

    // -------------------------------------------------------- Documentos

    public function chooseField(int $fieldId): void
    {
        $this->assertEditable();

        $this->uploadField = $fieldId;
        $this->upload = null;
        $this->resetErrorBag();
    }

    /** Livewire sube el archivo en cuanto se elige; aquí se guarda al vuelo. */
    public function updatedUpload(): void
    {
        $this->assertEditable();

        if ($this->uploadField === null) {
            return;
        }

        $this->validate(
            ['upload' => ['required', 'file', 'max:20480']],
            attributes: ['upload' => 'archivo'],
        );

        app(BookingFiles::class)->store($this->bookingId, $this->uploadField, $this->upload);

        $this->reset(['upload', 'uploadField']);
        session()->flash('status', 'Documento adjuntado.');
    }

    public function removeFile(int $fieldId, string $nombre): void
    {
        $this->assertEditable();

        app(BookingFiles::class)->remove($this->bookingId, $fieldId, $nombre);
    }

    public function render()
    {
        $fila = $this->header();

        return view('livewire.operations.booking-detail', [
            'booking' => $fila,
            'contenedores' => $this->containers(),
            'transacciones' => $this->transactions(),
            'checklist' => $this->checklist(),
            'tiposContenedor' => DB::table('container_types')->orderBy('container_name')->pluck('container_name', 'contType_id')->all(),
            'documentos' => app(BookingFiles::class)->fieldsFor(
                $this->bookingId,
                DB::table('booking')->where('booking_id', $this->bookingId)->value('client'),
            ),
        ])->layout('components.app-layout', [
            'title' => trim((string) $fila->booking_number) ?: 'Booking '.$this->bookingId,
        ]);
    }
}
