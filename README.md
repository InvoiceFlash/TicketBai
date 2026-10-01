# TicketBAI (módulo vqmod de InvoiceFlash)

Emite las facturas de venta con TicketBAI para las tres Haciendas Forales del País Vasco: Araba,
Bizkaia (Batuz/LROE) y Gipuzkoa.

Es **vqmod puro**: no usa Composer ni librerías externas. La firma XAdES-EPES, el
encadenamiento, el identificador TBAI con su CRC-8, el QR y el envío están en
`system/library/ticketbai.php`, que solo usa `DOMDocument`, `openssl` y `curl`.

## Requisitos

- PHP con `openssl`, `curl`, `dom`, `simplexml` y `zlib`. El código es compatible con PHP 5 y está
  probado en PHP 5.6 y 8.3.
- `curl` debe tener una lista de CA configurada (`curl.cainfo` en `php.ini`). Si no, se usa la misma
  CA opcional que VeriFactu (`config_aeat_ca_bundle`, en **Sistema > Ajustes**).
- El certificado digital `.p12`/`.pfx` (o `.pem` con clave) y su contraseña. 
- El alta del software en el registro TicketBAI de la Hacienda Foral correspondiente. Ella da la
  **licencia TBAI** y el **NIF de la entidad desarrolladora** que se ponen en los Ajustes del
  módulo.

## Contenido

```
TicketBai/
├── admin/controller/ticketbai/ticketbai.php        Ventas > TicketBAI: listado, Ajustes, reenviar, descargar XML
├── admin/model/ticketbai/ticketbai.php             datos de la factura, encadenamiento, estados
├── admin/language/{es_ES,en-gb}/ticketbai/ticketbai.php
├── admin/view/template/ticketbai/{ticketbai_list,ticketbai_setting}.tpl
├── system/library/ticketbai.php                    XML, firma, identificador, QR, envío (clase Ticketbai)
└── vqmod/xml/ticketbai.xml                         id ticketbai_modulo
```

`system/library/ticketbai.php` sigue estas fuentes:

- las especificaciones TicketBAI 1.2 y el esquema `ticketbaiv1-2-2.xsd`;
- el ejemplo oficial de factura firmada (`SigningCertificate` + `IssuerSerial`, `SPURI`);
- [barnetik/ticketbai](https://github.com/Barnetik/tbai-php-lib) (GPL-3.0-or-later, commit
  `d91ad03`), de donde salen los identificadores y digests de las políticas de firma de cada
  territorio (el de Gipuzkoa es la versión corregida en agosto de 2026), los endpoints y el formato
  LROE de Bizkaia.

Si una Hacienda cambia su política de firma o su endpoint, los valores están al principio de la
clase (`$policies`, `$endpoints`, `$qrUrls`).

## Qué hace el XML (`ticketbai.xml`)

| Fichero del núcleo | Cambio |
|---|---|
| `admin/controller/common/header.php` | Antes de `if ($sales) {`: crea la tabla `tbai_invoice` (idempotente), da permisos `ticketbai/ticketbai` al grupo 1 y añade **Ventas > TicketBAI** |
| `admin/language/{es_ES,en-gb}/common/header.php` | `text_ticketbai` |
| `admin/controller/sale/invoice.php` | Método nuevo `ticketbaiSend()`. Al principio de `autoSendAeat()`, si `ticketbai_active` está activo, envía a TicketBAI y sale. Eso cubre el alta de factura, los drafts y albaranes convertidos (`sale/draft`, `sale/delivery`) y el botón de reenvío de la pestaña AEAT |
| ídem, `delete()` ("Anular") | La factura negativa que crea `createNegativeInvoice()` se emite como **rectificativa por diferencias (R1, tipo I)** de la original |
| ídem, listado | El icono verde (`aeat_ok`) acepta también `aeat_status = 'TicketBAI Recibido'` |
| ídem, ficha | La pestaña "AEAT" pasa a llamarse "TicketBAI" y la fila CSV muestra el identificador TBAI |
| ídem, `invoice()` | Con TicketBAI activo pinta el QR TBAI (URL de la Hacienda + CRC) con el TCPDF del núcleo, en vez del de VeriFactu |


## Datos

Tabla `tbai_invoice`, con una fila por factura y `UNIQUE(invoice_id)`. Guarda:

- los datos con que se generó el XML (`document_json`) y el **XML firmado** (`signed_xml`);
- el identificador TBAI, la URL del QR y los 100 primeros caracteres de la firma (`chain_signature`,
  para encadenar la siguiente);
- el estado (`signed`, `sent`, `rejected` o `error`), el mensaje de Hacienda, la respuesta en bruto
  y el número de intentos.

Además refleja el resultado en `invoice.aeat_status`, `aeat_notice`, `aeat_csv` y las fechas, con
los valores fijos `TicketBAI Recibido`, `TicketBAI Rechazado` y `TicketBAI Error`. Nunca toca
`aeat_hash`, que es la cadena de VeriFactu.

Los ajustes van al grupo `ticketbai` de `setting`: `ticketbai_active`, `_territory` (01/02/03),
`_environment` (test/production), `_license`, `_developer_nif`, `_self_employed`, `_epigraph`,
`_exempt_reason` y `_foreign_operation`.

## Reglas que sigue

- **Una factura se firma una sola vez.** Si el envío falla, el reenvío manda el mismo XML firmado
  guardado. La firma de la siguiente factura depende de la anterior, así que volver a firmar
  rompería la cadena.
- **Encadenamiento** por tienda, NIF emisor y entorno (pruebas y producción llevan cadenas
  separadas). La firma se serializa con `GET_LOCK` para que dos altas simultáneas no se encadenen a
  la misma factura anterior.
- **Serie y número**: serie = `invoice_prefix`, número = `invoice_no` (o `invoice_id` si no hay),
  igual que VeriFactu. TicketBAI admite como máximo 20 caracteres en cada uno.
- **Cliente sin NIF**: se emite como factura simplificada, solo hasta 400 €.
- **Cliente extranjero**: se identifica con `IDOtro` (tipo 02 NIF-IVA para la UE, con el prefijo VIES;
  04 para el resto). Además lleva el desglose por tipo de operación, entrega de bienes o prestación
  de servicios según Ajustes, porque TicketBAI lo exige.
- **IVA**: el desglose sale de las filas `tax` de `invoice_total` (base = cuota ÷ tipo), igual que
  VeriFactu, como operación sujeta y no exenta S1. Una factura sin IVA va como sujeta y exenta con
  la causa E1–E6 elegida en Ajustes.
- **Líneas**: importe con IVA = `total` + `tax` × |cantidad| (`tax` es el IVA por unidad, según el
  convenio de OpenCart). El descuento en % se convierte a importe.
- **Bizkaia**: la factura va dentro de una petición LROE en gzip, modelo 240, o modelo 140 si es
  persona física, con el epígrafe IAE de Ajustes.
- Los textos del modelo se leen aparte (`text()`), no con `$this->language->load()`. Si no, pisarían
  claves como `heading_title` de la pantalla que llama (factura, albarán, draft).

## Instalación

1. Copiar `admin/` y `system/` sobre la instalación y `vqmod/xml/ticketbai.xml` a `vqmod/xml/`.
2. Borrar `vqmod/vqcache/*` y `vqmod/mods.cache` y recargar el admin (crea la tabla y los permisos).
3. En **Ventas > TicketBAI > Ajustes**, comprobar que dice "Servidor preparado para firmar y
   enviar". Rellenar territorio, licencia y NIF desarrollador, dejar el entorno en **Pruebas** y
   activar.
4. Emitir facturas de prueba y revisar su estado en **Ventas > TicketBAI**. Pasar a Producción solo
   cuando la Hacienda Foral dé el visto bueno.

Para otros grupos de usuarios, conceder `ticketbai/ticketbai` en **Usuarios > Grupos**.

## Pruebas hechas

Con un arnés PHP contra la BD local, un certificado autofirmado y una respuesta de Hacienda
simulada (sin red). Se hicieron en PHP 8.3 y en PHP 5.6, para los tres territorios:

- **Validación contra el XSD oficial TicketBAI 1.2.2**:
  - factura exenta y factura con IVA 21 %;
  - rectificativa;
  - simplificada sin destinatario;
  - cliente UE (`IDOtro` + desglose por operación);
  - exenta junto a dos tipos de IVA.
- **Firma**: un verificador propio recalcula los digests y comprueba la firma RSA. También la
  verifica una librería de terceros (`lyquidity/xml-signer`, la que usa barnetik). Además, barnetik
  lee el XML y calcula **el mismo identificador TBAI**.
- **CRC-8** del identificador y del QR: da los mismos valores que barnetik.
- **Peticiones LROE de Bizkaia** (240 y 140): validadas contra sus esquemas oficiales.
- **Flujo completo en la BD**:
  - envío sin conexión: queda en estado `error`;
  - reintento aceptado con el mismo XML;
  - una factura aceptada no se reenvía;
  - la rectificativa se encadena y, si Hacienda la rechaza, el reenvío manual se acepta;
  - el estado se refleja en `invoice.aeat_*`;
  - pantallas de listado y Ajustes, y guardado de Ajustes.

## Pendiente / limitaciones conocidas

- **Anulación** (`AnulaTicketBai`) y **Zuzendu** (subsanar una factura rechazada en Araba y
  Gipuzkoa): no están implementados. Una factura **rechazada** conserva su XML firmado y el botón
  Reenviar manda el mismo documento: sirve para errores de conexión, no para corregir datos.
- **Serie de las rectificativas**: la factura negativa usa el mismo prefijo que la original. El
  reglamento de facturación pide una serie específica para las rectificativas; hay que decidir cómo
  numerarlas en el núcleo antes de producción.
- La plantilla configurable del diseñador de informes (`{qr_code}` de `tools/report_designer`) pinta
  el QR TBAI, pero no el texto del identificador.
- Los documentos que no pasan por `autoSendAeat()` no se envían.
