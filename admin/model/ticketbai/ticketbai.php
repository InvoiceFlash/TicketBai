<?php
/**
 * TicketBAI (Araba, Bizkaia, Gipuzkoa) sobre system/library/ticketbai.php (sin Composer).
 *
 * Flujo de una factura: se construye el XML, se firma una sola vez, se guarda el XML firmado
 * en `tbai_invoice` y se envia. Si el envio falla, el reenvio manda el mismo XML firmado
 * (TicketBAI no permite volver a firmar una factura ya emitida: el encadenamiento de la
 * siguiente depende de esta firma).
 */
class ModelTicketbaiTicketbai extends Model {
	const STATUS_SIGNED   = 'signed';
	const STATUS_SENT     = 'sent';
	const STATUS_REJECTED = 'rejected';
	const STATUS_ERROR    = 'error';

	// Valores que se escriben en invoice.aeat_status. Fijos (no traducibles): el icono verde del
	// listado de facturas compara contra 'TicketBAI Recibido' (ver vqmod/xml/ticketbai.xml).
	const CORE_STATUS_SENT     = 'TicketBAI Recibido';
	const CORE_STATUS_REJECTED = 'TicketBAI Rechazado';
	const CORE_STATUS_ERROR    = 'TicketBAI Error';

	const APP_NAME = 'InvoiceFlash';

	private static $tables_checked = false;
	private $texts = null;

	public function install() {
		if (self::$tables_checked) {
			return;
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "tbai_invoice` (
			`tbai_invoice_id` INT(11) NOT NULL AUTO_INCREMENT,
			`invoice_id` INT(11) NOT NULL,
			`store_id` INT(11) NOT NULL DEFAULT '0',
			`issuer_nif` VARCHAR(20) NOT NULL DEFAULT '',
			`territory` CHAR(2) NOT NULL,
			`production` TINYINT(1) NOT NULL DEFAULT '0',
			`type` VARCHAR(16) NOT NULL DEFAULT 'invoice',
			`rectified_invoice_id` INT(11) NOT NULL DEFAULT '0',
			`series` VARCHAR(20) NOT NULL DEFAULT '',
			`number` VARCHAR(20) NOT NULL,
			`expedition_date` DATE NOT NULL,
			`total` DECIMAL(15,2) NOT NULL DEFAULT '0.00',
			`identifier` VARCHAR(64) NOT NULL DEFAULT '',
			`qr_url` VARCHAR(512) NOT NULL DEFAULT '',
			`chain_signature` VARCHAR(100) NOT NULL DEFAULT '',
			`document_json` MEDIUMTEXT NOT NULL,
			`signed_xml` MEDIUMTEXT NOT NULL,
			`status` VARCHAR(16) NOT NULL DEFAULT 'signed',
			`message` TEXT NOT NULL,
			`response` MEDIUMTEXT NOT NULL,
			`attempts` INT(11) NOT NULL DEFAULT '0',
			`date_signed` DATETIME NOT NULL,
			`date_sent` DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (`tbai_invoice_id`),
			UNIQUE KEY `invoice_id` (`invoice_id`),
			KEY `chain` (`store_id`, `issuer_nif`, `tbai_invoice_id`),
			KEY `status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");

		self::$tables_checked = true;
	}

	public function isActive() {
		return (bool)$this->config->get('ticketbai_active') && in_array((string)$this->config->get('ticketbai_territory'), array('01', '02', '03'));
	}

	public function isProduction() {
		return $this->config->get('ticketbai_environment') == 'production';
	}

	// '' si el servidor puede firmar y enviar; si no, el motivo (para mostrarlo tal cual).
	public function requirementsError() {
		foreach (array('openssl', 'curl', 'dom', 'simplexml', 'zlib') as $extension) {
			if (!extension_loaded($extension)) {
				return sprintf($this->text('error_extension'), $extension);
			}
		}

		if (!is_file($this->getLibraryPath())) {
			return $this->text('error_library');
		}

		return '';
	}

	// Motivo por el que no se puede emitir con la configuracion actual ('' si esta lista).
	public function configurationError() {
		if (!in_array((string)$this->config->get('ticketbai_territory'), array('01', '02', '03'))) {
			return $this->text('error_territory');
		}

		if (trim((string)$this->config->get('ticketbai_license')) === '' || trim((string)$this->config->get('ticketbai_developer_nif')) === '') {
			return $this->text('error_license');
		}

		if (!$this->config->get('certificado') || !is_file($this->getCertificatePath())) {
			return $this->text('error_certificate');
		}

		if ($this->config->get('ticketbai_territory') == '02' && $this->config->get('ticketbai_self_employed') && trim((string)$this->config->get('ticketbai_epigraph')) === '') {
			return $this->text('error_epigraph');
		}

		return '';
	}

	public function getRecord($invoice_id) {
		$this->install();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tbai_invoice` WHERE invoice_id = '" . (int)$invoice_id . "'");

		return $query->row;
	}

	public function getRecords($data = array()) {
		$this->install();

		$sql = "SELECT t.tbai_invoice_id, t.invoice_id, t.territory, t.production, t.type, t.rectified_invoice_id, t.series, t.number, t.expedition_date, t.total, t.identifier, t.status, t.message, t.attempts, t.date_signed, t.date_sent, i.payment_company, i.firstname, i.lastname FROM `" . DB_PREFIX . "tbai_invoice` t LEFT JOIN `" . DB_PREFIX . "invoice` i ON (i.invoice_id = t.invoice_id)" . $this->getRecordsWhere($data) . " ORDER BY t.tbai_invoice_id DESC";

		$start = isset($data['start']) ? max(0, (int)$data['start']) : 0;
		$limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 20;

		$sql .= " LIMIT " . $start . "," . $limit;

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalRecords($data = array()) {
		$this->install();

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "tbai_invoice` t" . $this->getRecordsWhere($data));

		return (int)$query->row['total'];
	}

	private function getRecordsWhere($data) {
		$where = array();

		if (!empty($data['filter_status'])) {
			$where[] = "t.status = '" . $this->db->escape($data['filter_status']) . "'";
		}

		return $where ? " WHERE " . implode(" AND ", $where) : '';
	}

	/**
	 * Firma y envia una factura de venta. $seller/$buyer vienen de
	 * ControllerSaleInvoice::getFacturaeParties() (mismos datos que VeriFactu y Facturae).
	 * $rectified_invoice_id > 0 la emite como rectificativa por diferencias de esa factura.
	 *
	 * Devuelve array('success' => bool, 'message' => string), el mismo contrato que autoSendAeat().
	 */
	public function send($invoice_id, $seller, $buyer, $rectified_invoice_id = 0) {
		$this->install();

		$error = $this->requirementsError();

		if (!$error) {
			$error = $this->configurationError();
		}

		if ($error) {
			$this->writeCoreStatus($invoice_id, self::CORE_STATUS_ERROR, $error, '', false);

			return array('success' => false, 'message' => $error);
		}

		$record = $this->getRecord($invoice_id);

		if ($record && $record['status'] == self::STATUS_SENT) {
			return array('success' => true, 'message' => $this->text('text_already_sent'));
		}

		if (!$record) {
			$signed = $this->signInvoice($invoice_id, $seller, $buyer, $rectified_invoice_id);

			if (!$signed['success']) {
				$this->writeCoreStatus($invoice_id, self::CORE_STATUS_ERROR, $signed['message'], '', false);

				return $signed;
			}

			$record = $this->getRecord($invoice_id);
		}

		return $this->submit($record);
	}

	// Reenvio manual de una factura ya firmada (pantalla TicketBAI).
	public function resend($invoice_id) {
		$record = $this->getRecord($invoice_id);

		if (!$record) {
			return array('success' => false, 'message' => $this->text('error_not_signed'));
		}

		if ($record['status'] == self::STATUS_SENT) {
			return array('success' => true, 'message' => $this->text('text_already_sent'));
		}

		$error = $this->requirementsError();

		if ($error) {
			return array('success' => false, 'message' => $error);
		}

		return $this->submit($record);
	}

	// Identificador TBAI y URL del QR de una factura firmada (para imprimirlos), o false.
	public function getQr($invoice_id) {
		if (!$this->isActive()) {
			return false;
		}

		$record = $this->getRecord($invoice_id);

		if (!$record || $record['identifier'] === '' || $record['qr_url'] === '') {
			return false;
		}

		return array(
			'identifier' => $record['identifier'],
			'url'        => $record['qr_url']
		);
	}

	private function signInvoice($invoice_id, $seller, $buyer, $rectified_invoice_id) {
		$this->load->model('sale/invoice');

		$invoice_info = $this->model_sale_invoice->getInvoice($invoice_id);

		if (!$invoice_info) {
			return array('success' => false, 'message' => $this->text('error_invoice_not_found'));
		}

		$store_id = (int)$invoice_info['store_id'];
		$issuer_nif = strtoupper($seller['nif']);

		// El encadenamiento exige que dos facturas no se firmen a la vez contra la misma
		// "anterior": se serializa por emisor con un bloqueo de MariaDB/MySQL.
		$lock_name = 'tbai_chain_' . $store_id . '_' . $issuer_nif;
		$lock = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($lock_name) . "', 15) AS l");

		if (!$lock->num_rows || !(int)$lock->row['l']) {
			return array('success' => false, 'message' => $this->text('error_lock'));
		}

		try {
			$result = $this->doSignInvoice($invoice_info, $seller, $buyer, $rectified_invoice_id, $issuer_nif);
		} catch (Exception $e) {
			$result = array('success' => false, 'message' => $this->text('error_sign') . ' ' . $e->getMessage());
		} catch (Error $e) {
			$result = array('success' => false, 'message' => $this->text('error_sign') . ' ' . $e->getMessage());
		}

		$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($lock_name) . "')");

		return $result;
	}

	private function doSignInvoice($invoice_info, $seller, $buyer, $rectified_invoice_id, $issuer_nif) {
		$invoice_id = (int)$invoice_info['invoice_id'];

		// Otra peticion pudo firmarla mientras esperabamos el bloqueo.
		if ($this->getRecord($invoice_id)) {
			return array('success' => true, 'message' => '');
		}

		$previous_query = $this->db->query("SELECT series, number, expedition_date, chain_signature FROM `" . DB_PREFIX . "tbai_invoice` WHERE store_id = '" . (int)$invoice_info['store_id'] . "' AND issuer_nif = '" . $this->db->escape($issuer_nif) . "' AND production = '" . ($this->isProduction() ? 1 : 0) . "' ORDER BY tbai_invoice_id DESC LIMIT 1");

		$previous = $previous_query->num_rows ? $previous_query->row : null;

		$rectified = null;

		if ($rectified_invoice_id) {
			$rectified = $this->getRecord($rectified_invoice_id);

			// Factura emitida antes de activar TicketBAI: se identifica con los datos de la propia factura.
			if (!$rectified) {
				$rectified_info = $this->model_sale_invoice->getInvoice($rectified_invoice_id);

				if (!$rectified_info) {
					return array('success' => false, 'message' => $this->text('error_rectified_not_sent'));
				}

				$rectified = array(
					'series'          => trim((string)$rectified_info['invoice_prefix']),
					'number'          => (string)($rectified_info['invoice_no'] ? $rectified_info['invoice_no'] : $rectified_info['invoice_id']),
					'expedition_date' => $rectified_info['date_added']
				);
			}
		}

		$data = $this->buildData($invoice_info, $seller, $buyer, $previous, $rectified);

		if (is_string($data)) {
			return array('success' => false, 'message' => $data);
		}

		$client = $this->createClient((string)$this->config->get('ticketbai_territory'), $this->isProduction());

		$signed_xml = $client->sign($client->buildInvoiceXml($data), $this->getCertificatePath(), (string)$this->config->get('clave'));

		$signature = Ticketbai::signatureValue($signed_xml);
		$identifier = Ticketbai::identifier($data['issuer']['nif'], $data['date'], $signature);
		$qr_url = $client->qrUrl($identifier, $data['series'], $data['number'], $data['total']);

		$this->db->query("INSERT INTO `" . DB_PREFIX . "tbai_invoice` SET
			invoice_id = '" . $invoice_id . "',
			store_id = '" . (int)$invoice_info['store_id'] . "',
			issuer_nif = '" . $this->db->escape($issuer_nif) . "',
			territory = '" . $this->db->escape((string)$this->config->get('ticketbai_territory')) . "',
			production = '" . ($this->isProduction() ? 1 : 0) . "',
			type = '" . ($rectified ? 'rectifying' : 'invoice') . "',
			rectified_invoice_id = '" . (int)$rectified_invoice_id . "',
			series = '" . $this->db->escape($data['series']) . "',
			number = '" . $this->db->escape($data['number']) . "',
			expedition_date = '" . $this->db->escape(date('Y-m-d', strtotime($invoice_info['date_added']))) . "',
			total = '" . (float)$data['total'] . "',
			identifier = '" . $this->db->escape($identifier) . "',
			qr_url = '" . $this->db->escape($qr_url) . "',
			chain_signature = '" . $this->db->escape(substr($signature, 0, 100)) . "',
			document_json = '" . $this->db->escape(json_encode($data)) . "',
			signed_xml = '" . $this->db->escape($signed_xml) . "',
			status = '" . self::STATUS_SIGNED . "',
			message = '',
			response = '',
			attempts = '0',
			date_signed = NOW()");

		return array('success' => true, 'message' => '');
	}

	private function submit($record) {
		$sent_date = date('Y-m-d H:i:s');

		$lroe = array();

		if ($record['territory'] == '02') {
			$document = json_decode($record['document_json'], true);

			$lroe = array(
				'nif'           => $document['issuer']['nif'],
				'name'          => $document['issuer']['name'],
				'year'          => date('Y', strtotime($record['expedition_date'])),
				'self_employed' => (bool)$this->config->get('ticketbai_self_employed'),
				'epigraph'      => trim((string)$this->config->get('ticketbai_epigraph'))
			);
		}

		try {
			$client = $this->createClient($record['territory'], (bool)$record['production']);

			$result = $client->submitInvoice($record['signed_xml'], $this->getCertificatePath(), (string)$this->config->get('clave'), $lroe);
		} catch (Exception $e) {
			return $this->saveSubmitResult($record, self::STATUS_ERROR, $this->text('error_connection') . ' ' . $e->getMessage(), '', $sent_date);
		} catch (Error $e) {
			return $this->saveSubmitResult($record, self::STATUS_ERROR, $this->text('error_connection') . ' ' . $e->getMessage(), '', $sent_date);
		}

		if ($result['delivered']) {
			return $this->saveSubmitResult($record, self::STATUS_SENT, '', $result['raw'], $sent_date);
		}

		$messages = array();

		foreach ($result['errors'] as $error) {
			$messages[] = trim($error['code'] . ': ' . $error['message']);
		}

		if (!$messages) {
			$messages[] = $result['message'] !== '' ? $result['message'] : sprintf($this->text('error_http'), $result['http_code']);
		}

		// Sin respuesta valida (HTTP distinto de 200) se trata como fallo de envio que se
		// puede reintentar; una respuesta 200 con errores es un rechazo de Hacienda.
		$status = ($result['http_code'] == 200) ? self::STATUS_REJECTED : self::STATUS_ERROR;

		return $this->saveSubmitResult($record, $status, implode("\n", $messages), $result['raw'], $sent_date);
	}

	private function saveSubmitResult($record, $status, $message, $raw, $sent_date) {
		$this->db->query("UPDATE `" . DB_PREFIX . "tbai_invoice` SET
			status = '" . $this->db->escape($status) . "',
			message = '" . $this->db->escape($message) . "',
			response = '" . $this->db->escape($raw) . "',
			attempts = attempts + 1,
			date_sent = '" . $this->db->escape($sent_date) . "'
			WHERE tbai_invoice_id = '" . (int)$record['tbai_invoice_id'] . "'");

		$ok = ($status == self::STATUS_SENT);

		$this->writeCoreStatus($record['invoice_id'], $ok ? self::CORE_STATUS_SENT : ($status == self::STATUS_REJECTED ? self::CORE_STATUS_REJECTED : self::CORE_STATUS_ERROR), $message, $record['identifier'], true, $sent_date);

		return array(
			'success' => $ok,
			'message' => $ok ? $this->text('text_success_sent') : $message
		);
	}

	// Refleja el resultado en las columnas aeat_* de la factura, que son las que pintan el
	// icono del listado y la pestana AEAT de la ficha. Nunca toca aeat_hash (cadena VeriFactu).
	private function writeCoreStatus($invoice_id, $status, $notice, $identifier, $sent, $sent_date = null) {
		$sql = "UPDATE `" . DB_PREFIX . "invoice` SET aeat_status = '" . $this->db->escape($status) . "', aeat_notice = '" . $this->db->escape($notice) . "'";

		if ($identifier !== '') {
			$sql .= ", aeat_csv = '" . $this->db->escape(utf8_substr($identifier, 0, 100)) . "'";
		}

		if ($sent) {
			$sql .= ", aeat_sent_date = '" . $this->db->escape($sent_date) . "', aeat_response_date = NOW()";
		}

		$this->db->query($sql . " WHERE invoice_id = '" . (int)$invoice_id . "'");
	}

	/**
	 * Datos de la factura en el formato de Ticketbai::buildInvoiceXml(). Devuelve un string con
	 * el error si a la factura le falta algo que TicketBAI exige.
	 */
	private function buildData($invoice_info, $seller, $buyer, $previous, $rectified) {
		$invoice_id = (int)$invoice_info['invoice_id'];

		$series = trim((string)$invoice_info['invoice_prefix']);
		$number = (string)($invoice_info['invoice_no'] ? $invoice_info['invoice_no'] : $invoice_id);

		if (utf8_strlen($series) > 20 || utf8_strlen($number) > 20) {
			return $this->text('error_series_length');
		}

		if (!preg_match('/^[A-Z0-9]{9}$/', strtoupper($seller['nif']))) {
			return $this->text('error_seller_nif');
		}

		$products = $this->model_sale_invoice->getInvoiceProducts($invoice_id);

		if (!$products) {
			return $this->text('error_no_products');
		}

		$details = array();
		$names = array();

		foreach ($products as $product) {
			$quantity = (int)$product['quantity'];
			$price = (float)$product['price'];
			$discount_rate = (float)$product['discount'];

			// invoice_product.total = precio x cantidad con el descuento (%) ya aplicado, sin IVA;
			// invoice_product.tax es el IVA por unidad (convenio de OpenCart). En la factura
			// negativa de "Anular" cantidad, total y tax van en negativo, por eso abs($quantity).
			$discount_amount = round(abs($price * $quantity) * $discount_rate / 100, 2);
			$line_total = round((float)$product['total'] + (float)$product['tax'] * abs($quantity), 2);

			$name = trim(html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'));
			$names[] = $name;

			$details[] = array(
				'description' => utf8_substr($name !== '' ? $name : '-', 0, 250),
				'quantity'    => (string)$quantity,
				'unit_price'  => $this->amount($price, 8),
				'discount'    => $discount_amount > 0 ? $this->amount($discount_amount) : '',
				'total'       => $this->amount($line_total)
			);
		}

		$breakdown = $this->buildBreakdown($invoice_id);

		if (is_string($breakdown)) {
			return $breakdown;
		}

		$total = (float)$invoice_info['total'];

		$simplified = false;
		$recipients = array();
		$operation = '';

		if ($buyer['nif'] === '') {
			// Sin NIF del cliente solo cabe factura simplificada (art. 4 RD 1619/2012: hasta 400 EUR).
			if (abs($total) > 400) {
				return $this->text('error_buyer_nif');
			}

			$simplified = true;
		} else {
			$recipient = $this->buildRecipient($buyer);

			if (is_string($recipient)) {
				return $recipient;
			}

			$recipients[] = $recipient;

			// Con destinatario extranjero TicketBAI exige el desglose por tipo de operacion.
			if ($recipient['country'] != 'ES') {
				$operation = $this->config->get('ticketbai_foreign_operation') == 'delivery' ? 'delivery' : 'services';
			}
		}

		$description = utf8_substr(implode(', ', $names), 0, 250);

		$data = array(
			'issuer'      => array(
				'nif'  => strtoupper($seller['nif']),
				'name' => utf8_substr(html_entity_decode($seller['name'], ENT_QUOTES, 'UTF-8'), 0, 120)
			),
			'recipients'  => $recipients,
			'series'      => $series,
			'number'      => $number,
			'date'        => date('d-m-Y', strtotime($invoice_info['date_added'])),
			'time'        => date('H:i:s', strtotime($invoice_info['date_added'])),
			'simplified'  => $simplified,
			'rectifying'  => null,
			'rectified'   => array(),
			'description' => $description !== '' ? $description : $this->text('text_default_description'),
			'details'     => $details,
			'total'       => $this->amount($total),
			'vat_regimes' => array('01'),
			'breakdown'   => $breakdown,
			'operation'   => $operation,
			'previous'    => $previous ? array(
				'series'    => $previous['series'],
				'number'    => $previous['number'],
				'date'      => date('d-m-Y', strtotime($previous['expedition_date'])),
				'signature' => $previous['chain_signature']
			) : null,
			'software'    => array(
				'license'       => trim((string)$this->config->get('ticketbai_license')),
				'developer_nif' => strtoupper(trim((string)$this->config->get('ticketbai_developer_nif'))),
				'name'          => self::APP_NAME,
				'version'       => defined('VERSION') ? (string)VERSION : '1.0'
			)
		);

		if ($rectified) {
			// Rectificativa por diferencias (tipo I): la factura negativa que genera "Anular"
			// resta el importe de la original. R1 = error fundado en derecho (art. 80 LIVA);
			// R5 es la equivalente para simplificadas.
			$data['rectifying'] = array('code' => $simplified ? 'R5' : 'R1', 'type' => 'I');
			$data['rectified'] = array(array(
				'series' => $rectified['series'],
				'number' => $rectified['number'],
				'date'   => date('d-m-Y', strtotime($rectified['expedition_date']))
			));
		}

		return $data;
	}

	// Desglose de IVA a partir de las filas `tax` de invoice_total (misma derivacion que VeriFactu:
	// base = cuota / tipo, asi funciona con varios tipos en la misma factura).
	private function buildBreakdown($invoice_id) {
		$this->load->model('localisation/tax_rate');

		$rates = array();

		foreach ($this->model_localisation_tax_rate->getTaxRates() as $tax_rate) {
			$rates[$tax_rate['name']] = $tax_rate;
		}

		$sub_total = 0;
		$taxes = array();

		foreach ($this->model_sale_invoice->getInvoiceTotals($invoice_id) as $total) {
			if ($total['code'] == 'sub_total') {
				$sub_total = (float)$total['value'];
			}

			if ($total['code'] == 'tax') {
				if (!isset($rates[$total['title']]) || $rates[$total['title']]['type'] != 'P' || (float)$rates[$total['title']]['rate'] <= 0) {
					return sprintf($this->text('error_tax_rate'), $total['title']);
				}

				$rate = (float)$rates[$total['title']]['rate'];
				$quota = (float)$total['value'];

				$taxes[] = array(
					'base'  => $this->amount($quota / ($rate / 100)),
					'rate'  => $this->amount($rate),
					'quota' => $this->amount($quota)
				);
			}
		}

		if ($taxes) {
			return array('exempt' => array(), 'not_exempt' => $taxes);
		}

		// Factura sin IVA: operacion sujeta y exenta con la causa elegida en Ajustes.
		$reason = (string)$this->config->get('ticketbai_exempt_reason');

		if (!in_array($reason, array('E1', 'E2', 'E3', 'E4', 'E5', 'E6'))) {
			return $this->text('error_exempt_reason');
		}

		return array('exempt' => array(array('reason' => $reason, 'base' => $this->amount($sub_total))), 'not_exempt' => array());
	}

	private function buildRecipient($buyer) {
		$name = utf8_substr(trim(html_entity_decode((string)$buyer['name'], ENT_QUOTES, 'UTF-8')), 0, 120);

		if ($name === '') {
			return $this->text('error_buyer_name');
		}

		$country = $this->getCountryIso2(isset($buyer['country']) ? $buyer['country'] : '');

		return array(
			'nif'      => strtoupper($buyer['nif']),
			'name'     => $name,
			'postcode' => utf8_substr((string)(isset($buyer['postcode']) ? $buyer['postcode'] : ''), 0, 20),
			'address'  => utf8_substr(trim(html_entity_decode((string)(isset($buyer['address']) ? $buyer['address'] : ''), ENT_QUOTES, 'UTF-8')), 0, 250),
			'country'  => $country,
			// NIF-IVA para clientes de la UE; documento oficial de su pais para el resto.
			'id_type'  => $country == 'ES' ? '' : ($this->isEuCountry($country) ? '02' : '04')
		);
	}

	private function getCountryIso2($iso3) {
		$iso3 = strtoupper((string)$iso3);

		if ($iso3 === '' || $iso3 == 'ESP') {
			return 'ES';
		}

		$query = $this->db->query("SELECT iso_code_2 FROM `" . DB_PREFIX . "country` WHERE iso_code_3 = '" . $this->db->escape($iso3) . "' LIMIT 1");

		return $query->num_rows && $query->row['iso_code_2'] ? strtoupper($query->row['iso_code_2']) : 'ES';
	}

	private function isEuCountry($iso2) {
		return in_array($iso2, array('AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'GR', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'));
	}

	private function amount($value, $decimals = 2) {
		$formatted = number_format(round((float)$value, $decimals), $decimals, '.', '');

		if ($decimals > 2) {
			$formatted = rtrim(rtrim($formatted, '0'), '.');
		}

		return ($formatted === '-0' || $formatted === '-0.00') ? ltrim($formatted, '-') : $formatted;
	}

	// Textos del modulo leidos aparte: $this->language->load() los mezclaria con los de la
	// pantalla que llama (sale/invoice, sale/delivery...) y pisaria claves como heading_title.
	private function text($key) {
		if ($this->texts === null) {
			$this->texts = array();

			$directory = 'en-gb';

			$query = $this->db->query("SELECT directory FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape((string)$this->config->get('config_admin_language')) . "' LIMIT 1");

			if ($query->num_rows && $query->row['directory']) {
				$directory = $query->row['directory'];
			}

			foreach (array_unique(array('en-gb', $directory)) as $dir) {
				$file = DIR_LANGUAGE . $dir . '/ticketbai/ticketbai.php';

				if (is_file($file)) {
					$_ = array();

					require($file);

					$this->texts = array_merge($this->texts, $_);
				}
			}
		}

		return isset($this->texts[$key]) ? $this->texts[$key] : $key;
	}

	protected function createClient($territory, $production) {
		require_once($this->getLibraryPath());

		$client = new Ticketbai($territory, $production);

		// Misma CA opcional que VeriFactu (Sistema > Ajustes), para servidores sin CA en curl.
		$ca_bundle = trim((string)$this->config->get('config_aeat_ca_bundle'));

		if ($ca_bundle !== '') {
			$root = dirname(rtrim(str_replace('\\', '/', DIR_APPLICATION), '/'));
			$path = is_file($ca_bundle) ? $ca_bundle : $root . '/' . ltrim(str_replace('\\', '/', $ca_bundle), '/');

			if (is_file($path)) {
				$client->setCaBundle($path);
			}
		}

		return $client;
	}

	protected function getLibraryPath() {
		return DIR_SYSTEM . 'library/ticketbai.php';
	}

	protected function getCertificatePath() {
		return DIR_DOWNLOAD . $this->config->get('certificado');
	}
}
