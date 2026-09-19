<?php

namespace App\Livewire\Payments;

use App\Models\Core\Transaction;
use Illuminate\Support\Collection;

/**
 * Tope de lo que cada renglón de una solicitud de pago puede aplicar. Lo usan el
 * alta (`PaymentRequestForm`) y la edición de una solicitud reabierta
 * (`PaymentRequestDetail`), igual que en Yii2 ambos modales validaban con
 * `Transaction::validateAmountToPay()`.
 *
 * Espera en el componente la propiedad `$amounts` (transc_id => importe).
 */
trait ChecksPaymentAmounts
{
    /**
     * Ningún renglón puede aplicar más de lo que la transacción admite.
     *
     * Porta `Transaction::validateAmountToPay()` de Yii2, que allá vivía en un
     * campo virtual del modelo (`public $amount_to_pay`, que no es columna de la
     * tabla) y se validaba por AJAX al teclear en la rejilla. Aquí se comprueba
     * al guardar, que es cuando se escribe.
     *
     * ⚠️ El tope NO es el saldo sino `saldo + lo ya pagado`, o sea el total del
     * documento. En Yii2 eso lo hace la rama `modeOpen` de la validación, y esta
     * pantalla siempre la enciende: `views/payment-request/_transactions.php`
     * arma el editable con `'modeopen' => 1` fijo. La idea es que, dentro de una
     * solicitud, lo ya aplicado se puede volver a repartir; el efecto es que una
     * transacción saldada sigue admitiendo importe. Sin esto, un renglón con
     * saldo 0 quedaba trabado: el 0 lo rechaza la primera regla y cualquier otra
     * cifra la segunda.
     *
     * Las notas de crédito van al revés porque restan: su saldo es negativo y el
     * importe tiene que serlo también, sin pasarse por debajo. Ahí el 0 SÍ pasa
     * (la regla del «mayor que cero» solo existe en la otra rama); comprobado
     * corriendo la validación original contra la base real.
     *
     * @param  Collection<int, object>  $transacciones
     * @param  string  $propuesto  La columna con el importe que la pantalla
     *                             propone solo (el saldo al crear, lo ya aplicado
     *                             al editar): ese valor entra sin revisarse.
     */
    private function assertAmountsFit(Collection $transacciones, string $propuesto): void
    {
        foreach ($transacciones as $transaccion) {
            $importe = round((float) ($this->amounts[$transaccion->transc_id] ?? 0), 2);
            $tope = $this->payableLimit($transaccion);
            $campo = 'amounts.'.$transaccion->transc_id;

            // El renglón que se queda con el importe propuesto entra tal cual.
            // En Yii2 esta validación solo corre al TECLEAR en la casilla, así
            // que el valor por omisión nunca se comprueba y una transacción ya
            // saldada se manda con 0 sin protestar.
            if ($importe === round((float) $transaccion->{$propuesto}, 2)) {
                continue;
            }

            if ((int) $transaccion->tran_type === Transaction::TYPE_CREDIT_BILL) {
                if ($importe > 0) {
                    $this->addError($campo, __('Una nota de crédito resta: el importe tiene que ser negativo.'));
                } elseif ($importe < $tope) {
                    $this->addError($campo, __('No puede ser menor que ').number_format($tope, 2).'.');
                }

                continue;
            }

            if ($importe === 0.0) {
                $this->addError($campo, __('El importe tiene que ser mayor que $0.00.'));
            } elseif ($importe > $tope) {
                $this->addError($campo, __('No puede ser mayor que ').number_format($tope, 2).'.');
            }
        }
    }

    /** Saldo + lo ya pagado: el `modeOpen` de `validateAmountToPay()` (ver arriba). */
    public function payableLimit(object $transaccion): float
    {
        return round((float) $transaccion->left_to_pay + (float) $transaccion->tran_paid_amount, 2);
    }
}
