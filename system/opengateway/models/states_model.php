<?php
/**
* States Model
*
* Contains all the methods used to get State/Province details.
*
* @version 1.0
* @author Electric Function, Inc.
* @package OpenGateway

*/

class States_model extends Model
{
	function __construct()
	{
		parent::__construct();
	}

	/**
	* Get State Name by Code.
	*
	* Validate the 2-letter State abbreviation
	*
	* @param string $state The state abbreviation
	*
	* @return string The abbreviation
	*/

	function GetStateByCode($state)
	{
		$this->db->where('name_short', strtoupper($state));
		$query = $this->db->get('states');
		if($query->num_rows() > 0) {
			return $query->row()->name_short;
		}

		return FALSE;
	}

	/**
	* Get State Code by Name.
	*
	* Returns the State abbreviation based on state name
	*
	* @param string $state The state name
	*
	* @return string The abbreviation
	*/

	function GetStateByName($state)
	{
		$this->db->where('name_long', ucwords($state));
		$query = $this->db->get('states');
		if($query->num_rows() > 0) {
			return $query->row()->name_short;
		}

		return FALSE;
	}

	/**
	* Get All States
	*
	* Returns all states
	*
	* @return array All states with keys: code, name
	*/
	function GetStates () {
		$this->db->order_by('name_long','ASC');
		$result = $this->db->get('states');

		$states = array();
		foreach ($result->result_array() as $state) {
			$states[] = array(
							'code' => $state['name_short'],
							'name' => $state['name_long']
						);
		}

		return $states;
	}

	/**
	* Get All Countries
	*
	* Returns an array of all countries
	*
	* @return array All countries with keys: id, name
	*/
	function GetCountries () {
		$this->db->order_by('name','ASC');
		$result = $this->db->get('countries');

		$countries = array();
		foreach ($result->result_array() as $country) {
			$countries[] = array(
							'iso2' => $country['iso2'],
							'name' => $country['name']
						);
		}

		return $countries;
	}
}