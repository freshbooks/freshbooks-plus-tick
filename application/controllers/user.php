<?php
/**
 * Controller for managing user login, new account setup and password reset functionality.
 *
 * @package User Controller
 * @author Kyle Hendricks kyleh@mendtechnologies.com
 **/
Class User extends MY_Controller 
{
	
	function __construct()
	{
		parent::MY_Controller();
		$this->load->helper(array('form', 'url', 'html'));
	}
	
	/**
	 * Default controller action. Login page.
	 *
	 * @return views/user/login_view.php
	 **/
	function index()
	{
		//load page specific variables
		$loggedin = $this->session->userdata('loggedin');

		$data['title'] = 'FreshBooks + Tick :: Login';
		$data['heading'] = 'FreshBooks + Tick Login';
		$data['error'] = FALSE;
		$data['navigation'] = FALSE;

		$data['ticksubdomain'] = (isset($_POST['ticksubdomain'])) ? $_POST['ticksubdomain'] : ((isset($_COOKIE['fbplustick-subdomain'])) ? $_COOKIE['fbplustick-subdomain'] : '');
		$data['tickemail'] = (isset($_POST['tickemail'])) ? $_POST['tickemail'] : ((isset($_COOKIE['fbplustick-email'])) ? $_COOKIE['fbplustick-email'] : '');

		//check to see if user is logged in
		if (!$loggedin) {
			$error = $this->session->userdata('error');
			if ($error) $data['error'] = $error;
			$this->load->view('user/login_view',$data);
		}else{
			redirect('settings/index');
		}
	}

	/**
	 * Verifies login and redirects user accordingly.
	 *
	 * @return views/user/login_view.php - settings/index controller - tick/index controller
	 **/
	function verify()
	{
		//load page specific variables
		$data['title'] = 'Tick to FreshBooks Invoice Generator::Login';
		$data['heading'] = 'Tick to FreshBooks Invoice Generator Login';
		$data['error'] = FALSE;
		$data['navigation'] = FALSE;
		
		// assemble credentials for integration check
		$subdomain = $this->input->post('ticksubdomain');
		$email     = $this->input->post('tickemail');
		$password  = $this->input->post('tickpassword');
		
		$data['ticksubdomain'] = (isset($subdomain)) ? $subdomain : ((isset($_COOKIE['fbplustick-subdomain'])) ? $_COOKIE['fbplustick-subdomain'] : '');
		$data['tickemail'] = (isset($email)) ? $email : ((isset($_COOKIE['fbplustick-email'])) ? $_COOKIE['fbplustick-email'] : '');
		
		// create the url out of the subdomain. try to make sure the url is well formed
		$url = $subdomain;
		if (substr($url, 0, 8) != 'https://') $url = 'https://' . $url;
		if (substr($url, strlen($url) - strlen("tickspot.com")) != "tickspot.com") $url = $url . ".tickspot.com";
		$url = preg_replace('/http\:\/\//', '', $url);
		
		// create an Invoice API object. 
		$params = array(
			'tickemail'    => $email,
			'tickpassword' => $password,
			'tickurl'      => $url,
			'fburl'        => '',	// default for now
			'fbtoken'      => ''	// default for now
		);
		$this->load->library('Invoice_api', $params);
		$this->load->model('User_model', 'user');
		
		// get a result from tickspot using these credentials
		if ($this->invoice_api->tickspot_login())
		{
			$user = $this->user->get_user($email);
			
			if ($user)
			{
				// update the user's key
				$key = substr(bin2hex($email . time()), 0, 32);
				$this->user->update_key($email, $key);
				
				$token = $this->encrypt->encode($password, $key);
				
				// start session - set vars
				$userinfo = array(
					'userid'    => $user->id, 
					'loggedin'  => TRUE, 
					'username'  => $user->email, 
					'authtoken' => $token
				);
				$this->session->set_userdata($userinfo); 

				//check for settings if user has settings send to sync page otherwise send to settings page
				$this->load->model('Settings_model', 'settings');
				$got_settings = $this->settings->get_settings();

				// check if cookies exist before attempting to set them
				if (!isset($_COOKIE['fbplustick-email']) or !isset($_COOKIE['fbplustick-subdomain']))
				{
					setcookie('fbplustick-email',$email,time()+3600*24*365,'/','.' . $_SERVER['SERVER_NAME']);
					setcookie('fbplustick-subdomain',$subdomain,time()+3600*24*365,'/','.' . $_SERVER['SERVER_NAME']);
				}

				if ($got_settings) 
				{
					redirect('tick/index');
				}
				else
				{
					redirect('settings/index');
				}
			}
			else
			{
				// instead of storing the password in plaintext in the database, we 
				// will store a key generated from the user's email and a timestamp.
				// the key will be used to encrypt the password. the encrypted password
				// will be stored in the session for later use. we can use the key to 
				// decrypt it any time we need to use it. Limit the key to 32 chars so
				// that it will work well with most encryption algorithms. 
				
				// store details about the user in the database. 
				$key = substr(bin2hex($email . time()), 0, 32);
				$this->user->insert_user($email, $key);

				//get user data to set session variables 
				$user = $this->user->get_user($email);
				$token = $this->encrypt->encode($password, $user->key);
				
				//set up session and set session vars
				$userinfo = array(
					'userid'    => $user->id, 
					'loggedin'  => TRUE, 
					'username'  => $user->email, 
					'authtoken' => $token
				);
				
				$this->session->set_userdata($userinfo);

				// set up some cookie stuff
				setcookie('fbplustick-email',$email,time()+3600*24*365,'/','.' . $_SERVER['SERVER_NAME']);
				setcookie('fbplustick-subdomain',$subdomain,time()+3600*24*365,'/','.' . $_SERVER['SERVER_NAME']);

				// redirect to populate more settings
				redirect('settings/index');
			}
		}
		else
		{
			$data['error'] = "Invalid Tickspot account - please try again.";
		}
		
		$error = $this->session->userdata('error');
		if ($error) {
			$data['error'] = $error;
		}

		$this->load->view('user/login_view',$data);
	}
	
	/**
	 * Logout user.
	 *
	 * @return user/index controller.
	 **/
	function logout()
	{
		$this->session->sess_destroy();
		redirect('user/index');
	}
}
/* End of file user.php */
/* Location: /application/controllers/user.php */
