<?php

namespace App\Models;

use App\Support\Theme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /** Roles heredados de Yii2 (`User::ROLE_*`). */
    public const ROLE_USER = 9;

    public const ROLE_ADMIN = 10;

    public const ROLE_SUPER_ADMIN = 20;

    /**
     * Solo los usuarios activos pueden autenticarse.
     */
    public function isActive(): bool
    {
        return (bool) $this->status;
    }

    /**
     * Equivalente a `User::isUserAdmin()` de Yii2: administrador o super
     * administrador. Es la puerta de los módulos internos — el resto de los roles
     * (clientes, proveedores, operación) no debe ver facturación.
     */
    public function isAdmin(): bool
    {
        return in_array((int) $this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return (int) $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Preferencias de interfaz (tema, densidad). Vive en una tabla propia de
     * Laravel para no alterar la tabla `users` heredada de Yii2.
     */
    public function preference(): HasOne
    {
        return $this->hasOne(UserPreference::class, 'usr_id', 'usr_id');
    }

    /**
     * Tema elegido por el usuario. Se llama `themePreference` y no `theme` para
     * que Eloquent no lo confunda con una relación al resolver `$user->theme`.
     */
    public function themePreference(): Theme
    {
        return $this->preference?->theme ?? Theme::System;
    }
}
