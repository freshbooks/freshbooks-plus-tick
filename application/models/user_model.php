<?php
Class User_model extends Model {

	function __construct()
	{
		// Call the Model constructor
		parent::Model();
	}
    
	function check_for_email($str)
	{
		$this->db->where('email', $str);
		$this->db->from('users');
		$query = $this->db->get();
		return $query->num_rows(); 
	}
    
	function getuser($email)
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

	function insert_user()
	{
		//prepare user data for input
		$password = md5($this->input->post('password'));
		$data = array(
			'name' => $this->input->post('name'),
			'email' => $this->input->post('email'),
			'password' => $password,
			);
		
		return $this->db->insert('users', $data);
	}
	
	function get_all_users()
	{
		$query = $this->db->get('users');
		return $query->result();
	}	

}