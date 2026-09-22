<?php

namespace Tests\Feature\Cfdi;

use App\Livewire\Transactions\TransactionDetail;
use App\Livewire\Transactions\TransactionTable;
use App\Models\CfdiCancelacion;
use App\Models\Core\Transaction;
use App\Models\User;
use App\Support\Cfdi\CancelResult;
use App\Support\Cfdi\PacClient;
use App\Support\Cfdi\SatStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CoreSchema;
use Tests\Support\FakePacClient;
use Tests\Support\FakeSatStatus;
use Tests\Support\InvoiceFixture;
use Tests\TestCase;

/**
 * El ciclo de vida real de una cancelación de CFDI.
 *
 * Pedir la cancelación no es cancelar: el PAC devuelve un acuse y el
 * comprobante sigue vigente ante el SAT hasta que el receptor autorice o se le
 * venza el plazo. Antes de esto el sistema marcaba «cancelada» en cuanto la
 * llamada no tronaba, y el ERP decía una cosa mientras el SAT decía otra.
 *
 * **Ni el PAC ni el SAT se tocan de verdad**: los dos van sustituidos.
 */
class CancellationStatusTest extends TestCase
{
    private FakePacClient $pac;

    protected function setUp(): void
    {
        parent::setUp();

        CoreSchema::create();
        Storage::fake('documentos');
        Http::preventStrayRequests();

        $this->pac = new FakePacClient;
        $this->app->instance(PacClient::class, $this->pac);

        InvoiceFixture::seed();
    }

    private function usuario(int $rol = User::ROLE_ADMIN): User
    {
        $usuario = new User;
        $usuario->usr_id = 7;
        $usuario->role = $rol;

        return $usuario;
    }

    /** Factura timbrada y lista para cancelar, con su XML guardado. */
    private function detalle(): Testable
    {
        $this->actingAs($this->usuario());

        return Livewire::test(TransactionDetail::class, ['transaction' => 1]);
    }

    private function timbrar(): void
    {
        $this->detalle()->call('stamp')->assertHasNoErrors();
    }

    private function cancelar(string $motivo = '02'): Testable
    {
        return $this->detalle()->call('startCancel')->set('cancelReason', $motivo)->call('cancelStamp');
    }

    private function sat(FakeSatStatus $sat): FakeSatStatus
    {
        $this->app->instance(SatStatus::class, $sat);

        return $sat;
    }

    // ------------------------------------------- Lo que contesta el PAC

    /** Cancelación consumada: ahí sí se marca, como siempre. */
    public function test_una_cancelacion_confirmada_marca_la_factura(): void
    {
        $this->timbrar();
        $this->pac->responde(new CancelResult(CancelResult::CANCELADA, 'GT02', 'El SAT canceló el comprobante.'));

        $this->cancelar()->assertHasNoErrors();

        $this->assertSame(1, (int) Transaction::find(1)->cancelled);
    }

    public function test_una_cancelacion_confirmada_guarda_el_motivo_y_el_folio_que_sustituye(): void
    {
        $this->timbrar();
        $this->pac->responde(new CancelResult(CancelResult::CANCELADA, 'GT02', 'El SAT canceló el comprobante.'));

        $this->detalle()
            ->call('startCancel')
            ->set('cancelReason', '01')
            ->set('replacementUuid', 'UUID-NUEVO')
            ->call('cancelStamp')
            ->assertHasNoErrors();

        $this->assertSame('UUID-NUEVO', Transaction::find(1)->new_seal);
    }

    /** GT11: el receptor tiene que autorizar, así que la factura SIGUE VIGENTE. */
    public function test_un_acuse_gt11_no_cancela_la_factura(): void
    {
        $this->timbrar();

        $this->cancelar()->assertHasNoErrors();

        $this->assertSame(0, (int) Transaction::find(1)->cancelled);
    }

    public function test_un_acuse_gt11_queda_anotado_como_solicitud(): void
    {
        $this->timbrar();

        $this->cancelar()->assertHasNoErrors();

        $solicitud = CfdiCancelacion::where('transc_id', 1)->first();

        $this->assertSame(
            [CancelResult::SOLICITADA, 'GT11', $this->pac->uuid, '02', 7],
            [$solicitud->estado, $solicitud->codigo, $solicitud->uuid, $solicitud->motivo, $solicitud->solicitado_por],
        );
    }

    /** El 402 del PAC no es un error: la solicitud ya estaba puesta. */
    public function test_el_folio_en_cola_no_se_enseña_como_error(): void
    {
        $this->timbrar();
        $this->pac->responde(new CancelResult(CancelResult::EN_COLA, '402', 'Este folio ya tenía una solicitud de cancelación en curso ante el SAT.'));

        $this->cancelar()->assertHasNoErrors();

        $this->assertSame(CancelResult::EN_COLA, CfdiCancelacion::where('transc_id', 1)->first()->estado);
    }

    /** Un rechazo de verdad (300: folio no localizado) sí se enseña. */
    public function test_un_rechazo_del_pac_no_deja_rastro_de_cancelacion(): void
    {
        $this->timbrar();
        $this->app->instance(PacClient::class, new FakePacClient(falla: 'El PAC respondió: [300] Error UUID no localizado en la base de timbrados'));

        $this->cancelar()->assertHasErrors('cfdi');

        $this->assertSame(0, CfdiCancelacion::count());
    }

    public function test_con_una_solicitud_en_curso_no_se_vuelve_a_pedir_la_cancelacion(): void
    {
        $this->timbrar();
        $this->cancelar()->assertHasNoErrors();

        $this->detalle()->call('startCancel')->assertForbidden();
    }

    // ------------------------------------------- Lo que contesta el SAT

    /** Vigente: el receptor todavía no contesta, y la factura no se toca. */
    public function test_si_el_sat_dice_vigente_la_factura_sigue_sin_cancelar(): void
    {
        $this->timbrar();
        $this->cancelar();
        $this->sat(new FakeSatStatus(estado: 'Vigente', estatusCancelacion: 'En proceso'));

        $this->detalle()->call('refreshSatStatus');

        $this->assertSame(0, (int) Transaction::find(1)->cancelled);
    }

    public function test_la_consulta_al_sat_queda_anotada(): void
    {
        $this->timbrar();
        $this->cancelar();
        $this->sat(new FakeSatStatus(estado: 'Vigente', estatusCancelacion: 'En proceso'));

        $this->detalle()->call('refreshSatStatus');

        $solicitud = CfdiCancelacion::where('transc_id', 1)->first();

        $this->assertSame(
            ['Vigente', 'En proceso', CancelResult::SOLICITADA],
            [$solicitud->sat_estado, $solicitud->sat_estatus, $solicitud->estado],
        );
    }

    /** Cancelado ante el SAT: ahora sí, sea por autorización o por plazo vencido. */
    public function test_si_el_sat_dice_cancelado_se_marca_la_factura(): void
    {
        $this->timbrar();
        $this->cancelar();
        $this->sat(new FakeSatStatus(estado: 'Cancelado', estatusCancelacion: 'Plazo vencido'));

        $this->detalle()->call('refreshSatStatus');

        $this->assertSame(1, (int) Transaction::find(1)->cancelled);
    }

    public function test_la_consulta_va_con_los_cuatro_datos_del_xml_timbrado(): void
    {
        $this->timbrar();
        $this->cancelar();
        $sat = $this->sat(new FakeSatStatus);

        $this->detalle()->call('refreshSatStatus');

        $this->assertSame(
            "?re=XAXX010101000&rr=AAA010101AAA&tt=2320.00&id={$this->pac->uuid}",
            $sat->consultas[0],
        );
    }

    /** Un servicio caído no puede dejar la factura en un estado inventado. */
    public function test_si_el_sat_no_contesta_no_se_cambia_nada(): void
    {
        $this->timbrar();
        $this->cancelar();
        $this->sat(new FakeSatStatus(caido: true));

        $this->detalle()->call('refreshSatStatus')->assertHasNoErrors();

        $this->assertNull(CfdiCancelacion::where('transc_id', 1)->first()->verificado_at);
    }

    /** Sin XML no hay con qué preguntar, y tampoco truena. */
    public function test_sin_xml_la_consulta_avisa_en_vez_de_tronar(): void
    {
        CfdiCancelacion::create([
            'transc_id' => 1, 'uuid' => 'UUID-SIN-XML', 'motivo' => '02',
            'estado' => CancelResult::SOLICITADA, 'solicitado_at' => now(),
        ]);

        $this->detalle()->call('refreshSatStatus')->assertSee('No se encontró el XML timbrado');
    }

    // ------------------------------------------------------- La pantalla

    public function test_el_detalle_avisa_que_falta_la_autorizacion_del_receptor(): void
    {
        $this->timbrar();

        $this->cancelar();

        $this->detalle()->assertSee('Cancelación en proceso')->assertSee('El receptor debe autorizarla');
    }

    public function test_el_listado_distingue_la_cancelacion_en_proceso_de_la_cancelada(): void
    {
        $this->timbrar();
        $this->cancelar();

        $this->actingAs($this->usuario());

        // La insignia roja de «Cancelada» es la otra de esta misma celda; con
        // una solicitud en curso no debe aparecer.
        Livewire::test(TransactionTable::class, ['screen' => 'invoice'])
            ->assertSee('Cancelación en proceso')
            ->assertDontSee('badge badge-danger ml-1', false);
    }

    // --------------------------------------------------------- El comando

    public function test_el_comando_confirma_las_solicitudes_pendientes(): void
    {
        $this->timbrar();
        $this->cancelar();
        $this->sat(new FakeSatStatus(estado: 'Cancelado', estatusCancelacion: 'Cancelado con aceptación'));

        $this->artisan('cfdi:revisar-cancelaciones', ['--pausa' => 0])->assertSuccessful();

        $this->assertSame(1, (int) Transaction::find(1)->cancelled);
    }

    public function test_el_comando_deja_en_paz_las_que_el_sat_ve_vigentes(): void
    {
        $this->timbrar();
        $this->cancelar();
        $this->sat(new FakeSatStatus(estado: 'Vigente', estatusCancelacion: 'En proceso'));

        $this->artisan('cfdi:revisar-cancelaciones', ['--pausa' => 0])->assertSuccessful();

        $this->assertTrue(CfdiCancelacion::where('transc_id', 1)->first()->estaPendiente());
    }

    /**
     * Las facturas que se marcaron canceladas sin mirar la respuesta del PAC
     * entran como solicitudes, para que el comando las revise y las corrija. Su
     * `cancelled` no se toca: cambiarlo a ciegas sería el mismo error al revés.
     */
    public function test_la_migracion_rellena_las_canceladas_de_antes(): void
    {
        Schema::drop('cfdi_cancelacion');
        DB::table('transaction')->where('transc_id', 1)->update([
            'cancelled' => 1, 'seal' => 'UUID-VIEJO', 'cancel_reason_id' => '03', 'modified_by' => 4,
        ]);

        (require database_path('migrations/2026_09_22_000001_create_cfdi_cancelacion.php'))->up();

        $solicitud = CfdiCancelacion::where('transc_id', 1)->first();

        $this->assertSame(
            ['UUID-VIEJO', '03', CancelResult::SOLICITADA, 1],
            [$solicitud->uuid, $solicitud->motivo, $solicitud->estado, (int) Transaction::find(1)->cancelled],
        );
    }

    public function test_el_comando_no_vuelve_a_preguntar_por_las_ya_cerradas(): void
    {
        $this->timbrar();
        $this->cancelar();
        CfdiCancelacion::where('transc_id', 1)->update(['estado' => CancelResult::CANCELADA]);
        $sat = $this->sat(new FakeSatStatus);

        $this->artisan('cfdi:revisar-cancelaciones', ['--pausa' => 0])->assertSuccessful();

        $this->assertSame([], $sat->consultas);
    }
}
