<?php

namespace App\Logging;

use App\Mail\AlertaDeError;
use App\Support\Diagnostico\ContextoPeticion;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Canal de log que manda por correo cada error de la aplicación.
 *
 * Se engancha al log y no al manejador de excepciones para enterarse también de
 * los `Log::error()` que el código registra sin lanzar nada (un correo al
 * cliente que no salió, por ejemplo).
 *
 * El mismo error repetido no llena el buzón: se manda el primero y los que
 * lleguen durante el silencio solo se cuentan; el siguiente aviso dice cuántos
 * hubo. La cuenta vive en archivos y no en la base ni en la caché, porque el
 * error puede ser justamente que la base no responde, y porque la caché de
 * archivos escrita desde la consola ya tumbó el sitio una vez (permisos).
 */
class AlertasPorCorreo extends AbstractProcessingHandler
{
    /** Un aviso que falla al mandarse registra otro error: sin esto, ciclo. */
    private static bool $enviando = false;

    public function __construct(
        private ?string $para = null,
        private int $silencioMinutos = 30,
        private ?string $carpeta = null,
        int|string|Level $level = Level::Error,
    ) {
        parent::__construct($level);
        $this->carpeta ??= storage_path('logs/alertas');
    }

    protected function write(LogRecord $record): void
    {
        if (self::$enviando || blank($this->para)) {
            return;
        }

        $excepcion = $record->context['exception'] ?? null;
        $titulo = $excepcion instanceof Throwable
            ? class_basename($excepcion).': '.$excepcion->getMessage()
            : $record->message;

        $firma = sha1($excepcion instanceof Throwable
            ? get_class($excepcion).$excepcion->getFile().$excepcion->getLine()
            : $record->message);

        $repeticiones = $this->repeticionesSiToca($firma);

        if ($repeticiones === null) {
            return;
        }

        $detalle = [
            'nivel' => $record->level->getName(),
            'fecha' => $record->datetime->format('Y-m-d H:i:s T'),
            ...ContextoPeticion::describir(),
            ...array_map(
                fn ($v) => is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                array_diff_key($record->context, ['exception' => true]),
            ),
        ];

        if ($excepcion instanceof Throwable) {
            $detalle['archivo'] = $excepcion->getFile().':'.$excepcion->getLine();
            $detalle['traza'] = implode("\n", array_slice(explode("\n", $excepcion->getTraceAsString()), 0, 12));
        }

        $enviar = function () use ($titulo, $detalle, $repeticiones) {
            self::$enviando = true;

            try {
                Mail::to($this->para)->send(new AlertaDeError($titulo, $detalle, $repeticiones));
            } catch (Throwable $e) {
                error_log('No se pudo mandar la alerta de error: '.$e->getMessage());
            } finally {
                self::$enviando = false;
            }
        };

        // En la web se manda después de responder: quien vio el error no espera
        // además al servidor de correo.
        app()->runningInConsole() ? $enviar() : defer($enviar);
    }

    /**
     * Cuántas veces se repitió desde el último aviso, o `null` si sigue en
     * silencio (y entonces solo se cuenta).
     */
    private function repeticionesSiToca(string $firma): ?int
    {
        try {
            if (! is_dir($this->carpeta)) {
                @mkdir($this->carpeta, 0777, true);
                @chmod($this->carpeta, 0777);
            }

            // «fecha del último aviso, repeticiones desde entonces». La fecha va
            // dentro y no en el `mtime`: el silencio corre desde el aviso, no
            // desde la última repetición.
            $archivo = $this->carpeta.'/'.$firma;
            [$avisado, $cuenta] = array_map('intval', explode(' ', (string) @file_get_contents($archivo)) + [0, 0]);

            if ($avisado > time() - $this->silencioMinutos * 60) {
                file_put_contents($archivo, $avisado.' '.($cuenta + 1));

                return null;
            }

            file_put_contents($archivo, time().' 0');
            // Web (www-data) y consola (ubuntu) escriben aquí: los dos tienen que poder.
            @chmod($archivo, 0666);

            return $cuenta;
        } catch (Throwable) {
            return 0;
        }
    }
}
