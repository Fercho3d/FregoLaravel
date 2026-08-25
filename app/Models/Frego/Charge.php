<?php

namespace App\Models\Frego;

/**
 * Concepto (línea) de una transacción.
 *
 * `price * quantity` es el subtotal de la línea; el IVA y la retención salen de
 * multiplicar ese subtotal por las tasas del `charge_type`.
 */
class Charge extends FregoModel
{
    protected $table = 'charge';

    protected $primaryKey = 'charge_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit' => 'float',
            'price' => 'float',
            'price_confirmation' => 'float',
        ];
    }

    /** La columna se llama `transaction`, así que la relación no puede llamarse igual. */
    public function transactionModel()
    {
        return $this->belongsTo(Transaction::class, 'transaction', 'transc_id');
    }

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class, 'type', 'charge_type_id');
    }
}
