<?php

namespace Tests\Feature\Exchange;

use App\Livewire\Exchange\ExchangeManager;
use App\Models\Core\Exchange;
use App\Models\User;
use App\Support\ExchangeRates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CoreSchema;
use Tests\TestCase;

/**
 * Tipos de cambio.
 *
 * La regla que más importa: no puede haber dos para la misma moneda el mismo
 * día, porque el motor de consulta une por esa pareja y duplicaría los importes.
 */
class ExchangeManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CoreSchema::create();
        CoreSchema::createUsers();

        Http::preventStrayRequests();

        DB::table('account')->insert([
            ['account_id' => 1, 'account_name' => 'Pesos', 'default' => 1, 'prefix' => 'MXN'],
            ['account_id' => 2, 'account_name' => 'Dólares', 'default' => null, 'prefix' => 'USD'],
        ]);
    }

    private function usuario(int $rol = User::ROLE_ADMIN): User
    {
        return User::create([
            'username' => 'operador'.$rol, 'password' => 'secreto-de-prueba', 'role' => $rol, 'status' => 1,
        ]);
    }

    private function pantalla(int $rol = User::ROLE_ADMIN): Testable
    {
        $this->actingAs($this->usuario($rol));

        return Livewire::test(ExchangeManager::class);
    }

    public function test_capturar_un_tipo_de_cambio(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('date', '2026-02-10')
            ->set('account', '2')
            ->set('value', '17.4321')
            ->call('save')
            ->assertHasNoErrors();

        $tipo = Exchange::first();

        $this->assertSame(17.4321, $tipo->exchange_value);
        $this->assertSame('2026-02-10', $tipo->date_exchange->toDateString());
    }

    /** El motor une por (fecha, moneda): dos filas duplicarían los importes. */
    public function test_no_se_repite_la_misma_moneda_el_mismo_dia(): void
    {
        Exchange::create(['exchange_id' => 1, 'date_exchange' => '2026-02-10', 'account' => 2, 'exchange_value' => 17]);

        $this->pantalla()
            ->call('create')
            ->set('date', '2026-02-10')
            ->set('account', '2')
            ->set('value', '18')
            ->call('save')
            ->assertHasErrors('date');

        $this->assertSame(1, Exchange::count());
    }

    public function test_dos_monedas_el_mismo_dia_si_conviven(): void
    {
        Exchange::create(['exchange_id' => 1, 'date_exchange' => '2026-02-10', 'account' => 1, 'exchange_value' => 1]);

        $this->pantalla()
            ->call('create')
            ->set('date', '2026-02-10')
            ->set('account', '2')
            ->set('value', '17.5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Exchange::count());
    }

    public function test_el_tipo_de_cambio_no_puede_ser_cero(): void
    {
        $this->pantalla()
            ->call('create')
            ->set('date', '2026-02-10')
            ->set('account', '2')
            ->set('value', '0')
            ->call('save')
            ->assertHasErrors('value');
    }

    public function test_editar_no_choca_consigo_mismo(): void
    {
        Exchange::create(['exchange_id' => 1, 'date_exchange' => '2026-02-10', 'account' => 2, 'exchange_value' => 17]);

        $this->pantalla()
            ->call('edit', 1)
            ->set('value', '17.9')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(17.9, Exchange::find(1)->exchange_value);
    }

    public function test_traer_el_del_dia_avisa_cuando_el_dof_no_contesta(): void
    {
        Http::fake(['www.banxico.org.mx/*' => Http::response(['bmx' => ['series' => [['datos' => []]]]])]);

        $this->pantalla()->call('fetchToday')->assertHasErrors('fetch');
    }

    /** El valor de Banxico para el día se guarda tal cual, en la cuenta del dólar. */
    public function test_el_del_dia_se_toma_de_banxico(): void
    {
        Http::fake(['www.banxico.org.mx/*' => Http::response(['bmx' => ['series' => [
            ['idSerie' => 'SF60653', 'datos' => [['fecha' => '24/08/2026', 'dato' => '16.9583']]],
        ]]])]);

        app(ExchangeRates::class)->ensureFor(Carbon::parse('2026-08-24'));

        $this->assertSame(
            [16.9583, 2],
            [(float) Exchange::whereDate('date_exchange', '2026-08-24')->value('exchange_value'), (int) Exchange::value('account')],
        );
    }

    /** Banxico rechaza el token: cualquier pantalla avisa que hay que llamar al administrador. */
    public function test_avisa_cuando_banxico_rechaza_el_token(): void
    {
        Http::fake(['www.banxico.org.mx/*' => Http::response(['error' => ['mensaje' => 'Token inválido']], 400)]);

        app(ExchangeRates::class)->ensureFor(Carbon::parse('2026-08-24'));

        $this->actingAs($this->usuario())->get(route('dashboard'))
            ->assertSee(__('El token de Banxico venció o no es válido y el tipo de cambio no se está registrando. Contacte a su administrador.'));
    }

    public function test_avisa_cuando_no_se_puede_conectar_con_banxico(): void
    {
        Http::fake(['www.banxico.org.mx/*' => Http::failedConnection()]);

        app(ExchangeRates::class)->ensureFor(Carbon::parse('2026-08-24'));

        $this->actingAs($this->usuario())->get(route('dashboard'))
            ->assertSee(__('No se pudo conectar con Banxico y el tipo de cambio no se está registrando. Contacte a su administrador.'));
    }

    /** En cuanto Banxico vuelve a contestar, el aviso se quita solo. */
    public function test_el_aviso_se_quita_cuando_banxico_contesta(): void
    {
        Http::fake(['www.banxico.org.mx/*' => Http::sequence()
            ->push(['error' => ['mensaje' => 'Token inválido']], 400)
            ->push(['bmx' => ['series' => [['datos' => [['fecha' => '24/08/2026', 'dato' => '16.9583']]]]]]),
        ]);

        app(ExchangeRates::class)->ensureFor(Carbon::parse('2026-08-24'));
        app(ExchangeRates::class)->ensureFor(Carbon::parse('2026-08-24'));

        $this->assertNull(ExchangeRates::failure());
    }

    public function test_quien_no_es_administrador_no_entra(): void
    {
        $this->actingAs($this->usuario(User::ROLE_USER));

        Livewire::test(ExchangeManager::class)->assertForbidden();
    }
}
