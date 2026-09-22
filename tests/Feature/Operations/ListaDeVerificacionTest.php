<?php

namespace Tests\Feature\Operations;

use App\Livewire\Operations\BookingDetail;
use App\Livewire\Operations\ContinuityReport;
use App\Models\User;
use App\Support\Milestones\BookingMilestones;
use App\Support\Milestones\Checklist;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CoreSchema;
use Tests\TestCase;

/**
 * La lista de verificación vuelve a escribirse.
 *
 * La continuidad tiene dos capas: la fecha planeada de cada hito
 * (`booking_continuity` / `hito_por_expediente`) y su cumplimiento
 * (`check_list`, con fecha y autor por casilla). Antes «marcar» escribía la
 * fecha de hoy sobre la planeada y nadie tocaba `check_list`, que es lo que la
 * operación marca a diario en el sistema original.
 */
class ListaDeVerificacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CoreSchema::create();
        CoreSchema::createUsers();

        DB::table('client')->insert([['client_id' => 1, 'fullName' => 'Cliente Uno']]);
        DB::table('booking')->insert([[
            'booking_id' => 1, 'booking_number' => 'BK-1', 'client' => 1, 'mode' => 10, 'is_draft' => 0, 'locked' => 0,
        ]]);
    }

    private function usuario(int $rol = User::ROLE_ADMIN): User
    {
        return User::create([
            'username' => 'operador'.$rol, 'name' => 'Ana Ruiz', 'password' => 'secreto-de-prueba',
            'role' => $rol, 'access' => User::ACCESS_INTERNAL, 'status' => 1,
        ]);
    }

    private function detalle(int $rol = User::ROLE_ADMIN): Testable
    {
        return Livewire::actingAs($this->usuario($rol))->test(BookingDetail::class, ['booking' => 1]);
    }

    private function casilla(string $casilla): ?object
    {
        $fila = DB::table('check_list')->where('booking', 1)->first();

        return $fila === null ? null : (object) ['fecha' => $fila->{$casilla.'_chk_date'}, 'por' => $fila->{$casilla.'_chk_by'}];
    }

    // ------------------------------------------------------------- Marcar

    /** Marcar escribe el cumplimiento, creando la fila de `check_list` si no existe. */
    public function test_marcar_escribe_check_list_con_fecha_y_autor(): void
    {
        $this->detalle()->call('marcaHito', 'pickup_date')->assertHasNoErrors();

        $marca = $this->casilla('pickup_date');

        $this->assertSame(
            [now()->toDateString(), (int) DB::table('users')->value('usr_id')],
            [substr((string) $marca->fecha, 0, 10), (int) $marca->por],
        );
    }

    public function test_marcar_no_toca_la_fecha_planeada(): void
    {
        BookingMilestones::guarda(1, 'pickup_date', '2026-03-01', 1);

        $this->detalle()->call('marcaHito', 'pickup_date');

        $this->assertSame(
            ['2026-03-01', '2026-03-01'],
            [
                substr((string) DB::table('booking_continuity')->where('booking', 1)->value('pickup_date'), 0, 10),
                substr(BookingMilestones::de(1)['pickup_date'], 0, 10),
            ],
        );
    }

    /** `modified_by` es el autor que lee el disparador de `check_list_history`. */
    public function test_cada_escritura_firma_modified_by(): void
    {
        $this->detalle()->call('marcaHito', 'pickup_date');

        $this->assertSame((int) DB::table('users')->value('usr_id'), (int) DB::table('check_list')->where('booking', 1)->value('modified_by'));
    }

    /** Marcar es de cualquier usuario interno, como el `check` del original. */
    public function test_quien_no_es_administrador_marca_la_primera_tarea(): void
    {
        $this->detalle(User::ROLE_USER)->call('marcaHito', 'pickup_date')->assertHasNoErrors();

        $this->assertNotNull($this->casilla('pickup_date')?->fecha);
    }

    /** Regla del original: sin la tarea anterior marcada, el usuario no marca la siguiente. */
    public function test_quien_no_es_administrador_no_se_salta_la_tarea_anterior(): void
    {
        $this->detalle(User::ROLE_USER)->call('marcaHito', 'doc_cut_of')->assertHasErrors('hito');

        $this->assertNull($this->casilla('doc_cut_of'));
    }

    public function test_con_la_anterior_marcada_el_usuario_sigue(): void
    {
        Checklist::marca(1, 'pickup_date', 1);

        $this->detalle(User::ROLE_USER)->call('marcaHito', 'doc_cut_of')->assertHasNoErrors();

        $this->assertNotNull($this->casilla('doc_cut_of')?->fecha);
    }

    /** El administrador no tiene que seguir el orden. */
    public function test_el_administrador_marca_en_cualquier_orden(): void
    {
        $this->detalle()->call('marcaHito', 'departure')->assertHasNoErrors();

        $this->assertNotNull($this->casilla('departure')?->fecha);
    }

    // ---------------------------------------------------------- Desmarcar

    public function test_otro_clic_del_administrador_desmarca(): void
    {
        Checklist::marca(1, 'pickup_date', 1);

        $this->detalle()->call('marcaHito', 'pickup_date');

        $this->assertSame([null, null], [$this->casilla('pickup_date')->fecha, $this->casilla('pickup_date')->por]);
    }

    /** Una tarea marcada solo la toca el administrador, como en el original. */
    public function test_quien_no_es_administrador_no_desmarca(): void
    {
        Checklist::marca(1, 'pickup_date', 1);

        $this->detalle(User::ROLE_USER)->call('marcaHito', 'pickup_date')->assertForbidden();

        $this->assertNotNull($this->casilla('pickup_date')->fecha);
    }

    // ------------------------------------------------------------ Detalle

    public function test_el_detalle_ensena_quien_marco_y_el_retraso(): void
    {
        BookingMilestones::guarda(1, 'pickup_date', '2026-03-01', 1);
        $usuario = $this->usuario();
        Checklist::marca(1, 'pickup_date', $usuario->usr_id, '2026-03-03 10:30:00');

        Livewire::actingAs($usuario)->test(BookingDetail::class, ['booking' => 1])
            ->assertSee('03/03/2026 10:30')
            ->assertSee('Ana Ruiz')
            ->assertSee('1 día, 10 horas, 30 minutos de retraso');
    }

    /** La palomita y el avance salen del cumplimiento, no de la fecha planeada. */
    public function test_la_fecha_planeada_sola_no_cuenta_como_marcada(): void
    {
        BookingMilestones::guarda(1, 'pickup_date', '2026-03-01', 1);

        $this->detalle()->assertSee('0 de 14');
    }

    // ---------------------------------------------------- Delivery time

    public function test_a_tiempo_si_se_cumple_el_mismo_dia_planeado(): void
    {
        $this->assertTrue(Checklist::retraso('2026-03-01', '2026-03-01 17:45:00')['aTiempo']);
    }

    public function test_con_hora_planeada_se_compara_a_la_hora(): void
    {
        $this->assertSame('2 horas de retraso', Checklist::retraso('2026-03-01 08:00:00', '2026-03-01 10:00:00')['texto']);
    }

    public function test_sin_una_de_las_dos_fechas_no_hay_retraso_que_calcular(): void
    {
        $this->assertNull(Checklist::retraso(null, '2026-03-01 10:00:00'));
    }

    // ---------------------------------------------------------- Reporte

    public function test_el_reporte_ensena_las_cumplidas_al_pedirlas(): void
    {
        BookingMilestones::guarda(1, 'pickup_date', '2026-03-01', 1);
        Checklist::marca(1, 'pickup_date', 1, '2026-03-09 10:00:00');

        $this->actingAs($this->usuario());

        Livewire::test(ContinuityReport::class)->assertSee('01/03')->assertDontSee('09/03');
        Livewire::test(ContinuityReport::class)->set('verCumplidas', true)->assertSee('09/03')->assertDontSee('01/03');
    }

    /** Viendo cumplidas no se captura: el cumplimiento se marca en el detalle. */
    public function test_viendo_cumplidas_no_se_captura(): void
    {
        $this->actingAs($this->usuario());

        Livewire::test(ContinuityReport::class)->set('verCumplidas', true)->call('editMilestone', 1, 'pickup_date', null)->assertStatus(422);
    }
}
