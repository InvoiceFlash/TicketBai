<?php
class ControllerTicketbaiTicketbai extends Controller {
	private $error = array();

	private $setting_keys = array(
		'ticketbai_active',
		'ticketbai_territory',
		'ticketbai_environment',
		'ticketbai_license',
		'ticketbai_developer_nif',
		'ticketbai_self_employed',
		'ticketbai_epigraph',
		'ticketbai_exempt_reason',
		'ticketbai_foreign_operation',
		'ticketbai_ca_bundle'
	);

	// Listado de facturas firmadas y su estado en la Hacienda Foral.
	public function index() {
		$this->language->load('ticketbai/ticketbai');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('ticketbai/ticketbai');

		$filter_status = isset($this->request->get['filter_status']) ? (string)$this->request->get['filter_status'] : '';
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = (int)$this->config->get('config_admin_limit') ? (int)$this->config->get('config_admin_limit') : 20;

		$this->data['heading_title'] = $this->language->get('heading_title');

		foreach (array('text_no_results', 'text_all', 'text_confirm_resend', 'text_active_note', 'column_invoice', 'column_type', 'column_customer', 'column_date', 'column_total', 'column_identifier', 'column_environment', 'column_status', 'column_message', 'column_action', 'entry_status', 'button_setting', 'button_resend', 'button_xml', 'button_filter') as $key) {
			$this->data[$key] = $this->language->get($key);
		}

		$this->data['statuses'] = array(
			ModelTicketbaiTicketbai::STATUS_SIGNED   => $this->language->get('text_status_signed'),
			ModelTicketbaiTicketbai::STATUS_SENT     => $this->language->get('text_status_sent'),
			ModelTicketbaiTicketbai::STATUS_REJECTED => $this->language->get('text_status_rejected'),
			ModelTicketbaiTicketbai::STATUS_ERROR    => $this->language->get('text_status_error')
		);

		$this->data['filter_status'] = $filter_status;
		$this->data['can_modify'] = $this->user->hasPermission('modify', 'ticketbai/ticketbai');

		$this->data['error_warning'] = '';
		$this->data['warnings'] = array();

		$requirements = $this->model_ticketbai_ticketbai->requirementsError();

		if ($requirements) {
			$this->data['warnings'][] = $requirements;
		}

		if ($this->model_ticketbai_ticketbai->isActive()) {
			$configuration = $this->model_ticketbai_ticketbai->configurationError();

			if ($configuration) {
				$this->data['warnings'][] = $configuration;
			}
		}

		$this->data['active'] = $this->model_ticketbai_ticketbai->isActive();

		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}

		$this->data['breadcrumbs'] = $this->getBreadcrumbs();

		$this->data['setting'] = $this->url->link('ticketbai/ticketbai/setting', 'token=' . $this->session->data['token'], 'SSL');
		$this->data['filter_action'] = str_replace('&amp;', '&', $this->url->link('ticketbai/ticketbai', 'token=' . $this->session->data['token'], 'SSL'));
		$this->data['resend_url'] = str_replace('&amp;', '&', $this->url->link('ticketbai/ticketbai/resend', 'token=' . $this->session->data['token'], 'SSL'));

		$filter = array(
			'filter_status' => $filter_status,
			'start'         => ($page - 1) * $limit,
			'limit'         => $limit
		);

		$territories = $this->getTerritories();

		$this->data['records'] = array();

		foreach ($this->model_ticketbai_ticketbai->getRecords($filter) as $record) {
			$customer = $record['payment_company'] ? $record['payment_company'] : trim($record['firstname'] . ' ' . $record['lastname']);

			$this->data['records'][] = array(
				'invoice_id'  => $record['invoice_id'],
				'number'      => ($record['series'] !== '' ? $record['series'] . ' ' : '') . $record['number'],
				'type'        => $this->language->get($record['type'] == 'rectifying' ? 'text_rectifying' : 'text_invoice'),
				'customer'    => $customer,
				'date'        => date($this->language->get('date_format_short'), strtotime($record['expedition_date'])),
				'total'       => $this->currency->format($record['total'], $this->config->get('config_currency'), '', true, true),
				'identifier'  => $record['identifier'],
				'environment' => (isset($territories[$record['territory']]) ? $territories[$record['territory']] : $record['territory']) . ' - ' . $this->language->get($record['production'] ? 'text_production' : 'text_test'),
				'status'      => $record['status'],
				'status_text' => isset($this->data['statuses'][$record['status']]) ? $this->data['statuses'][$record['status']] : $record['status'],
				'message'     => nl2br($record['message']),
				'can_resend'  => $record['status'] != ModelTicketbaiTicketbai::STATUS_SENT,
				'invoice'     => $this->url->link('sale/invoice/info', 'token=' . $this->session->data['token'] . '&invoice_id=' . $record['invoice_id'], 'SSL'),
				'xml'         => $this->url->link('ticketbai/ticketbai/xml', 'token=' . $this->session->data['token'] . '&invoice_id=' . $record['invoice_id'], 'SSL')
			);
		}

		$pagination = new Pagination();
		$pagination->total = $this->model_ticketbai_ticketbai->getTotalRecords($filter);
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link('ticketbai/ticketbai', 'token=' . $this->session->data['token'] . ($filter_status !== '' ? '&filter_status=' . urlencode($filter_status) : '') . '&page={page}', 'SSL');

		$this->data['pagination'] = $pagination->render();

		$this->template = 'ticketbai/ticketbai_list.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);

		$this->response->setOutput($this->render());
	}

	public function setting() {
		$this->language->load('ticketbai/ticketbai');

		$this->document->setTitle($this->language->get('heading_setting'));

		$this->load->model('setting/setting');
		$this->load->model('ticketbai/ticketbai');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateSetting()) {
			$data = array();

			foreach ($this->setting_keys as $key) {
				$data[$key] = isset($this->request->post[$key]) ? trim((string)$this->request->post[$key]) : '';
			}

			$data['ticketbai_developer_nif'] = strtoupper($data['ticketbai_developer_nif']);

			$this->model_setting_setting->editSetting('ticketbai', $data);

			$this->session->data['success'] = $this->language->get('text_success_setting');

			$this->redirect($this->url->link('ticketbai/ticketbai', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$this->data['heading_title'] = $this->language->get('heading_setting');

		foreach (array('text_yes', 'text_no', 'text_araba', 'text_bizkaia', 'text_gipuzkoa', 'text_test', 'text_production', 'text_active_note', 'text_certificate_note', 'text_ca_bundle_help', 'entry_ca_bundle', 'text_license_note', 'text_requirements_ok', 'entry_active', 'entry_territory', 'entry_environment', 'entry_license', 'entry_developer_nif', 'entry_self_employed', 'entry_epigraph', 'entry_exempt_reason', 'entry_foreign_operation', 'text_delivery', 'text_services', 'button_save', 'button_cancel') as $key) {
			$this->data[$key] = $this->language->get($key);
		}

		$this->data['exempt_reasons'] = array(
			'E1' => 'E1 - Art. 20 (Norma Foral del IVA)',
			'E2' => 'E2 - Art. 21 (exportaciones)',
			'E3' => 'E3 - Art. 22 (operaciones asimiladas a exportaciones)',
			'E4' => 'E4 - Art. 24 (reg&iacute;menes aduaneros y fiscales)',
			'E5' => 'E5 - Art. 25 (entregas intracomunitarias)',
			'E6' => 'E6 - Otras'
		);

		$this->data['requirements'] = $this->model_ticketbai_ticketbai->requirementsError();

		$this->data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$this->data['error_license'] = isset($this->error['license']) ? $this->error['license'] : '';
		$this->data['error_developer_nif'] = isset($this->error['developer_nif']) ? $this->error['developer_nif'] : '';

		$this->data['breadcrumbs'] = $this->getBreadcrumbs();
		$this->data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_setting'),
			'href'      => $this->url->link('ticketbai/ticketbai/setting', 'token=' . $this->session->data['token'], 'SSL'),
			'separator' => ' :: '
		);

		$this->data['action'] = $this->url->link('ticketbai/ticketbai/setting', 'token=' . $this->session->data['token'], 'SSL');
		$this->data['cancel'] = $this->url->link('ticketbai/ticketbai', 'token=' . $this->session->data['token'], 'SSL');

		$defaults = array(
			'ticketbai_environment'   => 'test',
			'ticketbai_exempt_reason'     => 'E1',
			'ticketbai_foreign_operation' => 'services'
		);

		foreach ($this->setting_keys as $key) {
			if (isset($this->request->post[$key])) {
				$this->data[$key] = $this->request->post[$key];
			} elseif ($this->config->get($key) !== null) {
				$this->data[$key] = $this->config->get($key);
			} else {
				$this->data[$key] = isset($defaults[$key]) ? $defaults[$key] : '';
			}
		}

		$this->template = 'ticketbai/ticketbai_setting.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);

		$this->response->setOutput($this->render());
	}

	public function resend() {
		$this->language->load('ticketbai/ticketbai');

		$json = array();

		if (!$this->user->hasPermission('modify', 'ticketbai/ticketbai')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('ticketbai/ticketbai');

			$invoice_id = isset($this->request->post['invoice_id']) ? (int)$this->request->post['invoice_id'] : 0;

			$result = $this->model_ticketbai_ticketbai->resend($invoice_id);

			if ($result['success']) {
				$json['success'] = $result['message'];
			} else {
				$json['error'] = $result['message'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// Boton "Reenviar a TicketBAI" de la pestana TicketBAI de la ficha de factura (sale/invoice).
	// A diferencia de resend(), si la factura aun no se firmo (TicketBAI se activo despues, o
	// fallo antes de firmar) la firma y la envia.
	public function invoiceResend() {
		$this->load->language('sale/invoice');
		$this->language->load('ticketbai/ticketbai');

		$json = array();

		if (!$this->user->hasPermission('modify', 'sale/invoice')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$invoice_id = isset($this->request->get['invoice_id']) ? (int)$this->request->get['invoice_id'] : 0;

			$this->load->model('ticketbai/ticketbai');

			if (!$this->model_ticketbai_ticketbai->isActive()) {
				$result = array('success' => false, 'message' => $this->language->get('error_territory'));
			} else {
				$result = $this->model_ticketbai_ticketbai->sendInvoice($invoice_id);
			}

			$json['success'] = $result['success'];
			$json['message'] = $result['message'];

			$json = array_merge($json, $this->model_ticketbai_ticketbai->getInfo($invoice_id));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	// Descarga del XML firmado tal como se envio (o se enviara) a la Hacienda Foral.
	public function xml() {
		$this->load->model('ticketbai/ticketbai');

		$invoice_id = isset($this->request->get['invoice_id']) ? (int)$this->request->get['invoice_id'] : 0;

		$record = $this->user->hasPermission('access', 'ticketbai/ticketbai') ? $this->model_ticketbai_ticketbai->getRecord($invoice_id) : array();

		if (!$record) {
			$this->redirect($this->url->link('ticketbai/ticketbai', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$filename = 'ticketbai_' . preg_replace('/[^A-Za-z0-9_-]/', '', $record['series'] . $record['number']) . '.xml';

		$this->response->addHeader('Content-Type: application/xml; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput($record['signed_xml']);
	}

	private function validateSetting() {
		if (!$this->user->hasPermission('modify', 'ticketbai/ticketbai')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$license = isset($this->request->post['ticketbai_license']) ? trim((string)$this->request->post['ticketbai_license']) : '';
		$developer_nif = isset($this->request->post['ticketbai_developer_nif']) ? trim((string)$this->request->post['ticketbai_developer_nif']) : '';

		if (utf8_strlen($license) > 20) {
			$this->error['license'] = $this->language->get('error_license_format');
		}

		if ($developer_nif !== '' && !preg_match('/^[A-Za-z0-9]{9}$/', $developer_nif)) {
			$this->error['developer_nif'] = $this->language->get('error_developer_nif_format');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	private function getTerritories() {
		return array(
			'01' => $this->language->get('text_araba'),
			'02' => $this->language->get('text_bizkaia'),
			'03' => $this->language->get('text_gipuzkoa')
		);
	}

	private function getBreadcrumbs() {
		return array(
			array(
				'text'      => $this->language->get('text_home'),
				'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => false
			),
			array(
				'text'      => $this->language->get('heading_title'),
				'href'      => $this->url->link('ticketbai/ticketbai', 'token=' . $this->session->data['token'], 'SSL'),
				'separator' => ' :: '
			)
		);
	}
}
