<?php
// Heading
$_['heading_title']              = 'TicketBAI';
$_['heading_setting']            = 'Ajustes de TicketBAI';

// Text
$_['text_home']                  = 'Inicio';
$_['text_success_setting']       = 'Ajustes de TicketBAI guardados.';
$_['text_success_sent']          = 'Factura enviada a TicketBAI y aceptada por la Hacienda Foral.';
$_['text_already_sent']          = 'La factura ya estaba enviada y aceptada en TicketBAI.';
$_['text_no_results']            = 'Todav&iacute;a no se ha firmado ninguna factura con TicketBAI.';
$_['text_all']                   = 'Todos';
$_['text_yes']                   = 'S&iacute;';
$_['text_no']                    = 'No';
$_['text_araba']                 = 'Araba / &Aacute;lava';
$_['text_bizkaia']               = 'Bizkaia (Batuz)';
$_['text_gipuzkoa']              = 'Gipuzkoa';
$_['text_test']                  = 'Pruebas';
$_['text_production']            = 'Producci&oacute;n';
$_['text_invoice']               = 'Factura';
$_['text_rectifying']            = 'Rectificativa';
$_['text_status_signed']         = 'Firmada, sin enviar';
$_['text_status_sent']           = 'Aceptada';
$_['text_status_rejected']       = 'Rechazada';
$_['text_status_error']          = 'Error de env&iacute;o';
$_['text_default_description']   = 'Venta';
$_['text_confirm_resend']        = '&iquest;Reenviar esta factura a TicketBAI?';
$_['text_active_note']           = 'Con TicketBAI activo, las facturas nuevas se firman y se env&iacute;an a la Hacienda Foral.';
$_['text_certificate_note']      = 'Se usa el mismo certificado (.p12/.pfx) y contrase&ntilde;a que Facturae, en Sistema &gt; Ajustes.';
$_['text_license_note']          = 'La licencia TicketBAI y el NIF de la entidad desarrolladora los da la Hacienda Foral al dar de alta el software en su registro.';
$_['text_requirements_ok']       = 'Servidor preparado para firmar y enviar.';
$_['text_pending_count']         = 'Hay %s factura(s) firmadas que no constan como aceptadas.';

// Column
$_['column_invoice']             = 'Factura';
$_['column_type']                = 'Tipo';
$_['column_customer']            = 'Cliente';
$_['column_date']                = 'Fecha';
$_['column_total']               = 'Total';
$_['column_identifier']          = 'Identificador TBAI';
$_['column_environment']         = 'Entorno';
$_['column_status']              = 'Estado';
$_['column_message']             = 'Mensaje';
$_['column_action']              = 'Acci&oacute;n';

// Entry
$_['entry_active']               = 'Activar TicketBAI';
$_['entry_territory']            = 'Territorio (Hacienda Foral)';
$_['entry_environment']          = 'Entorno';
$_['entry_license']              = 'Licencia TicketBAI';
$_['entry_developer_nif']        = 'NIF entidad desarrolladora';
$_['entry_self_employed']        = 'Persona f&iacute;sica (Bizkaia, modelo 140)';
$_['entry_epigraph']             = 'Ep&iacute;grafe IAE (modelo 140)';
$_['entry_exempt_reason']        = 'Causa de exenci&oacute;n (facturas sin IVA)';
$_['entry_ca_bundle']             = 'Ruta al almac&eacute;n CA (opcional)';
$_['text_ca_bundle_help']         = 'Solo si el servidor no tiene una lista de CA configurada para curl (curl.cainfo en php.ini).';
$_['entry_foreign_operation']    = 'Clientes extranjeros: tipo de operaci&oacute;n';
$_['text_delivery']              = 'Entrega de bienes';
$_['text_services']              = 'Prestaci&oacute;n de servicios';
$_['entry_status']               = 'Estado';

// Button
$_['button_setting']             = 'Ajustes';
$_['button_save']                = 'Guardar';
$_['button_cancel']              = 'Cancelar';
$_['button_resend']              = 'Reenviar';
$_['button_xml']                 = 'XML';
$_['button_filter']              = 'Filtrar';

// Error
$_['error_warning']            = 'Revise los datos marcados del formulario.';
$_['error_permission']           = 'No tiene permiso para modificar TicketBAI.';
$_['error_extension']            = 'TicketBAI necesita la extensi&oacute;n de PHP &quot;%s&quot; (act&iacute;vela en php.ini y reinicie el servidor web).';
$_['error_library']            = 'Falta la librer&iacute;a del m&oacute;dulo: system/library/ticketbai.php.';
$_['error_territory']            = 'Elija el territorio de TicketBAI en Ventas &gt; TicketBAI &gt; Ajustes.';
$_['error_license']              = 'Falta la licencia TicketBAI o el NIF de la entidad desarrolladora (Ventas &gt; TicketBAI &gt; Ajustes).';
$_['error_certificate']          = 'Falta el certificado digital: s&uacute;balo en Sistema &gt; Ajustes.';
$_['error_epigraph']             = 'Para el modelo 140 de Bizkaia hay que indicar el ep&iacute;grafe del IAE.';
$_['error_not_signed']           = 'Esta factura no se ha firmado con TicketBAI.';
$_['error_invoice_not_found']    = 'No se encuentra la factura.';
$_['error_lock']                 = 'Otra factura se est&aacute; firmando en este momento. Vuelva a intentarlo.';
$_['error_sign']                 = 'No se pudo firmar la factura:';
$_['error_rectified_not_sent']   = 'No se encuentra la factura que se rectifica.';
$_['error_connection']           = 'No se pudo conectar con la Hacienda Foral:';
$_['error_http']                 = 'La Hacienda Foral respondi&oacute; con el c&oacute;digo HTTP %s.';
$_['error_series_length']        = 'TicketBAI admite como m&aacute;ximo 20 caracteres en la serie (prefijo) y 20 en el n&uacute;mero de factura.';
$_['error_seller_nif']           = 'El NIF del emisor (Sistema &gt; Ajustes) debe tener 9 caracteres.';
$_['error_no_products']          = 'La factura no tiene l&iacute;neas.';
$_['error_buyer_nif']            = 'El cliente no tiene NIF y la factura supera 400 &euro;: no se puede emitir como simplificada.';
$_['error_buyer_name']           = 'El cliente no tiene nombre o raz&oacute;n social.';
$_['error_tax_rate']             = 'No se reconoce el impuesto &quot;%s&quot; como un IVA en porcentaje (Sistema &gt; Localizaci&oacute;n &gt; Impuestos).';
$_['error_exempt_reason']        = 'La factura no lleva IVA: elija la causa de exenci&oacute;n en Ventas &gt; TicketBAI &gt; Ajustes.';
$_['error_license_format']       = 'La licencia TicketBAI tiene como m&aacute;ximo 20 caracteres.';
$_['error_developer_nif_format'] = 'El NIF de la entidad desarrolladora debe tener 9 caracteres.';

// Pestana TicketBAI de la ficha de factura
$_['tab_ticketbai']              = 'TicketBAI';
$_['text_info_sent_date']        = 'Fecha de env&iacute;o';
$_['text_info_response_date']    = 'Fecha de respuesta';
$_['text_info_status']           = 'Estado';
$_['text_info_notice']           = 'Mensaje';
$_['text_info_identifier']       = 'Identificador TBAI';
$_['button_resend_invoice']      = 'Reenviar a TicketBAI';
