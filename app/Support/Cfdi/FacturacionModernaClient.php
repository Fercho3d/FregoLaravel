<?php

namespace App\Support\Cfdi;

use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Cliente SOAP del PAC (Facturación Moderna / DINVBOX).
 *
 * Traducción del componente `FacturacionModerna` de Yii2. El endpoint y las
 * credenciales salen de `config/timbrado.php`, que en local apunta al ambiente
 * de **pruebas** salvo que se encienda `TIMBRADO_PRODUCCION`.
 */
class FacturacionModernaClient implements PacClient
{
    private const TIEMPO_LIMITE = 60;

    public function stamp(string $layout): StampedInvoice
    {
        $respuesta = $this->soap('requestTimbrarCFDI', [
            'text2CFDI' => base64_encode($layout),
            'generarCBB' => false,
            'generarPDF' => true,
            'generarTXT' => false,
        ] + $this->credentials(), $this->endpoint());

        $xml = isset($respuesta->xml) ? base64_decode($respuesta->xml) : null;

        if (blank($xml)) {
            throw new CfdiException('El PAC no devolvió el XML del comprobante.');
        }

        return new StampedInvoice(
            uuid: $this->uuidFrom($xml),
            xml: $xml,
            pdf: isset($respuesta->pdf) ? base64_decode($respuesta->pdf) : null,
        );
    }

    /**
     * La petición es la MISMA que arma `FacturacionModerna::cancelar()` de Yii2,
     * que es la que hoy funciona en producción: método `requestCancelarCFDI`
     * contra el endpoint de timbrado, claves `Motivo`, `FolioSustitucion` (solo
     * con el motivo 01: con otros el PAC la rechaza) y `uuid` en minúsculas, más
     * las credenciales de la cuenta.
     *
     * En producción `emisorRFC` es el RFC con el que se timbró; en pruebas el
     * original manda el de la cuenta demo, y aquí igual.
     */
    public function cancel(string $uuid, string $rfcEmisor, string $motivo, ?string $sustituye = null): void
    {
        $peticion = ['Motivo' => $motivo];

        if ($motivo === '01') {
            $peticion['FolioSustitucion'] = (string) $sustituye;
        }

        $peticion['uuid'] = $uuid;

        $credenciales = $this->credentials();

        if (config('timbrado.produccion')) {
            $credenciales['emisorRFC'] = $rfcEmisor;
        }

        $this->soap('requestCancelarCFDI', $peticion + $credenciales, $this->cancellationEndpoint());
    }

    /**
     * El folio fiscal vive en el complemento de timbre del XML que devuelve el PAC.
     */
    private function uuidFrom(string $xml): string
    {
        $documento = @simplexml_load_string($xml);

        if ($documento === false) {
            throw new CfdiException('El PAC devolvió un XML que no se pudo leer.');
        }

        $documento->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $timbre = $documento->xpath('//tfd:TimbreFiscalDigital');

        if (! isset($timbre[0]['UUID'])) {
            throw new CfdiException('El comprobante llegó sin folio fiscal (UUID).');
        }

        return (string) $timbre[0]['UUID'];
    }

    /**
     * La llamada SOAP en sí. Es lo único que sale a la red, y por eso va aparte
     * y es sobrescribible: las pruebas la sustituyen para ver qué petición se
     * armó sin hablar con el PAC.
     *
     * @param  array<string, mixed>  $peticion  Ya con las credenciales.
     */
    protected function soap(string $metodo, array $peticion, string $endpoint): object
    {
        try {
            $cliente = new SoapClient($endpoint, [
                'exceptions' => true,
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'keep_alive' => false,
                'stream_context' => stream_context_create([
                    /*
                     * El sistema original desactivaba la verificación del
                     * certificado. Aquí se verifica por omisión —son documentos
                     * fiscales— y solo se puede desactivar a propósito, si la
                     * cadena de certificados del PAC diera problemas.
                     */
                    'ssl' => [
                        'verify_peer' => (bool) config('timbrado.verificar_tls', true),
                        'verify_peer_name' => (bool) config('timbrado.verificar_tls', true),
                    ],
                    'http' => ['user_agent' => 'CargoSuite/1.0', 'timeout' => self::TIEMPO_LIMITE],
                ]),
            ]);

            return (object) $cliente->{$metodo}((object) $peticion);
        } catch (SoapFault $e) {
            // El mensaje del PAC trae la clave del rechazo (CFDI40211, etc.) y es
            // lo que necesita ver quien factura; las credenciales no se registran.
            Log::warning('El PAC rechazó la operación', ['metodo' => $metodo, 'error' => $e->getMessage()]);

            throw new CfdiException('El PAC respondió: '.$e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            Log::error('No se pudo hablar con el PAC', ['metodo' => $metodo, 'error' => $e->getMessage()]);

            throw new CfdiException('No se pudo conectar con el PAC: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * Credenciales de la cuenta del PAC, con los nombres de clave que espera su
     * servicio web.
     *
     * @return array{emisorRFC: string, UserID: string, UserPass: string}
     */
    private function credentials(): array
    {
        if (! config('timbrado.produccion')) {
            $demo = config('timbrado.demo');

            return ['emisorRFC' => $demo['rfc_cuenta'], 'UserID' => $demo['usuario'], 'UserPass' => $demo['password']];
        }

        foreach (['rfc_cuenta', 'usuario', 'password'] as $clave) {
            if (blank(config("timbrado.{$clave}"))) {
                throw new CfdiException(
                    "Falta la credencial «{$clave}» del PAC: revisa el .env del servidor."
                );
            }
        }

        return [
            'emisorRFC' => config('timbrado.rfc_cuenta'),
            'UserID' => config('timbrado.usuario'),
            'UserPass' => config('timbrado.password'),
        ];
    }

    private function endpoint(): string
    {
        return config('timbrado.produccion')
            ? config('timbrado.endpoints.produccion')
            : config('timbrado.endpoints.pruebas');
    }

    /**
     * Un CFDI se cancela ante el MISMO PAC que lo timbró: por omisión es el
     * endpoint de timbrado, como en el original, que también admitía
     * sobrescribirlo (`urlCancelacion`).
     */
    private function cancellationEndpoint(): string
    {
        return config('timbrado.endpoints.cancelacion') ?: $this->endpoint();
    }
}
