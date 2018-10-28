<?php 
class ModelExtensionPaymentPayping extends Model {
  	public function getMethod($address) {
		$this->load->language('extension/payment/payping');

		if ($this->config->get('payment_payping_status')) {
      		$status = true;
      	} else {
			$status = false;
		}

		$method_data = array();
		
		if ($status) {
      		$method_data = array( 
        		'code'       => 'payping',
        		'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_payping_sort_order')
      		);
    	}
		
    	return $method_data;
  	}
}
?>
