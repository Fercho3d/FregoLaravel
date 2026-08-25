<?php

namespace Tests\Feature\Billing;

use App\Models\Frego\Booking;
use App\Support\Billing\BillingBlock;
use App\Support\Billing\ServiceMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\FregoDatabaseTestCase;

/**
 * Paridad del emparejamiento contra el sistema original, con datos reales.
 *
 * Para cada booking de la muestra se ejecuta el SQL que arma Yii2 en
 * `Booking::getInvoiceServices()` y `getServicesProvider()` —escrito aquí a mano,
 * no con el mismo constructor de consultas, para que la comparación valga— y se
 * confronta con lo que propone `ServiceMatcher`.
 *
 * Se comparan los tres bloques donde la promesa es «lo mismo que antes». Los dos
 * cambios deliberados quedan fuera y tienen su propia prueba:
 *
 * · el costo del transportista, donde el original colapsaba todos los servicios
 *   que empataban en un renglón al azar (aquí se comprueba la parte donde sí
 *   coinciden: cuando empata uno solo);
 * · los servicios de aduana del cliente, donde el original no filtraba por
 *   `active` (se compara contra el resultado del original ya sin las bajas).
 *
 * Corre contra la base local `frego` con datos de producción, así que es lenta:
 *   vendor/bin/phpunit --group parity
 */
#[Group('parity')]
class ServiceMatchingParityTest extends FregoDatabaseTestCase
{
    /** Bookings recientes a comparar. */
    private const MUESTRA = 200;

    private ServiceMatcher $emparejador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emparejador = app(ServiceMatcher::class);
    }

    public function test_la_factura_al_cliente_empata_los_mismos_servicios(): void
    {
        $conPropuesta = 0;

        foreach ($this->bookings() as $booking) {
            $original = array_merge(
                $this->legacyClientRoute($booking),
                $this->legacyBrokerExtras($booking),
            );

            $this->assertSameServices($original, BillingBlock::Invoice, $booking);
            $conPropuesta += $original === [] ? 0 : 1;
        }

        // Sin esto la prueba pasaría comparando vacíos contra vacíos.
        $this->assertGreaterThan(10, $conPropuesta, 'La muestra no trajo facturas que comparar.');
    }

    public function test_el_costo_de_la_naviera_empata_los_mismos_servicios(): void
    {
        $conPropuesta = 0;

        foreach ($this->bookings() as $booking) {
            if ($booking->carrier_id === null) {
                continue;
            }

            $original = $this->legacyCarrier($booking);
            $this->assertSameServices($original, BillingBlock::Carrier, $booking);
            $conPropuesta += $original === [] ? 0 : 1;
        }

        $this->assertGreaterThan(10, $conPropuesta, 'La muestra no trajo costos de naviera que comparar.');
    }

    /**
     * En la base local ningún agente aduanal tiene servicios auto-incluibles, así
     * que aquí se compara sobre todo que los dos lados coincidan en no proponer
     * nada. Vale la pena igual: si mañana se cargan, la prueba ya está puesta.
     */
    public function test_el_costo_del_agente_aduanal_empata_los_mismos_servicios(): void
    {
        foreach ($this->bookings() as $booking) {
            if ($booking->custom_brocker_id === null) {
                continue;
            }

            $this->assertSameServices($this->legacyBroker($booking), BillingBlock::Broker, $booking);
        }
    }

    /**
     * El transportista es el cambio deliberado. Donde el original acertaba —un
     * solo servicio empatando— la propuesta tiene que ser idéntica: el mismo
     * servicio y un costo por contenedor.
     */
    public function test_el_transportista_coincide_cuando_el_original_no_se_confundia(): void
    {
        $comparados = 0;

        foreach ($this->bookings() as $booking) {
            if ($booking->transport_id === null) {
                continue;
            }

            $original = $this->legacyTransport($booking);

            if (count($original) !== 1) {
                continue;
            }

            $propuesta = $this->emparejador->forBlock($booking, BillingBlock::Transport);

            $this->assertCount(1, $propuesta, 'Booking '.$booking->booking_id);
            $this->assertSame((int) $original[0]->service_id, $propuesta[0]->serviceId);
            $this->assertSame($this->totalContainers($booking), $propuesta[0]->documents);
            $comparados++;
        }

        $this->assertGreaterThan(10, $comparados, 'La muestra no trajo acarreos que comparar.');
    }

    // ------------------------------------------------------------ Comparación

    /**
     * Mismos servicios, mismos tipos de contenedor y mismas cantidades.
     *
     * @param  list<object>  $original
     */
    private function assertSameServices(array $original, BillingBlock $bloque, Booking $booking): void
    {
        $contenedores = (float) $this->totalContainers($booking);

        $esperado = [];

        foreach ($original as $fila) {
            $esperado[$fila->service_id.':'.($fila->container_type_id ?? 0)] =
                $this->legacyQuantity($bloque, $fila, $contenedores);
        }

        $obtenido = [];

        foreach ($this->emparejador->forBlock($booking, $bloque) as $candidato) {
            $obtenido[$candidato->serviceId.':'.($candidato->containerTypeId ?? 0)] = $candidato->quantity;
        }

        ksort($esperado);
        ksort($obtenido);

        $this->assertEquals(
            $esperado,
            $obtenido,
            sprintf('Booking %d, bloque %s', $booking->booking_id, $bloque->value),
        );
    }

    /** La fórmula de cantidad del original, tal cual, por bloque. */
    private function legacyQuantity(BillingBlock $bloque, object $fila, float $contenedores): float
    {
        $tipo = (int) $fila->price_type;
        $empatados = (float) ($fila->quantity ?? 0);

        return match ($bloque) {
            BillingBlock::Invoice => match ($tipo) {
                1 => $empatados,
                3 => $contenedores,
                2, 4 => 1.0,
                default => 0.0,
            },
            BillingBlock::Carrier => $tipo === 2 ? 1.0 : $empatados,
            BillingBlock::Broker => match ($tipo) {
                1 => $contenedores,
                2 => 1.0,
                default => 0.0,
            },
            BillingBlock::Transport => 1.0,
        };
    }

    // --------------------------------------------------- El SQL del original

    /** @return list<object> */
    private function legacyClientRoute(Booking $booking): array
    {
        $ataduras = [$booking->booking_id];
        $donde = [
            $this->condition('service.loading_port_id', $booking->loading_port, $ataduras),
            $this->condition('service.dicharge_port_id', $booking->dicharge_port_id, $ataduras),
            $this->condition('service.final_destination_id', $booking->final_destination_id, $ataduras),
            $this->condition('service.client_id', $booking->client, $ataduras),
            'service.auto_include = 1',
            'active = 1',
        ];

        if ((int) (DB::table('client')->where('client_id', $booking->client)->value('match_pickup_place') ?? 0) === 1) {
            $donde[] = $this->condition('service.pickup_place_id', $booking->pick_up_place_id, $ataduras);
        }

        return $this->routeSelect($donde, $ataduras);
    }

    /**
     * `getBrockerServices()`, ya sin las bajas: el original no las filtraba y ese
     * es uno de los dos cambios deliberados.
     *
     * @return list<object>
     */
    private function legacyBrokerExtras(Booking $booking): array
    {
        if ($booking->custom_brocker_id === null) {
            return [];
        }

        return DB::select(
            'SELECT service_id, price_type, container_type_id, null AS quantity
             FROM service
             WHERE client_id = ? AND auto_include = 1 AND (price_type = 3 OR price_type = 4)
               AND active = 1
             ORDER BY account_id',
            [$booking->client],
        );
    }

    /** @return list<object> */
    private function legacyCarrier(Booking $booking): array
    {
        $ataduras = [$booking->booking_id];
        $donde = [
            $this->condition('service.loading_port_id', $booking->loading_port, $ataduras),
            $this->condition('service.dicharge_port_id', $booking->dicharge_port_id, $ataduras),
            $this->condition('service.final_destination_id', $booking->final_destination_id, $ataduras),
            'service.auto_include = 1',
            $this->condition('service.provider_id', $booking->carrier_id, $ataduras),
            'service.active = 1',
            $booking->transport_id === null
                ? $this->condition('pickup_place_id', $booking->pick_up_place_id, $ataduras)
                : 'pickup_place_id IS NULL',
        ];

        return $this->routeSelect($donde, $ataduras);
    }

    /** @return list<object> */
    private function legacyBroker(Booking $booking): array
    {
        return DB::select(
            'SELECT service_id, price_type, container_type_id, null AS quantity
             FROM service
             WHERE auto_include = 1 AND provider_id = ? AND active = 1
             ORDER BY account_id',
            [$booking->custom_brocker_id],
        );
    }

    /**
     * Los servicios del transportista **sin** el `SUM` que el original dejaba sin
     * agrupar: aquí interesa cuántos empataban de verdad.
     *
     * @return list<object>
     */
    private function legacyTransport(Booking $booking): array
    {
        $ataduras = [];
        $donde = [
            $this->condition('loading_port_id', $booking->loading_port, $ataduras),
            $this->condition('pickup_place_id', $booking->pick_up_place_id, $ataduras),
            'auto_include = 1',
            $this->condition('provider_id', $booking->transport_id, $ataduras),
            'active = 1',
        ];

        return DB::select(
            'SELECT service_id, price_type, container_type_id, null AS quantity
             FROM service WHERE '.implode(' AND ', $donde).' ORDER BY account_id',
            $ataduras,
        );
    }

    /**
     * La consulta de ruta del original: el servicio tiene que ser de un tipo de
     * contenedor que el booking lleve, y la cantidad es la suma de esos
     * contenedores.
     *
     * @param  list<string>  $donde
     * @param  list<mixed>  $ataduras
     * @return list<object>
     */
    private function routeSelect(array $donde, array $ataduras): array
    {
        return DB::select(
            'SELECT service.service_id, service.price_type, service.container_type_id,
                    SUM(containers.quantity) AS quantity
             FROM service
             INNER JOIN container_types ON container_types.contType_id = service.container_type_id
             INNER JOIN containers ON containers.container_type = container_types.contType_id
                                  AND containers.booking = ?
             WHERE '.implode(' AND ', $donde).'
             GROUP BY container_types.contType_id, service.service_id
             ORDER BY service.account_id',
            $ataduras,
        );
    }

    /**
     * Traduce la costumbre de Yii2: `['columna' => null]` es `IS NULL`.
     *
     * @param  list<mixed>  $ataduras
     */
    private function condition(string $columna, ?int $valor, array &$ataduras): string
    {
        if ($valor === null) {
            return $columna.' IS NULL';
        }

        $ataduras[] = $valor;

        return $columna.' = ?';
    }

    // ------------------------------------------------------------- Utilidades

    /** @return Collection<int, Booking> */
    private function bookings()
    {
        return Booking::query()
            ->where('mode', Booking::MODE_BOOKING)
            ->whereNotNull('client')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('containers')->whereColumn('containers.booking', 'booking.booking_id'))
            ->orderByDesc('booking_id')
            ->limit(self::MUESTRA)
            ->get();
    }

    private function totalContainers(Booking $booking): int
    {
        return (int) DB::table('containers')->where('booking', $booking->booking_id)->sum('quantity');
    }
}
