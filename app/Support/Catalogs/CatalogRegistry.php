<?php

namespace App\Support\Catalogs;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Los catálogos maestros del sistema, en un solo lugar.
 *
 * Cada entrada corresponde a una opción del menú «Options» del sistema original.
 * Agregar un catálogo nuevo es agregar una entrada aquí: no hace falta
 * controlador, vista ni ruta.
 *
 * Quedan fuera a propósito los que no son un catálogo plano y necesitan pantalla
 * propia: clientes, proveedores, servicios (3 128 filas ligadas a un cliente o
 * proveedor y a un tipo de cargo), usuarios y el tipo de cambio.
 */
class CatalogRegistry
{
    /** @return array<string, CatalogDefinition> */
    public static function all(): array
    {
        $texto = ['required', 'string', 'max:100'];
        $textoOpcional = ['nullable', 'string', 'max:255'];

        $definiciones = [
            new CatalogDefinition(
                slug: 'puertos-carga',
                table: 'loading_ports',
                key: 'port_id',
                singular: 'Puerto de carga',
                plural: 'Puertos de carga',
                fields: [new CatalogField('port_name', 'Nombre', rules: $texto)],
                softDelete: 'deleted',
            ),
            new CatalogDefinition(
                slug: 'puertos-descarga',
                table: 'dicharge_port',
                key: 'dicharge_port_id',
                singular: 'Puerto de descarga',
                plural: 'Puertos de descarga',
                fields: [new CatalogField('name', 'Nombre', rules: $texto)],
                softDelete: 'deleted',
            ),
            new CatalogDefinition(
                slug: 'destinos-finales',
                table: 'final_destination',
                key: 'final_destination_id',
                singular: 'Destino final',
                plural: 'Destinos finales',
                fields: [new CatalogField('name', 'Nombre', rules: ['required', 'string', 'max:64'])],
                softDelete: 'deleted',
            ),
            new CatalogDefinition(
                slug: 'buques',
                table: 'vessel',
                key: 'vessel_id',
                singular: 'Buque',
                plural: 'Buques',
                fields: [new CatalogField('vessel_name', 'Nombre', rules: $texto)],
            ),
            new CatalogDefinition(
                slug: 'tipos-contenedor',
                table: 'container_types',
                key: 'contType_id',
                singular: 'Tipo de contenedor',
                plural: 'Tipos de contenedor',
                fields: [new CatalogField('container_name', 'Nombre', rules: ['required', 'string', 'max:25'])],
            ),
            new CatalogDefinition(
                slug: 'navieras',
                table: 'carrier',
                key: 'carrier_id',
                singular: 'Naviera',
                plural: 'Navieras',
                fields: [
                    new CatalogField('name', 'Nombre', rules: $texto),
                    new CatalogField('email', 'Correo', rules: ['nullable', 'email', 'max:50']),
                ],
                audited: true,
                searchable: ['name', 'email'],
                // La tabla guarda además contraseña y llaves de acceso del portal;
                // no se exponen aquí porque este catálogo es solo el directorio.
                note: 'Los accesos al portal de la naviera se administran aparte.',
            ),
            new CatalogDefinition(
                slug: 'modalidades',
                table: 'modality',
                key: 'modality_id',
                singular: 'Modalidad',
                plural: 'Modalidades',
                fields: [new CatalogField('modality_name', 'Nombre', rules: ['required', 'string', 'max:15'])],
            ),
            new CatalogDefinition(
                slug: 'terminos-pago',
                table: 'payment_terms',
                key: 'pay_terms_id',
                singular: 'Término de pago',
                plural: 'Términos de pago',
                fields: [new CatalogField('pay_terms', 'Término', rules: ['required', 'string', 'max:50'])],
            ),
            new CatalogDefinition(
                slug: 'lugares-recoleccion',
                table: 'pickup_place',
                key: 'pick_id',
                singular: 'Lugar de recolección',
                plural: 'Lugares de recolección',
                fields: [
                    new CatalogField('name', 'Nombre', rules: $texto),
                    new CatalogField('address1', 'Dirección', rules: $textoOpcional),
                    new CatalogField('address2', 'Dirección 2', rules: $textoOpcional, inList: false),
                    new CatalogField('city', 'Ciudad', rules: ['nullable', 'string', 'max:25']),
                    new CatalogField('state', 'Estado', rules: ['nullable', 'string', 'max:25']),
                    new CatalogField('country', 'País', rules: ['nullable', 'string', 'max:25'], inList: false),
                    new CatalogField('postal_code', 'Código postal', rules: ['nullable', 'string', 'max:25'], inList: false),
                ],
                audited: true,
                searchable: ['name', 'city', 'state'],
            ),
            new CatalogDefinition(
                slug: 'dias-festivos',
                table: 'holiday',
                key: 'holiday_id',
                singular: 'Día festivo',
                plural: 'Días festivos',
                fields: [
                    new CatalogField('name', 'Nombre', rules: $texto),
                    new CatalogField('start_date', 'Desde', type: 'date', rules: ['required', 'date']),
                    new CatalogField('end_date', 'Hasta', type: 'date', rules: ['nullable', 'date', 'after_or_equal:form.start_date']),
                ],
                orderBy: 'start_date',
                audited: true,
            ),
            new CatalogDefinition(
                slug: 'monedas',
                table: 'account',
                key: 'account_id',
                singular: 'Moneda',
                plural: 'Monedas',
                fields: [
                    new CatalogField('account_name', 'Nombre', rules: ['required', 'string', 'max:50']),
                    new CatalogField('prefix', 'Prefijo', rules: ['nullable', 'string', 'max:6']),
                    new CatalogField('default', 'Moneda base', type: 'boolean', rules: ['boolean']),
                ],
                orderBy: 'account_id',
                searchable: ['account_name', 'prefix'],
                note: 'La moneda base es la que no se convierte: su tipo de cambio vale 1.',
            ),
            new CatalogDefinition(
                slug: 'companias',
                table: 'company',
                key: 'company_id',
                singular: 'Compañía',
                plural: 'Compañías emisoras',
                fields: [
                    new CatalogField('name', 'Nombre corto', rules: ['required', 'string', 'max:150']),
                    new CatalogField('business_name', 'Razón social', rules: $textoOpcional),
                    new CatalogField('rfc', 'RFC', rules: ['nullable', 'string', 'max:15']),
                    new CatalogField('regimen_fiscal', 'Régimen fiscal', rules: ['nullable', 'string', 'max:5'], inList: false),
                    new CatalogField('postal_code', 'Código postal', rules: ['nullable', 'string', 'max:10'], inList: false),
                    new CatalogField('address', 'Dirección', rules: $textoOpcional, inList: false),
                    new CatalogField('active', 'Activa', type: 'boolean', rules: ['boolean']),
                ],
                orderBy: 'name',
                searchable: ['name', 'business_name', 'rfc'],
                note: 'El RFC, el régimen y el código postal son los que salen en el CFDI.',
            ),
            new CatalogDefinition(
                slug: 'tipos-cargo',
                table: 'charge_type',
                key: 'charge_type_id',
                singular: 'Tipo de cargo',
                plural: 'Tipos de cargo e impuestos',
                fields: [
                    new CatalogField('charge_type_name', 'Nombre', rules: ['required', 'string', 'max:25']),
                    new CatalogField('tax_name', 'Nombre del impuesto', rules: ['nullable', 'string', 'max:25']),
                    new CatalogField('tax_rate', 'Tasa de IVA', type: 'number', rules: ['required', 'numeric', 'between:0,1']),
                    new CatalogField('tax_retention', 'Retención', type: 'number', rules: ['required', 'numeric', 'between:0,1']),
                    new CatalogField('product_code', 'Clave de producto (SAT)', rules: ['nullable', 'string', 'max:64'], inList: false),
                    new CatalogField('non_deductible', 'No deducible', type: 'boolean', rules: ['boolean']),
                ],
                softDelete: 'deleted',
                orderBy: 'charge_type_name',
                searchable: ['charge_type_name', 'tax_name'],
                note: 'La tasa va en proporción: 0.16 es 16 %. De aquí sale en qué cubeta cae cada concepto.',
            ),
            new CatalogDefinition(
                slug: 'bancos',
                table: 'bank',
                key: 'bank_id',
                singular: 'Banco',
                plural: 'Bancos',
                fields: [
                    new CatalogField('bank_name', 'Nombre', rules: $texto),
                    new CatalogField('account_number', 'Número de cuenta', rules: ['nullable', 'string', 'max:64']),
                    new CatalogField('active', 'Activo', type: 'boolean', rules: ['boolean']),
                    new CatalogField('default', 'Predeterminado', type: 'boolean', rules: ['boolean']),
                ],
                orderBy: 'bank_name',
                audited: true,
                searchable: ['bank_name', 'account_number'],
            ),
            new CatalogDefinition(
                slug: 'campos-archivo',
                table: 'file_fields',
                key: 'field_id',
                singular: 'Campo de archivo',
                plural: 'Campos de archivo',
                fields: [
                    new CatalogField('field', 'Campo', rules: ['required', 'string', 'max:25']),
                    new CatalogField('label', 'Etiqueta', rules: ['nullable', 'string', 'max:25']),
                    new CatalogField('default', 'Por omisión', type: 'boolean', rules: ['boolean']),
                ],
                orderBy: 'label',
                searchable: ['field', 'label'],
            ),
            new CatalogDefinition(
                slug: 'codigos-impuesto',
                table: 'tax_code',
                key: 'tax_code_id',
                singular: 'Código de impuesto',
                plural: 'Códigos de impuesto',
                fields: [
                    new CatalogField('tax_code', 'Código', rules: ['required', 'string', 'max:255']),
                    new CatalogField('tax_rate', 'Tasa', type: 'number', rules: ['nullable', 'numeric']),
                    new CatalogField('tax_retention', 'Retención', rules: $textoOpcional),
                ],
                orderBy: 'tax_code',
            ),
        ];

        return collect($definiciones)->keyBy(fn (CatalogDefinition $d) => $d->slug)->all();
    }

    public static function find(string $slug): CatalogDefinition
    {
        return self::all()[$slug] ?? throw new NotFoundHttpException("No existe el catálogo «{$slug}».");
    }
}
