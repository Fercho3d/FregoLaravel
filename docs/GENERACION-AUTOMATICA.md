# Generación automática de la factura y los costos de un booking

Porta `Booking::generateInvoice()`, `generateBills()`, `getInvoiceServices()`,
`getBrockerServices()` y `getServicesProvider()` del sistema Yii2 (unas 250 líneas
repartidas en `models/Booking.php`).

En el original era un botón —**Gen Bill/Invoice**— que disparaba cuatro
generaciones seguidas y volvía a la pantalla del booking con un «yes» o un «no»
por cada una. Lo que se había escrito solo se veía yendo a la lista de
transacciones, y si algo fallaba a la mitad quedaba escrito a medias.

Aquí son dos pasos: **proponer** y **escribir**.

| Pieza | Qué hace |
| --- | --- |
| `App\Support\Billing\ServiceMatcher` | Busca los servicios contratados que empatan con el booking y calcula la cantidad de cada uno. No escribe nada. |
| `App\Support\Billing\ServiceCandidate` | Un servicio que empató, con su cantidad, su divisa y su vigencia. |
| `App\Actions\Bookings\PlanBookingBilling` | Reparte los renglones elegidos en documentos (`BillingPlan` / `PlannedDocument`). |
| `App\Actions\Bookings\GenerateBookingBilling` | Escribe el plan: crea las transacciones con `SaveTransaction` y les cuelga los conceptos. |
| `App\Livewire\Operations\BillingGenerator` | La pantalla `/operacion/bookings/{id}/generar`. |

## Las reglas

Un servicio entra si está **activo**, marcado como **auto-incluible** y su ruta es
la del booking. Los campos vacíos del servicio empatan con bookings que tampoco
los tengan (`IS NULL`, no `= NULL` — ver más abajo).

| Bloque | Qué se compara | Cantidad del concepto |
| --- | --- | --- |
| **Factura al cliente** | puerto de carga, puerto de descarga, destino final, cliente y —solo si el cliente tiene `match_pickup_place`— lugar de recolección. Además el servicio tiene que ser de un tipo de contenedor que el booking lleve. | por contenedor → los contenedores de ese tipo; por BL → 1; aduana por contenedor → toda la carga; aduana por BL → 1; sin tipo de precio → 0 |
| **+ servicios de aduana** | si el booking lleva agente aduanal, se agregan los servicios del cliente con precio «de aduana» (3 y 4), sin mirar la ruta | igual que arriba |
| **Costo de la naviera** | igual que la factura, pero por proveedor. Con transportista se piden los servicios **sin** lugar de recolección (el acarreo lo cobra el otro); sin transportista, los del lugar de recolección del booking | por BL → 1; lo demás → los contenedores que empataron |
| **Costo del transportista** | puerto de carga y lugar de recolección | 1 por documento, y **un documento por contenedor** |
| **Costo del agente aduanal** | solo el proveedor: sus honorarios no dependen de la ruta | por contenedor → toda la carga; por BL → 1; sin tipo de precio → 0 |

**Un documento por divisa.** Los renglones vienen ordenados por divisa y se abre
una transacción nueva cada vez que cambia: una factura no puede mezclar pesos con
dólares porque se timbra en una sola moneda. Se conserva una consecuencia del
original: los servicios de aduana se pegan al final con su propio orden, así que
si la divisa vuelve a la de un documento anterior se abre otro en vez de sumarse.

Las facturas al cliente toman folio consecutivo (`F-n`) al crearse, salvo en
cotizaciones (`mode = 9`). Eso no es cosa de aquí: lo hace `SaveTransaction`, la
misma puerta que usa el alta manual.

## Lo que cambia respecto al original, y por qué

**1. El costo del transportista ya no sale de un renglón al azar.**
El original pedía `SUM(containers.quantity)` **sin `GROUP BY`**, así que la base
colapsaba todos los servicios que empataban en una sola fila: se quedaba con los
datos de uno cualquiera y con una suma inflada (servicios × contenedores). Con un
solo servicio por ruta —el caso normal— daba lo correcto; con varios facturaba
varias veces el mismo precio. En la base local hay rutas con **cinco** precios
distintos para el mismo transportista. Ahora se enseñan todos y elige el operador.

**2. Un servicio dado de baja ya no se cuela en la factura.**
`getBrockerServices()` no filtraba por `active`: un precio desactivado seguía
apareciendo en facturas nuevas. Los otros cuatro caminos sí filtraban.

**3. El concepto de la factura guarda de qué servicio salió.**
El original lo hacía en los costos pero no en la factura, por un error de dedo
(`$service->service_id = $service->service_id`, que se asigna a sí mismo). La
columna ya existía y nadie decide nada con que esté vacía.

Además, la descripción se recorta a 100 caracteres (el tamaño de la columna). En
el original una descripción larga hacía fallar la validación del cargo y el
método se detenía con `exit()` **a media generación**.

## La vigencia

El catálogo de precios se pacta por temporadas (`start_date` / `end_date`) y el
original **no las miraba**: una ruta muy usada acumula años de precios y los
proponía todos juntos. En la base local, la ruta Veracruz–Rotterdam de una naviera
empata con **diez** servicios de meses distintos.

La vigencia no descarta nada por su cuenta —eso sería cambiar la regla sin
permiso— pero decide qué viene marcado: de entrada solo se marcan los precios
vigentes a la fecha del documento, y los demás quedan a un clic, contados.

## Qué garantiza la prueba de paridad

`tests/Feature/Billing/ServiceMatchingParityTest` (grupo `parity`) ejecuta sobre
la base real, para los 200 bookings más recientes con carga, **el SQL del
original escrito a mano** y lo compara con lo que propone `ServiceMatcher`:
mismos servicios, mismos tipos de contenedor y mismas cantidades en los tres
bloques donde la promesa es «lo mismo que antes» (factura, naviera, agente). Del
transportista se comprueba la parte donde el original acertaba: cuando empata un
solo servicio.

```bash
vendor/bin/phpunit --group parity --filter=ServiceMatchingParityTest
```

## Pendientes

- **Preguntarle a Héctor por los precios duplicados del transportista**: si los
  cinco de una misma ruta son alternativas históricas, conviene darlos de baja o
  ponerles vigencia; hoy la pantalla obliga a elegir cada vez.
- El **contrato en PDF** del servicio (`service.contract`, 156 archivos) todavía
  no se sube desde Laravel; el resto del catálogo ya se administra completo.
