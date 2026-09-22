<?php

namespace App\Livewire\Payments;

use App\Actions\Payments\CreatePaymentRequest;
use App\Models\Core\Bank;
use App\Models\Core\PaymentByTransaction;
use App\Models\Core\PaymentRequest;
use App\Queries\PaymentRequestFilters;
use App\Queries\PaymentRequestQuery;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detalle de una solicitud de pago: su encabezado, las transacciones que agrupa
 * y las acciones (pagar, reabrir, borrar, imprimir).
 *
 * Antes esto se desplegaba dentro de la tabla del listado; ahora es una pantalla
 * propia con dirección, para poder enlazarla y volver a ella. El botón «Volver»
 * regresa al listado tal como venía (mismo filtro).
 *
 * Mientras no esté pagada (recién creada o reabierta) se corrige aquí mismo:
 * número, fecha, banco, importe de cada renglón, quitar renglones y agregar
 * otras transacciones del mismo tercero. Es lo que hacía el modal de Yii2
 * después de «Open Payment», con su botón «Add» y su selector.
 */
class PaymentRequestDetail extends Component
{
    use ChecksPaymentAmounts;

    /** Cuántas candidatas enseña el panel de agregar antes de pedir que se afine la búsqueda. */
    private const MAX_CANDIDATAS = 50;

    public int $requestId;

    /** El panel «Agregar transacción» está abierto. */
    public bool $showAdd = false;

    /** Búsqueda del panel, por número de transacción o de booking. */
    public string $addSearch = '';

    public string $number = '';

    public string $date = '';

    public string $bankId = '';

    /** @var array<int, string> transc_id => importe aplicado */
    public array $amounts = [];

    /** A dónde regresa el botón «Volver» (el listado con su filtro). */
    public string $volver = '';

    public function mount(int $request): void
    {
        if (PaymentRequest::whereKey($request)->doesntExist()) {
            throw new NotFoundHttpException(__('No se encontró la solicitud de pago.'));
        }

        $this->requestId = $request;
        $this->volver = $this->safeBack((string) request()->query('volver', ''));
        $this->loadEditable();
    }

    /** Llena los campos editables con lo guardado. */
    private function loadEditable(): void
    {
        $solicitud = PaymentRequest::findOrFail($this->requestId);

        $this->number = (string) $solicitud->number;
        $this->date = $solicitud->date ? substr((string) $solicitud->date, 0, 10) : now()->toDateString();
        $this->bankId = (string) $solicitud->bank_id;
        $this->amounts = PaymentByTransaction::where('request_id', $this->requestId)
            ->pluck('amount', 'transc_id')
            ->map(fn ($importe) => (string) round((float) $importe, 2))
            ->all();
    }

    /** Solo se corrige lo que no está pagado. */
    private function assertEditable(): PaymentRequest
    {
        $this->assertAdmin();

        $solicitud = PaymentRequest::findOrFail($this->requestId);

        abort_if((bool) $solicitud->paid, 422, __('Una solicitud pagada no se corrige: primero hay que reabrirla.'));

        return $solicitud;
    }

    /** Solo se acepta volver a una dirección propia del sistema. */
    private function safeBack(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? $url
            : route('payments.requests', absolute: false);
    }

    public function folio(int|string|null $requestId): string
    {
        return str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
    }

    private function assertAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);
    }

    // ------------------------------------------------------------ Acciones

    /** Marca la solicitud como pagada (misma regla que el listado). */
    public function markPaid(): void
    {
        // Como el «Pay» del modal de Yii2: guarda lo corregido y paga.
        if (! $this->save()) {
            return;
        }

        $solicitud = PaymentRequest::findOrFail($this->requestId);
        abort_if(
            PaymentByTransaction::where('request_id', $this->requestId)->doesntExist(),
            422,
            __('La solicitud no tiene transacciones.'),
        );

        $solicitud->forceFill(['paid' => 1, 'opened' => 0])->save();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' marcada como pagada.');
    }

    /** Vuelve a abrir una solicitud pagada, para corregirla. */
    public function reopen(): void
    {
        $this->assertAdmin();

        PaymentRequest::findOrFail($this->requestId)->forceFill(['paid' => 0, 'opened' => 1])->save();

        $this->loadEditable();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' reabierta.');
    }

    /** Guarda el encabezado y los importes corregidos. */
    public function save(): bool
    {
        $solicitud = $this->assertEditable();

        $this->validate([
            'number' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date'],
            'bankId' => ['required', 'exists:bank,bank_id'],
            'amounts.*' => ['required', 'numeric'],
        ], attributes: [
            'number' => __('número'),
            'date' => 'fecha',
            'bankId' => 'banco',
            'amounts.*' => 'importe',
        ]);

        $this->assertAmountsFit($this->transactionsForValidation(), 'applied_here');

        if ($this->getErrorBag()->isNotEmpty()) {
            return false;
        }

        foreach ($this->amounts as $transaccion => $importe) {
            PaymentByTransaction::where('request_id', $this->requestId)
                ->where('transc_id', $transaccion)
                ->update(['amount' => round((float) $importe, 2)]);
        }

        $this->saveHeader($solicitud);

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' guardada.');

        return true;
    }

    /** Suelta una transacción de la solicitud; la última no, eso es borrarla. */
    public function removeTransaction(int $transaccion): void
    {
        $solicitud = $this->assertEditable();

        abort_if(count($this->amounts) < 2, 422, __('Es la última transacción: para quitarla, borra la solicitud.'));

        PaymentByTransaction::where('request_id', $this->requestId)->where('transc_id', $transaccion)->delete();
        unset($this->amounts[$transaccion]);

        $this->saveHeader($solicitud);
    }

    /** Abre o cierra el panel «Agregar transacción». */
    public function toggleAdd(): void
    {
        $this->assertEditable();

        $this->showAdd = ! $this->showAdd;
        $this->addSearch = '';
    }

    /**
     * Mete una transacción a la solicitud y le aplica el pago igual que al
     * crear. El importe por omisión es su saldo, que es también el tope al
     * agregar (regla del alta), así que no hay más que validar; después se
     * puede corregir en su casilla como cualquier otro renglón.
     */
    public function addTransaction(int $transaccion, CreatePaymentRequest $crear): void
    {
        $solicitud = $this->assertEditable();

        abort_if(
            PaymentByTransaction::where('request_id', $this->requestId)->where('transc_id', $transaccion)->exists(),
            422,
            __('La transacción ya está en la solicitud.'),
        );

        // Se busca con los mismos filtros del panel: si no sale, no cumple
        // alguna regla (tipo, tercero, divisa, saldo o cancelada).
        $filtros = $this->candidateFilters($solicitud);
        $filtros->tran_in = [$transaccion];
        $candidata = TransactionQuery::make($filtros)->get()->first();

        abort_if(
            $candidata === null,
            422,
            __('La transacción no se puede agregar: tiene que ser del mismo tipo, del mismo tercero y de la misma divisa, con saldo pendiente.'),
        );

        $importe = round((float) $candidata->left_to_pay, 2);

        $crear->applyTo($candidata, $this->requestId, $importe);
        $this->amounts[$transaccion] = (string) $importe;
        $this->saveHeader($solicitud);

        session()->flash('status', __('Transacción :num agregada a la solicitud.', ['num' => $candidata->tran_number ?: $transaccion]));
    }

    /** Número, fecha, banco y el importe, que es la suma de los renglones. */
    private function saveHeader(PaymentRequest $solicitud): void
    {
        $importe = round(collect($this->amounts)->sum(fn ($v) => (float) $v), 2);

        $solicitud->forceFill([
            'number' => $this->number,
            'date' => $this->date,
            'bank_id' => (int) $this->bankId,
            'amount' => $importe,
            'total_to_pay' => $importe,
            'modified_by' => auth()->user()?->usr_id,
        ])->save();
    }

    /** Borra la solicitud y suelta sus transacciones; regresa al listado. */
    public function delete()
    {
        $this->assertAdmin();

        $solicitud = PaymentRequest::findOrFail($this->requestId);

        abort_if((bool) $solicitud->paid, 422, __('Una solicitud pagada no se borra: primero hay que reabrirla.'));

        PaymentByTransaction::where('request_id', $this->requestId)->delete();
        $solicitud->delete();

        session()->flash('status', __('Solicitud ').$this->folio($this->requestId).' borrada.');

        return $this->redirect($this->volver, navigate: true);
    }

    // ------------------------------------------------------------ Consulta

    /** El encabezado de la solicitud, con los mismos campos que el listado. */
    private function header(): ?object
    {
        return PaymentRequestQuery::make(PaymentRequestFilters::make(['request_id' => $this->requestId]))
            ->get()
            ->first();
    }

    /**
     * Las transacciones que agrupa, con «Pagado» y «Por pagar» contados SOLO
     * con lo de esta solicitud, como la rejilla del modal de Yii2.
     *
     * @return Collection<int, object>
     */
    private function transactions(): Collection
    {
        $filtros = TransactionFilters::make(['request_id' => $this->requestId]);
        $filtros->paymentMode = true;
        $filtros->noExchange = true;

        return TransactionQuery::make($filtros)->get();
    }

    /**
     * Las mismas transacciones vistas desde fuera: `left_to_pay` contra TODO lo
     * pagado (también por otras solicitudes) y, aparte, lo aplicado aquí.
     *
     * `transactions()` no sirve para validar: al filtrar por `request_id` el
     * motor sólo suma los pagos de esta solicitud, así que su saldo ignora lo
     * que otras ya pagaron y dejaría sobre-aplicar.
     *
     * @return Collection<int, object>
     */
    private function transactionsForValidation(): Collection
    {
        $aplicado = PaymentByTransaction::where('request_id', $this->requestId)->pluck('amount', 'transc_id');

        if ($aplicado->isEmpty()) {
            return collect();
        }

        $filtros = TransactionFilters::make(['tran_in' => $aplicado->keys()->all()]);
        $filtros->paymentMode = true;
        $filtros->noExchange = true;
        $filtros->showCancelled = 1;

        return TransactionQuery::make($filtros)->get()->each(function (object $transaccion) use ($aplicado) {
            $transaccion->applied_here = round((float) $aplicado[$transaccion->transc_id], 2);
        });
    }

    /** En la reabierta: el saldo más lo aplicado en ESTA solicitud (ver `ChecksPaymentAmounts`). */
    protected function payableLimit(object $transaccion): float
    {
        return round((float) $transaccion->left_to_pay + (float) $transaccion->applied_here, 2);
    }

    /**
     * Lo que se le puede agregar a la solicitud: mismo tipo (costos o
     * facturas), mismo tercero y misma divisa, con saldo pendiente, sin
     * cancelar y que no esté ya dentro. Es `actionTransactionSelector` de Yii2
     * dicho con los filtros de `TransactionQuery`, sin SQL propio.
     */
    private function candidateFilters(PaymentRequest $solicitud): TransactionFilters
    {
        $esCobro = (int) $solicitud->type === 1;

        $filtros = TransactionFilters::make([
            'customer' => $esCobro ? (int) $solicitud->client_id : null,
            'vendor' => $esCobro ? null : (int) $solicitud->provider_id,
            'account' => (int) $solicitud->currency_id,
            'notIn' => $this->requestId,
            // Los mismos tipos que admitía `actionAddTransaction` por cada lado.
            'type_in' => $esCobro ? [0, 10] : [1, 2, 3, 4],
        ]);
        $filtros->onlyUndpaid = true;
        $filtros->paymentMode = true;
        $filtros->noExchange = true;

        return $filtros;
    }

    /**
     * Las candidatas del panel, filtradas por lo tecleado. La búsqueda se hace
     * sobre el resultado porque el motor filtra número y booking por separado
     * (con Y) y aquí una sola casilla busca en los dos.
     *
     * @return Collection<int, object>
     */
    private function candidates(PaymentRequest $solicitud): Collection
    {
        $busqueda = mb_strtolower(trim($this->addSearch));

        return TransactionQuery::make($this->candidateFilters($solicitud))->get()
            ->filter(fn (object $t) => $busqueda === ''
                || str_contains(mb_strtolower((string) $t->tran_number), $busqueda)
                || str_contains(mb_strtolower((string) $t->booking_number), $busqueda))
            ->values();
    }

    public function render()
    {
        $solicitud = $this->header();

        abort_if($solicitud === null, 404);

        $editable = (auth()->user()?->isAdmin() ?? false) && ! $solicitud->paid;
        $candidatas = $editable && $this->showAdd
            ? $this->candidates(PaymentRequest::findOrFail($this->requestId))
            : collect();

        return view('livewire.payments.payment-request-detail', [
            'solicitud' => $solicitud,
            'transacciones' => $this->transactions(),
            'banks' => Bank::options(),
            'editable' => $editable,
            'candidatas' => $candidatas->take(self::MAX_CANDIDATAS),
            'candidatasTotal' => $candidatas->count(),
            'maxCandidatas' => self::MAX_CANDIDATAS,
        ])->layout('components.app-layout', ['title' => __('Solicitud ').$this->folio($this->requestId)]);
    }
}
