# Correos y documentos impresos

Todo lo que el sistema manda por correo o imprime en PDF. En Yii2 estaba
repartido entre cuatro controladores, dos modelos y cinco plantillas, con las
direcciones y el remitente escritos a mano en cada sitio.

El motor de PDF es **el mismo que el original** (mPDF, carta, márgenes de 7 mm):
los documentos están armados con `float` y anchos en píxeles, y otro renderizador
los acomodaría distinto. Ver `App\Support\Pdf\PdfWriter`.

## Los documentos

| Documento | Dónde | Origen en Yii2 |
| --- | --- | --- |
| Confirmación de booking | `/operacion/bookings/{id}/confirmacion.pdf` y adjunta al correo del cliente | `Booking::generateBokingConfirmation()` |
| Solicitud de pago (el cheque) | `/pagos/solicitudes/{id}/documento.pdf`, botón «Imprimir» del listado | `PaymentRequest::generateDocument()` |

La confirmación tiene **prueba de paridad contra el documento real**: el fixture
guarda el HTML que produce Yii2 para doce bookings de la base local —diez
normales y dos cotizaciones— y se compara celda por celda.

```bash
php tools/export_legacy_booking_pdf.php          # regenera el fixture
vendor/bin/phpunit --group parity --filter=BookingConfirmationParityTest
```

Rarezas que el documento conserva:

- Los datos de la empresa van fijos en la plantilla, **con el RFC escrito a mano**
  (FTM1507038V6), aunque el sistema ya factura con varias compañías.
- En una cotización, el total de la tabla de facturas **arranca con el número de
  piezas**: el original reutiliza la variable que venía sumando los contenedores.

## Los correos

| Correo | Cuándo sale | A quién |
| --- | --- | --- |
| Confirmación de booking | al **confirmar** el borrador desde el detalle (nunca en cotizaciones), y a mano desde el detalle | correos de notificación del cliente |
| Factura timbrada (PDF + XML) | al timbrar, y a mano con «Reenviar al cliente» | cliente, con copia oculta interna |
| Tarea sin marcar | comando programado, dos niveles | soporte de operación |

Las direcciones ya no están en el código: viven en `config/frego.php` y se
cambian por `.env` (`MAIL_FROM_ADDRESS`, `MARCA_MAIL_FACTURAS_BCC`,
`MARCA_MAIL_AVISOS`).

**La factura solo le llega al cliente si el timbrado está en producción**
(`TIMBRADO_PRODUCCION=true`). Mientras esté en pruebas, los documentos no son
fiscales y el correo va solo a la copia interna (como destinatario, sin
repetirla en copia oculta). El original decidía lo mismo, pero adivinando por
el nombre del servidor.

«Reenviar al cliente» exige solo el PDF, como el original: una factura
histórica con el PDF cargado a mano y sin sello también se reenvía, con aviso
de que va sin sello.

### Avisos de tareas atrasadas

El camino normal es **la bandeja de la aplicación** (`/avisos`, con campana en la
barra): enseña lo mismo sin llenarle el buzón a nadie y solo mira **embarques
vivos** —abiertos y con carga en los últimos seis meses—, que es la diferencia
entre algo útil y un montón de ruido: sobre los datos de hoy hay 1,394 tareas
vencidas, casi todas de bookings de hace años que nadie cerró; acotado a lo vivo
quedan tres.

El correo automático es un extra **apagado por omisión** (`MARCA_AVISOS_CORREO`).
Antes de encenderlo en producción hay que decidir si se acota, porque la primera
corrida manda todo lo vencido acumulado.

En Yii2 eran dos **direcciones web** que un cron llamaba desde fuera
(`booking-continuity/notification` y `.../deadline`): cualquiera que diera con la
dirección disparaba los correos. Aquí es un comando:

```bash
php artisan operacion:avisos-continuidad aviso      # la fecha estimada aún no llega
php artisan operacion:avisos-continuidad vencido    # ya se cumplió o se pasó
php artisan operacion:avisos-continuidad aviso --simular   # enseña sin mandar
```

Está programado una vez al día (07:00 y 07:05, hora de la operación), pero solo
se registra si `MARCA_AVISOS_CORREO=true`. Sin eso, `schedule:list` no enseña
nada y no se manda ningún correo.

Rareza conservada: «Gated Out» aparece dos veces en la lista de hitos del
original, así que ese hito manda dos correos iguales.

Diferencia de forma: el original consultaba la continuidad y la lista de
verificación una vez por booking —unas diez mil consultas por corrida— y aquí las
tres tablas se traen de una vez. Los correos que salen son los mismos.

## Timbrado y cancelación de CFDI

El CFDI se timbra y se cancela ante Facturación Moderna con el cliente SOAP de
`App\Support\Cfdi\FacturacionModernaClient`, traducción del componente
`FacturacionModerna` de Yii2.

**Antes de timbrar se exige la compañía emisora completa.** Si la transacción
no tiene compañía, la compañía ya no existe o le falta RFC, razón social,
régimen fiscal o código postal, el timbrado se detiene con el mismo aviso que
daba el original (`getEmisorError()` / `fiscalWarning`) y el aviso se ve en el
detalle de la factura antes del botón Timbrar. El layout ya no cae al RFC ni al
nombre de la cuenta del PAC: antes de esto se podía timbrar a nombre
equivocado.

**La cancelación viaja igual que en el original**, que es lo que funciona hoy
en producción: método `requestCancelarCFDI` contra el mismo endpoint del
timbrado (se puede sobrescribir con `TIMBRADO_URL_CANCELACION`), con las
claves `Motivo`, `FolioSustitucion` (solo con el motivo 01) y `uuid`, más
`emisorRFC`, `UserID` y `UserPass`. En producción `emisorRFC` es el RFC con el
que se timbró, leído del XML guardado; en pruebas es el de la cuenta demo,
como hacía el original.

**Pendiente: probar la cancelación en el sandbox del PAC** antes de cancelar
una factura real desde esta aplicación. Las pruebas automáticas fijan la
petición que se arma (`FacturacionModernaClientTest`), pero nunca hablan con
el PAC; la única comprobación de punta a punta es la del sistema viejo.

## Dos cosas del original que no se pudieron dejar igual

**El correo de confirmación al cliente está roto en Yii2.** La plantilla
`createdBookingMail.php` pide `carrierModel->name`, y la naviera es un `Provider`,
que no tiene esa columna —se llama `fullName`—. Yii2 responde con
`UnknownPropertyException`; comprobado contra la base real:

```
yii\base\UnknownPropertyException: Getting unknown property: app\models\Provider::name
```

Como `sendCreateEmail()` se llama sin `try` justo después de guardar, **el alta de
un booking con naviera truena la pantalla** aunque el booking sí quede guardado.
Aquí se usa el nombre bueno y el envío va dentro de un `try`: si el correo falla,
queda anotado en la bitácora y la captura sigue.

**`transaction/custom-pdf` es código muerto.** Llama a `generateCustomCFDI()` y
`generateCustomCSS()`, que no existen en ningún modelo, y arma la ruta del archivo
con una variable indefinida. No se portó: invocarla sería un error fatal.

## Lo que no aplica

`ContactForm` (el formulario de contacto del sitio público de Yii2) no tiene
equivalente: la aplicación nueva es el sistema interno y el portal, sin sitio
público.
