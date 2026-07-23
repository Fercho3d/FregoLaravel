<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed inicial: roles del sistema y un usuario administrador de desarrollo.
     */
    public function run(): void
    {
        $roles = [
            'super-admin',   // acceso total
            'admin',         // administración interna
            'operaciones',   // bookings / contenedores
            'facturacion',   // CFDI / timbrado
            'pagos',         // bancos / conciliación
            'cliente',       // portal cliente
            'proveedor',     // portal proveedor
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Usuario administrador SOLO para desarrollo local.
        // TODO: eliminar/rotar antes de producción.
        $admin = User::updateOrCreate(
            ['username' => 'dev.admin'],
            [
                'name' => 'Administrador Dev',
                'email' => 'dev.admin@frego.local',
                'password' => Hash::make('Frego2026$dev'),
                'status' => 1,
                'role' => 1,
            ]
        );

        $admin->syncRoles(['super-admin']);
    }
}
