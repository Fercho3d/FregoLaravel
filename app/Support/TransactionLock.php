<?php

namespace App\Support;

use App\Models\User;

/**
 * ¿Se puede editar esta transacción?
 *
 * Traducción literal del bloque de candados que abre `views/transaction/_form.php`
 * en Yii2. El orden importa y tiene una rareza que se conserva a propósito: la
 * regla que devuelve la edición al super administrador queda anulada más abajo
 * en cuanto la transacción tiene importe y ya está saldada. Cambiarlo abriría a
 * edición documentos que hoy el sistema protege.
 *
 * La compañía emisora es la excepción: se puede cambiar aunque esté bloqueada.
 */
final class TransactionLock
{
    private function __construct(
        public readonly bool $locked,
        public readonly ?string $reason,
    ) {}

    /**
     * @param  object  $row  Fila del motor de consulta (trae `amount_original`,
     *                       `left_to_pay` y `tran_paid_amount`, que no son columnas).
     */
    public static function evaluate(object $row, bool $bookingLocked, ?User $user): self
    {
        $sinPermiso = ! ($user?->isAdmin() ?? false);
        $timbrada = filled($row->seal ?? null);
        $conPagos = (float) ($row->tran_paid_amount ?? 0) > 0;
        $saldada = (float) ($row->amount_original ?? 0) > 0
            && (float) ($row->left_to_pay ?? 0) === 0.0;

        $bloqueada = $sinPermiso || $timbrada || $saldada || $conPagos;

        if (($user?->isSuperAdmin() ?? false) && (float) ($row->left_to_pay ?? 0) === 0.0) {
            $bloqueada = false;
        }

        if ($bookingLocked) {
            $bloqueada = true;
        }

        // Esta última regla del original deshace el permiso del super administrador
        // siempre que el documento tenga importe y esté saldado.
        if ($saldada) {
            $bloqueada = true;
        }

        return new self($bloqueada, match (true) {
            ! $bloqueada => null,
            $sinPermiso => 'Tu cuenta no tiene permiso para editar facturación.',
            $bookingLocked => 'El booking está bloqueado.',
            $saldada => 'La transacción ya está saldada.',
            $timbrada => 'La transacción ya está timbrada.',
            $conPagos => 'La transacción tiene pagos aplicados.',
            default => 'La transacción está bloqueada.',
        });
    }
}
