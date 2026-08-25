<?php

namespace App\Livewire\Transactions;

use App\Models\Frego\Account;
use App\Models\Frego\Booking;
use App\Models\Frego\Company;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de transacciones.
 *
 * Es la misma pantalla para los cuatro listados del sistema original
 * (`invoice`, `bill`, `all` y las transacciones de un booking): cambian el filtro
 * base y las columnas, no la mecánica.
 *
 * Filtrar, ordenar y paginar no recargan la página: Livewire repinta solo la
 * tabla. Junto con `wire:navigate` en el menú, la navegación se siente de app.
 */
class TransactionTable extends Component
{
    use WithPagination;

    /** invoice | bill | all | booking */
    public string $screen = 'invoice';

    public ?int $bookingId = null;

    #[Url(as: 'num', except: '')]
    public string $tranNumber = '';

    #[Url(as: 'bk', except: '')]
    public string $bookingNumber = '';

    #[Url(as: 'q', except: '')]
    public string $appliedTo = '';

    #[Url(as: 'f', except: '')]
    public string $dates = '';

    #[Url(as: 'co', except: '')]
    public string $companyId = '';

    #[Url(as: 'ccy', except: '')]
    public string $accountId = '';

    /** '' = todas, 0 = sin pagar, 1 = pagadas, 2 = parciales. */
    #[Url(as: 'pago', except: '')]
    public string $paid = '';

    #[Url(as: 'canc', except: '0')]
    public string $showCancelled = '0';

    #[Url(as: 'ord', except: 'transc_id')]
    public string $sort = 'transc_id';

    #[Url(as: 'dir', except: 'desc')]
    public string $direction = 'desc';

    #[Url(as: 'n', except: 50)]
    public int $perPage = 50;

    /** Totales de TODO el filtro: se calculan solo si el usuario los pide. */
    public ?array $totals = null;

    /** Milisegundos que tardó la consulta de la última pintada. */
    public float $queryMs = 0;

    /**
     * Livewire pisa `Paginator::defaultView()` con su propia vista en cada
     * render, así que la vista propia hay que declararla aquí; el registro del
     * `AppServiceProvider` solo cubre los paginadores fuera de Livewire.
     */
    public function paginationView(): string
    {
        return 'vendor.pagination.frego';
    }

    public function paginationSimpleView(): string
    {
        return 'vendor.pagination.frego';
    }

    public function mount(string $screen = 'invoice', ?int $booking = null): void
    {
        $this->screen = $screen;
        $this->bookingId = $booking;
    }

    /** Cualquier cambio de filtro vuelve a la primera página e invalida totales. */
    public function updated(string $property): void
    {
        if ($property === 'page') {
            return;
        }

        $this->resetPage();
        $this->totals = null;
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'desc';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['tranNumber', 'bookingNumber', 'appliedTo', 'dates', 'companyId', 'accountId', 'paid']);
        $this->showCancelled = '0';
        $this->totals = null;
        $this->resetPage();
    }

    /** Suma las columnas de dinero sobre el conjunto filtrado completo. */
    public function calculateTotals(): void
    {
        $this->totals = $this->query()->totals([
            'amount_original', 'sub_16_mxn', 'sub_0_mxn', 'tax_16_mxn', 'total_amount', 'left_to_pay',
        ]);
    }

    private function filters(): TransactionFilters
    {
        $filters = TransactionFilters::make([
            'tran_number' => $this->tranNumber ?: null,
            'booking_number' => $this->bookingNumber ?: null,
            'appliedTo' => $this->appliedTo ?: null,
            'dates' => $this->dates ?: null,
            'company_id' => $this->companyId !== '' ? (int) $this->companyId : null,
            'account' => $this->accountId !== '' ? (int) $this->accountId : null,
            'paid' => $this->paid !== '' ? (int) $this->paid : null,
            'showCancelled' => (int) $this->showCancelled,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ]);

        // Cada pantalla es el mismo motor con otro filtro base, igual que las
        // acciones actionInvoice / actionBill / actionAll / actionIndex de Yii2.
        match ($this->screen) {
            'invoice' => $filters->type = [0],
            'bill' => [$filters->type = [1, 2], $filters->paymentMode = true],
            'booking' => $filters->booking = $this->bookingId,
            default => null,
        };

        return $filters;
    }

    private function query(): TransactionQuery
    {
        return TransactionQuery::make($this->filters());
    }

    public function render()
    {
        $start = microtime(true);
        $rows = $this->query()->paginate($this->perPage, $this->getPage());
        $this->queryMs = round((microtime(true) - $start) * 1000, 1);

        return view('livewire.transactions.transaction-table', [
            'rows' => $rows,
            'companies' => Company::options(),
            'currencies' => Account::options(),
            'booking' => $this->bookingId ? Booking::find($this->bookingId) : null,
        ])->layout('components.app-layout', ['title' => $this->title()]);
    }

    public function title(): string
    {
        return match ($this->screen) {
            'invoice' => 'Facturas',
            'bill' => 'Costos',
            'booking' => 'Transacciones del booking',
            default => 'Todas las transacciones',
        };
    }
}
