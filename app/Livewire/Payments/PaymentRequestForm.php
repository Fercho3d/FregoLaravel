<?php

namespace App\Livewire\Payments;

use App\Actions\Payments\CreatePaymentRequest;
use App\Models\Frego\Bank;
use App\Models\Frego\Transaction;
use App\Queries\TransactionFilters;
use App\Queries\TransactionQuery;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Alta de una solicitud de pago a partir de las transacciones seleccionadas en
 * el listado.
 *
 * En Yii2 esto era un modal que recibía las casillas marcadas de la rejilla; el
 * flujo es el mismo, pero aquí la pantalla tiene dirección propia y los importes
 * se pueden ajustar renglón por renglón antes de guardar.
 */
class PaymentRequestForm extends Component
{
    /** @var int[] */
    public array $ids = [];

    public string $number = '';

    public string $date = '';

    public string $bankId = '';

    /** @var array<int, string> transc_id => importe a aplicar */
    public array $amounts = [];

    public function mount(): void
    {
        $this->ids = collect(explode(',', (string) request()->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($this->ids === []) {
            throw new NotFoundHttpException('No se seleccionó ninguna transacción.');
        }

        $this->date = now()->toDateString();

        foreach ($this->transactions() as $transaccion) {
            $this->amounts[$transaccion->transc_id] = (string) round((float) $transaccion->left_to_pay, 2);
        }
    }

    /**
     * Las transacciones seleccionadas, con los mismos modos que usaba el
     * original al armar la solicitud: importes en su divisa y con signo.
     *
     * @return Collection<int, object>
     */
    public function transactions(): Collection
    {
        $filtros = TransactionFilters::make(['tran_in' => $this->ids]);
        $filtros->noExchange = true;
        $filtros->invoiceMode = true;
        $filtros->paymentMode = true;

        return TransactionQuery::make($filtros)->get();
    }

    public function isCollection(): bool
    {
        $primera = $this->transactions()->first();

        return $primera !== null && (int) $primera->tran_type === Transaction::TYPE_INVOICE;
    }

    public function total(): float
    {
        return round(collect($this->amounts)->sum(fn ($v) => (float) $v), 2);
    }

    public function save(CreatePaymentRequest $crear): void
    {
        abort_unless(auth()->user()?->isAdmin() ?? false, 403);

        $this->validate([
            'number' => ['required', 'string', 'max:64'],
            'date' => ['required', 'date'],
            'bankId' => ['required', 'exists:bank,bank_id'],
            'amounts.*' => ['required', 'numeric'],
        ], attributes: [
            'number' => 'número',
            'date' => 'fecha',
            'bankId' => 'banco',
            'amounts.*' => 'importe',
        ]);

        $transacciones = $this->transactions();

        $this->assertAmountsFit($transacciones);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $importes = collect($this->amounts)
            ->mapWithKeys(fn ($valor, $id) => [(int) $id => round((float) $valor, 2)])
            ->all();

        $solicitud = $crear->handle(
            $transacciones,
            ['number' => $this->number, 'date' => $this->date, 'bank_id' => $this->bankId],
            $importes,
            auth()->user(),
        );

        session()->flash('status', __('Solicitud ').str_pad((string) $solicitud->request_id, 4, '0', STR_PAD_LEFT).' creada.');
        $this->redirectRoute('payments.requests', navigate: true);
    }

    /**
     * Ningún renglón puede aplicar más de lo que la transacción debe.
     *
     * Porta `Transaction::validateAmountToPay()` de Yii2, que allá vivía en un
     * campo virtual del modelo (`public $amount_to_pay`, que no es columna de la
     * tabla) y se validaba por AJAX al teclear en la rejilla. Aquí se comprueba
     * al guardar, que es cuando se escribe.
     *
     * Las notas de crédito van al revés porque restan: su saldo es negativo y el
     * importe tiene que serlo también, sin pasarse por debajo.
     *
     * @param  Collection<int, object>  $transacciones
     */
    private function assertAmountsFit(Collection $transacciones): void
    {
        foreach ($transacciones as $transaccion) {
            $importe = round((float) ($this->amounts[$transaccion->transc_id] ?? 0), 2);
            $saldo = round((float) $transaccion->left_to_pay, 2);
            $campo = 'amounts.'.$transaccion->transc_id;

            if ((int) $transaccion->tran_type === Transaction::TYPE_CREDIT_BILL) {
                if ($importe > 0) {
                    $this->addError($campo, 'Una nota de crédito resta: el importe tiene que ser negativo.');
                } elseif ($importe < $saldo) {
                    $this->addError($campo, 'No puede ser menor que el saldo de '.number_format($saldo, 2).'.');
                }

                continue;
            }

            if ($importe === 0.0) {
                $this->addError($campo, 'El importe tiene que ser mayor que $0.00.');
            } elseif ($importe > $saldo) {
                $this->addError($campo, 'No puede ser mayor que el saldo de '.number_format($saldo, 2).'.');
            }
        }
    }

    public function render()
    {
        return view('livewire.payments.payment-request-form', [
            'transacciones' => $this->transactions(),
            'banks' => Bank::options(),
        ])->layout('components.app-layout', [
            'title' => $this->isCollection() ? 'Nueva solicitud de cobro' : 'Nueva solicitud de pago',
        ]);
    }
}
