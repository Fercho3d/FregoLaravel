<?php

namespace App\Models;

use App\Support\Cfdi\CancelResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Solicitud de cancelación de un CFDI y lo que el SAT ha contestado sobre ella.
 *
 * Tabla propia de Laravel (`cfdi_cancelacion`); las columnas heredadas de la
 * transacción quedan intactas y el sistema viejo no se entera.
 */
class CfdiCancelacion extends Model
{
    protected $table = 'cfdi_cancelacion';

    /** Las marcas de tiempo que interesan son las del trámite, no las de la fila. */
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'solicitado_at' => 'datetime',
            'verificado_at' => 'datetime',
        ];
    }

    /** Las que siguen esperando respuesta del receptor o del SAT. */
    public function scopePendientes(Builder $consulta): Builder
    {
        return $consulta->whereIn('estado', CancelResult::PENDIENTES);
    }

    public function estaPendiente(): bool
    {
        return in_array($this->estado, CancelResult::PENDIENTES, true);
    }
}
