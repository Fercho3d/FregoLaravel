<?php

namespace App\Models\Frego;

/**
 * Tipo de cargo: define la tasa de IVA, la retención y si es no deducible.
 *
 * Es el catálogo que decide en qué cubeta cae cada cargo al agregarse:
 *  - `tax_rate = 0.16` y `non_deductible = 0` → cubeta VAT16
 *  - `tax_rate = 0`    y `non_deductible = 0` → cubeta VAT0
 *  - `tax_rate = 0`    y `non_deductible = 1` → cubeta no deducible
 */
class ChargeType extends FregoModel
{
    protected $table = 'charge_type';

    protected $primaryKey = 'charge_type_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tax_rate' => 'float',
            'tax_retention' => 'float',
            'non_deductible' => 'boolean',
            'deleted' => 'boolean',
        ];
    }

    public function charges()
    {
        return $this->hasMany(Charge::class, 'type', 'charge_type_id');
    }
}
