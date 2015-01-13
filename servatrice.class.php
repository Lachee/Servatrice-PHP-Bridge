<?php 
/**
 * Servatrice PHP bridge
 *
 * This class bridges servatrice with php via access through the database.
 *
 * @author     Lachee <lachlan.h@hotmail.com>
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version    Release: 1
 */
class Servatrice {

	//The prefix to apend to the database tables
	public $prefix = "cockatrice";
	
	//The database connection. Not sure if this is the best way to do this.
	protected $sql;
	
	#region countries
	
	//Here is a list of all countries Cockatrice currently has suported.
	public $countries = array(
		'ar' => 'Argentina',
		'at' => 'Austria',
		'au' => 'Australia',
		'be' => 'Belgium',
		'br' => 'Brazil',
		'by' => 'Belarus',
		'ca' => 'Canada',
		'ch' => 'Switzerland',
		'cl' => 'Chile',
		'cn' => 'China',
		'cz' => 'Czech Republic',
		'de' => 'Germany',
		'dk' => 'Denmark',
		'do' => 'Dominican Republic',
		'es' => 'Spain',
		'fi' => 'Finland',
		'fr' => 'France',
		'ge' => 'Georgia',
		'gr' => 'Greece',
		'gt' => 'Guatemala',
		'hr' => 'Croatia',
		'hu' => 'Hungary',
		'ie' => 'Ireland',
		'il' => 'Israel',
		'it' => 'Italy',
		'lu' => 'Luxembourg',
		'lv' => 'Latvia',
		'mx' => 'Mexico',
		'my' => 'Malaysia',
		'nl' => 'Netherlands',
		'no' => 'Norway',
		'nz' => 'New Zealand',
		'pe' => 'Peru',
		'ph' => 'Philippines',
		'pl' => 'Poland',
		'pt' => 'Portugal',
		'ro' => 'Romania',
		'ru' => 'Russia',
		'se' => 'Sweden',
		'sg' => 'Singapore',
		'si' => 'Slovenia',
		'sk' => 'Slovakia',
		'tr' => 'Turkey',
		'ua' => 'Ukraine',
		'uk' => 'United Kingdom',
		'us' => 'United States',
		've' => 'Venezuela',
		'za' => 'South Africa'
		
	);
	#endregion
	
	public function __construct($host, $database, $username, $password) {
		//Establish a link to the database. Could potentially just require a connection instead of making a new one?
		$this->sql = new mysqli($host, $username, $password, $database);
		if($this->sql->connect_error) die("Servatrice Connection Failed: " . $this->sql->connect_error);
	}
	
	#region Statictics
	public function getOnlineSessions() {
	
		//Select all sessions that are still connected (end_time is null)
		$query = "SELECT * FROM {$this->prefix}_sessions WHERE end_time IS NULL";
		$result = $this->sql->query($query);
		
		//Fetch all retrived sessions and stick them into an array to return.
		$sessions = array();		
		if($result->num_rows > 0) 	
			while($row = $result->fetch_assoc()) $sessions[] = $row;		
				
		return $sessions;
	}
	#endregion
	
	#region User Authication
	public function registerUser($username, $email, $password, $realname = '', $gender = 'r', $country = '', $active = true, $token = '') {
		
		//Catch any errors that may occur.
		$errors = array();
		
		//Check if the gender input is valid.
		if($gender != 'r' && $gender != 'm' && $gender != 'f') $errors['gender'] = "Invalid gender character";
		
		//Check if the country code is valid.
		if(!empty($country) && !$this->validateCountry($country)) $errors['country'] = "Invalid country code";
	
		//Stick everything into an array so it can be modified and used more easily in the query.
		//While we are at it, make the username etc an escaped string to use with MySQL.
		$fields = array( 
			'name' => $this->sql->real_escape_string($username),
			'email' => $this->sql->real_escape_string($email),
			'password_sha512' => $this->sql->real_escape_string($this->encryptPassword($password)),
			'realname' => $this->sql->real_escape_string($realname),
			'gender' =>	$gender,
			'country' => $country,
			'registrationDate' => date('Y-m-d H:i:s'),
			'active' => $active,
			'token' => $this->sql->real_escape_string($token)
		);
		
		//Check if the username and/or email address is already taken.
		$query = "SELECT `name`, `email` FROM {$this->prefix}_users WHERE `name` = '{$fields['name']}' OR `email` = '{$fields['email']}'";
		$result = $this->sql->query($query);
		
		//We had a match, so we now go and create a specific error code for the user.
		if($result->num_rows != 0) {
			$match_username = false;
			$match_email = false;
			
			//Foreach match, check if it conflics with username, email or both.
			while($row = $result->fetch_assoc()) {
				if($row['name'] == $username) $match_username = true;
				if($row['email'] == $email) $match_email = true;
				if($match_username && $match_email) break;
			}			
			
			//Log each errors to the array to return.
			if($match_username) $errors['username'] = "Username already exists";
			if($match_email)	$errors['email']	= "Email already exists";
		}
		
		//If we have errors, return the list, proventing the creation of an account
		if(count($errors) > 0) return $errors;
		
		//Prepare the query for the new user.
		$query = "INSERT INTO {$this->prefix}_users ".
			"(`name`, `email`, `password_sha512`, `realname`, `gender`, `country`, `registrationDate`, `active`, `token`) VALUES (
				'". $fields['name']. "', 
				'". $fields['email']. "', 
				'". $fields['password_sha512']. "', 
				'". $fields['realname']. "', 
				'". $fields['gender']. "', 
				'". $fields['country']. "', 
				'". $fields['registrationDate']. "', 
				'". $fields['active']. "', 
				'". $fields['token']. "'
			)";
			
		//Send the query off.
		$this->sql->query($query);
		
		//TODO: Catch errors from the query and throw them here.
		return $errors;
	}
	
	public function getAuthicatedUser($username, $password) {
		//This checks if the users password is corrent and if it is, returns the ServatriceUser.

		//Retrive the user with username and get everything. Could use wildcard, but might give unwanted information in later versions of Servatrice.
		$query = "SELECT `password_sha512`, `id`, `admin`, `active`, `name`, `realname`, `email`, `token`, `gender`, `country`, `registrationDate`, `avatar_bmp` FROM {$this->prefix}_users WHERE `name` = '{$username}' LIMIT 1";
		$result = $this->sql->query($query);			
		
		//If don't have exactly 1 user, they don't exists or there has been a duplicated entry D:
		if($result->num_rows != 1) return null;
		$result = $result->fetch_assoc();
		
		//Generate a hashed password using the retrived salt
		$hashed_password = $this->encryptPassword($password, substr($result['password_sha512'], 0, 16));
				
		//If the password hash is not the same as the stored password, return null.
		if($hashed_password != $result['password_sha512']) return null;
		
		//Otherwise the user is all valid and we return the ServatriceUser of said user.
		return new ServatriceUser($result);	
	}
	public function getUser($user) {
	
		//Retrive all nessary information about the user. We do not want the password as it is unessary.
		$query = "SELECT `id`, `admin`, `active`, `name`, `realname`, `email`, `token`, `gender`, `country`, `registrationDate`, `avatar_bmp` FROM {$this->prefix}_users WHERE `username` = '{$user}' OR `id` = '{$user}'  LIMIT 1";
		$result = $this->sql->query($query);		

		//If don't have exactly 1 user, they don't exists or there has been a duplicated entry D:		
		if($result->num_rows != 1) return null;
		
		//Return the ServatriceUser of the queried user.
		return new ServatriceUser($result->fetch_assoc());		
	}
	public function updateUser($userid, $realname = '', $gender = 'r', $country = '') {
		//Update the users details. Avatar does not get updated here.
		
		//Prepare the error array.
		$errors = array();
		
		//Check if the gender input is valid.
		if($gender != 'r' && $gender != 'm' && $gender != 'f') $errors['gender'] = "Invalid gender character";
		
		//Check if the country code is valid.
		if(!empty($country) && !$this->validateCountry($country)) $errors['country'] = "Invalid country code";
		
		//Create SQL safe realname
		$realname = $this->sql->real_escape_string($realname);
				
		//Update all modified fields
		//TODO: Check if its correct the hardway for error reporting.
		$query = "UPDATE {$this->prefix}_users SET `realname` = '{$realname}', `gender` = '{$gender}', `country` = '{$country}' WHERE `id` = '{$userid}'";
		$this->sql->query($query);	
	}
	public function activateUser($username, $token) {
		//Make the username and token SQL safe
		$username = $this->sql->real_escape_string($username);
		$token = $this->sql->real_escape_string($token);
		
		//Update active to true were username and token matches.
		//TODO: Check if its correct the hardway for error reporting.
		$query = "UPDATE {$this->prefix}_users SET `active` = true WHERE `name` = '{$username}' AND `token` = '{$token}'";
		$this->sql->query($query);
	}
	public function updateUserPassword($userid, $password) {
		//Updates the user password.
		
		//==== NOTE ====
		// This method DOES NOT check permisions, it just does it without questions. Make sure you all ways check before using this function!
		
		$hashed_password = $this->sql->real_escape_string($this->encryptPassword($password));		
		
		//TODO: Check if its correct the hardway for error reporting.
		$query = "UPDATE {$this->prefix}_users SET `password_sha512` = '{$hashed_password}' WHERE `id` = '{$userid}'";
		$this->sql->query($query);		
	}
	public function setUser($user) {
		//Sets the Servatrice user.
		
		//==== NOTE ====
		// This method DOES NOT check permisions, it just does it without questions. Make sure you all ways check before using this function!
		//Catch any errors that may occur.
		
		$errors = array();
		
		//Check if the gender input is valid.
		if($user->gender != 'r' && $user->gender != 'm' && $user->gender != 'f') $errors['gender'] = "Invalid gender character";
		
		//Check if the country code is valid.
		if(!empty($user->country) && !$this->validateCountry($user->country)) $errors['country'] = "Invalid country code";
	
		//Stick everything into an array so it can be modified and used more easily in the query.
		//While we are at it, make the username etc an escaped string to use with MySQL.
		$fields = array( 
			'name' => $this->sql->real_escape_string($user->name),
			'email' => $this->sql->real_escape_string($user->email),
			'realname' => $this->sql->real_escape_string($user->realname),
			'gender' =>	$user->gender,
			'country' => $user->country,
			'registrationDate' => $user->registrationDate,
			'active' => $user->active,
			'token' => $this->sql->real_escape_string($user->token)
		);
						
		//If we have errors, return the list, proventing the creation of an account
		if(count($errors) > 0) return $errors;
		
		//Prepare the query for the updated user.
		$query = "UPDATE {$this->prefix}_users SET 
				`name` = '". $fields['name']. "', 
				`email` = '". $fields['email']. "',  
				`realname` = '". $fields['realname']. "', 
				`gender` = '". $fields['gender']. "', 
				`country` = '". $fields['country']. "', 
				`registrationDate` = '". $fields['registrationDate']. "', 
				`active` = '". $fields['active']. "', 
				`token` = '". $fields['token']. "'
			WHERE `id` = '". $user->id ."'";
			
		//Send the query off.
		$this->sql->query($query);
		
		//TODO: Catch errors from the query and throw them here.
		return $errors;
	}
	
	
	#endregion
	
	#region helpers
	public function validateCountry($code) {
		//Validate the country code by checking through each individual code and check for a match.
		foreach($this->countries as $ccode => $name) if($code == $ccode) return true;	
		return false;
	}

	public function generateToken() {	
		//Generates a random token that is 16 long for use in email activation. 
		//When creating an account, generate a token using this method and pass it to the registerUser function. 
		//Once the user has registered, email the token and a link to a page with authicated it with {TODO: Create token authication method}.
	
		//Prepare the token variable
		$token = '';
		
		//All possible characters for the token. Could use special characters (eg: !@#$%^&*()_-+=) but it might break URL's if activation link uses GET method.
		$tokenChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		
		//Generate the 16 long random token using selected possible characters
		for ($i = 0; $i < 16; ++$i) $token .= $tokenChars[rand(0, strlen($tokenChars) - 1)];
		
		//Return the token
		return $token;
	}
	public function encryptPassword($password, $salt = '') {
		if ($salt == '') {
			$saltChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			for ($i = 0; $i < 16; ++$i) $salt .= $saltChars[rand(0, strlen($saltChars) - 1)];
		}
		$key = $salt . $password;
		for ($i = 0; $i < 1000; ++$i) $key = hash('sha512', $key, true);
		return $salt . base64_encode($key);
	}

	public static function getGenderChar($value) {
		//Converts a number into the appropriate gender character
		switch($value) { 
			default: return 'r';
			case 1: return 'm';
			case 2: return 'f';
		}
	}	
	public static function getGenderId($value) {
		//Converts a gender character into appropriate number
		switch($value) { 
			default: return 0;
			case 'm': return 1;
			case 'f': return 2;
		}
	}
	#endregion
}

//The servatrice user
class ServatriceUser {
	//Unique ID of the user
	public $id = 0;
	
	//The admin level of this user. 
	//0 = user, 1 = moderator, 2 = admin
	public $admin = 0;
	
	//If this account is active.
	public $active = 0;
	
	//The username of this user
	public $name = "Guest";	
	
	//The display name of this user
	public $realname = "Guest";
	
	//Email of this user. Should not be displayed, only used for administrative purposes.
	public $email = "";
	
	//The token used to authicate a valid email account (generated via servatrice->generateToken)
	public $authToken = "";
	
	//User's gender.
	//'r' = Robot, 'm' = Male, 'f' = Female
	public $gender = 'r';
	
	//The 2 character country code. Validate via servatrice->validateCountry.
	public $country = '';
	
	//The day the user registrated
	public $registrationDate = "";
	
	//TODO: Implement avatar use. Maybe a link to a php script that generates the image and caches it?
	public $avatar_bmp = null;
	
	public function __construct($data) {
		//Wooooh! This is a construction zone, wear your hardhat -> (|:P
		$this->id = $data['id'];
		$this->admin = $data['admin'];
		$this->active = $data['active'];
		$this->name = $data['name'];
		$this->realname = $data['realname'];
		$this->email = $data['email'];
		$this->authToken = $data['token'];
		$this->gender = $data['gender'];
		$this->country = $data['country'];
		$this->registrationDate = $data['registrationDate'];
		
		//TODO: Make this work as stated above.
		$this->avatar_bmp = $data['avatar_bmp'];
	}
}
?>