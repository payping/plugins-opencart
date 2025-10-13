<?php 
namespace Opencart\Catalog\Model\Extension\Payping\Payment; 

class Payping extends \Opencart\System\Engine\Model {
  	
  	public function getMethods(array $address): array {
        
		$this->load->language('extension/payping/payment/payping'); 

		if (!$this->config->get('payment_payping_status')) {
      		$status = false;
      	} else {
			$status = true;
		}

		$method_data = [];
		
		if ($status) {
      		$method_data = [
                'code'       => 'payping',
                'name'       => $this->language->get('text_title'),
                'option'     => [
                    'payping' => [
                        'code' => 'payping.payping',
                        'name' => $this->language->get('text_title')
                    ]
                ],
                'sort_order' => (int)$this->config->get('payment_payping_sort_order'),
                'terms'      => ''
            ];
    	}
		
    	return $method_data;
  	}
}