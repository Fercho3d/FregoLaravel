<?php

namespace Tests\Feature\Users;

use App\Livewire\Users\UserManager;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CoreSchema;
use Tests\TestCase;

/**
 * Altas y accesos de usuarios.
 *
 * Solo el super administrador entra, igual que en el sistema original.
 */
class UserManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CoreSchema::create();
        CoreSchema::createUsers();

        DB::table('client')->insert([['client_id' => 1, 'fullName' => 'Cliente Uno']]);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'username' => 'super.admin', 'email' => 'super@ejemplo.com',
            'password' => 'secreto-de-prueba', 'role' => User::ROLE_SUPER_ADMIN, 'status' => 1,
        ]);
    }

    private function pantalla(?User $como = null): Testable
    {
        $this->actingAs($como ?? $this->superAdmin());

        return Livewire::test(UserManager::class);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.normal', 'password' => 'secreto-de-prueba',
            'role' => User::ROLE_ADMIN, 'status' => 1,
        ]);
    }

    public function test_un_usuario_normal_no_entra(): void
    {
        $usuario = User::create([
            'name' => 'Juan', 'username' => 'juan.normal', 'password' => 'secreto-de-prueba',
            'role' => User::ROLE_USER, 'status' => 1,
        ]);

        $this->actingAs($usuario);

        Livewire::test(UserManager::class)->assertForbidden();
    }

    public function test_un_administrador_si_administra_usuarios(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(UserManager::class)->assertSee(__('Usuarios y accesos'));
    }

    public function test_un_administrador_no_puede_crear_super_administradores(): void
    {
        $this->pantalla($this->admin())
            ->call('create')
            ->set('username', 'aspirante')
            ->set('email', 'aspirante@ejemplo.com')
            ->set('userRole', (string) User::ROLE_SUPER_ADMIN)
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors('userRole');

        $this->assertNull(User::where('username', 'aspirante')->first());
    }

    public function test_un_administrador_no_puede_tocar_a_un_super_administrador(): void
    {
        $dueno = $this->superAdmin();

        $this->pantalla($this->admin())->call('edit', $dueno->usr_id)->assertForbidden();
    }

    public function test_crear_un_usuario_interno(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('name', 'Karina')
            ->set('username', 'karina')
            ->set('email', 'karina@ejemplo.com')
            ->set('userRole', (string) User::ROLE_USER)
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasNoErrors();

        $creado = User::where('username', 'karina')->first();

        $this->assertNotNull($creado);
        $this->assertSame(1, (int) $creado->status);
        $this->assertTrue(Hash::check('contrasena-larga', $creado->password), 'La contraseña debe guardarse cifrada.');
    }

    public function test_el_usuario_no_se_repite(): void
    {
        $this->superAdmin();

        $this->pantalla()
            ->call('create')
            ->set('username', 'super.admin')
            ->set('email', 'otro@ejemplo.com')
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors('username');
    }

    public function test_las_contrasenas_deben_coincidir(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('username', 'nuevo')
            ->set('email', 'nuevo@ejemplo.com')
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'otra-distinta')
            ->call('save')
            ->assertHasErrors('password');
    }

    /** Un acceso de portal no tiene sentido sin el cliente o proveedor al que pertenece. */
    public function test_el_acceso_de_portal_exige_a_quien_pertenece(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('username', 'portal.cliente')
            ->set('email', 'portal@ejemplo.com')
            ->set('access', (string) UserManager::ACCESS_CLIENT)
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors('partyId');
    }

    public function test_un_acceso_de_portal_queda_ligado_a_su_cliente(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('username', 'portal.cliente')
            ->set('email', 'portal@ejemplo.com')
            ->set('access', (string) UserManager::ACCESS_CLIENT)
            ->set('userRole', (string) User::ROLE_CLIENT_READONLY)
            ->set('partyId', '1')
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasNoErrors();

        $creado = User::where('username', 'portal.cliente')->first();

        $this->assertSame(1, (int) $creado->client_id);
        $this->assertNull($creado->provider_id);
    }

    public function test_editar_sin_tocar_la_contrasena_la_deja_igual(): void
    {
        $otro = User::create([
            'name' => 'Karina', 'username' => 'karina', 'email' => 'karina@ejemplo.com',
            'password' => 'contrasena-original', 'role' => User::ROLE_USER, 'status' => 1,
        ]);

        $this->pantalla()
            ->call('edit', $otro->usr_id)
            ->set('name', 'Karina Pérez')
            ->call('save')
            ->assertHasNoErrors();

        $otro->refresh();

        $this->assertSame('Karina Pérez', $otro->name);
        $this->assertTrue(Hash::check('contrasena-original', $otro->password));
    }

    public function test_cambiar_la_contrasena(): void
    {
        $otro = User::create([
            'username' => 'karina', 'password' => 'contrasena-original',
            'role' => User::ROLE_USER, 'status' => 1,
        ]);

        $this->pantalla()
            ->call('startPasswordChange', $otro->usr_id)
            ->set('password', 'contrasena-nueva')
            ->set('passwordConfirmation', 'contrasena-nueva')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('contrasena-nueva', $otro->refresh()->password));
    }

    /** No se borran: la tabla la referencian las columnas de auditoría del sistema. */
    public function test_dar_de_baja_y_reactivar(): void
    {
        $otro = User::create([
            'username' => 'karina', 'password' => 'x', 'role' => User::ROLE_USER, 'status' => 1,
        ]);

        $this->pantalla()->call('toggleActive', $otro->usr_id);
        $this->assertSame(0, (int) $otro->refresh()->status);

        $this->pantalla()->call('toggleActive', $otro->usr_id);
        $this->assertSame(1, (int) $otro->refresh()->status);
    }

    public function test_nadie_se_da_de_baja_a_si_mismo(): void
    {
        $super = $this->superAdmin();

        $this->pantalla($super)->call('toggleActive', $super->usr_id)->assertStatus(422);

        $this->assertSame(1, (int) $super->refresh()->status);
    }
    // ------------------------------------------------- Roles de portal (A20)

    /** Cuenta del portal de cliente con rol 13 (editor), como las 81 que hay en la base. */
    private function cuentaDePortal(int $rol = User::ROLE_CLIENT_EDITOR): User
    {
        return User::create([
            'username' => 'portal.editor', 'email' => 'editor@ejemplo.com', 'password' => 'x',
            'role' => $rol, 'access' => User::ACCESS_CLIENT, 'client_id' => 1, 'status' => 1,
        ]);
    }

    public function test_editar_una_cuenta_de_portal_sin_tocar_el_rol_guarda_bien(): void
    {
        $cuenta = $this->cuentaDePortal();

        $this->pantalla()
            ->call('edit', $cuenta->usr_id)
            ->set('name', 'Editor del cliente')
            ->call('save')
            ->assertHasNoErrors();

        $cuenta->refresh();

        $this->assertSame('Editor del cliente', $cuenta->name);
        $this->assertSame(User::ROLE_CLIENT_EDITOR, (int) $cuenta->role);
    }

    public function test_un_acceso_de_cliente_no_acepta_un_rol_interno(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('username', 'portal.admin')
            ->set('email', 'portal@ejemplo.com')
            ->set('access', (string) UserManager::ACCESS_CLIENT)
            ->set('partyId', '1')
            ->set('userRole', (string) User::ROLE_ADMIN)
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors('userRole');

        $this->assertNull(User::where('username', 'portal.admin')->first());
    }

    /** Al cambiar el acceso en el formulario, el rol pasa al primero de la lista que toca. */
    public function test_al_cambiar_el_acceso_cambia_la_lista_de_roles(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('access', (string) UserManager::ACCESS_PROVIDER)
            ->assertSet('userRole', (string) User::ROLE_PROVIDER_CUSTOMS_BROKER)
            ->assertSeeHtml('<option value="'.User::ROLE_PROVIDER_CARRIER.'" >'.__('Transportista').'</option>');
    }

    public function test_el_listado_pinta_la_etiqueta_del_rol_de_portal(): void
    {
        $this->cuentaDePortal(User::ROLE_CLIENT_CUSTOMS_BROKER);

        $this->pantalla()->assertSee(__('Agente aduanal del cliente'));
    }

    /** Los roles de portal no dan permisos internos. */
    public function test_los_roles_de_portal_no_son_administradores(): void
    {
        foreach (User::clientRoles() + User::providerRoles() as $rol => $etiqueta) {
            $cuenta = new User(['role' => $rol, 'access' => User::ACCESS_CLIENT]);

            $this->assertFalse($cuenta->isAdmin(), "El rol {$rol} ({$etiqueta}) no debe ser administrador.");
            $this->assertTrue($cuenta->isPortal());
        }
    }

    // --------------------------------------------- Baja y sesión (A19)

    public function test_dar_de_baja_limpia_el_token_de_recordar(): void
    {
        $otro = User::create([
            'username' => 'karina', 'password' => 'x', 'role' => User::ROLE_USER, 'status' => 1,
            'remember_token' => 'token-de-la-cookie',
        ]);

        $this->pantalla()->call('toggleActive', $otro->usr_id);

        $this->assertNull($otro->refresh()->remember_token);
    }

    // ------------------------------------------------- Uno mismo (extra)

    public function test_nadie_se_da_de_baja_a_si_mismo_desde_el_formulario(): void
    {
        $super = $this->superAdmin();

        $this->pantalla($super)
            ->call('edit', $super->usr_id)
            ->set('active', false)
            ->call('save')
            ->assertHasErrors('active');

        $this->assertSame(1, (int) $super->refresh()->status);
    }

    public function test_nadie_se_cambia_el_rol_a_si_mismo(): void
    {
        $super = $this->superAdmin();

        $this->pantalla($super)
            ->call('edit', $super->usr_id)
            ->set('userRole', (string) User::ROLE_USER)
            ->call('save')
            ->assertHasErrors('userRole');

        $this->assertTrue($super->refresh()->isSuperAdmin());
    }

    public function test_al_editarse_uno_mismo_no_aparece_la_casilla_de_activo(): void
    {
        $super = $this->superAdmin();

        $this->pantalla($super)->call('edit', $super->usr_id)->assertDontSeeHtml('wire:model="active"');
    }

    // ------------------------------------------------------ Correo (extra)

    public function test_el_correo_es_obligatorio(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('username', 'sin.correo')
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors(['email' => 'required']);
    }

    public function test_un_correo_nuevo_debe_tener_forma_de_correo(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('username', 'nuevo')
            ->set('email', 'esto-no-es-un-correo')
            ->set('password', 'contrasena-larga')
            ->set('passwordConfirmation', 'contrasena-larga')
            ->call('save')
            ->assertHasErrors(['email' => 'email']);
    }

    /** La tabla heredada guarda en `email` valores que no son correos; eso no debe bloquear la edición. */
    public function test_un_correo_heredado_sin_forma_no_impide_editar(): void
    {
        $otro = User::create([
            'username' => 'elymaersk', 'email' => 'elymaersk', 'password' => 'x',
            'role' => User::ROLE_USER, 'status' => 1,
        ]);

        $this->pantalla()
            ->call('edit', $otro->usr_id)
            ->set('name', 'Ely')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Ely', $otro->refresh()->name);
    }
}
