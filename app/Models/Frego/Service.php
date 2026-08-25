<?php

namespace App\Models\Frego;

use Illuminate\Support\Collection;

/**
 * Servicio contratado con un cliente o con un proveedor.
 *
 * Es el catálogo del que salen los conceptos de una transacción: aporta la
 * descripción y, cuando trae precio, también el importe. `type` distingue el
 * servicio de venta (1, ligado a `client_id`) del de compra (2, `provider_id`).
 */
class Service extends FregoModel
{
    /** Servicio que se le vende a un cliente. */
    public const TYPE_CLIENT = 1;

    /** Servicio que compra la empresa a un proveedor. */
    public const TYPE_PROVIDER = 2;

    protected $table = 'service';

    protected $primaryKey = 'service_id';

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'active' => 'boolean',
        ];
    }

    public function chargeType()
    {
        return $this->belongsTo(ChargeType::class, 'charge_type_id', 'charge_type_id');
    }

    /**
     * Servicios activos de un tipo de cargo para una contraparte concreta.
     * Réplica de `Service::getListFilter()` en Yii2.
     *
     * @return Collection<int, Service>
     */
    public static function optionsFor(int $chargeTypeId, int $partyId, int $type)
    {
        return static::query()
            ->where('charge_type_id', $chargeTypeId)
            ->where(static::partyColumn($type), $partyId)
            ->where('type', $type)
            ->where('active', 1)
            ->orderBy('description')
            ->get(['service_id', 'description', 'price']);
    }

    /** La columna que guarda la contraparte depende del sentido del servicio. */
    public static function partyColumn(int $type): string
    {
        return $type === self::TYPE_CLIENT ? 'client_id' : 'provider_id';
    }
}
