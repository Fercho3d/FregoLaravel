<?php

namespace App\Models\Frego;

/**
 * Cliente (a quien se le factura). En el módulo de transacciones aparece como
 * `customer` y su nombre visible es `fullName`.
 */
class Client extends FregoModel
{
    protected $table = 'client';

    protected $primaryKey = 'client_id';

    protected $hidden = ['password', 'auth_key', 'password_reset_token', 'verification_code'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'customer', 'client_id');
    }

    /**
     * Correos a los que se envía la factura timbrada.
     * `email_notification` guarda una lista separada por comas o punto y coma.
     */
    public function notificationEmails(): array
    {
        $raw = trim((string) ($this->email_notification ?: $this->email));

        return array_values(array_filter(
            array_map('trim', preg_split('/[,;]+/', $raw) ?: []),
            fn ($mail) => filter_var($mail, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
