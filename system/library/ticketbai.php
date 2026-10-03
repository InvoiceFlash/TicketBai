<?php
/**
 * TicketBAI (Araba, Bizkaia, Gipuzkoa): XML, firma XAdES-EPES, identificador TBAI, QR y envio.
 *
 * Autocontenida, sin Composer ni dependencias: solo DOMDocument, openssl y curl, y sintaxis
 * compatible con PHP 5 (igual que facturae_signer.php del nucleo).
 *
 * Estructuras, politicas de firma y endpoints tomados de:
 *  - Especificaciones TicketBAI 1.2 y el esquema ticketbaiv1-2-2.xsd de las Haciendas Forales.
 *  - barnetik/ticketbai (https://github.com/Barnetik/tbai-php-lib, GPL-3.0-or-later), commit d91ad03.
 *  - La firma sigue el ejemplo oficial de alta de factura (SigningCertificate + IssuerSerial, SPURI).
 */
class TicketbaiException extends Exception {
}

class Ticketbai {
	const ARABA    = '01';
	const BIZKAIA  = '02';
	const GIPUZKOA = '03';

	const NS_TBAI  = 'urn:ticketbai:emision';
	const NS_DS    = 'http://www.w3.org/2000/09/xmldsig#';
	const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

	const VERSION_TBAI = '1.2';

	private $territory;
	private $production;
	private $caBundle = '';
	private $timeout = 30;

	// Politica de firma de cada Hacienda: identificador y SHA-256 (base64) del documento publicado.
	private static $policies = array(
		self::ARABA    => array('https://ticketbai.araba.eus/tbai/sinadura/', '4Vk3uExj7tGn9DyUCPDsV9HRmK6KZfYdRiW3StOjcQA='),
		self::BIZKAIA  => array('https://www.batuz.eus/fitxategiak/batuz/ticketbai/sinadura_elektronikoaren_zehaztapenak_especificaciones_de_la_firma_electronica_v1_1.pdf', 'K2baIY0fk8jbkPHkffk5F5C46O5VuzDwH21dAovjVRs='),
		self::GIPUZKOA => array('https://www.gipuzkoa.eus/ticketbai/sinadura', '4LDJbY5hqHHHX858s9QV1P8yVGzo6H23P/iNRRv+PnQ=')
	);

	private static $endpoints = array(
		self::ARABA    => array('https://pruebas-ticketbai.araba.eus/TicketBAI/v1/facturas', 'https://ticketbai.araba.eus/TicketBAI/v1/facturas'),
		self::BIZKAIA  => array('https://pruesarrerak.bizkaia.eus/N3B4000M/aurkezpena', 'https://sarrerak.bizkaia.eus/N3B4000M/aurkezpena'),
		self::GIPUZKOA => array('https://tbai-z.prep.gipuzkoa.eus/sarrerak/alta', 'https://tbai-z.egoitza.gipuzkoa.eus/sarrerak/alta')
	);

	private static $qrUrls = array(
		self::ARABA    => array('https://pruebas-ticketbai.araba.eus/tbai/qrtbai/', 'https://ticketbai.araba.eus/tbai/qrtbai/'),
		self::BIZKAIA  => array('https://batuz.eus/QRTBAI/', 'https://batuz.eus/QRTBAI/'),
		self::GIPUZKOA => array('https://tbai.prep.gipuzkoa.eus/qr/', 'https://tbai.egoitza.gipuzkoa.eus/qr/')
	);

	public function __construct($territory, $production = false) {
		if (!isset(self::$policies[$territory])) {
			throw new TicketbaiException('Territorio TicketBAI no valido: ' . $territory);
		}

		$this->territory = $territory;
		$this->production = (bool)$production;
	}

	// CA para verificar el servidor de la Hacienda si el curl de PHP no trae una configurada.
	public function setCaBundle($path) {
		$this->caBundle = (string)$path;
	}

	/**
	 * XML TicketBAI sin firmar.
	 *
	 * $data:
	 *  issuer       array(nif, name)
	 *  recipients   array of array(nif, name, postcode, address, country (ISO 3166-1 alfa-2), id_type)
	 *               id_type solo para country != ES: 02 NIF-IVA, 03 pasaporte, 04 doc. oficial, 05, 06
	 *  series, number, date (dd-mm-aaaa), time (hh:mm:ss), simplified (bool)
	 *  rectifying   null | array(code R1..R5, type I|S)
	 *  rectified    array of array(series, number, date)
	 *  description, details (array of array(description, quantity, unit_price, discount, total)), total
	 *  vat_regimes  array('01', ...)
	 *  breakdown    array('exempt' => array of array(reason, base), 'not_exempt' => array of array(base, rate, quota))
	 *  operation    '' desglose por factura; 'delivery' | 'services' desglose por tipo de operacion
	 *               (obligatorio si el destinatario es extranjero)
	 *  previous     null | array(series, number, date, signature)  encadenamiento
	 *  software     array(license, developer_nif, name, version)
	 */
	public function buildInvoiceXml(array $data) {
		$doc = new DOMDocument('1.0', 'UTF-8');

		$root = $doc->createElementNS(self::NS_TBAI, 'T:TicketBai');
		$doc->appendChild($root);

		$header = $this->el($doc, $root, 'Cabecera');
		$this->el($doc, $header, 'IDVersionTBAI', self::VERSION_TBAI);

		// Sujetos
		$subjects = $this->el($doc, $root, 'Sujetos');
		$issuer = $this->el($doc, $subjects, 'Emisor');
		$this->el($doc, $issuer, 'NIF', $data['issuer']['nif']);
		$this->el($doc, $issuer, 'ApellidosNombreRazonSocial', $data['issuer']['name']);

		if (!empty($data['recipients'])) {
			$recipients = $this->el($doc, $subjects, 'Destinatarios');

			foreach ($data['recipients'] as $recipient) {
				$this->recipientXml($doc, $recipients, $recipient);
			}

			$this->el($doc, $subjects, 'VariosDestinatarios', count($data['recipients']) > 1 ? 'S' : 'N');
			$this->el($doc, $subjects, 'EmitidaPorTercerosODestinatario', 'N');
		}

		// Factura
		$invoice = $this->el($doc, $root, 'Factura');

		$invoiceHeader = $this->el($doc, $invoice, 'CabeceraFactura');

		if ((string)$data['series'] !== '') {
			$this->el($doc, $invoiceHeader, 'SerieFactura', $data['series']);
		}

		$this->el($doc, $invoiceHeader, 'NumFactura', $data['number']);
		$this->el($doc, $invoiceHeader, 'FechaExpedicionFactura', $data['date']);
		$this->el($doc, $invoiceHeader, 'HoraExpedicionFactura', $data['time']);
		$this->el($doc, $invoiceHeader, 'FacturaSimplificada', !empty($data['simplified']) ? 'S' : 'N');

		if (!empty($data['rectifying'])) {
			$rectifying = $this->el($doc, $invoiceHeader, 'FacturaRectificativa');
			$this->el($doc, $rectifying, 'Codigo', $data['rectifying']['code']);
			$this->el($doc, $rectifying, 'Tipo', $data['rectifying']['type']);

			if (!empty($data['rectified'])) {
				$rectifiedList = $this->el($doc, $invoiceHeader, 'FacturasRectificadasSustituidas');

				foreach ($data['rectified'] as $rectified) {
					$rectifiedEl = $this->el($doc, $rectifiedList, 'IDFacturaRectificadaSustituida');

					if ((string)$rectified['series'] !== '') {
						$this->el($doc, $rectifiedEl, 'SerieFactura', $rectified['series']);
					}

					$this->el($doc, $rectifiedEl, 'NumFactura', $rectified['number']);
					$this->el($doc, $rectifiedEl, 'FechaExpedicionFactura', $rectified['date']);
				}
			}
		}

		$invoiceData = $this->el($doc, $invoice, 'DatosFactura');
		$this->el($doc, $invoiceData, 'DescripcionFactura', $data['description']);

		$details = $this->el($doc, $invoiceData, 'DetallesFactura');

		foreach ($data['details'] as $detail) {
			$detailEl = $this->el($doc, $details, 'IDDetalleFactura');
			$this->el($doc, $detailEl, 'DescripcionDetalle', $detail['description']);
			$this->el($doc, $detailEl, 'Cantidad', $detail['quantity']);
			$this->el($doc, $detailEl, 'ImporteUnitario', $detail['unit_price']);

			if (isset($detail['discount']) && (string)$detail['discount'] !== '' && (float)$detail['discount'] != 0) {
				$this->el($doc, $detailEl, 'Descuento', $detail['discount']);
			}

			$this->el($doc, $detailEl, 'ImporteTotal', $detail['total']);
		}

		$this->el($doc, $invoiceData, 'ImporteTotalFactura', $data['total']);

		$keys = $this->el($doc, $invoiceData, 'Claves');

		foreach ($data['vat_regimes'] as $regime) {
			$key = $this->el($doc, $keys, 'IDClave');
			$this->el($doc, $key, 'ClaveRegimenIvaOpTrascendencia', $regime);
		}

		$breakdownType = $this->el($doc, $invoice, 'TipoDesglose');

		if (empty($data['operation'])) {
			$this->subjectXml($doc, $this->el($doc, $breakdownType, 'DesgloseFactura'), $data['breakdown']);
		} else {
			$byOperation = $this->el($doc, $breakdownType, 'DesgloseTipoOperacion');
			$this->subjectXml($doc, $this->el($doc, $byOperation, $data['operation'] == 'services' ? 'PrestacionServicios' : 'Entrega'), $data['breakdown']);
		}

		// HuellaTBAI
		$fingerprint = $this->el($doc, $root, 'HuellaTBAI');

		if (!empty($data['previous'])) {
			$previous = $this->el($doc, $fingerprint, 'EncadenamientoFacturaAnterior');

			if ((string)$data['previous']['series'] !== '') {
				$this->el($doc, $previous, 'SerieFacturaAnterior', $data['previous']['series']);
			}

			$this->el($doc, $previous, 'NumFacturaAnterior', $data['previous']['number']);
			$this->el($doc, $previous, 'FechaExpedicionFacturaAnterior', $data['previous']['date']);
			$this->el($doc, $previous, 'SignatureValueFirmaFacturaAnterior', substr($data['previous']['signature'], 0, 100));
		}

		$software = $this->el($doc, $fingerprint, 'Software');
		$this->el($doc, $software, 'LicenciaTBAI', $data['software']['license']);
		$developer = $this->el($doc, $software, 'EntidadDesarrolladora');
		$this->el($doc, $developer, 'NIF', $data['software']['developer_nif']);
		$this->el($doc, $software, 'Nombre', $data['software']['name']);
		$this->el($doc, $software, 'Version', $data['software']['version']);

		return $doc->saveXML();
	}

	/**
	 * Firma XAdES-EPES enveloped con la politica del territorio.
	 *
	 * @throws TicketbaiException
	 */
	public function sign($xml, $certificatePath, $certificatePassword) {
		list($certPem, $keyPem) = $this->loadCertificate($certificatePath, $certificatePassword);

		$certDer = $this->pemToDer($certPem);
		$certInfo = openssl_x509_parse($certPem);
		$privateKey = openssl_pkey_get_private($keyPem);

		if (!$certInfo || !$privateKey) {
			throw new TicketbaiException('No se pudo leer el certificado o su clave privada');
		}

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->preserveWhiteSpace = true;

		if (!$doc->loadXML($xml)) {
			throw new TicketbaiException('El XML de la factura no es valido');
		}

		$root = $doc->documentElement;

		// Digest del documento sin la firma: es lo que da la transformacion enveloped-signature
		// una vez que ds:Signature quede como ultimo hijo de la raiz.
		$documentDigest = base64_encode(hash('sha256', $doc->C14N(false, false), true));

		$suffix = $this->uniqueSuffix();
		$signatureId = 'Signature-' . $suffix;
		$referenceId = 'Reference-' . $suffix;
		$signedPropertiesId = 'SignedProperties-' . $suffix;

		$signature = $doc->createElementNS(self::NS_DS, 'ds:Signature');
		$signature->setAttribute('Id', $signatureId);
		$root->appendChild($signature);

		$signedInfo = $this->dsEl($doc, $signature, 'ds:SignedInfo');
		$this->dsEl($doc, $signedInfo, 'ds:CanonicalizationMethod')->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
		$this->dsEl($doc, $signedInfo, 'ds:SignatureMethod')->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');

		$reference = $this->dsEl($doc, $signedInfo, 'ds:Reference');
		$reference->setAttribute('Id', $referenceId);
		$reference->setAttribute('URI', '');
		$transforms = $this->dsEl($doc, $reference, 'ds:Transforms');
		$this->dsEl($doc, $transforms, 'ds:Transform')->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
		$this->dsEl($doc, $reference, 'ds:DigestMethod')->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
		$this->dsEl($doc, $reference, 'ds:DigestValue', $documentDigest);

		$object = $this->dsEl($doc, $signature, 'ds:Object');

		$qualifying = $doc->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
		$qualifying->setAttribute('Target', '#' . $signatureId);
		$object->appendChild($qualifying);

		$signedProperties = $this->xadesEl($doc, $qualifying, 'xades:SignedProperties');
		$signedProperties->setAttribute('Id', $signedPropertiesId);

		$signatureProperties = $this->xadesEl($doc, $signedProperties, 'xades:SignedSignatureProperties');
		$this->xadesEl($doc, $signatureProperties, 'xades:SigningTime', date('c'));

		$signingCertificate = $this->xadesEl($doc, $signatureProperties, 'xades:SigningCertificate');
		$cert = $this->xadesEl($doc, $signingCertificate, 'xades:Cert');
		$certDigest = $this->xadesEl($doc, $cert, 'xades:CertDigest');
		$this->dsEl($doc, $certDigest, 'ds:DigestMethod')->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
		$this->dsEl($doc, $certDigest, 'ds:DigestValue', base64_encode(hash('sha256', $certDer, true)));
		$issuerSerial = $this->xadesEl($doc, $cert, 'xades:IssuerSerial');
		$this->dsEl($doc, $issuerSerial, 'ds:X509IssuerName', $this->formatIssuerDn($certInfo));
		$this->dsEl($doc, $issuerSerial, 'ds:X509SerialNumber', isset($certInfo['serialNumber']) ? (string)$certInfo['serialNumber'] : '0');

		$policy = self::$policies[$this->territory];

		$policyIdentifier = $this->xadesEl($doc, $signatureProperties, 'xades:SignaturePolicyIdentifier');
		$policyId = $this->xadesEl($doc, $policyIdentifier, 'xades:SignaturePolicyId');
		$sigPolicyId = $this->xadesEl($doc, $policyId, 'xades:SigPolicyId');
		$this->xadesEl($doc, $sigPolicyId, 'xades:Identifier', $policy[0]);
		$sigPolicyHash = $this->xadesEl($doc, $policyId, 'xades:SigPolicyHash');
		$this->dsEl($doc, $sigPolicyHash, 'ds:DigestMethod')->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
		$this->dsEl($doc, $sigPolicyHash, 'ds:DigestValue', $policy[1]);
		$qualifiers = $this->xadesEl($doc, $policyId, 'xades:SigPolicyQualifiers');
		$qualifier = $this->xadesEl($doc, $qualifiers, 'xades:SigPolicyQualifier');
		$this->xadesEl($doc, $qualifier, 'xades:SPURI', $policy[0]);

		$dataObjectProperties = $this->xadesEl($doc, $signedProperties, 'xades:SignedDataObjectProperties');
		$dataObjectFormat = $this->xadesEl($doc, $dataObjectProperties, 'xades:DataObjectFormat');
		$dataObjectFormat->setAttribute('ObjectReference', '#' . $referenceId);
		$this->xadesEl($doc, $dataObjectFormat, 'xades:MimeType', 'text/xml');

		// SignedProperties ya esta completo y en su sitio definitivo: se resume y se referencia.
		$propertiesReference = $this->dsEl($doc, $signedInfo, 'ds:Reference');
		$propertiesReference->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
		$propertiesReference->setAttribute('URI', '#' . $signedPropertiesId);
		// C14N explicito: es lo que se aplica por defecto, pero hay verificadores que sin
		// Transforms resumen el XML serializado tal cual y darian la firma por mala.
		$propertiesTransforms = $this->dsEl($doc, $propertiesReference, 'ds:Transforms');
		$this->dsEl($doc, $propertiesTransforms, 'ds:Transform')->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
		$this->dsEl($doc, $propertiesReference, 'ds:DigestMethod')->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
		$this->dsEl($doc, $propertiesReference, 'ds:DigestValue', base64_encode(hash('sha256', $signedProperties->C14N(false, false), true)));

		$signatureRaw = '';

		if (!openssl_sign($signedInfo->C14N(false, false), $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256)) {
			throw new TicketbaiException('No se pudo firmar la factura: ' . openssl_error_string());
		}

		$signatureValue = $doc->createElementNS(self::NS_DS, 'ds:SignatureValue', base64_encode($signatureRaw));
		$signature->insertBefore($signatureValue, $object);

		$keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
		$x509Data = $this->dsEl($doc, $keyInfo, 'ds:X509Data');
		$this->dsEl($doc, $x509Data, 'ds:X509Certificate', base64_encode($certDer));
		$signature->insertBefore($keyInfo, $object);

		return $doc->saveXML();
	}

	// SignatureValue completo de un XML firmado (base del identificador y del encadenamiento).
	public static function signatureValue($signedXml) {
		$doc = new DOMDocument();

		if (!@$doc->loadXML($signedXml)) {
			throw new TicketbaiException('XML firmado no valido');
		}

		$nodes = $doc->getElementsByTagNameNS(self::NS_DS, 'SignatureValue');

		if (!$nodes->length) {
			throw new TicketbaiException('El XML no esta firmado');
		}

		return preg_replace('/\s+/', '', $nodes->item(0)->textContent);
	}

	// TBAI-<NIF>-<ddmmaa>-<13 primeros caracteres de la firma>-<CRC-8>
	public static function identifier($issuerNif, $date, $signatureValue) {
		$parts = explode('-', $date);

		$code = 'TBAI-' . $issuerNif . '-' . $parts[0] . $parts[1] . substr($parts[2], -2) . '-' . substr($signatureValue, 0, 13) . '-';

		return $code . self::crc8($code);
	}

	public function qrUrl($identifier, $series, $number, $total) {
		$urls = self::$qrUrls[$this->territory];

		$url = $urls[$this->production ? 1 : 0] . '?id=' . rawurlencode($identifier) . '&s=' . rawurlencode($series) . '&nf=' . rawurlencode($number) . '&i=' . rawurlencode($total);

		return $url . '&cr=' . self::crc8($url);
	}

	// CRC-8/SMBUS (polinomio 0x07, sin reflexion), en 3 cifras.
	public static function crc8($data) {
		$crc = 0;
		$length = strlen($data);

		for ($i = 0; $i < $length; $i++) {
			$crc ^= ord($data[$i]);

			for ($bit = 0; $bit < 8; $bit++) {
				$crc = ($crc & 0x80) ? (($crc << 1) ^ 0x07) & 0xFF : ($crc << 1) & 0xFF;
			}
		}

		return str_pad((string)$crc, 3, '0', STR_PAD_LEFT);
	}

	/**
	 * Envia una factura firmada. En Bizkaia va dentro del LROE (modelo 240, o 140 si $lroe['self_employed']).
	 *
	 * $lroe (solo Bizkaia): array(nif, name, year, self_employed, epigraph)
	 *
	 * Devuelve array(http_code, delivered, errors => array of array(code, message), message, raw)
	 * Lanza TicketbaiException si no hay respuesta (error de conexion).
	 */
	public function submitInvoice($signedXml, $certificatePath, $certificatePassword, array $lroe = array()) {
		$url = self::$endpoints[$this->territory][$this->production ? 1 : 0];

		if ($this->territory == self::BIZKAIA) {
			list($body, $headers) = $this->bizkaiaRequest($signedXml, $lroe);
		} else {
			$body = $signedXml;
			$headers = array('Content-Type: application/xml;charset=UTF-8');
		}

		list($status, $responseHeaders, $content) = $this->post($url, $body, $headers, $certificatePath, $certificatePassword);

		if ($this->territory == self::BIZKAIA) {
			return $this->parseBizkaiaResponse($status, $responseHeaders, $content);
		}

		return $this->parseResponse($status, $content);
	}

	private function bizkaiaRequest($signedXml, array $lroe) {
		$selfEmployed = !empty($lroe['self_employed']);
		$model = $selfEmployed ? '140' : '240';

		$doc = new DOMDocument('1.0', 'UTF-8');

		if ($selfEmployed) {
			$root = $doc->createElementNS('https://www.batuz.eus/fitxategiak/batuz/LROE/esquemas/LROE_PF_140_1_1_Ingresos_ConfacturaConSG_AltaPeticion_V1_0_2.xsd', 'lrpficfcsgap:LROEPF140IngresosConFacturaConSGAltaPeticion');
		} else {
			$root = $doc->createElementNS('https://www.batuz.eus/fitxategiak/batuz/LROE/esquemas/LROE_PJ_240_1_1_FacturasEmitidas_ConSG_AltaPeticion_V1_0_2.xsd', 'lrpjfecsgap:LROEPJ240FacturasEmitidasConSGAltaPeticion');
		}

		$doc->appendChild($root);

		$header = $this->el($doc, $root, 'Cabecera');
		$this->el($doc, $header, 'Modelo', $model);
		$this->el($doc, $header, 'Capitulo', '1');
		$this->el($doc, $header, 'Subcapitulo', '1.1');
		$this->el($doc, $header, 'Operacion', 'A00');
		$this->el($doc, $header, 'Version', '1.0');
		$this->el($doc, $header, 'Ejercicio', $lroe['year']);
		$obliged = $this->el($doc, $header, 'ObligadoTributario');
		$this->el($doc, $obliged, 'NIF', $lroe['nif']);
		$this->el($doc, $obliged, 'ApellidosNombreRazonSocial', $lroe['name']);

		if ($selfEmployed) {
			$income = $this->el($doc, $this->el($doc, $root, 'Ingresos'), 'Ingreso');
			$this->el($doc, $income, 'TicketBai', base64_encode($signedXml));
			$detail = $this->el($doc, $this->el($doc, $income, 'Renta'), 'DetalleRenta');
			$this->el($doc, $detail, 'Epigrafe', $lroe['epigraph']);
			$this->el($doc, $detail, 'CriterioCobrosYPagos', 'N');
		} else {
			$invoice = $this->el($doc, $this->el($doc, $root, 'FacturasEmitidas'), 'FacturaEmitida');
			$this->el($doc, $invoice, 'TicketBai', base64_encode($signedXml));
		}

		$dataHeader = json_encode(array(
			'con'  => 'LROE',
			'apa'  => '1.1',
			'inte' => array('nif' => (string)$lroe['nif'], 'nrs' => (string)$lroe['name']),
			'drs'  => array('mode' => $model, 'ejer' => (string)$lroe['year'])
		));

		return array(gzencode($doc->saveXML()), array(
			'Accept-Encoding: gzip',
			'Content-Encoding: gzip',
			'Content-Type: application/octet-stream',
			'eus-bizkaia-n3-version: 1.0',
			'eus-bizkaia-n3-content-type: application/xml',
			'eus-bizkaia-n3-data: ' . $dataHeader
		));
	}

	private function post($url, $body, array $headers, $certificatePath, $certificatePassword) {
		$headers[] = 'Content-Length: ' . strlen($body);
		$headers[] = 'Expect:';

		$extension = strtolower(pathinfo($certificatePath, PATHINFO_EXTENSION));

		$options = array(
			CURLOPT_URL            => $url,
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => true,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_SSLCERTTYPE    => ($extension == 'p12' || $extension == 'pfx') ? 'P12' : 'PEM',
			CURLOPT_SSLCERT        => $certificatePath,
			CURLOPT_SSLCERTPASSWD  => (string)$certificatePassword
		);

		if ($this->caBundle !== '') {
			$options[CURLOPT_CAINFO] = $this->caBundle;
		}

		$attempt = 0;

		// Un reintento solo ante fallos de conexion (no ante respuestas de la Hacienda).
		while (true) {
			$attempt++;

			$ch = curl_init();
			curl_setopt_array($ch, $options);

			$response = curl_exec($ch);
			$errno = curl_errno($ch);
			$error = curl_error($ch);
			$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);

			curl_close($ch);

			if (!$errno && $response !== false) {
				break;
			}

			if ($attempt >= 2 || !in_array($errno, array(6, 7, 28, 35, 56))) {
				throw new TicketbaiException('Error de conexion (' . $errno . '): ' . $error);
			}

			sleep(2);
		}

		$responseHeaders = array();

		foreach (explode("\r\n", substr($response, 0, $headerSize)) as $line) {
			$pair = explode(':', $line, 2);

			if (count($pair) == 2) {
				$responseHeaders[strtolower(trim($pair[0]))] = trim($pair[1]);
			}
		}

		return array($status, $responseHeaders, (string)substr($response, $headerSize));
	}

	// Araba y Gipuzkoa: <Salida><Estado>00</Estado> = recibida; ResultadosValidacion = errores.
	public function parseResponse($status, $content) {
		$result = array('http_code' => $status, 'delivered' => false, 'errors' => array(), 'message' => '', 'raw' => $content);

		if ($status != 200) {
			$result['message'] = 'HTTP ' . $status;

			return $result;
		}

		$xml = @simplexml_load_string($content);

		if (!$xml || !isset($xml->Salida)) {
			$result['message'] = 'Respuesta no reconocida de la Hacienda Foral';

			return $result;
		}

		foreach ($xml->Salida->ResultadosValidacion as $validation) {
			$result['errors'][] = array('code' => (string)$validation->Codigo, 'message' => (string)$validation->Descripcion);
		}

		$result['delivered'] = ((string)$xml->Salida->Estado === '00') && !$result['errors'];

		if (isset($xml->Salida->Descripcion)) {
			$result['message'] = (string)$xml->Salida->Descripcion;
		}

		return $result;
	}

	// Bizkaia: cabecera eus-bizkaia-n3-tipo-respuesta y registros (cuerpo XML en gzip).
	public function parseBizkaiaResponse($status, array $headers, $content) {
		if ($content !== '' && substr($content, 0, 2) === "\x1f\x8b") {
			$decoded = @gzdecode($content);
			$content = ($decoded !== false) ? $decoded : $content;
		}

		$result = array('http_code' => $status, 'delivered' => false, 'errors' => array(), 'message' => '', 'raw' => $content);

		$type = isset($headers['eus-bizkaia-n3-tipo-respuesta']) ? $headers['eus-bizkaia-n3-tipo-respuesta'] : '';
		$result['message'] = isset($headers['eus-bizkaia-n3-mensaje-respuesta']) ? $headers['eus-bizkaia-n3-mensaje-respuesta'] : ($status != 200 ? 'HTTP ' . $status : '');

		if ($status != 200) {
			return $result;
		}

		$xml = $content !== '' ? @simplexml_load_string($content) : false;

		if ($xml && isset($xml->Registros)) {
			foreach ($xml->Registros->Registro as $record) {
				if ((string)$record->SituacionRegistro->CodigoErrorRegistro !== '') {
					$result['errors'][] = array('code' => (string)$record->SituacionRegistro->CodigoErrorRegistro, 'message' => (string)$record->SituacionRegistro->DescripcionErrorRegistroES);
				}
			}
		}

		$result['delivered'] = ($type !== '' && $type !== 'Incorrecto') && !$result['errors'];

		return $result;
	}

	private function subjectXml(DOMDocument $doc, DOMElement $parent, array $breakdown) {
		$subject = $this->el($doc, $parent, 'Sujeta');

		if (!empty($breakdown['exempt'])) {
			$exempt = $this->el($doc, $subject, 'Exenta');

			foreach ($breakdown['exempt'] as $item) {
				$detail = $this->el($doc, $exempt, 'DetalleExenta');
				$this->el($doc, $detail, 'CausaExencion', $item['reason']);
				$this->el($doc, $detail, 'BaseImponible', $item['base']);
			}
		}

		if (!empty($breakdown['not_exempt'])) {
			$notExempt = $this->el($doc, $subject, 'NoExenta');
			$detail = $this->el($doc, $notExempt, 'DetalleNoExenta');
			$this->el($doc, $detail, 'TipoNoExenta', 'S1');
			$vat = $this->el($doc, $detail, 'DesgloseIVA');

			foreach ($breakdown['not_exempt'] as $item) {
				$vatDetail = $this->el($doc, $vat, 'DetalleIVA');
				$this->el($doc, $vatDetail, 'BaseImponible', $item['base']);
				$this->el($doc, $vatDetail, 'TipoImpositivo', $item['rate']);
				$this->el($doc, $vatDetail, 'CuotaImpuesto', $item['quota']);
				$this->el($doc, $vatDetail, 'OperacionEnRecargoDeEquivalenciaORegimenSimplificado', 'N');
			}
		}
	}

	private function recipientXml(DOMDocument $doc, DOMElement $parent, array $recipient) {
		$el = $this->el($doc, $parent, 'IDDestinatario');

		$country = isset($recipient['country']) && $recipient['country'] !== '' ? strtoupper($recipient['country']) : 'ES';

		if ($country == 'ES') {
			$this->el($doc, $el, 'NIF', $recipient['nif']);
		} else {
			$idType = isset($recipient['id_type']) ? $recipient['id_type'] : '02';
			$id = $recipient['nif'];

			// El NIF-IVA lleva delante el prefijo VIES del pais (Grecia es EL, no GR).
			$vies = $country == 'GR' ? 'EL' : $country;

			if ($idType == '02' && substr($id, 0, 2) !== $vies) {
				$id = $vies . $id;
			}

			$other = $this->el($doc, $el, 'IDOtro');
			$this->el($doc, $other, 'CodigoPais', $country);
			$this->el($doc, $other, 'IDType', $idType);
			$this->el($doc, $other, 'ID', $id);
		}

		$this->el($doc, $el, 'ApellidosNombreRazonSocial', $recipient['name']);

		if (!empty($recipient['postcode'])) {
			$this->el($doc, $el, 'CodigoPostal', $recipient['postcode']);
		}

		if (!empty($recipient['address'])) {
			$this->el($doc, $el, 'Direccion', $recipient['address']);
		}
	}

	private function loadCertificate($path, $password) {
		if (!is_file($path)) {
			throw new TicketbaiException('No se encuentra el fichero del certificado');
		}

		$raw = file_get_contents($path);
		$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		if ($extension == 'p12' || $extension == 'pfx') {
			$certs = array();

			if (!openssl_pkcs12_read($raw, $certs, (string)$password)) {
				throw new TicketbaiException('No se pudo leer el certificado .p12/.pfx (revise la contrasena)');
			}

			return array($certs['cert'], $certs['pkey']);
		}

		$certificate = openssl_x509_read($raw);
		$key = openssl_pkey_get_private($raw, (string)$password);

		if (!$certificate || !$key) {
			throw new TicketbaiException('El certificado no incluye una clave privada utilizable (use un .p12/.pfx o un .pem con la clave)');
		}

		openssl_x509_export($certificate, $certPem);
		openssl_pkey_export($key, $keyPem);

		return array($certPem, $keyPem);
	}

	private function pemToDer($pem) {
		return base64_decode(preg_replace('/\s+/', '', preg_replace('/-----(BEGIN|END) CERTIFICATE-----/', '', $pem)));
	}

	private function formatIssuerDn($certInfo) {
		$parts = array();

		foreach ($certInfo['issuer'] as $key => $value) {
			foreach ((array)$value as $single) {
				$parts[] = $key . '=' . str_replace(array(',', '='), array('\,', '\='), $single);
			}
		}

		return implode(',', array_reverse($parts));
	}

	// Elemento sin espacio de nombres con texto escapado (createElement() no escapa '&').
	private function el(DOMDocument $doc, DOMElement $parent, $name, $text = null) {
		$el = $doc->createElement($name);

		if ($text !== null) {
			$el->appendChild($doc->createTextNode((string)$text));
		}

		$parent->appendChild($el);

		return $el;
	}

	private function dsEl(DOMDocument $doc, DOMElement $parent, $name, $text = null) {
		return $this->nsEl($doc, $parent, self::NS_DS, $name, $text);
	}

	private function xadesEl(DOMDocument $doc, DOMElement $parent, $name, $text = null) {
		return $this->nsEl($doc, $parent, self::NS_XADES, $name, $text);
	}

	private function nsEl(DOMDocument $doc, DOMElement $parent, $namespace, $name, $text) {
		$el = $doc->createElementNS($namespace, $name);

		if ($text !== null) {
			$el->appendChild($doc->createTextNode((string)$text));
		}

		$parent->appendChild($el);

		return $el;
	}

	private function uniqueSuffix() {
		return strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 12));
	}
}
