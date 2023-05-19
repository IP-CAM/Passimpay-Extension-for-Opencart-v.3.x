<?php 
class ModelExtensionPaymentPassimpay extends Model {
	public function getMethod($address, $total) {
		$title = $this->config->get('payment_passimpay_title');

		return array(
			'code' => 'passimpay',
			'terms' => '',
			'title' => ($title ? $title : 'PASSIMPAY'),
			'sort_order' => $this->config->get('payment_passimpay_sort_order')
		);
	}
}
?>