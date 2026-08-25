<?php

namespace App\Models\Frego;

/**
 * Proveedor. En el módulo de transacciones aparece como `vendor`.
 */
class Provider extends FregoModel
{
    protected $table = 'provider';

    protected $primaryKey = 'provider_id';

    protected $hidden = ['password', 'auth_key', 'password_reset_token', 'verification_code'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'vendor', 'provider_id');
    }
}
