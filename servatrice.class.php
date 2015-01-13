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
	public $prefix = "cockatrice";
	protected $sql;
	
	#region countries
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
		$this->sql = new mysqli($host, $username, $password, $database);
		if($this->sql->connect_error) die("Servatrice Connection Failed: " . $this->sql->connect_error);
	}
	
	#region Statictics
	public function getOnlineSessions() {

		$query = "SELECT * FROM {$this->prefix}_sessions WHERE end_time IS NULL";
		$result = $this->sql->query($query);
		
		$sessions = array();
		
		if($result->num_rows > 0) 	
			while($row = $result->fetch_assoc()) $sessions[] = $row;		
		
		
		return $sessions;
	}
	#endregion
	
	#region User Authication
	public function registerUser($username, $email, $password, $realname = '', $gender = 'r', $country = '', $active = true, $token = '') {
		
		$errors = array();
		if($gender != 'r' && $gender != 'm' && $gender != 'f') $errors['gender'] = "Invalid gender character";
		if(!empty($country) && !$this->validateCountry($country)) $errors['country'] = "Invalid country code";
	
		//First stick everything into a nice array list.
		$fields = array( 
			'name' => $this->sql->real_escape_string($username),
			'email' => $this->sql->real_escape_string($email),
			'password_sha512' => $this->sql->real_escape_string(Servatrice::encryptPassword($password)),
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
			
			while($row = $result->fetch_assoc()) {
				if($row['name'] == $username) $match_username = true;
				if($row['email'] == $email) $match_email = true;
				if($match_username && $match_email) break;
			}			
			
			if($match_username) $errors['username'] = "Username already exists";
			if($match_email)	$errors['email']	= "Email already exists";
		}
		
		if(count($errors) > 0) return $errors;
		
		//We had no matches, so it is safe to register the users.
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
			
		$this->sql->query($query);
		return $errors;
	}
	
	public function getAuthicatedUser($username, $password) {
		$query = "SELECT `password_sha512`, `id`, `admin`, `active`, `name`, `realname`, `email`, `token`, `gender`, `country`, `registrationDate`, `avatar_bmp` FROM {$this->prefix}_users WHERE `name` = '{$username}' LIMIT 1";
		
		$result = $this->sql->query($query);			
		if($result->num_rows != 1) return null;
		$result = $result->fetch_assoc();
		
		$hashed_password = Servatrice::encryptPassword($password, substr($result['password_sha512'], 0, 16));
				
		if($hashed_password != $result['password_sha512']) return null;
		
		return new ServatriceUser($result);	
	}
	public function getUser($user) {
		$query = "SELECT `id`, `admin`, `active`, `name`, `realname`, `email`, `token`, `gender`, `country`, `registrationDate`, `avatar_bmp` FROM {$this->prefix}_users WHERE `username` = '{$user}' OR `id` = '{$user}'  LIMIT 1";
		$result = $this->sql->query($query);
			
		if($result->num_rows != 1) return null;
	
		return new ServatriceUser($result->fetch_assoc());		
	}
	#endregion
	
	#region helpers
	public function validateCountry($code) {
		foreach($this->countries as $ccode => $name) if($code == $ccode) return true;	
		return false;
	}
	#endregion
	
	#region Statics
	public static function generateToken() {	
		$token = '';
		$tokenChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		for ($i = 0; $i < 16; ++$i) $token .= $tokenChars[rand(0, strlen($tokenChars) - 1)];
		return $token;
	}
	public static function encryptPassword($password, $salt = '') {
		if ($salt == '') {
			$saltChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			for ($i = 0; $i < 16; ++$i) $salt .= $saltChars[rand(0, strlen($saltChars) - 1)];
		}
		$key = $salt . $password;
		for ($i = 0; $i < 1000; ++$i) $key = hash('sha512', $key, true);
		return $salt . base64_encode($key);
	}
	public static function getGenderChar($value) {
		switch($value) { 
			default: return 'r';
			case 1: return 'm';
			case 2: return 'f';
		}
	}	
	public static function getGenderId($value) {
		switch($value) { 
			default: return 0;
			case 'm': return 1;
			case 'f': return 2;
		}
	}
	#endregion
}

class ServatriceUser {
	public $id = 0;
	public $admin = 0;
	public $active = 0;
	
	public $name = "Guest";	
	public $realname = "Guest";
	public $email = "";
	public $authToken = "";
	
	public $gender = 'r';
	public $country = '';
	
	public $registrationDate = "";
	public $avatar_bmp = null;
	
	public function __construct($data) {
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
		$this->avatar_bmp = $data['avatar_bmp'];
	}
}
?>