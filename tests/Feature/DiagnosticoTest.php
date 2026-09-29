<?php

namespace Tests\Feature;

use App\Mail\AlertaDeError;
use App\Support\Diagnostico\ContextoPeticion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Alertas por correo, peticiones lentas y fallas que reporta el navegador.
 */
class DiagnosticoTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carpeta = sys_get_temp_dir().'/frego-alertas-'.uniqid();
        config([
            'logging.channels.alertas.with.para' => 'alertas@example.com',
            'logging.channels.alertas.with.carpeta' => $this->carpeta,
        ]);
        Log::forgetChannel('alertas');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);

        parent::tearDown();
    }

    public function test_un_error_manda_la_alerta_con_la_excepcion(): void
    {
        Log::channel('alertas')->error('falló', ['exception' => new RuntimeException('se cayó el PAC')]);

        Mail::assertSent(AlertaDeError::class, fn (AlertaDeError $m) => $m->hasTo('alertas@example.com')
            && $m->titulo === 'RuntimeException: se cayó el PAC'
            && str_contains($m->detalle['archivo'], 'DiagnosticoTest.php'));
    }

    public function test_un_aviso_no_manda_correo(): void
    {
        Log::channel('alertas')->warning('Petición lenta: 6000 ms');

        Mail::assertNothingSent();
    }

    public function test_sin_destinatario_no_manda_nada(): void
    {
        config(['logging.channels.alertas.with.para' => null]);
        Log::forgetChannel('alertas');

        Log::channel('alertas')->error('falló');

        Mail::assertNothingSent();
    }

    public function test_el_mismo_error_repetido_se_cuenta_y_no_se_vuelve_a_mandar(): void
    {
        Log::channel('alertas')->error('falló');
        Log::channel('alertas')->error('falló');
        Log::channel('alertas')->error('falló');

        Mail::assertSentCount(1);
    }

    public function test_pasado_el_silencio_se_manda_otra_vez_con_las_repeticiones(): void
    {
        Log::channel('alertas')->error('falló');
        Log::channel('alertas')->error('falló');

        // Se simula que el aviso salió hace una hora.
        $archivo = $this->carpeta.'/'.sha1('falló');
        file_put_contents($archivo, (time() - 3600).' 1');

        Log::channel('alertas')->error('falló');

        Mail::assertSent(AlertaDeError::class, fn (AlertaDeError $m) => $m->repeticiones === 1);
    }

    public function test_describe_el_componente_y_el_metodo_de_livewire(): void
    {
        $request = Request::create('/livewire/update', 'POST', ['components' => [[
            'snapshot' => json_encode(['memo' => ['name' => 'operations.booking-detail']]),
            'calls' => [['method' => 'confirm']],
            'updates' => [],
        ]]]);

        $this->assertSame('operations.booking-detail → confirm', ContextoPeticion::describir($request)['livewire']);
    }

    public function test_una_peticion_lenta_queda_en_el_log(): void
    {
        config(['logging.peticion_lenta_ms' => 0]);
        Log::spy();

        $this->postJson(route('diagnostico.navegador'), ['tipo' => 'js', 'mensaje' => 'x']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje) => str_starts_with($mensaje, 'Petición lenta'));
    }

    public function test_una_accion_sin_respuesta_se_registra_como_error(): void
    {
        Log::spy();

        $this->postJson(route('diagnostico.navegador'), [
            'tipo' => 'sin_respuesta',
            'mensaje' => 'operations.booking-detail → confirm sin respuesta tras 35 s',
            'accion' => 'operations.booking-detail → confirm',
        ])->assertNoContent();

        Log::shouldHaveReceived('log')->withArgs(fn ($nivel, $mensaje, $contexto) => $nivel === 'error'
            && $contexto['livewire'] === 'operations.booking-detail → confirm');
    }

    public function test_un_error_de_javascript_solo_queda_como_aviso(): void
    {
        Log::spy();

        $this->postJson(route('diagnostico.navegador'), ['tipo' => 'js', 'mensaje' => 'x is undefined'])->assertNoContent();

        Log::shouldHaveReceived('log')->withArgs(fn ($nivel) => $nivel === 'warning');
    }

    public function test_un_tipo_desconocido_se_rechaza(): void
    {
        $this->postJson(route('diagnostico.navegador'), ['tipo' => 'otro', 'mensaje' => 'x'])->assertStatus(422);
    }
}
