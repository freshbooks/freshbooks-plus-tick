<?php

/**
 * Abstract class for use by Tick+FB application. Code that needs to be shared
 * by various Controller classes should be in here. 
 * 
 * @author Paul Osman <paul@freshbooks.com>
 */
abstract class MY_Controller extends Controller
{
	/**
	 * Load any libraries or helpers used by all controllers.
	*/
	function MY_Controller() 
	{
		parent::Controller();
		$this->load->library('encrypt');
	}
	
	/**
	 * Retrieves application settings. Most are stored in the settings model, 
	 * except the password which is stored in the user model. This method will
	 * redirect to the settings page if unable to fetch these settings. 
	 *
	 * @return array Array of API settings on success
	 **/
	protected function _get_settings()
	{ 
		$this->load->model('Settings_model','settings');
		$settings = $this->settings->get_settings();
		
		if (!$settings) 
		{
			redirect('settings/index');
			return;
		}
		
		$this->load->model('User_model', 'user');
		$user  = $this->user->get_user($settings->tickemail);
		$token = $this->encrypt->decode(
			$this->session->userdata('authtoken'), $user->key);
		
		return array(
			'tickemail'    => $user->email, 
			'tickpassword' => $token,
			'tickurl'      => $settings->tickurl,
			'fburl'        => $settings->fburl,
			'fbtoken'      => $settings->fbtoken,
		);
	}
	
	/**
	 * Checks to see if the 'loggedin' flag is set in the session. Redirects
	 * to the user/index page if the user is not logged in. 
	 *
	 * @return bool	true on success, false otherwise
	 **/
	protected function _check_login()
	{
		$loggedin = $this->session->userdata('loggedin');
		if (!$loggedin) 
		{
			redirect('user/index');
			return false;
		}
		return true;
	}
	
	/**
	 * Simply check an exception message for a 401 error code. If present, 
	 * redirect to the logout action in the user controller so that the 
	 * user can re-enter their login credentials. 
	 * 
	 * @param $exception_msg string The exception message. 
	 */
	protected function check_for_auth_error($exception_msg) 
	{
		if (strstr($exception_msg, 'HTTP Status Code: 401')) {
			$this->session->unset_userdata('loggedin');
			$this->session->unset_userdata('userid');
			$this->session->unset_userdata('username');
			$this->session->unset_userdata('name');
			$this->session->set_userdata(array('error' => 'Tick Authentication Error. Please check your Tick credentials and try again.'));
			redirect('user/index');
		}
	}
}

?>