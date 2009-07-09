<?php
/**
 * Model for managing user transactions in the user and password reset tables.
 *
 * @package User Model
 * @author Kyle Hendricks kyleh@mendtechnologies.com
 **/

Class User_model extends Model 
{

	function __construct()
	{
		// Call the Model constructor
		parent::Model();
	}
    
	/**
	 * Checks to see if email is already in database.
	 *
	 * @param $str, string - email address from post field
	 * @return int 1 if exist, 0 if no exist
	 **/
	function check_for_email($str)
	{
		$this->db->where('email', $str);
		$this->db->from('users');
		$query = $this->db->get();
		return $query->num_rows(); 
	}
    
	/**
	 * Gets user given an email address.
	 *
	 * @param $email, string - email address
	 * @return object of user info on success, False on fail
	 **/
	function get_user($email)
	{
		$this->db->where('email', $email);
		$this->db->from('users');
		$query = $this->db->get();
			if ($query->num_rows > 0) 
			{
				return $query->row();
			}else{
				return FALSE;
			}
    }

	/**
	 * Insert a user record. A user consists of an email and an encryption key
	 * used to encrypt / decrypt the password (which is stored in the session).
	 *
	 * @param $email string the email address
	 * @param $key   string an encryption key
	 *
	 * @return bool True on success, False on fail
	 **/
	function insert_user($email, $key)
	{
		//prepare user data for input
		$data = array(
			'email' => $email,
			'key'   => $key
		);
		
		return $this->db->insert('users', $data);
	}
	
	/**
	 * Gets all user.
	 *
	 * @param $email, string - email address
	 * @return object/bool - Object of users on success, False on fail
	 **/
	function get_all_users()
	{
		$query = $this->db->get('users');
		return $query->result();
	}

	/**
	 * Updates encryption key in user table
	 *
	 * @param $email string the user's email address
	 * @param $key   string the encryption key
	 * 
	 * @return bool - True on success, False on fail
	 **/
	function update_key($email, $key)
	{
		$data = array(
			'key' => $key
		);
		$this->db->where('email', $email);
		$this->db->update('users', $data);
	}
}
/* End of file user_model.php */
/* Location: /application/models/user_model.php */
