<?php
/**
* Email Model
*
* Contains all the methods used to create, update, and delete client Emails.
*
* @version 1.0
* @author Electric Function, Inc.
* @package OpenGateway

*/

class Email_model extends Model
{
	function __construct()
	{
		parent::__construct();
	}

	/**
	* Get Email Trigger ID
	*
	* Returns the Email trigger ID based on the trigger_name.
	*
	* @param string $trigger_name The email trigger system name
	*
	* @return int The email trigger ID
	*/

	function GetTriggerId($trigger_name)
	{
		$this->db->where('system_name', $trigger_name);
		$query = $this->db->get('email_triggers');
		if($query->num_rows() > 0) {
			return $query->row()->email_trigger_id;
		} else {
			return FALSE;
		}
	}

	/**
	* Get All Email Triggers
	*
	* Returns an array containing all email triggers.
	*
	* @return mixed Array with all email triggers
	*/

	function GetTriggers() {
		$this->db->order_by('human_name');
		$query = $this->db->get('email_triggers');
		foreach ($query->result_array() as $row) {
			$return[] = $row;
		}

		return $return;
	}

	/**
	* Save a new customer email
	*
	* Creates a new customer email to be called by an email trigger
	*
	* @param int $client_id The client ID
	* @param int $trigger_id The Email Trigger ID
	* @param string $params['email_subject'] The subject line of the email
	* @param string $params['from_name'] The name that the email will be sent from
	* @param string $params['from_email'] The email address that the email will be sent from
	* @param string $params['email_body'] The email body.  Can be in plain text or HTML
	* @param int $params['plan'] Plan ID to trigger email. Optional.
	* @param int $params['is_html'] Whether or not the email to be sent is HTML.  If the email_body is in HTML, this must be set to 1. Optional.
	* @param string $params['to_address'] The email address to send the email to.  If not supplied, the customer's saved email address will be used. Optional.
	* @param string $params['bcc_address'] The email address to send as BCC.  Optional.
	*
	* @return int The email ID
	*/

	function SaveEmail($client_id, $trigger_id, $params)
	{
		$insert_data['client_id'] = $client_id;
		$insert_data['trigger_id'] = $trigger_id;
		$insert_data['email_subject'] = $params['email_subject'];

		$insert_data['from_name'] = $params['from_name'];
		$insert_data['from_email'] = $params['from_email'];
		$insert_data['active'] = 1;
		$insert_data['email_body'] = $params['email_body'];

		if(isset($params['plan'])) {
			$insert_data['plan_id'] = $params['plan'];
		} else {
			$insert_data['plan_id'] = '';
		}

		if(isset($params['is_html']) and $params['is_html'] == '1') {
			$insert_data['is_html'] = $params['is_html'];
		} else {
			$insert_data['is_html'] = 0;
		}

		if(isset($params['to_address'])) {
			$insert_data['to_address'] = $params['to_address'];
		}
		else {
			$insert_data['to_address'] = 'customer';
		}

		if(isset($params['bcc_address'])) {
			$insert_data['bcc_address'] = $params['bcc_address'];
		} else {
			$insert_data['bcc_address'] = '';
		}

		$this->db->insert('client_emails', $insert_data);

		return $this->db->insert_id();
	}

	/**
	* Update an existing email
	*
	* Updates an existing customer email with the supplied parameters.
	*
	* @param int $client_id The client ID
	* @param int $trigger_id The Email Trigger ID. Optional.
	* @param string $params['email_subject'] The subject line of the email. Optional.
	* @param string $params['from_name'] The name that the email will be sent from. Optional.
	* @param string $params['from_email'] The email address that the email will be sent from. Optional.
	* @param string $params['email_body'] The email body.  Can be in plain text or HTML. Optional.
	* @param int $params['plan'] Plan ID to trigger email. Optional.
	* @param int $params['is_html'] Whether or not the email to be sent is HTML.  If the email_body is in HTML, this must be set to 1. Optional.
	* @param string $params['to_address'] The email address to send the email to.  If not supplied, the customer's saved email address will be used. Optional.
	* @param string $params['bcc_address'] The email address to send as BCC.  Optional.
	*
	* @return boolean TRUE or FALSE depending in update success
	*/

	function UpdateEmail($client_id, $email_id, $params, $trigger_id = FALSE)
	{
		if($trigger_id) {
			$update_data['trigger_id'] = $trigger_id;
		}

		if(isset($params['plan'])) {
			$update_data['plan_id'] = $params['plan'];
		}

		if(isset($params['email_subject'])) {
			$update_data['email_subject'] = $params['email_subject'];
		}

		if(isset($params['email_body'])) {
			$update_data['email_body'] = $params['email_body'];
		}

		if(isset($params['from_name'])) {
			$update_data['from_name'] = $params['from_name'];
		}

		if(isset($params['from_email'])) {
			$update_data['from_email'] = $params['from_email'];
		}

		if(isset($params['is_html'])) {
			$update_data['is_html'] = $params['is_html'];
		}

		if(isset($params['to_address'])) {
			$update_data['to_address'] = $params['to_address'];
		}

		if(isset($params['bcc_address'])) {
			$update_data['bcc_address'] = $params['bcc_address'];
		}

		$this->db->where('client_email_id', $email_id);
		$this->db->where('client_id', $client_id);

		$this->db->update('client_emails', $update_data);

		return TRUE;
	}

	/**
	* Delete an email
	*
	* Marks an existing email as inactive
	*
	* @param int $client_id The client ID
	* @param int $email_id The Email ID
	*/

	function DeleteEmail($client_id, $email_id)
	{
		$update_data['active'] = 0;

		$this->db->where('client_id', $client_id);
		$this->db->where('client_email_id', $email_id);

		$this->db->update('client_emails', $update_data);
	}

	/**
	* Get email variables
	*
	* Returns all variables that can be replaced in the body or subject line of an email.
	*
	* @param int $trigger_id The email trigger ID.
	*
	* @return mixed An array containing all of the available email variables
	*/

	function GetEmailVariables($trigger_id)
	{
		$this->db->select('available_variables');
		$this->db->where('email_trigger_id', $trigger_id);
		$query = $this->db->get('email_triggers');
		if($query->num_rows() > 0) {
			$vars = unserialize($query->row()->available_variables);
			foreach($vars as $var) {
				$result[] = $var;
			}
			return $result;
		} else {
			return FALSE;
		}
	}

	/**
	* Get email details.
	*
	* Returns the details of an existing client email.
	*
	* @param int $client_id The client ID
	* @param int $email_id The email ID
	*
	* @return mixed An array containing all of the email details
	*/

	function GetEmail($client_id, $email_id)
	{
		$params = array('id' => $email_id);

		$data = $this->GetEmails($client_id, $params);

		if (!empty($data)) {
			return $data[0];
		}
		else {
			return FALSE;
		}
	}

	/**
	* Get details of all emails
	*
	* Returns details of all emails for a client.  All parameters except $client_id are optional.
	*
	* @param int $client_id The client ID
	*
	* @param int $params['deleted'] Whether or not the email is deleted.  Possible values are 1 for deleted and 0 for active
	* @param string $params['trigger'] Returns only emails for a specific email_trigger.  May be a trigger_id or system_name.  Optional.
	* @param int $params['id'] The email ID.  GetEmail could also be used. Optional.
	* @param string $params['to_address'] The email address to send to. Optional.
	* @param string $params['email_subject'] The email subject line. Optional.
	* @param string $params['plan_id'] The linked plan.
	* @param int $params['offset']
	* @param int $params['limit'] The number of records to return. Optional.
	*
	* @return mixed Array containg all emails meeting criteria
	*/

	function GetEmails($client_id, $params)
	{
		if(isset($params['trigger'])) {
			$trigger_id = (!is_numeric($params['trigger'])) ? $this->GetTriggerId($params['trigger']) : $params['trigger'];
			$this->db->where('trigger_id', $trigger_id);
		}

		if(isset($params['deleted']) and $params['deleted'] == '1') {
			$this->db->where('client_emails.active', '0');
		}
		else {
			$this->db->where('client_emails.active', '1');
		}

		if(isset($params['id'])) {
			$this->db->where('client_emails.client_email_id', $params['id']);
		}

		if(isset($params['plan_id'])) {
			$this->db->where('client_emails.plan_id', $params['plan_id']);
		}

		if(isset($params['to_address'])) {
			$this->db->where('client_emails.to_address', $params['to_address']);
		}

		if(isset($params['email_subject'])) {
			$this->db->where('client_emails.email_subject', $params['email_subject']);
		}

		if (isset($params['offset'])) {
			$offset = $params['offset'];
		}
		else {
			$offset = 0;
		}

		if(isset($params['limit'])) {
			$this->db->limit($params['limit'], $offset);
		}

		$this->db->join('email_triggers', 'email_triggers.email_trigger_id = client_emails.trigger_id', 'inner');
		$this->db->join('plans', 'client_emails.plan_id = plans.plan_id', 'left');

		$this->db->select('client_emails.*');
		$this->db->select('email_triggers.*');
		$this->db->select('`plans`.`plan_id` AS `true_plan_id`',true);
		$this->db->select('plans.name');

		$this->db->where('client_emails.client_id', $client_id);

		$query = $this->db->get('client_emails');
		$data = array();
		if($query->num_rows() > 0) {
			foreach($query->result_array() as $row)
			{
				$array = array(
								'id' => $row['client_email_id'],
								'trigger' => $row['system_name'],
								'email_subject' => $row['email_subject'],
								'email_body' => $row['email_body'],
								'from_name' => $row['from_name'],
								'from_email' => $row['from_email'],
								'is_html' => $row['is_html'],
								'to_address' => $row['to_address'],
								'bcc_address' => $row['bcc_address'],
								'plan' => $row['plan_id'],
								);

				if (isset($row['plan_name'])) {
					$array['plan_name'] = $row['name'];
				}

				$data[] = $array;
			}

		} else {
			return FALSE;
		}

		return $data;
	}

	/**
	* Get all emails by trigger
	*
	* Returns an array containg the details of all emails for a specific email_trigger_id
	*
	* @param int $client_id The client ID
	* @param int $trigger_type_id The Email Trigger ID.
	* @param int $plan_id Plan ID to limit results.  Possible values are -1 for no plans, 0 for all plans, or X where X equals a specific plan_id
	*
	* @return mixed Arrary containing all email details
	*/

	function GetEmailsByTrigger($client_id, $trigger_type_id, $plan_id = false)
	{
		$this->db->join('email_triggers', 'email_triggers.email_trigger_id = client_emails.trigger_id', 'inner');
		$this->db->where('client_id', $client_id);
		$this->db->where('trigger_id', $trigger_type_id);
		$this->db->where('client_emails.active','1');

		if ($plan_id != false) {
			// plan ID can be -1 for No plans
			// 				   0 for All plans
			//              or X referring to a specific plan ID X

			// must match this specific plan and all plans
			$this->db->where('(`plan_id` = \'' . $plan_id . '\' or `plan_id` = \'0\' or `plan_id` = \'\')',NULL,FALSE);
		}
		else {
			// must match no plans or an empty plan_id
			$this->db->where('(`plan_id` = \'-1\' or `plan_id` = \'\')',NULL,FALSE);
		}

		$query = $this->db->get('client_emails');

		if($query->num_rows() > 0) {
			$emails = array();
			foreach ($query->result_array() as $row) {
				$emails[] = $row;
			}
			return $emails;
		} else {
			return FALSE;
		}
	}
}