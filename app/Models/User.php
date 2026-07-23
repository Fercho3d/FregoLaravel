<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Modelo de usuario mapeado sobre la tabla `users` heredada de Yii2.
 *
 * Convenciones especiales del esquema Frego:
 *  - Llave primaria: `usr_id` (no `id`).
 *  - Marca de actualización: `modified_at` (no `updated_at`).
 *  - Contraseñas bcrypt generadas por Yii2 → compatibles con Hash::check.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'users';

    protected $primaryKey = 'usr_id';

    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'modified_at';

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'unit',
        'role',
        'access',
        'client_id',
        'provider_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'auth_key',
        'remember_token',
        'password_reset_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'modified_at' => 'datetime',
            'last_login' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'status' => 'boolean',
        ];
    }

    /**
     * Solo los usuarios activos pueden autenticarse.
     */
    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
