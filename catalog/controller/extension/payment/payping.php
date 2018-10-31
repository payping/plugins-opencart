<?php
/* ********************** Support And Update By OpenCartCms - https://opencartcms.org ***************************** */

class ControllerExtensionPaymentPayping extends Controller {
	public function index() {
		$this->load->language('extension/payment/payping');
		
		$data['text_connect'] = $this->language->get('text_connect');
		$data['text_loading'] = $this->language->get('text_loading');
		$data['text_wait'] = $this->language->get('text_wait');
		
    	$data['button_confirm'] = $this->language->get('button_confirm');

		return $this->load->view('extension/payment/payping', $data);
	}

	public function confirm() {
		$this->load->language('extension/payment/payping');
		
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		$amount = $this->correctAmount($order_info);
		
		$data['return'] = $this->url->link('checkout/success', '', true);
		$data['cancel_return'] = $this->url->link('checkout/payment', '', true);
		$data['back'] = $this->url->link('checkout/payment', '', true);
		

		$Amount = $amount;
		$Description = $this->language->get('text_order_no') . $order_info['order_id']; // Required
		$Email = isset($order_info['email']) ? $order_info['email'] : ''; 	// Optional
		$Mobile = isset($order_info['fax']) ? $order_info['fax'] : $order_info['telephone']; 	// Optional
		$data['order_id'] = $this->encryption->encrypt($this->config->get('config_encryption'), $this->session->data['order_id']);
		$CallbackURL = $this->url->link('extension/payment/payping/callback', 'order_id=' . $data['order_id'], true);  // Required



		$dataSend = array( 'Amount' => $Amount,'payerIdentity'=> $Email , 'returnUrl' => $CallbackURL, 'Description' => $Description , 'clientRefId' => $order_info['order_id']  );
		try {
			$curl = curl_init();
			curl_setopt_array($curl, array(CURLOPT_URL => "https://api.payping.ir/v1/pay", CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => json_encode($dataSend), CURLOPT_HTTPHEADER => array("accept: application/json", "authorization: Bearer " . $this->config->get('payment_payping_pin'), "cache-control: no-cache", "content-type: application/json"),));
			$response = curl_exec($curl);
			$header = curl_getinfo($curl);
			$err = curl_error($curl);
			curl_close($curl);
			if ($err) {
				$json['error']= "cURL Error #:" . $err;
			} else {
				if ($header['http_code'] == 200) {
					$response = json_decode($response, true);
					if (isset($response["code"]) and $response["code"] != '') {
						$data['action'] = sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"]);
						$json['success']= $data['action'];
					} else {
						$json['error'] =  ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع ';
					}
				} elseif ($header['http_code'] == 400) {
					$json['error'] = ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true))) ;
				} else {
					$json['error'] = ' تراکنش ناموفق بود- شرح خطا : ' . $this->checkState($header['http_code']) . '(' . $header['http_code'] . ')';
				}
			}
		} catch (Exception $e){
			$json['error'] = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function callback() {
		if ($this->session->data['payment_method']['code'] == 'payping') {
			$this->load->language('extension/payment/payping');

			$this->document->setTitle($this->language->get('text_title'));
			
			$data['heading_title'] = $this->language->get('text_title');
			$data['results'] = "";
			
			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'), 
				'href' => $this->url->link('common/home', '', true)
			);
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_title'), 
				'href' => $this->url->link('extension/payment/payping/callback', '', true)
			);

			try {

				if (isset($this->request->get['clientrefid'])) {
					$order_id = $this->encryption->decrypt($this->config->get('config_encryption'), $this->request->get['clientrefid']);
				} else {
					if (isset($this->session->data['order_id'])) {
						$order_id = $this->session->data['order_id'];
					} else {
						$order_id = 0;
					}
				}
	
				$this->load->model('checkout/order');
				$order_info = $this->model_checkout_order->getOrder($order_id);

				if (!$order_info)
					throw new Exception($this->language->get('error_order_id'));

				$authority = $this->request->get['refid'];
				$amount = $this->correctAmount($order_info);


				$data = array('refId' => $authority, 'amount' => $amount);

				$curl = curl_init();
				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($data),
					CURLOPT_HTTPHEADER => array(
						"accept: application/json",
						"authorization: Bearer ".$this->config->get('payment_payping_pin'),
						"cache-control: no-cache",
						"content-type: application/json",
					),
				));
				$response = curl_exec($curl);
				$err = curl_error($curl);
				$header = curl_getinfo($curl);
				curl_close($curl);
				if ($err) {
					throw new Exception('خطا در ارتباط به پی‌پینگ : شرح خطا '.$err);
				} else {
					if ($header['http_code'] == 200) {
						$response = json_decode($response, true);
						if (isset($_GET["refid"]) and $_GET["refid"] != '') {
							$comment = $this->language->get('text_results') . $authority;
							$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_payping_order_status_id'), $comment, true);
							$data['error_warning'] = NULL;
							$data['results'] = $authority;
							$data['button_continue'] = $this->language->get('button_complete');
							$data['continue'] = $this->url->link('checkout/success');
						} else {
							throw new Exception('متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . $this->checkState($header['http_code']) . '(' . $header['http_code'] . ')' );
						}
					} elseif ($header['http_code'] == 400) {
						throw new Exception('تراکنش ناموفق بود- شرح خطا : ' .  implode('. ',array_values (json_decode($response,true))) );
					}  else {
						throw new Exception(' تراکنش ناموفق بود- شرح خطا : ' . $this->checkState($header['http_code']) . '(' . $header['http_code'] . ')');
					}
				}

			} catch (Exception $e) {
				$data['error_warning'] = $e->getMessage();
				$data['button_continue'] = $this->language->get('button_view_cart');
				$data['continue'] = $this->url->link('checkout/cart');
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('extension/payment/payping_confirm', $data));
		}
	}

	private function correctAmount($order_info) {
		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		$amount = round($amount);
		$amount = $this->currency->convert($amount, $order_info['currency_code'], "TOM");
		return (int)$amount;
	}


	private function checkState($status) {
		$json = array();
		$json['error'] = $this->language->get('error_status_undefined');

		if ($this->language->get('error_status_' . $status) != 'error_status_' . $status ) {
			$json['error'] = $this->language->get('error_status_' . $status);
		}

		return $json;
	}
}
?>
