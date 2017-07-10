<?php

namespace SafePal;

use Predis as redis;

//register Redis
redis\Autoloader::register();

//use \Firebase\JWT\JWT;

/**
* Handles all authentication work with db
*/
final class SafePalAuth
{
	protected $redis;
	protected $db;

	function __construct()
	{
		try {
			$this->redis = new redis\Client(getenv('REDIS_URL'));
			$this->redis = ((getenv('APP_ENV') == 'dev') ? new redis\Client(getenv('REDIS_URL')) : new redis\Client([
				'host'   => getenv('REDIS_HOST'),
				'password' => getenv('REDIS_PWD'), 
				'port'   => getenv('REDIS_PORT'),]));
		} catch (Exception $e) {

			throw new Exception($e->getMessage(), 1);
		}

		$this->db = new SafePalDB();
	}

	//get token
	public function GetToken($userid){
		$token = $this->GenerateToken();
		//cache with redis
		$this->redis->set("{$token}", "{$userid}");  //--NOTE: MUST BE CAST AS STRINGS!!
		$this->redis->expire($token, ((getenv('APP_ENV') == 'dev') ? getenv('DEV_REDIS_EXPIRE') : getenv('REDIS_EXPIRE')));
		return $token;
	}

	//generate token
	protected function GenerateToken(){
		$token = bin2hex(openssl_random_pseudo_bytes(getenv('TOKEN_SIZE')));
		return $token;
	}

	//check if token-user match exists
	public function CheckToken($token, $user){
		if ($this->ValidateUser($user)) {
			return ($this->redis->exists($token)) ? true : false;
		}
	}

	//validate user
	public function ValidateUser($userid){
		$user = $this->db->CheckUser($userid);

		if (!$user) {
			return false;
		}

		return true;
	}

	//authenticate user
	public function CheckAuth($username, $hash){
		return $this->db->CheckAuth($username, $hash);
	}

	//logout
	public function LogAccess($user, $eventType){
		return $this->db->LogAccess($user, $eventType);
	}

}

?>
