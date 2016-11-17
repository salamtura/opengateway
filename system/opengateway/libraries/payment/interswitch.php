<?php
class interswitch
{
	var $settings;
	
	function interswitch(){
		$this->settings = $this->Settings();
	}
	
	function Settings()
	{
		$settings = array();
		
		$settings['name'] = 'Interswtich Payment Gateway';
		$settings['class_name'] = 'interswitch';
		$settings['external'] = TRUE;
		$settings['no_credit_card'] = TRUE;
		$settings['description'] = 'Accept Nigeria cards with interswtich.Merchant account with interswtich is required';
		$settings['is_preferred'] = 1;
		$settings['setup_fee'] = '$0';
		$settings['monthly_fee'] = '$0';
		$settings['transaction_fee'] = '1.5%';
		$settings['purchase_link'] = 'https://www.interswitchng.com/';
		$settings['allows_updates'] = 0;
		$settings['allows_refunds'] = 0;
		$settings['requires_customer_information'] = 0;
		$settings['requires_customer_ip'] = 0;
		$settings['required_fields'] = array('enabled',
											 'mode',
											 'cust_name',
											 'product_id',
											 'pay_item_id',
											 'site_name',
											 'site_redirect_url',
											 'trans_api',
											 'currency',
											 'MAC_key'
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
																		'test' => 'Sandbox'
																		)
														),
										'cust_name' => array(
														'text' => 'Organization',
														'type' => 'text'
														),
										'product_id' => array(
														'text' => 'Product ID',
														'type' => 'text'
														),
										'pay_item_id' => array(
														'text' => 'Pay Item ID',
														'type' => 'text',
														),
										'site_name' => array(
														'text' => 'Website Name',
														'type' => 'text'
														),
										'site_redirect_url' => array(
														'text' => 'Site Redirect URL',
														'type' => 'text'
														),
										'trans_api' => array(
														'text' => 'Transaction Log API',
														'type' => 'text'
														),
										'currency' => array(
														'text' => 'Currency',
														'type' => 'select',
														'options' => array(
																		'566' => 'Naira'
																	)
														),
										'MAC_key' => array(
														'text' => 'MAC Key',
														'type' => 'text'
														)
											);
		return $settings;
	}
	
	function Charge($client_id, $order_id, $gateway, $customer, $amount, $credit_card, $return_url, $cancel_url,$data)
	{
		$CI =& get_instance();
		$CI->load->model('charge_data_model');
		$CI->load->helper('url');
		//$CI->load->model('client_model');
		
		$client = $CI->client_model->GetClient($client_id,$client_id); //get client details	
		$CI->charge_data_model->Save($order_id, 'transaction_type', $data['transaction_type']);
		$CI->charge_data_model->Save($order_id, 'description', $data['description']);
		$CI->charge_data_model->Save($order_id, 'payment_id', $data['payment_id']);
		$CI->charge_data_model->Save($order_id, 'merchant_id', $data['merchant_id']);
		$CI->charge_data_model->Save($order_id, 'invoice_id', $data['invoice_id']);
		$CI->charge_data_model->Save($order_id, 'return_url', $return_url);
		
		//$this->log_it('order data : ',$data);
		
		$interswitch_args_array = array();
		
		$post_url = $this->GetAPIURL($gateway);
		
		$txnref = $order_id;
		if($gateway['mode'] == 'test'){
			$txnref = $order_id.'-'.$order_id.'-'.$order_id;
		}
		
		//make hash
		$product_id = $gateway['product_id'];
		$pay_item_id = $gateway['pay_item_id'];
		$amount = (int)$amount*100;
		$site_url = 'https://opengateway.nobipay.com/billing/callback/interswitch/confirm/' . $order_id;//$gateway['site_redirect_url'];
		$mac_key = '72B00C7ECC8FC12DB3E81D0401F3B617B9672E124D96A2DF320401BF8435CB5A784D104FD31100209D9BD913DFD16F160ABBC04B30258B7419EA30729039F649';//$gateway['MAC_key'];		
		$hash = $txnref.$product_id.$pay_item_id.$amount.$site_url.$mac_key; //sha512 hash
		$hash = hash('sha512', $hash);
		
		
		
		$interswitch_args = array(
				'product_id' 		=> $gateway['product_id'],
				'amount' 			=> $amount,
				'site_name' 		=> $gateway['site_name'],
				'currency' 			=> $gateway['currency'],
				'pay_item_id'		=> $gateway['pay_item_id'],
				'hash'				=> $hash,
				'txn_ref'			=> $txnref,
				'site_redirect_url'	=> $site_url,//$gateway['site_redirect_url'],
				'cust_name'			=> $gateway['cust_name'],
				'cust_id'			=> $customer['email']
			);
			
		foreach ($interswitch_args as $key => $value) {
			$interswitch_args_array[] = '<input type="hidden" name="' . $key. '" value="' . $value . '" />';
		}
		
		$form = '<form action="'.$post_url .'" method="post" id="interswitch_payment_form" name="interswitch_payment_form">
					' . implode('', $interswitch_args_array) . '
				</form>';
		
		$response_array = array(
							'not_completed' => TRUE, // don't mark charge as complete
							'charge_id' => $order_id,
							'form' => $form
						);
			
		$response = $CI->response->TransactionResponse(1, $response_array);
		
		return $response;
	}
	
	function Callback_confirm ($client_id, $gateway, $charge, $params) {
		
		$CI =& get_instance();
		
		$this->log_it('interswitch Response POST : ',$_POST);
		
		$txnref  = $_POST["txnref"];
		$payref  = $_POST["payRef"];
		$retref  = $_POST["retRef"];
		$cardnum = $_POST["cardNum"];
		$appramt = $_POST["apprAmt"];
		$resp    = $_POST["resp"];
		$desc 	 = $_POST["desc"];	
		
		//https://stageserv.interswitchng.com/test_paydirect/Pay/ResponsePost/?
		/*
		resp=Z6&
		desc=Customer%20cancellation&
		txnRef=1397-1397-1397&
		payRef=&
		retRef=&
		cardNum=0&
		apprAmt=0&
		url=https%3a%2f%2fopengateway.nobipay.com%2fbilling%2fcallback%2finterswitch%2fconfirm%2f1397
		
		
		https://stageserv.interswitchng.com/test_paydirect/Pay/ResponsePost/?
		txnRef=1400-1400-1400&
		payRef=FBP|WEB|Goldilocks|15-06-2014|000648&
		retRef=000000000733&
		cardNum=0&
		apprAmt=0&
		url=https%3a%2f%2fopengateway.nobipay.com%2fbilling%2fcallback%2finterswitch%2fconfirm%2f1400
		*/
			
		$order_id = $charge['id'];
		$amount = (int)$charge["amount"]*100;// convert to kobo because of dumb interswitch
		
		$resp_codes = array("00","51","57","61","81","A0","Z0","Z1","Z2","Z6");
		$resp_other = array("Z4","Z5","Z7");
		//get transaction status
		$response = $this->get_transaction_status($txnref,$amount,$gateway);
		
		if($resp == ""){
			// save authorization (transaction id #)
			$CI->load->model('order_authorization_model');
			if($payref == ""){
				$CI->order_authorization_model->SaveAuthorization($charge['id'], $response["ResponseCode"]." - NOT AUTHORIZED");
			}else{
				$CI->order_authorization_model->SaveAuthorization($charge['id'], $payref);
			}
			
			//save order details
			$CI->load->model('charge_data_model');
			$CI->charge_data_model->Save($order_id, 'payRef', $payref);
			$CI->charge_data_model->Save($order_id, 'retRef', $retref);
			$CI->charge_data_model->Save($order_id, 'resp', $response["ResponseCode"]);
			$CI->charge_data_model->Save($order_id, 'desc', $response["ResponseDescription"]);

			$code = 0;
			if($response["ResponseCode"] == "00"){
				// transaction successful
				$CI->charge_model->SetStatus($charge['id'], 1);
				$code = 1;
				TriggerTrip('charge', $client_id, $charge['id']);
			}else{
				//failed transaction
				$CI->charge_model->SetStatus($charge['id'], 0);
				$code = 2;
			}			
			
			//update card last four digits
			$CI->charge_model->SetCardLastFour($charge['id'], $response["CardNumber"]);

			// get return URL from original OpenGateway request
			//$CI->load->model('charge_data_model');
			$data = $CI->charge_data_model->Get($charge['id']);
			
			// log transaction with nobipay api
			$post_url = $gateway['trans_api'];

			$post = array();
			$post['id'] = $order_id;
			$post['amount'] = $charge["amount"];
			$post['responseCode'] = $code;
			$post['responseText'] = $desc;
			$post['payRef'] = $gateway['signature'];
			$post['customerId'] = $charge['customer']['id'];
			$post['description'] = $data['description'];
			$post['tranType'] = $data['transaction_type'];			
			$post['paymentId'] = $data['payment_id'];
			$post['merchantId'] =  $data['merchant_id'];
			$post['invoiceId'] =  $data['invoice_id'];
	
			$post_response = $this->ProcessJsonPost($post_url, $post);
			//$this->log_it('Call to API : ',$post_response);
			
			$response_array = array(
					'charge_id' 	=> $order_id,
					'type'			=> 'Interswitch',
					'reason'		=> $response["ResponseDescription"],
					'pay_ref'		=> $payref,
					'customer_id'		=> $charge['customer']['id'],
					'transaction_type' => $data['transaction_type']
				);
				
			$return_response = $CI->response->TransactionResponse($code, $response_array);
			
			//$this->log_it('Interswitch Transaction response : ',$return_response);
			// redirect back to user's site		

			$merchant_return = $gateway['site_redirect_url'];
			
			if(!$this->IsNullOrEmptyString($data['return_url'])){
				$merchant_return = $data['return_url'];
			}
			$this->return_get($merchant_return,$return_response);
			die();
			
		}else{
			//error in transaction
			$CI->load->model('charge_data_model');
			$CI->charge_data_model->Save($order_id, 'payRef', $payref);
			$CI->charge_data_model->Save($order_id, 'retRef', $retref);
			$CI->charge_data_model->Save($order_id, 'resp', $resp);
			$CI->charge_data_model->Save($order_id, 'desc', $desc);	
			
			//failed transaction
			$CI->charge_model->SetStatus($charge['id'], 0);
			
			//update card last four digits
			$CI->charge_model->SetCardLastFour($charge['id'], $response["CardNumber"]);
			
			$data = $CI->charge_data_model->Get($charge['id']);
			
			// log transaction with nobipay api
			$post_url = $gateway['trans_api'];

			$post = array();
			$post['id'] = $order_id;
			$post['amount'] = $charge["amount"];
			$post['responseCode'] = 2;
			$post['responseText'] = $desc;
			$post['payRef'] = $gateway['signature'];
			$post['customerId'] = $charge['customer']['id'];
			$post['description'] = $data['description'];
			$post['tranType'] = $data['transaction_type'];
			$post['paymentId'] = $data['payment_id'];
			$post['merchantId'] =  $data['merchant_id'];
			$post['invoiceId'] =  $data['invoice_id'];
	
			$post_response = $this->ProcessJsonPost($post_url, $post);
			//$this->log_it('Call to API : ',$post_response);
			
			
			$response_array = array(
					'charge_id' 	=> $order_id,
					'type'			=> 'Interswitch',
					'reason'		=> $desc,
					'customer_id'		=> $charge['customer']['id'],
					'transaction_type' => $data['transaction_type']
				);
				
			$return_response = $CI->response->TransactionResponse(2, $response_array);
			
			//$this->log_it('Interswitch Transaction response : ',$response);
			// redirect back to user's site	
			$merchant_return = $gateway['site_redirect_url'];
			
			if(!$this->IsNullOrEmptyString($data['return_url'])){
				$merchant_return = $data['return_url'];
			}
			$this->return_get($merchant_return,$return_response);
			die();
		}			
			
	}
	
	function Callback_requery ($client_id, $gateway, $charge, $params) {
		
		$CI =& get_instance();
		
		$txnref = $charge['id'];
		
		if($gateway['mode'] == 'test'){
			$txnref = $txnref.'-'.$txnref.'-'.$txnref;
		}
		
			
		$order_id = $charge['id'];
		$amount = (int)$charge["amount"]*100;// convert to kobo because of dumb interswitch
		
		//get transaction status
		$response = $this->get_transaction_status($txnref,$amount,$gateway);
		
		//save order details		

		$code = 0;
		if($response["ResponseCode"] == "00"){
			// transaction successful
			$CI->load->model('charge_data_model');
			$CI->charge_model->SetStatus($charge['id'], 1);
			$code = 1;
			
			TriggerTrip('charge', $client_id, $charge['id']);
		}
		die();			
					
			
	}
	
	function Process($url, $post_data)
	{
		$CI =& get_instance();

		$data = '';

		// Build the data string for the request body
		foreach($post_data as $key => $value)
		{
			if(!empty($value))
			{
				$data .= strtoupper($key) . '=' . urlencode(trim($value)) . '&';
			}
		}

		// remove the extra ampersand
		$data = substr($data, 0, strlen($data) - 1);

		// setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		// turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// setting the nvpreq as POST FIELD to curl
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		// getting response from server
		$response = curl_exec($ch);

		// Throw an error if we can't continue. Will help in debugging.
		if (curl_error($ch))
		{
			show_error(curl_error($ch));
		}

		$response = $this->response_to_array($response);

		return $response;
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
	
	function TestConnection($client_id, $gateway)
	{
		return true;
		/*$url = $this->GetAPIURL($gateway);
		
		if($url == NULL) return false;  
	    $ch = curl_init($url);  
	    curl_setopt($ch, CURLOPT_TIMEOUT, 5);  
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
	    $data = curl_exec($ch);  
	    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
	    curl_close($ch);  
	    if($httpcode>=200 && $httpcode<300){  
	        return true;  
	    } else {  
	        return false;  
	    }*/
	}
	
	private function GetAPIURL ($gateway) {
		if ($gateway['mode'] == 'test') {
			return $gateway['url_test'];
		}
		else {
			return $gateway['url_live'];
		}
	}
	
	private function get_trackback_url($gateway){
		if ($gateway['mode'] == 'test') {
			return $gateway['arb_url_test'];
		}
		else {
			return $gateway['arb_url_live'];
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
	
	private function get_transaction_status($txnref,$amount,$gateway)
	{
		$data = "?productid=".$gateway["product_id"]."&transactionreference=".$txnref."&amount=".$amount;
		$mac_key = '72B00C7ECC8FC12DB3E81D0401F3B617B9672E124D96A2DF320401BF8435CB5A784D104FD31100209D9BD913DFD16F160ABBC04B30258B7419EA30729039F649';
		$hash = hash("sha512",$gateway["product_id"].$txnref.$mac_key); //sha512 hash
		
		$url = $this->get_trackback_url($gateway);
		
		// setting the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url.$data);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('hash : '.$hash ));
	
		// turning off the server and peer verification(TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
		// getting response from server
		$response = curl_exec($ch);
		
		$response = json_decode($response,true);		
		
		return $response;
	}	
	
	private function return_post($url,$data)
	{
		$post = array();
		
		foreach ($data as $key => $value) {
			$post[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
		}
		
		$form = '<form action="'.$url.'" method="post" id="payment_form" name="payment_form">
					' . implode('', $post) . '					
				</form>
				<script language="JavaScript">
					document.payment_form.submit();
				</script>';
				
		echo $form;
	}
	
	private function return_get($url,$data)
	{
		$post = array();
		
		foreach ($data as $key => $value) {
			$post[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
		}
		
		$form = '<form action="'.$url.'" method="get" id="payment_form" name="payment_form">
					' . implode('', $post) . '					
				</form>
				<script language="JavaScript">
					document.payment_form.submit();
				</script>';
				
		echo $form;
	}
	
	function IsNullOrEmptyString($question){
	    return (!isset($question) || trim($question)==='');
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
?>