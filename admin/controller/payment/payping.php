<?php
namespace Opencart\Admin\Controller\Extension\Payping\Payment;

class Payping extends \Opencart\System\Engine\Controller {
    // 1. تعریف متغیر خطا
    private array $error = [];

	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('extension/payping/payment/payping');

		$this->document->setTitle($this->language->get('heading_title'));
        
        // --- مدیریت پیام‌ها و خطاها ---
        
        // نمایش پیام موفقیت (پس از ذخیره موفقیت آمیز)
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // نمایش خطاهای اعتبارسنجی
		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_api_key'] = $this->error['api_key'] ?? '';
		// --------------------------------

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payping/payment/payping', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/payping/payment/payping.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');


        // PayPing Token
		if (isset($this->request->post['payment_payping_api_key'])) {
			$data['payment_payping_api_key'] = $this->request->post['payment_payping_api_key'];
		} else {
			$data['payment_payping_api_key'] = $this->config->get('payment_payping_api_key');
		}
        
        // Order Status ID
        if (isset($this->request->post['payment_payping_order_status_id'])) {
			$data['payment_payping_order_status_id'] = $this->request->post['payment_payping_order_status_id'];
		} else {
			$data['payment_payping_order_status_id'] = $this->config->get('payment_payping_order_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Status (Enabled/Disabled)
        if (isset($this->request->post['payment_payping_status'])) {
            $data['payment_payping_status'] = $this->request->post['payment_payping_status'];
        } else {
            $data['payment_payping_status'] = $this->config->get('payment_payping_status');
        }
        
        // Sort Order
        if (isset($this->request->post['payment_payping_sort_order'])) {
            $data['payment_payping_sort_order'] = $this->request->post['payment_payping_sort_order'];
        } else {
            $data['payment_payping_sort_order'] = $this->config->get('payment_payping_sort_order');
        }
        
        
        // ----------------------------------------------------

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payping/payment/payping', $data));
	}

	/**
	 * Save
	 *
	 * @return void
	 */
    public function save(): void {
		$this->load->language('extension/payping/payment/payping');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/payping/payment/payping')) {
			$json['error'] = $this->language->get('error_permission');
		}
		if (!$this->validate()) {
            $json['error'] = $this->error;
        }

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_payping', $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
    
    /**
	 * Validate
	 *
	 * @return bool
	 */
	private function validate(): bool {
        
		if (!$this->user->hasPermission('modify', 'extension/payping/payment/payping')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!trim($this->request->post['payment_payping_api_key'])) {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}
        
		return !$this->error;
	}

	/**
	 * Install
	 *
	 * @return void
	 */
	public function install(): void {
		if ($this->user->hasPermission('modify', 'extension/payment')) {
			$this->load->model('extension/payping/payment/payping');
			$this->model_extension_payping_payment_payping->install();
		}
		
	}

	/**
	 * Uninstall
	 *
	 * @return void
	 */
	public function uninstall(): void {
		if ($this->user->hasPermission('modify', 'extension/payment')) {
			$this->load->model('extension/payping/payment/payping');
			$this->model_extension_payping_payment_payping->uninstall();
		}
	}
}