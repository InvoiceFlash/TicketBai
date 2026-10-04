# TicketBAI (módulo vqmod de InvoiceFlash)

Emite las facturas de venta con TicketBAI para las tres Haciendas Forales del País Vasco: Araba,
Bizkaia (Batuz/LROE) y Gipuzkoa.

Es **vqmod puro**: no usa Composer ni librerías externas. La firma XAdES-EPES, el
encadenamiento, el identificador TBAI con su CRC-8, el QR y el envío están en
`system/library/ticketbai.php`, que solo usa `DOMDocument`, `openssl` y `curl`, igual que
`facturae_signer.php` del núcleo.

Módulo independiente: solo se ancla al núcleo de InvoiceFlash (0.0.17 o posterior) y no depende de
ningún otro módulo. Con TicketBAI activo, las facturas nuevas se firman y se envían a la Hacienda
Foral.

## Requisitos

- InvoiceFlash 0.0.17 o posterior.
- PHP con `openssl`, `curl`, `dom`, `simplexml` y `zlib`. El código es compatible con PHP 5 y está
  probado en PHP 5.6 y 8.3.
- `curl` debe tener una lista de CA configurada (`curl.cainfo` en `php.ini`). Si no, se indica la
  ruta a un almacén CA en **Ventas > TicketBAI > Ajustes**.
- El certificado digital `.p12`/`.pfx` (o `.pem` con clave) y su contraseña. Son los mismos que usa
  Facturae: campos `certificado` y `clave` de **Sistema > Ajustes**.
- El alta del software en el registro TicketBAI de la Hacienda Foral correspondiente. Ella da la
  **licencia TBAI** y el **NIF de la entidad desarrolladora** que se ponen en los Ajustes del
  módulo.

## Contenido

```
TicketBai/
├── admin/controller/ticketbai/ticketbai.php        Ventas > TicketBAI: listado, Ajustes, reenviar, descargar XML
├── admin/model/ticketbai/ticketbai.php             datos de la factura, encadenamiento, estados
├── admin/language/{es_ES,en-gb}/ticketbai/ticketbai.php
├── admin/view/template/ticketbai/{ticketbai_list,ticketbai_setting,info_pane,info_script}.tpl
│                                                   listado, Ajustes y pestaña TicketBAI de la ficha de factura
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
| `admin/controller/sale/invoice.php`, alta | Tras `addInvoice()`, `autoSend()` firma y envía la factura si `ticketbai_active` está activo |
| `sale/draft.php`, `sale/delivery.php` | Lo mismo al convertir un borrador o un albarán en factura |
| `sale/invoice.php`, `delete()` ("Anular") | La factura negativa que crea `createNegativeInvoice()` se emite como **rectificativa por diferencias (R1, tipo I)** de la original |
| ídem, listado y `sale/invoice_list.tpl` | Columna con el icono QR: verde si la factura está aceptada en TicketBAI |
| ídem, ficha y `sale/invoice_info.tpl` | Pestaña **TicketBAI** (fecha de envío, estado, mensaje, identificador TBAI) con el botón **Reenviar a TicketBAI** (`ticketbai/ticketbai/invoiceResend`: si la factura aún no se firmó, la firma y la envía) |
| ídem, factura impresa y `sale/invoice_invoice.tpl`, `sale/invoice_printPDF.tpl`, `sale/reports/invoice_invoice.tpl` | QR TBAI (URL de la Hacienda + CRC, con el TCPDF del núcleo) con el identificador debajo. Pasa `qr_code`, `qr_code_pdf` y `qr_label` |

## Datos

Tabla `tbai_invoice`, con una fila por factura y `UNIQUE(invoice_id)`. Guarda:

- los datos con que se generó el XML (`document_json`) y el **XML firmado** (`signed_xml`);
- el identificador TBAI, la URL del QR y los 100 primeros caracteres de la firma (`chain_signature`,
  para encadenar la siguiente);
- el estado (`signed`, `sent`, `rejected` o `error`), el mensaje de Hacienda, la respuesta en bruto
  y el número de intentos.

No toca ninguna tabla ni columna del núcleo: el listado y la pestaña de la ficha leen el estado de
`tbai_invoice`. Los errores que ocurren antes de firmar (configuración incompleta, datos de la
factura) no se guardan: salen como mensaje al pulsar **Reenviar a TicketBAI**.

Los ajustes van al grupo `ticketbai` de `setting`: `ticketbai_active`, `_territory` (01/02/03),
`_environment` (test/production), `_license`, `_developer_nif`, `_self_employed`, `_epigraph`,
`_exempt_reason`, `_foreign_operation` y `_ca_bundle`.

## Reglas que sigue

- **Una factura se firma una sola vez.** Si el envío falla, el reenvío manda el mismo XML firmado
  guardado. La firma de la siguiente factura depende de la anterior, así que volver a firmar
  rompería la cadena.
- **Encadenamiento** por tienda, NIF emisor y entorno (pruebas y producción llevan cadenas
  separadas). La firma se serializa con `GET_LOCK` para que dos altas simultáneas no se encadenen a
  la misma factura anterior.
- **Serie y número**: serie = `invoice_prefix`, número = `invoice_no` (o `invoice_id` si no hay).
  TicketBAI admite como máximo 20 caracteres en cada uno.
- **Cliente sin NIF**: se emite como factura simplificada, solo hasta 400 €.
- **Cliente extranjero**: se identifica con `IDOtro` (tipo 02 NIF-IVA para la UE, con el prefijo VIES;
  04 para el resto). Además lleva el desglose por tipo de operación, entrega de bienes o prestación
  de servicios según Ajustes, porque TicketBAI lo exige.
- **IVA**: el desglose sale de las filas `tax` de `invoice_total` (base = cuota ÷ tipo), como operación sujeta y no exenta S1. Una factura sin IVA va como sujeta y exenta con
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
  - el estado de la factura en `tbai_invoice`;
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
- Solo se envían las facturas que pasan por los hooks de arriba (alta, borrador, albarán, Anular y el
  botón de la ficha).

## Licencia

GNU GPL v3, la misma que InvoiceFlash (ver `LICENSE`). Parte de `system/library/ticketbai.php` se basa
en [barnetik/ticketbai](https://github.com/Barnetik/tbai-php-lib) (GPL-3.0-or-later), compatible con
esta licencia.
