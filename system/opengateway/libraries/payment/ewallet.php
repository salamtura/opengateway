<?php

class ewallet
{
	var $settings;
	
	function offline() {
		$this->settings = $this->Settings();
	}

	function Settings()
	{
		$settings = array();
		
		$settings['name'] = 'NobiPay eWallet';
		$settings['class_name'] = 'ewallet';
		$settings['external'] = FALSE;
		$settings['no_credit_card'] = TRUE;
		$settings['description'] = 'Use OpenGateway to record offline payments with this gateway.  One-time charges are simply recorded in the system.  Subscription payments are assumed paid until the subscription is cancelled or expires.';
		$settings['is_preferred'] = 0;
		$settings['setup_fee'] = 'n/a';
		$settings['monthly_fee'] = 'n/a';
		$settings['transaction_fee'] = 'n/a';
		$settings['purchase_link'] = '';
		$settings['allows_updates'] = 1;
		$settings['allows_refunds'] = 1;
		$settings['requires_customer_information'] = 1;
		$settings['requires_customer_ip'] = 0;
		$settings['required_fields'] = array(
										'enabled',
										'trans_api'
										);
										
		$settings['field_details'] = array(
										'enabled' => array(
														'text' => 'Enable this gateway?',
														'type' => 'radio',
														'options' => array(
																		'1' => 'Enabled',
																		'0' => 'Disabled')
														),
										'trans_api' => array(
														'text' => 'Transaction Log API',
														'type' => 'text'
														)
											);
		
		return $settings;
	}
	
	function TestConnection ($client_id, $gateway) 
	{
		return TRUE;
	}
	
	function Charge ($client_id, $order_id, $gateway, $customer, $amount, $credit_card,$return_url, $cancel_url, $data)
	{	
		$CI =& get_instance();
		
		// log transaction with nobipay api
		$post_url = $gateway['trans_api'];

		$post = array();
		$post['id'] = $order_id;
		$post['amount'] = $amount;
		$post['responseCode'] = 1;
		$post['responseText'] = "Success";
		$post['payRef'] = "eWallet Payment";
		$post['customerId'] = $customer['id'];
		$post['description'] = $data['description'];
		$post['tranType'] = $data['transaction_type'];
		$post['paymentId'] = $data['payment_id'];
		$post['merchantId'] =  $data['merchant_id'];
		$post['invoiceId'] =  $data['invoice_id'];
		
		//$this->log_it('Charge post response : ',$post);

		$post_response = $this->ProcessJsonPost($post_url, $post);
		
		$this->log_it('eWallet Transaction response : ',$post_response);
		
		$response_array = array('charge_id' => $order_id);
		$response = $CI->response->TransactionResponse(1, $response_array);
		
		return $response;
	}
	
	function Recur ($client_id, $gateway, $customer, $amount, $charge_today, $start_date, $end_date, $interval, $credit_card, $subscription_id, $total_occurrences = FALSE)
	{		
		$CI =& get_instance();
		// if a payment is to be made today, process it.
		if ($charge_today === TRUE) {
			// Create an order for today's payment
			$CI->load->model('charge_model');
			$order_id = $CI->charge_model->CreateNewOrder($client_id, $gateway['gateway_id'], $amount, $credit_card, $subscription_id, $customer['customer_id'], $customer['ip_address']);
			
			$CI->charge_model->SetStatus($order_id, 1);
			$response_array = array('charge_id' => $order_id, 'recurring_id' => $subscription_id);
			$response = $CI->response->TransactionResponse(100, $response_array);
		}
		else {
			$response = $CI->response->TransactionResponse(100, array('recurring_id' => $subscription_id));
		}
		
		return $response;
	}
	
	function Refund ($client_id, $gateway, $charge, $authorization)
	{	
		return TRUE;
	}
	
	function CancelRecurring($client_id, $subscription)
	{	
		return TRUE;
	}
	
	function AutoRecurringCharge ($client_id, $order_id, $gateway, $params) {
		$response = array();
		$response['success'] = TRUE;

		return $response;
	}
	
	function UpdateRecurring()
	{
		return TRUE;
	}
	
	function ProcessJsonPost($url, $post_data){  
		$content = json_encode($post_data);
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER,
		        array("Content-type: application/json"));
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
		
		$json_response = curl_exec($curl);
		
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		// Throw an error if we can't continue. Will help in debugging.
		if (curl_error($curl))
		{
			show_error(curl_error($curl));
			$this->log_it('Call to API Error : ', $curl);
		}
		
		//if ( $status != 201 ) {
		//    die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
		//}
		
		
		curl_close($curl);
		
		$response = $this->response_to_array($json_response); //json_decode($json_response, true);	
		
		
		return $response;
	}
	
	private function response_to_array($string)
	{
		$string = urldecode($string);
		$pairs = explode('&', $string);
		$values = array();

		foreach($pairs as $pair)
		{
			list($key, $value) = explode('=', $pair);
			$values[$key] = $value;
		}

		return $values;
	}
	
	//--------------------------------------------------------------------

	/*
		Method: log_it()

		Logs the transaction to a file. Helpful with debugging callback
		transactions, since we can't actually see what's going on.

		Parameters:
			$heading	- A string to be placed above the resutls
			$params		- Typically an array to print_r out so that we can inspect it.
	*/
	public function log_it($heading, $params)
	{
		$file = FCPATH .'writeable/gateway_log.txt';

		$content .= "# $heading\n";
		$content .= date('Y-m-d H:i:s') ."\n\n";
		$content .= print_r($params, true);
		file_put_contents($file, $content, FILE_APPEND);
	}

	//--------------------------------------------------------------------
}