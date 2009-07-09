<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Handles all API requests for Tick and FreshBooks.
 *
 * @package Invoice_api
 * @author Kyle Hendricks kyleh@mendtechnologies.com
 **/

Class Invoice_api
{
	/**
	 * FreshBooks API URL.
	 *
	 * @var string
	 **/
	private $fburl;
	
	/**
	 * FreshBooks API token.
	 *
	 * @var string
	 **/
	private $fbtoken;
	
	/**
	 * Tick API URL.
	 *
	 * @var string
	 **/
	private $tickurl;
	
	/**
	 * Tick email and password combined into get request string.
	 *
	 * @var string
	 **/
	private $auth;
	
	function __construct($params)
	{
		$this->fburl   = $params['fburl'];
		$this->fbtoken = $params['fbtoken'];
		$this->tickurl = $params['tickurl'];
		$this->auth    = array(
			'email'    => $params['tickemail'],
			'password' => $params['tickpassword']
		);
	}
	
	/**
	 * Convert a multi-dimensional array into XML. This is used to convert 
	 * arrays into XML requests. Note, the keys in the array *must* contain
	 * only characters that are valid for XML element names. Text node data
	 * will be passed through htmlentities(), so characters with XML Entity 
	 * equivalents (i.e. &, <, >) will be encoded properly. 
	 * 
	 * @return a portion of an XML document as a string.
	 */
	function array_to_xml($arr, $last_key = null)
	{
		$xmlstr = '';
		foreach ($arr as $key => $val) 
		{
			// a numeric key indicates a non-associative array. these are 
			// converted into XML sibling elements wrapped in the parent
			// (i.e. <lines><line>..</line><line>...</line></lines>)
			if (is_numeric($key)) 
			{
				// preserve the last key which should not be numeric
				$xmlstr .= "<$last_key>" . $this->array_to_xml($val, $last_key) . "</$last_key>";
				continue;
			}
			
			if (is_array($val)) 
			{
				$keys = array_keys($val);
				$wrap = false;
			
				foreach ($keys as $k) 
					if (!is_numeric($k)) $wrap = true;
				
				if ($wrap) $xmlstr .= "<$key>";
				$xmlstr .= $this->array_to_xml($val, $key);
				if ($wrap) $xmlstr .= "</$key>";
			} else {
				$xmlstr .= "<$key>" . htmlentities($val) . "</$key>";
			}
		}
		
		return $xmlstr;
	}
	
	/**
	 * Sends a request to the Tick API.
	 *
	 * @param $method string  The Tick API method to call (i.e. clients).
	 * @param $args   array   A hash containing parameters to the method call. 
	 * @throws Exception If the Tick API request fails for any reason.
	 * @return object a SimpleXMLElement instance with the tick response. 
	 **/
	private function send_tick_request($method, array $args = array())
	{
		$url = $this->tickurl . '/api/' . $method;
		$url .= '?' . http_build_query(array_merge($this->auth, $args));
		
		// send request
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		
		$result = curl_exec($ch);
		$info   = curl_getinfo($ch);
		$errno  = curl_errno($ch);
		$error  = curl_error($ch);
		
		curl_close($ch);
		
		// if we cannot get an http_code or the content_type, something is up.
		if (!(array_key_exists('http_code', $info) && 
		      array_key_exists('content_type', $info)))
		{
			$msg = 'Error:';
			if (!empty($result)) 
				$msg .= ': ' . $result;
			$msg .= ' (' . $errno . ', ' . $error . ') ';
			$msg .= 'Please check your Tick settings and try again.';
			throw new Exception($msg);
		}
		
		// valid results:
		// 1) HTTP 200 and application/xml content-type
		// 2) HTTP 200, text/html content-type and empty string result of length 1. 
		if ($info['http_code'] == 200 && strstr($info['content_type'], 'application/xml;'))
		{
			return simplexml_load_string($result);
		} 
		elseif ($info['http_code'] == 200 && strstr($info['content_type'], 'text/html;') && $result === ' ')
		{
			return simplexml_load_string('');
		}
		{
			$code = 0;
			$msg = "Unexpected response from Tick. Errno: $errno";
			if ($errno != 0) 
				$msg .= ", Error: $error";
			if (array_key_exists('http_code', $info)) {
				$msg .= " HTTP Status Code: " . $info['http_code'];
				$code = $info['http_code'];
			}
			throw new Exception($msg, $code);
		}
	}
	
	/**
	 * Sends XML requests to FreshBooks API.
	 *
	 * @param $xml string
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	private function send_freshbooks_request($method, $args)
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<request method="' . $method . '">';
		$xml .= $this->array_to_xml($args);
		$xml .= '</request>';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->fburl);
		curl_setopt($ch, CURLOPT_USERPWD, $this->fbtoken);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
		
		$result = curl_exec($ch);
		$info   = curl_getinfo($ch);
		$errno  = curl_errno($ch);
		$error  = curl_error($ch);
		
		curl_close ($ch);
		
		//check for non xml result
		if($result == FALSE || $info['http_code'] != 200)
		{
			$code = 0;
			$msg = 'Error: Unable to connect to the FreshBooks API.';
			if (array_key_exists('http_code', $info)) {
				$msg .= ' HTTP Status Code: ' . $info['http_code'] . '.';
				$code = $info['http_code'];
			}
			if ($errno) 
				$msg .= 'Errno: ' . $errno . ', Error: ' . $error;
			$msg .= " Please check your FreshBooks API URL setting and try again.";
			$msg .= "The FreshBooks API url is different from your FreshBooks account url.";
			
			throw new Exception($msg, $code);
		}
		
		//if xml check for FB status
		if($info['http_code'] == 200)
		{
			$fbxml = simplexml_load_string($result);
			if($fbxml->attributes()->status == 'fail')
			{
				throw new Exception('Error: The following FreshBooks error occurred: '.$fbxml->error);
			}
			else
			{
				return $fbxml;
			}
		} 
	}

	/**
	 * Gets FreshBooks projects.
	 *
	 * @param $page(optional), int FreshBooks page number
	 * @param $client_id string, FreshBooks client id
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	private function get_fb_projects($client_id, $page=1)
	{
		return $this->send_freshbooks_request('project.list', array(
			'client_id' => $client_id,
			'page'      => $page,
			'per_page'  => '100'
		));
	}
	
	/**
	 * Gets all FreshBooks items.
	 *
	 * @param $page(optional), int FreshBooks page number
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	private function get_all_items($page=1)
	{
		return $this->send_freshbooks_request('item.list', array(
			'page'     => $page,
			'per_page' => '100'
		));
	}
	
	/**
	 * Gets all FreshBooks tasks or tasks assigned to projects if optional project_id is provided.
	 *
	 * @param $page(optional), int FreshBooks page number
	 * @param $project_id(optional) string, FreshBooks project id
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	private function get_all_tasks($project_id=0, $page=1)
	{
		$args = array(
			'page'       => $page,
			'per_page'   => '100'
		);
		
		if ($project_id != 0) 
			$args['project_id'] = $project_id;
		
		return $this->send_freshbooks_request('task.list', $args); 
	}
	
	/**
	 * Determine billing rate for FreshBooks project task-rate billing method also if no project exists in FreshBooks.
	 *
	 * @param $tick_task, string - Tick task description
	 * @param $project_id, string - FreshBooks project id
	 * @return float - Unit cost if task/item match else 0
	 **/
	private function task_rate_billing($tick_task, $project_id)
	{
		//check for matching task
		$tick_task_name = trim($tick_task);
		try 
		{
			$tasks = $this->get_all_tasks($project_id);
		}
		catch (Exception $e) 
		{
			return 0;
		}
		
		foreach ($tasks->tasks->task as $task)
		{
			if($task->name == $tick_task_name)
			{
				$unit_cost = $task->rate;
				return $unit_cost;
			}
		}
		
		//chcek for multiple task pages
		$num_pages = (integer)$tasks->tasks->attributes()->pages;
		$page = 2;
		if ($num_pages > 1) {
			while ($page <= $num_pages)
			{
				try 
				{
					$tasks = $this->get_all_tasks($project_id, $page);
				}
				catch (Exception $e) 
				{
					return 0;
				}

				foreach ($tasks->tasks->task as $task)
				{
					if($task->name == $tick_task_name)
					{
						$unit_cost = $task->rate;
						return $unit_cost;
					}
				}
				$page++;
			}
		}
		
		//check for matching item
		//FB items have a 15 character limit
		$task_length = strlen($tick_task_name);
		if($task_length <= 15)
		{
			try 
			{
				$items = $this->get_all_items();
			} 
			catch (Exception $e) 
			{
				return 0;
			}
			
			foreach($items->items->item as $item)
			{
				if($item->name == $tick_task_name)
				{
					$unit_cost = $item->unit_cost;
					return $unit_cost;
				}
			}
		
			//check for multiple task pages
			$num_pages = (integer)$items->items->attributes()->pages;
			$page = 2;
			if ($num_pages > 1) {
				while ($page <= $num_pages)
				{
					try 
					{
						$items = $this->get_all_items();
					} 
					catch (Exception $e) 
					{
						return 0;
					}

					foreach($items->items->item as $item)
					{
						if($item->name == $tick_task_name)
						{
							$unit_cost = $item->unit_cost;
							return $unit_cost;
						}
					}
					$page++;
				}
			}
		}

		//default to zero if no task/item found
		return 0;
	}
	
	/**
	 * Returns billing rate to create invoice methods.
	 *
	 * @param $bill_method, string - FreshBooks project billing method
	 * @param $tick_task, string - Tick task description
	 * @param $project_rate, string - FreshBooks project rate
	 * @param $project_id, string - FreshBooks project id
	 * @return float - Unit cost if task/item match else 0
	 **/
	private function get_billing_rate($bill_method, $tick_task, $project_rate, $project_id)
	{
		//check bill method to determine line item rate
		switch ($bill_method) {
			case 'flat-rate':
				$unit_cost = 0;
				break;
			case 'task-rate':
				$unit_cost = $this->task_rate_billing($tick_task, $project_id);
				break;
			case 'project-rate':
				$unit_cost = $project_rate;
				break;
			case 'staff-rate':
				$unit_cost = $project_rate;
				break;
			case 'no-project-found':
				$unit_cost = $this->task_rate_billing($tick_task, $project_id);
				break;
		}
		
		return $unit_cost;
	}
	
	/**
	 * Checks account credentials at Tickspot .. pass no constraints
	 *
	 * @return boolean returns true if login is successful, false otherwise. 
	 **/
	public function tickspot_login()
	{
		try {
			$this->send_tick_request('clients');
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Returns all open Tick entries for the past 5 years.
	 *
	 * @param $id(optional), string - Tick project id
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function get_all_open_entries($id = 0)
	{
		$args = array(
			'updated_at' => date("m/d/Y", mktime(0, 0, 0, date("m"), date("d"),   date("Y")-5)),
			'entry_billable' => 'true',
			'billed' => 'false'
		);
		
		if ($id != 0) 
			$args['project_id'] = $id;
		
		return $this->send_tick_request('entries', $args);
	}

	/**
	 * Returns open Tick entries with start date, end date and optional Tick project id.
	 *
	 * @param $id(optional), string - Tick project id
	 * @param $start_date(optional), string - defaults to first day of current month
 	 * @param $end_date(optional), string - defaults to current day of current month
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function get_open_entries($id = 0, $start_date = '', $end_date = '')
	{
		if (!$start_date)
		{
			$start_date = date("m").'/'.'01'.'/'.date("Y");
			$end_date = date("m/d/Y");
		}
		
		$args = array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'entry_billable' => 'true',
			'billed'     => 'false'
		);
		
		if ($id != 0) 
			$args['project_id'] = $id;
		
		return $this->send_tick_request('entries', $args);
	}
	
	/**
	 * Change Tick entries billing status.
	 *
	 * @param $status, bool - true or false
	 * @param $id, string - Tick entries id
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function change_billed_status($status, $id)
	{
		return $this->send_tick_request('update_entry', array(
			'id' => $id, 
			'billed' => $status
		));
	}
	
	/**
	 * Creates multidimential array from Tick entries xml object.
	 *
	 * @param $entries, XML Object of Tick entries
	 * @return array	multidimential array of tick entries
	 **/
	public function process_entries($entries)
	{
		$processed_entries = array();
			foreach ($entries as $entry)
			{
				$dataset = array(
					'entry_id' => (integer)$entry->id,
					'entry_date' => (string)$entry->date,
					'client_name' => (string)$entry->client_name,
					'project_name' => (string)$entry->project_name,
					'project_id' => (string)$entry->project_id,
					'task_name' => (string)$entry->task_name,
					'task_id' => (integer)$entry->task_id,
					'notes' => (string)$entry->notes,
					'hours' => (float)$entry->hours,
					);
				$processed_entries[] = $dataset;
			}
		return $processed_entries;
	}
	
	/**
	 * Returns FreshBooks invoice given invoice id.
	 *
	 * @param $id, FreshBooks invoice id
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function get_invoice($id)
	{
		return $this->send_freshbooks_request('invoice.get', array(
			'invoice_id' => $id
		));
	}
	
	/**
	 * Returns FreshBooks invoice status given invoice id.
	 *
	 * @param $id, FreshBooks invoice id
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function check_invoice_status($id)
	{
		try 
		{
			$invoice_info = $this->get_invoice($id);
			return (string)$invoice_info->invoice->status;
		} 
		catch (Exception $e) 
		{
			return 'deleted';
		}
	}

	/**
	 * Returns FreshBooks clients.
	 *
	 * @param $page(optional), int FreshBooks page number
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function get_fb_clients($page=1)
	{
		return $this->send_freshbooks_request('client.list', array(
			'page'     => $page,
			'per_page' => '100'
		)); 
	}
	
	/**
	 * Checks for a match between a Tick client name and all FreshBooks client names.
	 *
	 * @param $fbclients, object - object containing FreshBooks clients
	 * @param $ts_client_name, string - Tick client name
	 * @return integer/bool	integer containing FreshBooks client id on success False on fail
	 **/
	public function match_clients($fbclients, $ts_client_name)
	{
		foreach ($fbclients->clients->client as $client)
		{
			$fb_client_name = trim((string)$client->organization);
			if (strcasecmp($fb_client_name, $ts_client_name) == 0)
			{
				$client_id = $client->client_id;
				return $client_id;
			}
		}
		return FALSE;
	}
	
	/**
	 * Returns FreshBooks project details array given a Tick client and project.
	 * Allows fo multiple instances of the same client in FreshBooks.
	 *
	 * @param $ts_project_name, string - Tick project name
	 * @param $ts_client_name, string - Tick client name
	 * @return array/string	FreshBooks project details array or string with error details on fail
	 **/
	public function get_billing_details($ts_client_name, $ts_project_name)
	{
		//get FB clients
		$fbclients = $this->get_fb_clients();
		
		foreach ($fbclients->clients->client as $client)
		{
			$fb_client_name = trim((string)$client->organization);
			if (strcasecmp($fb_client_name, $ts_client_name) == 0)
			{
				//get FB projects for client
				$fb_projects = $this->get_fb_projects($client->client_id);

				//loop through projects looking for match
				foreach ($fb_projects->projects->project as $project)
				{
					$fb_project_name = trim((string)$project->name);
					$fb_project_id = (integer)$project->project_id;
					$fb_project_billmethod = trim((string)$project->bill_method);
					$ts_project_name = $ts_project_name;
					//if match find bill method and type
					if (strcasecmp($fb_project_name, $ts_project_name) == 0)
					{
						$bill_rate = (float)$project->rate;
						$client_id = (integer)$client->client_id;
						$bill_details = array('bill_method' => $fb_project_billmethod, 'bill_rate' => $bill_rate, 'client_id' => $client_id, 'project_id' => $fb_project_id);
						return $bill_details;
					}//endif
				}//end foreach
			}//endif
		}//end foreach
		
		//loop through multiple FreshBooks response pages if necessary
		$num_pages = (integer)$fbclients->clients->attributes()->pages;
		if ($num_pages > 1) 
		{
			$page = 2;
			while ($page <= $num_pages)
			{
				//get FB clients
				$fbclients = $this->get_fb_clients($page);
				
				foreach ($fbclients->clients->client as $client)
				{
					$fb_client_name = trim((string)$client->organization);
					if (strcasecmp($fb_client_name, $ts_client_name) == 0)
					{
						//get FB projects for client
						$fb_projects = $this->get_fb_projects($client->client_id);
						
						//loop through projects looking for match
						foreach ($fb_projects->projects->project as $project)
						{
							$fb_project_name = trim((string)$project->name);
							$fb_project_id = (integer)$project->project_id;
							$fb_project_billmethod = trim((string)$project->bill_method);
							$ts_project_name = $ts_project_name;
							//if match find bill method and type
							if (strcasecmp($fb_project_name, $ts_project_name) == 0)
							{
								$bill_rate = (float)$project->rate;
								$client_id = (integer)$client->client_id;
								$bill_details = array('bill_method' => $fb_project_billmethod, 'bill_rate' => $bill_rate, 'client_id' => $client_id, 'project_id' => $fb_project_id);
								return $bill_details;
							}//endif
						}//end foreach
					}//endif
				}//end foreach
				
				$page++;
			}//end while
		}
		return $bill_details = array('bill_method' => 'no-project-found', 'bill_rate' => 0, 'client_id' => NULL, 'project_id' => 0);
	}
	
	/**
	 * Creates summary invoice in FreshBooks.
	 *
	 * @param $client_data, array - array containing general invoice data
	 * @param $line_item_summary, array - array containing detailed line item invoice data
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function create_summary_invoice($client_data, $line_item_summary)
	{
		$client_id = $client_data['client_id'];
		$client_name = $client_data['client_name'];
		$total_hours = $client_data['total_hours'];
		$project_name = $client_data['project_name'];
		$project_id = $client_data['project_id'];
		$project_rate = $client_data['project_rate'];
		$bill_method = $client_data['bill_method'];
		
		$args = array(
			'invoice' => array(
				'client_id' => $client_id,
				'status'    => 'draft',
				'organization' => $client_name,
				'lines' => array()
			)
		);

		//if bill method is flat rate append line with flat rate
		if ($bill_method == 'flat-rate')
		{
			$args['invoice']['lines']['line'] = array(
				'description' => '[' . $project_name . '] Flat Rate',
				'unit_cost'   => $project_rate,
				'quantity'    => '1'
			);
		}
		else
		{
			//determine unit cost by cumulating hours
			$unit_cost_summary = 0;
			foreach ($line_item_summary as $item) 
			{
				//set hours
				$hours = $item['hours'];
				//set unit cost
				$tick_task = $item['task'];
				$unit_cost = $this->get_billing_rate($bill_method, $tick_task, $project_rate, $project_id);
				$unit_cost_summary += ($hours * $unit_cost);
			}//end foreach
			
			$args['invoice']['lines']['line'] = array(
				'description' => '[' . $project_name . ']',
				'unit_cost'   => $unit_cost_summary,
				'quantity'    => '1'
			);
		}
		
		return $this->send_freshbooks_request('invoice.create', $args);
	}

	/**
	 * Creates detailed line item invoice in FreshBooks.
	 *
	 * @param $client_data, array - array containing general invoice data
	 * @param $line_item_summary, array - array containing detailed line item invoice data
	 * @return string/object	string containing error desc on error, xmlobject on success 
	 **/
	public function create_detailed_invoice($client_data, $line_item_summary)
	{
		$client_id = $client_data['client_id'];
		$client_name = $client_data['client_name'];
		$total_hours = $client_data['total_hours'];
		$project_name = $client_data['project_name'];
		$project_id = $client_data['project_id'];
		$project_rate = $client_data['project_rate'];
		$bill_method = $client_data['bill_method'];

		//open xml file with core data
		$args = array(
			'invoice' => array(
				'client_id' => $client_id,
				'status'    => 'draft',
				'organization' => $client_name,
				'lines' => array(
					'line' => array()
				)
			)
		);
				
		foreach ($line_item_summary as $item) 
		{
			//set hours
			$hours = $item['hours'];
			//set description
			$description = '['.$project_name.']  ';
			if ($item['task'] != 'No Task Selected') 
			{
				$description .= $item['task'];
			}
			//set unit cost
			$tick_task = $item['task'];
			$unit_cost = $this->get_billing_rate($bill_method, $tick_task, $project_rate, $project_id);
			
			$args['invoice']['lines']['line'][] = array(
				'name' => '',
				'description' => $description,
				'unit_cost'   => $unit_cost,
				'quantity'    => $hours
			);
		}//end foreach
		
		//if bill method is flat rate append line with flat rate
		if ($bill_method == 'flat-rate')
		{
			$args['invoice']['lines']['line'][] = array(
				'name' => '',
				'description' => '[' . $project_name . '] Flat Rate',
				'unit_cost'   => $project_rate,
				'quantity'    => '1'
			);
		}
		
		//send invoice create request to FB
		return $this->send_freshbooks_request('invoice.create', $args);
	}

}
/* End of file Invoice_api.php */
/* Location: /application/libraries/Invoice_api.php */ 
