<?php

namespace App\Livewire\Parties\Concerns;

use App\Models\Core\Account;
use App\Models\Core\Provider;

/**
 * Lo que comparten la lista y la ficha de clientes y proveedores: el modo, su
 * tabla y los campos capturables.
 */
trait PartyFields
{
    /** client | provider */
    public string $mode = 'client';

    public function isClient(): bool
    {
        return $this->mode === 'client';
    }

    public function table(): string
    {
        return $this->isClient() ? 'client' : 'provider';
    }

    public function key(): string
    {
        return $this->isClient() ? 'client_id' : 'provider_id';
    }

    public function title(): string
    {
        return $this->isClient() ? __('Clientes') : __('Proveedores');
    }

    /**
     * Campos capturables: etiqueta, tipo y reglas.
     *
     * @return array<string, array{0: string, 1: string, 2: array<int, mixed>}>
     */
    public function fields(): array
    {
        $texto = ['nullable', 'string', 'max:255'];

        $comunes = [
            'fullName' => [__('Nombre o razón social'), 'text', ['required', 'string', 'max:255']],
            'rfc' => [__('RFC'), 'text', ['nullable', 'string', 'max:20']],
            'email' => [__('Correo'), 'text', ['nullable', 'email', 'max:255']],
            'phone' => [__('Teléfono'), 'text', ['nullable', 'string', 'max:50']],
            'address' => [__('Dirección'), 'text', $texto],
            'city' => [__('Ciudad'), 'text', ['nullable', 'string', 'max:100']],
            'state' => [__('Estado o provincia'), 'text', ['nullable', 'string', 'max:100']],
            'postal_code' => [__('Código postal'), 'text', ['nullable', 'string', 'max:20']],
            'account_id' => [__('Divisa habitual'), 'select', ['nullable', 'integer']],
        ];

        if (! $this->isClient()) {
            return $comunes + [
                'type_id' => [__('Tipo de proveedor'), 'select', ['nullable', 'integer']],
            ];
        }

        // Los campos del CFDI solo salen donde se factura al SAT: en una
        // instalación sin timbrado son cuatro casillas que nadie sabe llenar.
        $fiscales = config('timbrado.habilitado') ? [
            'regimen_fiscal_id' => [__('Régimen fiscal (SAT)'), 'text', ['nullable', 'string', 'max:10']],
            'invoice_use' => [__('Uso del CFDI'), 'text', ['nullable', 'string', 'max:10']],
            'pay_method' => [__('Método de pago'), 'text', ['nullable', 'string', 'max:10']],
            'pay_form' => [__('Forma de pago'), 'text', ['nullable', 'string', 'max:10']],
        ] : [];

        // Datos que solo tienen sentido en un cliente.
        return $comunes + $fiscales + [
            'email_notification' => [__('Correos para facturas'), 'text', $texto],
        ];
    }

    /** @return array<int, string> */
    public function optionsFor(string $campo): array
    {
        return match ($campo) {
            'account_id' => Account::options(),
            'type_id' => [
                Provider::TYPE_CARRIER => __('Naviera'),
                Provider::TYPE_TRANSPORT => __('Transportista'),
                Provider::TYPE_BROKER => 'Agente aduanal',
            ],
            default => [],
        };
    }

    /** La lista de este modo: a dónde regresa la ficha si no trae otra dirección. */
    public function listRoute(): string
    {
        return $this->isClient() ? 'parties.clients' : 'parties.providers';
    }
}
