<?php
// Heading
$_['heading_title']              = 'TicketBAI';
$_['heading_setting']            = 'TicketBAI settings';

// Text
$_['text_home']                  = 'Home';
$_['text_success_setting']       = 'TicketBAI settings saved.';
$_['text_success_sent']          = 'Invoice sent to TicketBAI and accepted by the Provincial Treasury.';
$_['text_already_sent']          = 'The invoice was already sent and accepted in TicketBAI.';
$_['text_no_results']            = 'No invoice has been signed with TicketBAI yet.';
$_['text_all']                   = 'All';
$_['text_yes']                   = 'Yes';
$_['text_no']                    = 'No';
$_['text_araba']                 = 'Araba / &Aacute;lava';
$_['text_bizkaia']               = 'Bizkaia (Batuz)';
$_['text_gipuzkoa']              = 'Gipuzkoa';
$_['text_test']                  = 'Testing';
$_['text_production']            = 'Production';
$_['text_invoice']               = 'Invoice';
$_['text_rectifying']            = 'Corrective';
$_['text_status_signed']         = 'Signed, not sent';
$_['text_status_sent']           = 'Accepted';
$_['text_status_rejected']       = 'Rejected';
$_['text_status_error']          = 'Sending error';
$_['text_default_description']   = 'Sale';
$_['text_confirm_resend']        = 'Send this invoice to TicketBAI again?';
$_['text_active_note']           = 'While TicketBAI is active, new invoices are signed and sent to the Provincial Treasury.';
$_['text_certificate_note']      = 'The same certificate (.p12/.pfx) and password as Facturae is used, from System &gt; Settings.';
$_['text_license_note']          = 'The TicketBAI licence and the developer entity NIF are issued by the Provincial Treasury when the software is registered.';
$_['text_requirements_ok']       = 'Server ready to sign and send.';
$_['text_pending_count']         = '%s signed invoice(s) are not recorded as accepted.';

// Column
$_['column_invoice']             = 'Invoice';
$_['column_type']                = 'Type';
$_['column_customer']            = 'Customer';
$_['column_date']                = 'Date';
$_['column_total']               = 'Total';
$_['column_identifier']          = 'TBAI identifier';
$_['column_environment']         = 'Environment';
$_['column_status']              = 'Status';
$_['column_message']             = 'Message';
$_['column_action']              = 'Action';

// Entry
$_['entry_active']               = 'Enable TicketBAI';
$_['entry_territory']            = 'Territory (Provincial Treasury)';
$_['entry_environment']          = 'Environment';
$_['entry_license']              = 'TicketBAI licence';
$_['entry_developer_nif']        = 'Developer entity NIF';
$_['entry_self_employed']        = 'Self-employed (Bizkaia, form 140)';
$_['entry_epigraph']             = 'IAE heading (form 140)';
$_['entry_exempt_reason']        = 'Exemption reason (invoices without VAT)';
$_['entry_ca_bundle']             = 'CA bundle path (optional)';
$_['text_ca_bundle_help']         = 'Only if the server has no CA list configured for curl (curl.cainfo in php.ini).';
$_['entry_foreign_operation']    = 'Foreign customers: type of operation';
$_['text_delivery']              = 'Delivery of goods';
$_['text_services']              = 'Provision of services';
$_['entry_status']               = 'Status';

// Button
$_['button_setting']             = 'Settings';
$_['button_save']                = 'Save';
$_['button_cancel']              = 'Cancel';
$_['button_resend']              = 'Resend';
$_['button_xml']                 = 'XML';
$_['button_filter']              = 'Filter';

// Error
$_['error_warning']            = 'Please check the highlighted fields.';
$_['error_permission']           = 'You do not have permission to modify TicketBAI.';
$_['error_extension']            = 'TicketBAI needs the PHP extension &quot;%s&quot; (enable it in php.ini and restart the web server).';
$_['error_library']            = 'The module library is missing: system/library/ticketbai.php.';
$_['error_territory']            = 'Choose the TicketBAI territory in Sales &gt; TicketBAI &gt; Settings.';
$_['error_license']              = 'The TicketBAI licence or the developer entity NIF is missing (Sales &gt; TicketBAI &gt; Settings).';
$_['error_certificate']          = 'The digital certificate is missing: upload it in System &gt; Settings.';
$_['error_epigraph']             = 'Bizkaia form 140 needs the IAE heading.';
$_['error_not_signed']           = 'This invoice has not been signed with TicketBAI.';
$_['error_invoice_not_found']    = 'Invoice not found.';
$_['error_lock']                 = 'Another invoice is being signed right now. Please try again.';
$_['error_sign']                 = 'The invoice could not be signed:';
$_['error_rectified_not_sent']   = 'The invoice being corrected was not found.';
$_['error_connection']           = 'Could not connect to the Provincial Treasury:';
$_['error_http']                 = 'The Provincial Treasury answered with HTTP code %s.';
$_['error_series_length']        = 'TicketBAI allows at most 20 characters for the series (prefix) and 20 for the invoice number.';
$_['error_seller_nif']           = 'The issuer NIF (System &gt; Settings) must be 9 characters long.';
$_['error_no_products']          = 'The invoice has no lines.';
$_['error_buyer_nif']            = 'The customer has no NIF and the invoice exceeds 400 &euro;: it cannot be issued as a simplified invoice.';
$_['error_buyer_name']           = 'The customer has no name.';
$_['error_tax_rate']             = 'The tax &quot;%s&quot; is not recognised as a percentage VAT (System &gt; Localisation &gt; Taxes).';
$_['error_exempt_reason']        = 'The invoice has no VAT: choose the exemption reason in Sales &gt; TicketBAI &gt; Settings.';
$_['error_license_format']       = 'The TicketBAI licence is at most 20 characters long.';
$_['error_developer_nif_format'] = 'The developer entity NIF must be 9 characters long.';

// TicketBAI tab of the invoice page
$_['tab_ticketbai']              = 'TicketBAI';
$_['text_info_sent_date']        = 'Sent on';
$_['text_info_response_date']    = 'Answered on';
$_['text_info_status']           = 'Status';
$_['text_info_notice']           = 'Message';
$_['text_info_identifier']       = 'TBAI identifier';
$_['button_resend_invoice']      = 'Resend to TicketBAI';
