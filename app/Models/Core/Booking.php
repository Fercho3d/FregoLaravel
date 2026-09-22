<?php

namespace App\Models\Core;

/**
 * Booking (embarque). `mode` distingue el booking real (10) de la cotización (9);
 * el módulo de transacciones filtra por ese campo en todas sus pantallas.
 */
class Booking extends CoreModel
{
    public const MODE_QUOTATION = 9;

    public const MODE_BOOKING = 10;

    /** `booking_type`: el original lo guardaba como 1 = Import y 2 = Export. */
    public const TYPE_IMPORT = 1;

    public const TYPE_EXPORT = 2;

    protected $table = 'booking';

    protected $primaryKey = 'booking_id';

    protected function casts(): array
    {
        return [
            'loading_EDT' => 'date',
            'dicharge_ETA' => 'date',
            'locked' => 'boolean',
            'is_draft' => 'boolean',
        ];
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'booking', 'booking_id');
    }

    /** La columna se llama `client`, así que la relación lleva otro nombre. */
    public function clientModel()
    {
        return $this->belongsTo(Client::class, 'client', 'client_id');
    }

    public function isQuotation(): bool
    {
        return (int) $this->mode === self::MODE_QUOTATION;
    }

    /** @return array<int, string> valor de `booking_type` => etiqueta traducida */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_IMPORT => __('Importación'),
            self::TYPE_EXPORT => __('Exportación'),
        ];
    }

    public function typeLabel(): ?string
    {
        return self::typeLabels()[(int) $this->booking_type] ?? null;
    }
}
