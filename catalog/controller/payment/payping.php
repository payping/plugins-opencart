<?php
namespace Opencart\Catalog\Controller\Extension\Payping\Payment;
class Payping extends \Opencart\System\Engine\Controller {
	public function index() {
		$this->load->language('extension/payping/payment/payping');
		
		$data['text_connect'] = $this->language->get('text_connect');
		$data['text_loading'] = $this->language->get('text_loading');
		$data['text_wait'] = $this->language->get('text_wait');
		
    	$data['button_confirm'] = $this->language->get('button_confirm');

		return $this->load->view('extension/payping/payment/payping', $data);
	}

	public function confirm() {
	    $this->load->model('checkout/order');
		$this->load->language('extension/payping/payment/payping');
		
		
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		
		
		//$amount = $this->correctAmount($order_info);
		$amount = (int)floor($order_info['total']);
		
		$data['return'] = $this->url->link('checkout/success', '', true);
		$data['cancel_return'] = $this->url->link('checkout/payment', '', true);
		$data['back'] = $this->url->link('checkout/payment', '', true);
		

		$Amount = $amount;
		$Description = $this->language->get('text_order_no') . $order_info['order_id'];
		$Email = isset($order_info['email']) ? $order_info['email'] : '';
		$Mobile = isset($order_info["fax"]) ? $order_info["fax"] : $order_info["telephone"]; 
		$PayerName = $order_info['firstname'] . ' ' . $order_info['lastname'];
		$NationalCode = '';
		$IsReversible = true; 
		$CallbackURL = $this->url->link('extension/payping/payment/payping|callback', '' , true);
        
		$dataSend = array( 
			'amount' => $Amount,
			'returnUrl' => $CallbackURL,
			'payerIdentity' => $Email,
			'payerName' => $PayerName, 
			'description' => $Description, 
			'clientRefId' => $order_info['order_id'],
			'nationalCode' => $NationalCode, 
			'isReversible' => $IsReversible 
		);
		
		try {
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.payping.ir/v3/pay", 
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_ENCODING => "", 
				CURLOPT_MAXREDIRS => 10, 
				CURLOPT_TIMEOUT => 30, 
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, 
				CURLOPT_CUSTOMREQUEST => "POST", 
				CURLOPT_POSTFIELDS => json_encode($dataSend), 
				CURLOPT_HTTPHEADER => array(
					"accept: application/json", 
					"authorization: Bearer " . $this->config->get('payment_payping_api_key'), 
					"cache-control: no-cache", 
					"content-type: application/json",
                    "X-Platform: OpenCart",
                    "X-Platform-Version: 2.0.0"
				),
			));
			$response = curl_exec($curl);
			
			$header = curl_getinfo($curl);
			$err = curl_error($curl);
			curl_close($curl);
			
			if ($err) {
				$json['error']= "cURL Error #:" . $err;
			} else {
				if ($header['http_code'] == 200) {
					$response = json_decode($response, true);
					
					if (isset($response["paymentCode"]) and $response["paymentCode"] != '') {
						$payment_code = $response["paymentCode"];
                        
						$this->session->data['payping_payment_code'] = $payment_code; 
                        
						$data['action'] = sprintf('https://api.payping.ir/v3/pay/start/%s', $payment_code);
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
		} catch (\Exception $e){ 
			$json['error'] = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function callback() {
        
        $post_status = isset($this->request->post['status']) ? (int)$this->request->post['status'] : -1;
        $post_data = [];
        if (isset($this->request->post['data'])) {
            $post_data = json_decode($this->request->post['data'], true);
        }
        $saved_payment_code = $this->session->data['payping_payment_code'];
        
		$is_payping_callback = (
            $this->request->server['REQUEST_METHOD'] == 'POST' && 
            ($post_status !== -1 || !empty($post_data))
        );

		if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] == 'payping' || $is_payping_callback) {


			if ($post_status === 0) {
				// User cancelled the payment
				$this->load->language('extension/payping/payment/payping');
				
                $this->document->setTitle($this->language->get('text_title'));
                $data['heading_title'] = $this->language->get('text_title');
                $data['error_warning'] = $this->language->get('text_user_cancelled');
                $data['button_continue'] = $this->language->get('button_continue');
                $data['continue'] = $this->url->link('checkout/cart', '', true); // هدایت به سبد خرید
                
                $data['column_left'] = $this->load->controller('common/column_left');
                $data['column_right'] = $this->load->controller('common/column_right');
                $data['content_top'] = $this->load->controller('common/content_top');
                $data['content_bottom'] = $this->load->controller('common/content_bottom');
                $data['footer'] = $this->load->controller('common/footer');
                $data['header'] = $this->load->controller('common/header');
				$this->response->setOutput($this->load->view('extension/payping/payment/payping_confirm', $data));
				
				// Clear the stored order_id from session after retrieval
				if (isset($this->session->data['order_id'])) {
					unset($this->session->data['order_id']);
				}
				
				return;
			}
            
			$this->load->language('extension/payping/payment/payping');

			$this->document->setTitle($this->language->get('text_title'));
			
			$data['heading_title'] = $this->language->get('text_title');
			$data['results'] = "";
			$data['error_warning'] = NULL;
			
			$data['breadcrumbs'] = array();
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'), 
				'href' => $this->url->link('common/home', '', true)
			);
			$data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_title'),
                'href' => $this->url->link('extension/payping/payment/payping|callback', '', true)
            );
            
            
			try {
                $order_id = 0;
				if (isset($post_data['clientRefId'])) {
					$order_id = $post_data['clientRefId'] ;
				} elseif (isset($this->request->get['clientrefid'])) { 
                    $order_id = $this->request->get['clientrefid'];
                } elseif (isset($this->session->data['order_id'])) {
					$order_id = $this->session->data['order_id'];
				} else {
                    throw new \Exception($this->language->get('error_order_id_not_found'));
				}
	
				$this->load->model('checkout/order');
				$order_info = $this->model_checkout_order->getOrder($order_id);

				if (!$order_info) {
					throw new \Exception($this->language->get('error_order_id'));
				}
				
				$data_raw = $this->request->post['data'] ?? '';
				$data_raw = html_entity_decode($data_raw, ENT_QUOTES, 'UTF-8');
				
				$response_data = json_decode($data_raw, true);
				
				$paymentCode_returned   = $response_data['paymentCode'] ?? null;
                $Transaction_ID         = $response_data['paymentRefId'] ?? null;
                $clientRefId_returned   = $response_data['clientRefId'] ?? null;
                
                
				
				// Clear the stored payment code from session after retrieval
				if (isset($this->session->data["payping_payment_code"])) {
					unset($this->session->data["payping_payment_code"]);
				}
				
				$authority = $Transaction_ID;
				//$amount_expected = $this->correctAmount($order_info);
				$amount_expected = (int)floor($order_info['total']);
				
				
				if (empty($authority)) {
                    $error_message = isset($this->request->post['errorCode']) ? $this->language->get('error_status_' . $this->request->post['errorCode']) : $this->language->get('error_transaction_failed');
					throw new \Exception($error_message);
				}

				if ($saved_payment_code != $paymentCode_returned) {
					throw new \Exception($this->language->get("error_payment_code_mismatch"));
				}
				
				if ($clientRefId_returned != $order_id) {
					throw new \Exception($this->language->get('error_client_ref_id'));
				}

				// --- مرحله اعتبارسنجی (Verify) ---
				
	            $data_verify = [
					'paymentRefId' => $Transaction_ID,
					'paymentCode'  => $paymentCode_returned,
					'amount'       => $amount_expected
				];
				$curl = curl_init();
				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.payping.ir/v3/pay/verify",
					CURLOPT_HTTPHEADER => array(
						"accept: application/json",
						"authorization: Bearer ".$this->config->get("payment_payping_api_key"),
						"cache-control: no-cache",
						"content-type: application/json"
						
					),
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($data_verify),

				));
				$response = curl_exec($curl);
				$err = curl_error($curl);
				$header = curl_getinfo($curl);
				curl_close($curl);
				
				if ($err) {
                    throw new \Exception('خطا در ارتباط با پی‌پینگ: '.$err);
                } else {
                    // --- حالت موفق (200) ---
                    if ($header['http_code'] == 200) {
                        $response = json_decode($response, true);
                
                        $amount_verified = isset($response['amount']) ? (int)$response['amount'] : 0;
                        if ($amount_verified != $amount_expected) {
                            throw new \Exception(sprintf($this->language->get('error_amount_mismatch'), number_format($amount_verified), number_format($amount_expected)));
                        }
                
                        // ✅ پرداخت موفق → تغییر وضعیت سفارش
                        $comment = sprintf(
                            "پرداخت موفق از طریق پی‌پینگ\nکد پرداخت: %s\nکد تراکنش: %s\nشماره کارت: %s",
                            $response['code'] ?? '',
                            $response['paymentRefId'] ?? '',
                            $response['cardNumber'] ?? ''
                        );
                
                        $this->model_checkout_order->addHistory(
                            (int)$order_id,
                            (int)$this->config->get('payment_payping_order_status_id'),
                            $comment,
                            true
                        );
                
                        $data['error_warning'] = NULL;
                        $data['results'] = $response['paymentRefId'];
                        $data['button_continue'] = $this->language->get('button_complete');
                        $data['continue'] = $this->url->link('checkout/success');
                
                    // --- حالت 409 (تراکنش تکراری اما موفق) ---
                    } elseif ($header['http_code'] == 409) {
                        $response_data = json_decode($response, true);
                        if (
                            isset($response_data['metaData']['code'])
                            && (int)$response_data['metaData']['code'] === 110
                            && isset($response_data['metaData']['message'])
                        ) {
                            $meta = $response_data['metaData']['message'];
                
                            $comment = sprintf(
                                "تراکنش تکراری اما موفق از طریق پی‌پینگ\nکد پرداخت: %s\nکد تراکنش: %s\nشماره کارت: %s",
                                $meta['Code'] ?? '',
                                $meta['PaymentRefId'] ?? '',
                                $meta['CardNumber'] ?? ''
                            );
                
                            // بررسی اینکه آیا سفارش قبلاً پرداخت‌شده نیست
                            if ($order_info['order_status_id'] != $this->config->get('payment_payping_order_status_id')) {
                                $this->model_checkout_order->addHistory(
                                    (int)$order_id,
                                    (int)$this->config->get('payment_payping_order_status_id'),
                                    $comment,
                                    true 
                                );
                            }
                
                            $data['error_warning'] = NULL;
                            $data['results'] = $meta['PaymentRefId'] ?? '';
                            $data['button_continue'] = $this->language->get('button_complete');
                            $data['continue'] = $this->url->link('checkout/success');
                
                        } else {
                            throw new \Exception('تراکنش تکراری نامشخص یا نامعتبر است.');
                        }
                
                    // --- سایر وضعیت‌ها ---
                    } elseif ($header['http_code'] == 400) {
                        $error_details = json_decode($response, true);
                        $error_message = is_array($error_details)
                            ? implode('. ', array_values($error_details))
                            : 'خطای نامشخص در هنگام وریفای.';
                        throw new \Exception('تراکنش ناموفق بود - شرح خطا: ' . $error_message);
                
                    } else {
                        throw new \Exception(
                            'تراکنش ناموفق بود - شرح خطا: ' .
                            $this->checkState($header['http_code']) . ' (' . $header['http_code'] . ')'
                        );
                    }
                }


			} catch (\Exception $e) {
				$data['error_warning'] = $e->getMessage();
				
				$data['button_continue'] = $this->language->get('button_continue');
				$data['continue'] = $this->url->link('common/home', '', true);
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('extension/payping/payment/payping_confirm', $data));
		
		} else {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
		}
	}
    /* در اپن کارت فارسی نیاز به این تابع نیست */
	/*private function correctAmount($order_info) {
        $total = (float)$order_info['total'];
        $currency_code = strtoupper($order_info['currency_code']);
        if ($currency_code === 'IRR') {
            $total = $total / 10;
        }
        
        return (int)floor($total);
    }*/




	private function checkState($status) {
		$json = $this->language->get('error_status_undefined');

		if ($this->language->get('error_status_' . $status) != 'error_status_' . $status ) {
			$json = $this->language->get('error_status_' . $status);
		}

		return $json;
	}
}
?>