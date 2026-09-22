<?php

namespace App\Livewire\Operations;

use App\Queries\BookingFilters;
use App\Queries\BookingQuery;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de bookings (embarques).
 *
 * Equivale a `actionIndex` del `BookingController` de Yii2. Además de los datos
 * del embarque enseña el avance de su lista de verificación, que es lo que
 * operación mira todo el día para saber qué falta de cada carga.
 */
class BookingList extends Component
{
    use WithPagination;

    #[Url(as: 'bk', except: '')]
    public string $bookingNumber = '';

    #[Url(as: 'cliente', except: '')]
    public string $clientName = '';

    #[Url(as: 'buque', except: '')]
    public string $vesselName = '';

    #[Url(as: 'mercancia', except: '')]
    public string $commodity = '';

    /** Rango de fecha de recolección, "dd/mm/aaaa - dd/mm/aaaa". */
    #[Url(as: 'recoleccion', except: '')]
    public string $dates = '';

    /** Rango de fecha de carga (`loading_EDT`). */
    #[Url(as: 'carga', except: '')]
    public string $loadingDates = '';

    #[Url(as: 'cerrados', except: '0')]
    public string $onlyLocked = '0';

    /** 1 = importaciones, 2 = exportaciones, vacío = todas. */
    #[Url(as: 'tipo', except: '')]
    public string $bookingType = '';

    /**
     * Borradores: los bookings recién dados de alta que aún no se confirmaron.
     * No se mezclan con los reales por omisión, como en el original.
     */
    #[Url(as: 'borradores', except: '0')]
    public string $drafts = '0';

    /** 10 = bookings reales, 9 = cotizaciones. */
    #[Url(as: 'modo', except: '10')]
    public string $mode = '10';

    #[Url(as: 'n', except: 50)]
    public int $perPage = 50;

    public float $queryMs = 0;

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['bookingNumber', 'clientName', 'vesselName', 'commodity', 'dates', 'loadingDates', 'bookingType']);
        $this->onlyLocked = '0';
        $this->drafts = '0';
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'vendor.pagination.app';
    }

    private function filters(): BookingFilters
    {
        $filtros = BookingFilters::make([
            'booking_number' => $this->bookingNumber ?: null,
            'client_name' => $this->clientName ?: null,
            'vessel_name' => $this->vesselName ?: null,
            'commodity' => $this->commodity ?: null,
            'dates' => $this->dates ?: null,
            'loading_EDT' => $this->loadingDates ?: null,
            'booking_type' => $this->bookingType ?: null,
            // Por omisión el listado se acota al año en curso por fecha de
            // creación del booking (que SIEMPRE existe; el pickup no, y filtrar
            // por él escondía bookings recientes). Se aplica solo si el usuario
            // no puso su propio rango de recolección o de carga.
            'created' => $this->dates === '' && $this->loadingDates === ''
                ? now()->startOfYear()->format('d/m/Y').' - '.now()->format('d/m/Y')
                : null,
        ]);

        $filtros->mode = (int) $this->mode;
        $filtros->onlyLocked = $this->onlyLocked === '1';
        $filtros->is_draft = $this->drafts === '1' ? 1 : 0;

        return $filtros;
    }

    public function render()
    {
        $inicio = microtime(true);
        $filas = BookingQuery::make($this->filters())->paginate($this->perPage, $this->getPage());
        $this->queryMs = round((microtime(true) - $inicio) * 1000, 1);

        return view('livewire.operations.booking-list', [
            'filas' => $filas,
        ])->layout('components.app-layout', [
            'title' => $this->mode === '9' ? 'Cotizaciones' : 'Bookings',
        ]);
    }
}
