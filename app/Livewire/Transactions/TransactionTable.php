<?php

namespace App\Livewire\Transactions;

use App\Actions\Transactions\StampTransaction;
use App\Models\Core\Account;
use App\Models\Core\Booking;
use App\Models\Core\Company;
use App\Models\Core\Transaction;
use App\Queries\ProfitByBooking;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use App\Support\Cfdi\CfdiException;
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

    /** Totales de TODO el filtro: se muestran mientras `showTotals` esté encendido. */
    public ?array $totals = null;

    /** Casilla «mostrar sumatoria» del pie; se recuerda en cookie (ver `mount`). */
    public bool $showTotals = false;

    /**
     * Utilidad por booking de la pantalla de Facturas. También bajo demanda: el
     * original la calculaba en cada carga y era la parte más cara de la pantalla.
     */
    public ?array $profit = null;

    /**
     * Transacciones marcadas para agrupar en una solicitud de pago.
     *
     * @var int[]
     */
    public array $selected = [];

    /**
     * Resultado del último timbrado en lote: cuántas se timbraron y qué facturas
     * fallaron con su motivo. Se muestra arriba del listado.
     */
    public ?array $stampResult = null;

    /**
     * Cuántas facturas se timbran por tanda. Cada una es una llamada al PAC, así
     * que se acota para no exceder el tiempo de la petición; si hay más, se
     * timbran en varias vueltas.
     */
    private const STAMP_BATCH = 25;

    /** Milisegundos que tardó la consulta de la última pintada. */
    public float $queryMs = 0;

    /** Propiedades que cambian el conjunto de renglones (ver `updated()`). */
    private const FILTERS = [
        'tranNumber',
        'bookingNumber',
        'appliedTo',
        'dates',
        'companyId',
        'accountId',
        'paid',
        'showCancelled',
        'perPage',
    ];

    /**
     * Livewire pisa `Paginator::defaultView()` con su propia vista en cada
     * render, así que la vista propia hay que declararla aquí; el registro del
     * `AppServiceProvider` solo cubre los paginadores fuera de Livewire.
     */
    public function paginationView(): string
    {
        return 'vendor.pagination.app';
    }

    public function paginationSimpleView(): string
    {
        return 'vendor.pagination.app';
    }

    public function mount(string $screen = 'invoice', ?int $booking = null): void
    {
        $this->screen = $screen;
        $this->bookingId = $booking;
        $this->showTotals = request()->cookie('mostrar_totales') === '1';

        // Por omisión el listado arranca acotado al año en curso (del 1 de enero
        // a hoy): así no trae años de historia de golpe —las pantallas van
        // ligeras y «Ver todas» no revienta la memoria—. La pantalla de un
        // booking no se acota: ahí ya se ve solo ese booking.
        if ($this->dates === '' && $this->screen !== 'booking') {
            $this->dates = $this->defaultDates();
        }
    }

    /** Rango por omisión: del 1 de enero de este año a hoy («dd/mm/aaaa - dd/mm/aaaa»). */
    private function defaultDates(): string
    {
        return now()->startOfYear()->format('d/m/Y').' - '.now()->format('d/m/Y');
    }

    /** Cualquier cambio de filtro vuelve a la primera página e invalida totales. */
    public function updated(string $property): void
    {
        // Solo los FILTROS rehacen la consulta. El hook se dispara con cualquier
        // propiedad, así que sin esta lista `selected` se vaciaba a sí misma en
        // cuanto se marcaba una casilla (la casilla se veía "saltar" sola).
        if (! in_array($property, self::FILTERS, true)) {
            return;
        }

        $this->resetPage();
        $this->totals = null;
        $this->profit = null;
        $this->selected = [];
        $this->stampResult = null;
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

    /**
     * Aplica los filtros capturados. Los campos son diferidos (no consultan por
     * tecla): al enviar el formulario sus valores llegan de golpe, `updated()`
     * los detecta y aquí solo se vuelve a la primera página. Es el botón
     * «Filtrar», que hace la captura ligera.
     */
    public function filtrar(): void
    {
        $this->resetPage();
    }

    /**
     * «Ver todas»: trae el filtro completo en una sola página, sin paginar. El
     * tope es muy alto (no una página real de ese tamaño): la consulta devuelve
     * las filas que haya, que en la práctica son unos miles.
     */
    public function verTodas(): void
    {
        $this->perPage = 100000;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['tranNumber', 'bookingNumber', 'appliedTo', 'dates', 'companyId', 'accountId', 'paid']);
        // Limpiar vuelve al default (año en curso), no a «toda la historia».
        if ($this->screen !== 'booking') {
            $this->dates = $this->defaultDates();
        }
        $this->showCancelled = '0';
        $this->totals = null;
        $this->profit = null;
        $this->selected = [];
        $this->stampResult = null;
        $this->resetPage();
    }

    /** Manda a armar una solicitud de pago con lo que esté marcado. */
    public function createPaymentRequest(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        if ($this->selected === []) {
            $this->addError('selected', __('Marca al menos una transacción.'));

            return;
        }

        if (! $this->selectionIsGroupable()) {
            return;
        }

        $this->redirectRoute('payments.requests.create', [
            'ids' => implode(',', $this->selected),
            'volver' => $this->currentUrl(),
        ], navigate: true);
    }

    /**
     * Antes de llevar a la pantalla de pago, las transacciones marcadas tienen
     * que poder agruparse en UNA solicitud: misma divisa y misma contraparte,
     * porque una solicitud se paga con un solo cheque a un solo tercero. Es la
     * regla que el controlador de Yii2 aplicaba al agrupar, adelantada aquí al
     * momento de seleccionar para no pasar a una pantalla donde el guardado
     * fallaría sin decir por qué.
     */
    private function selectionIsGroupable(): bool
    {
        $marcadas = Transaction::whereIn('transc_id', $this->selected)->get(['account', 'customer', 'vendor']);

        if ($marcadas->pluck('account')->unique()->count() > 1) {
            $this->addError('selected', __('No se pueden agrupar transacciones de distinta divisa en la misma solicitud.'));

            return false;
        }

        $esCobro = $this->screen === 'invoice';

        if ($marcadas->pluck($esCobro ? 'customer' : 'vendor')->unique()->count() > 1) {
            $this->addError('selected', __($esCobro
                ? 'Todas las facturas de una solicitud tienen que ser del mismo cliente.'
                : 'Todos los costos de una solicitud tienen que ser del mismo proveedor.'));

            return false;
        }

        return true;
    }

    /**
     * Timbra en lote las facturas seleccionadas, como el botón «Seal» del
     * sistema viejo. Cada timbrado es una llamada real al PAC, así que se
     * procesan de a pocas por tanda y se informa una por una: las que se
     * timbraron y las que no, con su motivo (ya timbrada, sin conceptos, etc.).
     */
    public function stampSelected(StampTransaction $stamp): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        // Solo se timbran facturas al cliente, no costos de proveedor.
        if ($this->screen !== 'invoice') {
            return;
        }

        if ($this->selected === []) {
            $this->addError('selected', __('Marca al menos una factura para timbrar.'));

            return;
        }

        $lote = array_slice($this->selected, 0, self::STAMP_BATCH);

        // Cada factura es una llamada al PAC; puede tardar.
        set_time_limit(0);

        $done = 0;
        $errors = [];

        foreach (Transaction::whereIn('transc_id', $lote)->get() as $factura) {
            try {
                $stamp->handle($factura);
                $done++;
            } catch (CfdiException $e) {
                $errors[$factura->tran_number ?: (string) $factura->transc_id] = $e->getMessage();
            }
        }

        $this->stampResult = [
            'done' => $done,
            'errors' => $errors,
            'pending' => max(0, count($this->selected) - count($lote)),
        ];

        $this->selected = [];
    }

    /**
     * Los filtros de la pantalla, tal como viajan en la dirección.
     *
     * @return array<string, mixed>
     */
    private function urlParams(): array
    {
        return array_filter([
            'num' => $this->tranNumber,
            'bk' => $this->bookingNumber,
            'q' => $this->appliedTo,
            'f' => $this->dates,
            'co' => $this->companyId,
            'ccy' => $this->accountId,
            'pago' => $this->paid,
            'canc' => $this->showCancelled,
            'ord' => $this->sort,
            'dir' => $this->direction,
        ], fn ($valor) => $valor !== null && $valor !== '');
    }

    /** Esta misma pantalla, con su filtro: a dónde volver desde otra sección. */
    public function currentUrl(): string
    {
        $ruta = match ($this->screen) {
            'bill' => 'transactions.bill',
            'all' => 'transactions.all',
            'booking' => 'transactions.booking',
            default => 'transactions.invoice',
        };

        $parametros = $this->urlParams();

        if ($this->screen === 'booking') {
            $parametros['booking'] = $this->bookingId;
        }

        return route($ruta, $parametros, absolute: false);
    }

    /**
     * Dirección de la descarga, con el MISMO filtro que se está viendo.
     *
     * Los nombres son los que la pantalla ya usa en la dirección, así que el
     * archivo trae exactamente la tabla de enfrente —y el enlace se puede
     * compartir igual que el de la pantalla.
     */
    public function exportUrl(): string
    {
        $parametros = $this->urlParams();

        if ($this->bookingId !== null) {
            $parametros['booking'] = $this->bookingId;
        }

        return route('transactions.export', ['screen' => $this->screen] + $parametros);
    }

    /** ¿Esta pantalla permite agrupar en solicitudes de pago? */
    public function allowsSelection(): bool
    {
        return in_array($this->screen, ['invoice', 'bill'], true);
    }

    /** Utilidad por booking: facturas menos costos, a TC del documento y del pago. */
    public function calculateProfit(): void
    {
        $this->profit = (new ProfitByBooking($this->filters()))->summary();
    }

    /** Suma las columnas de dinero sobre el conjunto filtrado completo. */
    private function calculateTotals(): void
    {
        $this->totals = $this->query()->totals([
            'amount_original', 'sub_16_mxn', 'sub_0_mxn', 'tax_16_mxn', 'tax_ret_mxn', 'total_amount', 'left_to_pay',
        ]);
    }

    /**
     * La casilla «mostrar sumatoria» se recuerda en una cookie (un año), para
     * que quien la prende la encuentre puesta la próxima vez, en cualquier
     * pantalla del listado. Al apagarla se borra el pie.
     */
    public function updatedShowTotals(bool $value): void
    {
        cookie()->queue('mostrar_totales', $value ? '1' : '0', 60 * 24 * 365);

        if (! $value) {
            $this->totals = null;
        }
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

        // Con la sumatoria encendida, el pie se calcula en cada pintada para que
        // siga al filtro que se esté viendo.
        if ($this->showTotals) {
            $this->calculateTotals();
        } else {
            $this->totals = null;
        }

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
            'invoice' => __('Facturas'),
            'bill' => __('Costos'),
            'booking' => __('Transacciones del booking'),
            default => __('Todas las transacciones'),
        };
    }
}
