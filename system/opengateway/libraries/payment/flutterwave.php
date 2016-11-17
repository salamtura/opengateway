<?

class flutterwave{
	
	var $settings;
	
	function flutterwave(){
		$this->settings = $this->Settings();
	}
	
	
	function Settings(){
		
		$settings = array();

		$settings['name'] = 'Flutterwave Payments';
		$settings['class_name'] = 'flutterwave';
		$settings['external'] = TRUE;
		$settings['no_credit_card'] = FALSE;
		$settings['description'] = 'Flutterwave Payments Nigeria';
		$settings['is_preferred'] = 1;
		$settings['setup_fee'] = 'N0.00';
		$settings['monthly_fee'] = 'N0.00';
		$settings['transaction_fee'] = 'N20.00';
		$settings['purchase_link'] = 'http://www.flutterwave.com';
		$settings['allows_updates'] = 1;
		$settings['allows_refunds'] = 1;
		$settings['requires_customer_information'] = 0;
		$settings['requires_customer_ip'] = 0;
		$settings['required_fields'] = array(
										'enabled',
										'mode',
										'merchant_key',
										'api_key',
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
										'mode' => array(
														'text' => 'Mode',
														'type' => 'select',
														'options' => array(
																		'live' => 'Live Mode',
																		'test' => 'Test Mode',
																		'dev' => 'Development Server'
																		)
														),
										'merchant_key' => array(
														'text' => 'Merchant Key',
														'type' => 'text'
														),
										'api_key' => array(
														'text' => 'API Key',
														'type' => 'text'
														),
										'trans_api' => array(
														'text' => 'Transaction API',
														'type' => 'text'
														)
										
											);
		
		return $settings;
	}
	
	/*
		Method: Charge()

		Performs a one-time charge.

		Parameters:
			$client_id		- The ID of the OpenGateway client
			$order_id		- The internal OpenGateway order id.
			$gateway		- An array of gateway information
			$customer		- An array with customer information
			$amount			- The amount of the charge
			$credit_card	- An array of credit card information

		Returns:
			$response	- A TransactionResponse object.
	*/
	public function Charge($client_id, $order_id, $gateway, $customer, $amount, $credit_card, $return_url=null, $cancel_url=null, $data=array())
	{
		$CI =& get_instance();
		$CI->load->model('charge_model');
		$CI->load->model('charge_data_model');
		
		$CI->charge_data_model->Save($order_id, 'transaction_type', $data['transaction_type']);
		$CI->charge_data_model->Save($order_id, 'description', $data['description']);
		$CI->charge_data_model->Save($order_id, 'payment_id', $data['payment_id']);
		$CI->charge_data_model->Save($order_id, 'merchant_id', $data['merchant_id']);
		$CI->charge_data_model->Save($order_id, 'invoice_id', $data['invoice_id']);
		$CI->charge_data_model->Save($order_id, 'return_url', $return_url);

		$post_url = $this->GetAPIUrl($gateway).'card/mvva/pay';
		
		$key = $gateway['api_key'];
		
		$amount = number_format((float)$amount, 2, '.', '');
		$customer_id = $customer['id'];
		
		//$this->log_it('Data: ', $data);
		$post_values = array();
		$return_response = array();
		
		if($data['payment_type'] == "recur" && $data['payment_token'] != ""){
		
			$post_values = array(
	    		"amount" 	=> $this->encrypt($amount,$key),
			    "narration" 		=> $this->encrypt($data['description'],$key),
			    "currency" 		=> $this->encrypt("NGN",$key),
			    "chargetoken"	=> $this->encrypt($data['payment_token'],$key),
			    "merchantid" 	=> $gateway['merchant_key'],
			    "custid" 	=> $this->encrypt($customer['email'],$key)
			);
		}
		else if($data['payment_type'] == "onetime"){

			$post_values = array(
	    		"amount" 	=> $this->encrypt($amount,$key),
			    "narration" 		=> $this->encrypt($data['description'],$key),
			    "currency" 		=> $this->encrypt("NGN",$key),
			    "cardno" 		=> $this->encrypt($credit_card['card_num'],$key),
			    "pin" 			=> $this->encrypt($data['card_pin'],$key),
			    "expirymonth"		=> $this->encrypt($credit_card['exp_month'],$key),
			    "expiryyear"		=> $this->encrypt($credit_card['exp_year'],$key),
			    "cvv" 			=> $this->encrypt($credit_card['cvv'],$key),
			    "merchantid" 	=> $gateway['merchant_key'],
			    "custid" 	=> $this->encrypt($customer['email'],$key),
			    "authmodel"		=> $this->encrypt("PIN",$key)
			);
		}else{
			
			$response_data = array(
					'error' => "Invalid payment type or token"
				);
				
			return $CI->response->TransactionResponse(2, $response_data);
		}
		
		//$this->log_it('Request: ', $post_values);
		
		$response = $this->Process($order_id,$post_url,$post_values);
		
		
		
		if($response["status"] == 'success'){
			
			$CI->load->model('order_authorization_model');
			$CI->order_authorization_model->SaveAuthorization($order_id, $response['data']['transactionreference']);
			
			$response_code = $response['data']['responsecode'];
			
			if($response_code == '00'){
				$CI->charge_model->SetStatus($order_id, 1);
				$return_response = $CI->response->TransactionResponse(1, $response['data']);
				$return_response['charge_id'] = $order_id;
				$return_response['customer_id'] = $customer_id;
				
				$r = $this->log_tran($gateway['trans_api'],$order_id,$amount,$customer['id'],$data);
				
				$CI->charge_data_model->Save($order_id, 'responsetoken', $response['data']['responsetoken']);
				$CI->charge_data_model->Save($order_id, 'nobipay_log', $r);
				
			}else if($response_code == '02'){
				$CI->charge_model->SetStatus($order_id, 0);
				
				$return_response = $CI->response->TransactionResponse(1, $response['data']);
				$return_response['charge_id'] = $order_id;
				$return_response['customer_id'] = $customer_id;
				
				$CI->charge_data_model->Save($order_id, 'otptransactionidentifier', $response['data']['otptransactionidentifier']);
				
			}else{
				$CI->charge_model->SetStatus($order_id, 0);
				$return_response = $CI->response->TransactionResponse(2, $response['data']);
				$return_response['charge_id'] = $order_id;
				$return_response['customer_id'] = $customer_id;
			}
			
			//save order details
			$CI->charge_data_model->Save($order_id, 'responsecode', $response['data']['responsecode']);
			$CI->charge_data_model->Save($order_id, 'responsemessage', $response['data']['responsemessage']);
			$CI->charge_data_model->Save($order_id, 'transactionreference', $response['data']['transactionreference']);
			
		}else{
			$CI->charge_model->SetStatus($order_id, 0);
			
			if(is_array($response['data'])){
				$response_data = $response['data'];
			}else{
				$response_data = array(
					'error' => $response['data'],
					'charge_id' => $order_id 
				);
			}

			$return_response = $CI->response->TransactionResponse(2, $response_data);
			$return_response['charge_id'] = $order_id;
		}
		
		return $return_response;
	}
	
	function log_tran($post_url,$order_id,$amount,$customer_id,$data){
		
		
		$post = array();
		$post['Id'] = $order_id;
		$post['Amount'] = $amount;
		$post['ResponseCode'] = 1;
		$post['ResponseText'] = $data['description'];
		$post['TransactionReference'] = "txnRef";
		$post['CustomerId'] = $customer_id;
		$post['Description'] = $data['description'];
		$post['TransactionType'] = $data['transaction_type'];
		$post['PaymentId'] = isset($data['payment_id']) ? $data['payment_id'] : "";
		$post['MerchantId'] =  $data['merchant_id'];
		$post['InvoiceId'] =  isset($data['invoice_id']) ? $data['invoice_id'] : "";
		
		$response = $this->Process($order_id, $post_url, $post);
		
		return $response;
	}
	
	function Callback_validate ($client_id, $gateway, $charge, $params) {
		$CI =& get_instance();
		$CI->load->model('charge_data_model');
		$CI->load->model('charge_model');
		
		$data = $CI->charge_data_model->Get($charge['id']);
		
		$otp = $_POST["otp"];
		
		$order_id = $charge['id'];
		$amount = $charge['amount'];
		$key = $gateway['api_key'];
		
		$post_url = $this->GetAPIUrl($gateway).'card/mvva/pay/validate';
		
		$post_values = array(
				        "merchantid" => $gateway['merchant_key'],
				        "otp" => $this->encrypt($otp,$key),
				        "otptransactionidentifier" => $this->encrypt($data['otptransactionidentifier'],$key)
				);
				
		$return_response = array();
		
		if(isset($otp)){
			$response = $this->Process($order_id, $post_url, $post_values);
			
			$response_code = $response['data']['responsecode'];
			
			if($response['status'] == 'success'){
				
				if($response_code == '00'){
					$CI->charge_model->SetStatus($order_id, 1);
					$return_response = $CI->response->TransactionResponse(1, $response['data']);
					$return_response['charge_id'] = $order_id;
					$return_response['customer_id'] = $charge['customer']['id'];
					
					$CI->charge_data_model->Save($order_id, 'responsetoken', $response['data']['responsetoken']);
					$r = $this->log_tran($gateway['trans_api'],$order_id,$amount,$charge['customer']['id'],$data);
					$CI->charge_data_model->Save($order_id, 'nobipay_log', $r);
				}
			}else{
				$return_response = $CI->response->TransactionResponse(2, $response['data']);
			}
		}else{
			$return_response = $CI->response->TransactionResponse(2, $response['data']);
		}
		
		if(empty($return_response)){
			$return_response = array(
				"response_code" => "2",
				"response_text" => "Transaction Declined.",
				"responsecode" => "RR",
				"responsemessage" => "Order not found",
				"charge_id" => $order_id,
				'customer_id' => $charge['customer']['id']
				);
		}
		
		header('Content-Type: application/json');
		
		echo json_encode($return_response);
		
		//return $return_response;
	}
	
	function Process($order_id, $post_url, $post_values)
	{
		$CI =& get_instance();
		
		$data_string = json_encode($post_values); 
		
		$this->log_it('Flutterwave Request: ',$data_string);

		$request = curl_init($post_url); // initiate curl object
		curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
		curl_setopt($request, CURLOPT_VERBOSE, 1);
		curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);   // Verify it belongs to the server.
	    curl_setopt($request, CURLOPT_SSL_VERIFYHOST, FALSE);   // Check common exists and matches the server host name
		curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
		curl_setopt($request, CURLOPT_POSTFIELDS, $data_string); // use HTTP POST to send form data
		curl_setopt($request, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                                
		    'Content-Length: ' . strlen($data_string))                                                                       
		); 
		
		$post_response = curl_exec($request); // execute curl post and store results in $post_response
		//	echo '<pre>'; die(print_r($post_response));
		curl_close ($request); // close curl object
		
		//$this->log_it('Flutterwave Raw Response: ',$post_response);
		
		$response = json_decode($post_response,true);

		$this->log_it('Flutterwave Response: ',$response);
		
		return $response;
	}
	
	private function GetAPIURL ($gateway) {
		if ($gateway['mode'] == 'test') {
			return $gateway['url_test'];
		}
		else {
			return $gateway['url_live'];
		}
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
	
	public function log_it($heading, $params)
	{
		$file = FCPATH .'writeable/gateway_log.txt';
		
		$content = "\n# $heading\n";
		$content .= date('Y-m-d H:i:s') ."\n\n";
		$content .= print_r($params, true);
		file_put_contents($file, $content, FILE_APPEND);
	}
	
	public function encrypt($msg,$key){
		$CI =& get_instance();
		$CI->load->library('encrypt');
		$CI->encrypt->set_mode(MCRYPT_MODE_ECB);
		$CI->encrypt->set_key($key);
		$CI->encrypt->set_cipher(MCRYPT_3DES);
		
		$encrypted_string = $CI->encrypt->encryptText_3des($msg,$key);
		
		return $encrypted_string;
	}
	
	public function TestConnection(){
		return true;
	}

}