<?php

namespace Tests\Feature\Transactions;

use App\Livewire\Transactions\TransactionTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CoreSchema;
use Tests\TestCase;

/**
 * Reglas del listado que se ven mejor con datos diminutos hechos a mano que
 * con la base real: la pantalla de una cotización, la casilla de selección y
 * el tipo de documento. Complementa a `TransactionTableTest`, que va contra
 * la base local por el volumen.
 */
class TransactionTableFixtureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CoreSchema::create();
        $this->seedFixture();
        $this->actingAs($this->admin());
    }

    private function seedFixture(): void
    {
        DB::table('account')->insert([['account_id' => 1, 'account_name' => 'Pesos', 'default' => 1, 'prefix' => 'MXN']]);
        DB::table('exchange')->insert([['exchange_id' => 1, 'exchange_value' => 1, 'date_exchange' => '2026-01-15', 'account' => 1]]);
        DB::table('charge_type')->insert([['charge_type_id' => 1, 'charge_type_name' => 'Flete', 'tax_rate' => 0.16, 'tax_retention' => 0, 'non_deductible' => 0]]);
        DB::table('company')->insert(['company_id' => 1, 'name' => 'FTM', 'rfc' => 'AAA010101AAA']);
        DB::table('client')->insert(['client_id' => 1, 'fullName' => 'Cliente Uno']);
        DB::table('provider')->insert(['provider_id' => 1, 'fullName' => 'Proveedor Uno']);

        DB::table('booking')->insert([
            ['booking_id' => 1, 'booking_number' => 'BK-1', 'client' => 1, 'mode' => 10],
            // Una cotización: el motor la filtra por `booking.mode` = 9.
            ['booking_id' => 9, 'booking_number' => 'COT-9', 'client' => 1, 'mode' => 9],
        ]);

        $base = [
            'account' => 1, 'company_id' => 1, 'tran_date' => '2026-01-15', 'invoice_type' => 1,
            'cancelled' => 0, 'pdf_attach' => '', 'customer' => null, 'vendor' => null,
        ];

        DB::table('transaction')->insert([
            ['transc_id' => 1, 'booking' => 1, 'tran_type' => 0, 'customer' => 1, 'tran_number' => 'F-1'] + $base,
            // Saldada: se cobra completa abajo.
            ['transc_id' => 2, 'booking' => 1, 'tran_type' => 0, 'customer' => 1, 'tran_number' => 'F-2'] + $base,
            ['transc_id' => 3, 'booking' => 1, 'tran_type' => 0, 'customer' => 1, 'tran_number' => 'F-3', 'cancelled' => 1] + $base,
            ['transc_id' => 4, 'booking' => 1, 'tran_type' => 1, 'vendor' => 1, 'tran_number' => 'B-1'] + $base,
            ['transc_id' => 5, 'booking' => 1, 'tran_type' => 2, 'vendor' => 1, 'tran_number' => 'CB-1'] + $base,
            ['transc_id' => 6, 'booking' => 9, 'tran_type' => 0, 'customer' => 1, 'tran_number' => 'F-9'] + $base,
            ['transc_id' => 7, 'booking' => 1, 'tran_type' => 0, 'customer' => 1, 'tran_number' => 'HIST-1', 'invoice_type' => 2] + $base,
            ['transc_id' => 8, 'booking' => 1, 'tran_type' => 0, 'customer' => 1, 'tran_number' => 'NC-1', 'invoice_type' => 3] + $base,
        ]);

        foreach ([1 => 1000, 2 => 500, 3 => 100, 4 => 200, 5 => 50, 6 => 300, 7 => 10, 8 => 20] as $transaccion => $precio) {
            DB::table('charge')->insert([
                'charge_id' => $transaccion, 'transaction' => $transaccion, 'type' => 1, 'quantity' => 1, 'price' => $precio,
            ]);
        }

        // F-2 cobrada completa: 500 + 16 % = 580.
        DB::table('payment_request')->insert([
            'request_id' => 1, 'number' => 'PR-1', 'amount' => 580, 'paid' => 1,
            'client_id' => 1, 'currency_id' => 1, 'type' => 1, 'date' => '2026-01-15',
        ]);
        DB::table('payments_by_transaction')->insert(['request_id' => 1, 'transc_id' => 2, 'amount' => 580, 'paid' => 1]);
    }

    private function admin(): User
    {
        $usuario = new User;
        $usuario->usr_id = 7;
        $usuario->role = User::ROLE_ADMIN;

        return $usuario;
    }

    private function pantalla(string $screen, ?int $booking = null): Testable
    {
        return Livewire::test(TransactionTable::class, ['screen' => $screen, 'booking' => $booking]);
    }

    /** @return int[] */
    private function tipos(Testable $pantalla): array
    {
        return collect($pantalla->viewData('rows')->items())->map(fn ($fila) => (int) $fila->tran_type)->unique()->values()->all();
    }

    // ------------------------------------------------------- Cotizaciones

    /**
     * El motor filtra por `booking.mode`, y la pantalla del booking no le decía
     * que era una cotización (modo 9): salía vacía, y su profit también.
     */
    public function test_las_transacciones_de_una_cotizacion_aparecen_en_su_pantalla(): void
    {
        $pantalla = $this->pantalla('booking', 9);

        $this->assertSame([6], collect($pantalla->viewData('rows')->items())->pluck('transc_id')->map(fn ($id) => (int) $id)->all());
        $this->assertEqualsWithDelta(300, $pantalla->viewData('bookingProfit')['inv_doc'], 0.01, 'El profit de la cotización también salía vacío.');
    }

    public function test_la_descarga_de_una_cotizacion_tampoco_sale_vacia(): void
    {
        $csv = $this->get(route('transactions.export', ['screen' => 'booking', 'booking' => 9]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('F-9', $csv);
    }

    // ------------------------------------------------ Casilla de selección

    public function test_una_transaccion_con_saldo_se_puede_marcar(): void
    {
        $pantalla = $this->pantalla('invoice');
        $fila = collect($pantalla->viewData('rows')->items())->firstWhere('tran_number', 'F-1');

        $this->assertNull($pantalla->instance()->unselectableReason($fila));
    }

    /** El original apagaba la casilla de los documentos con importe y sin saldo. */
    public function test_una_transaccion_saldada_no_se_puede_marcar(): void
    {
        $pantalla = $this->pantalla('invoice');
        $fila = collect($pantalla->viewData('rows')->items())->firstWhere('tran_number', 'F-2');

        $this->assertNotNull($pantalla->instance()->unselectableReason($fila));
        $this->assertMatchesRegularExpression(
            '/value="2"\s+aria-label="Seleccionar transacción F-2"\s+disabled\s+title="[^"]+"/u',
            $pantalla->html(),
            'La casilla de la factura saldada tiene que ir deshabilitada y con su motivo.',
        );
    }

    public function test_una_transaccion_cancelada_no_se_puede_marcar(): void
    {
        $pantalla = $this->pantalla('invoice')->set('showCancelled', '1');
        $fila = collect($pantalla->viewData('rows')->items())->firstWhere('tran_number', 'F-3');

        $this->assertNotNull($pantalla->instance()->unselectableReason($fila));
    }

    // ------------------------------------------------- Tipo de documento

    public function test_el_listado_dice_el_tipo_de_cada_documento(): void
    {
        $this->pantalla('all')
            ->assertSee(__('Nota de crédito prov.'))
            ->assertSee(__('Nota de crédito cliente'))
            ->assertSee(__('Histórica'))
            ->assertSee(__('Costo'));
    }

    public function test_el_filtro_de_tipo_acota_el_listado(): void
    {
        $pantalla = $this->pantalla('all')->set('docType', 'nota-credito');

        $this->assertSame([2], $this->tipos($pantalla));
        $this->assertSame(1, $pantalla->viewData('rows')->total());
    }

    public function test_el_filtro_de_tipo_distingue_las_facturas_por_su_tipo_de_factura(): void
    {
        $pantalla = $this->pantalla('invoice')->set('docType', 'historica');

        $this->assertSame(['HIST-1'], collect($pantalla->viewData('rows')->items())->pluck('tran_number')->all());
    }

    /** El filtro solo estrecha: en Facturas, pedir «costo» no mete costos. */
    public function test_el_filtro_de_tipo_no_abre_la_pantalla_a_otros_documentos(): void
    {
        $pantalla = $this->pantalla('invoice')->set('docType', 'costo');

        $this->assertSame([0], $this->tipos($pantalla));
    }

    public function test_cada_pantalla_ofrece_solo_sus_tipos(): void
    {
        $this->assertSame(['factura', 'historica', 'nota-credito-cliente'], array_keys($this->pantalla('invoice')->instance()->typeOptions()));
        $this->assertSame(['costo', 'nota-credito'], array_keys($this->pantalla('bill')->instance()->typeOptions()));
        $this->assertCount(5, $this->pantalla('all')->instance()->typeOptions());
    }

    public function test_la_descarga_lleva_el_tipo_y_respeta_su_filtro(): void
    {
        $csv = $this->get(route('transactions.export', ['screen' => 'all', 'tipo' => 'nota-credito']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString(__('Tipo'), $csv);
        $this->assertStringContainsString('CB-1', $csv);
        $this->assertStringNotContainsString(',B-1,', $csv);
    }

    public function test_la_pantalla_del_booking_ofrece_la_nota_de_credito_de_proveedor(): void
    {
        $this->pantalla('booking', 1)
            ->assertSee(__('Nueva nota de crédito'))
            ->assertSeeHtml('tipo=nota-credito');
    }
}
