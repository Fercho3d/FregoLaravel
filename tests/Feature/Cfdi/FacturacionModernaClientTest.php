<?php

namespace Tests\Feature\Cfdi;

use App\Support\Cfdi\CfdiException;
use App\Support\Cfdi\FacturacionModernaClient;
use Tests\TestCase;

/**
 * La petición SOAP que se le arma al PAC, sin hablar con él.
 *
 * La cancelación tiene que viajar EXACTAMENTE como la manda el componente
 * `FacturacionModerna` de Yii2, que es lo que hoy funciona en producción:
 * mismo método, mismo endpoint y mismos nombres de clave (con sus mayúsculas).
 * Un nombre distinto no da error claro: el PAC contesta que no encuentra el
 * folio.
 */
class FacturacionModernaClientTest extends TestCase
{
    /** Cliente que apunta lo que iba a mandar en vez de salir a la red. */
    private function cliente(): FacturacionModernaClient
    {
        return new class extends FacturacionModernaClient
        {
            /** @var array<int, array{metodo: string, peticion: array<string, mixed>, endpoint: string}> */
            public array $llamadas = [];

            protected function soap(string $metodo, array $peticion, string $endpoint): object
            {
                $this->llamadas[] = compact('metodo', 'peticion', 'endpoint');

                return (object) [];
            }
        };
    }

    public function test_la_cancelacion_va_como_en_el_sistema_original(): void
    {
        config(['timbrado.produccion' => false]);

        $cliente = $this->cliente();
        $cliente->cancel('UUID-1', 'FTM1507038V6', '02');

        $llamada = $cliente->llamadas[0];

        $this->assertSame('requestCancelarCFDI', $llamada['metodo']);
        $this->assertSame(config('timbrado.endpoints.pruebas'), $llamada['endpoint'], 'Se cancela ante el mismo endpoint que timbra.');
        $this->assertSame(['Motivo', 'uuid', 'emisorRFC', 'UserID', 'UserPass'], array_keys($llamada['peticion']));
        $this->assertSame('02', $llamada['peticion']['Motivo']);
        $this->assertSame('UUID-1', $llamada['peticion']['uuid']);
        // En pruebas el original manda el RFC de la cuenta demo, no el del emisor.
        $this->assertSame(config('timbrado.demo.rfc_cuenta'), $llamada['peticion']['emisorRFC']);
        $this->assertSame(config('timbrado.demo.usuario'), $llamada['peticion']['UserID']);
    }

    public function test_el_folio_de_sustitucion_solo_viaja_con_el_motivo_01(): void
    {
        config(['timbrado.produccion' => false]);

        $cliente = $this->cliente();
        $cliente->cancel('UUID-1', 'FTM1507038V6', '01', 'UUID-NUEVO');
        $cliente->cancel('UUID-1', 'FTM1507038V6', '03', 'UUID-QUE-SOBRA');

        $this->assertSame('UUID-NUEVO', $cliente->llamadas[0]['peticion']['FolioSustitucion']);
        $this->assertArrayNotHasKey('FolioSustitucion', $cliente->llamadas[1]['peticion']);
    }

    /** En producción `emisorRFC` es el RFC con el que se timbró: el PAC busca el folio en la base de ese emisor. */
    public function test_en_produccion_cancela_con_el_rfc_del_emisor_y_las_credenciales_reales(): void
    {
        config([
            'timbrado.produccion' => true,
            'timbrado.rfc_cuenta' => 'CUENTA010101AAA',
            'timbrado.usuario' => 'usuario-real',
            'timbrado.password' => 'clave-real',
        ]);

        $cliente = $this->cliente();
        $cliente->cancel('UUID-1', 'FTM1507038V6', '02');

        $peticion = $cliente->llamadas[0]['peticion'];

        $this->assertSame('FTM1507038V6', $peticion['emisorRFC']);
        $this->assertSame('usuario-real', $peticion['UserID']);
        $this->assertSame('clave-real', $peticion['UserPass']);
        $this->assertSame(config('timbrado.endpoints.produccion'), $cliente->llamadas[0]['endpoint']);
    }

    public function test_el_endpoint_de_cancelacion_se_puede_sobrescribir(): void
    {
        config(['timbrado.produccion' => false, 'timbrado.endpoints.cancelacion' => 'https://otro.pac.test/wsdl']);

        $cliente = $this->cliente();
        $cliente->cancel('UUID-1', 'FTM1507038V6', '02');

        $this->assertSame('https://otro.pac.test/wsdl', $cliente->llamadas[0]['endpoint']);
    }

    /** El timbrado sí viaja con el RFC de la CUENTA: el del emisor va dentro del layout. */
    public function test_el_timbrado_viaja_con_las_credenciales_de_la_cuenta(): void
    {
        config(['timbrado.produccion' => false]);

        $cliente = $this->cliente();

        try {
            $cliente->stamp('[ComprobanteFiscalDigital]');
        } catch (CfdiException) {
            // El doble no devuelve XML; aquí solo interesa la petición.
        }

        $llamada = $cliente->llamadas[0];

        $this->assertSame('requestTimbrarCFDI', $llamada['metodo']);
        $this->assertSame(config('timbrado.endpoints.pruebas'), $llamada['endpoint']);
        $this->assertSame(base64_encode('[ComprobanteFiscalDigital]'), $llamada['peticion']['text2CFDI']);
        $this->assertSame(config('timbrado.demo.rfc_cuenta'), $llamada['peticion']['emisorRFC']);
    }
}
