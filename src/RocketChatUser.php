<?php
namespace RocketChat;
use Httpful\Request;
use RocketChat\Client;
class User extends Client {
	public $username;
	private $password;
	public $id;
	public $nickname;
	public $email;
	public $customFields;

	public function __construct($url, $username, $password, $fields = array()){
		parent::__construct($url);
		$this->username = $username;
		$this->password = $password;
		if( isset($fields['nickname']) ) {
			$this->nickname = $fields['nickname'];
		}
		if( isset($fields['email']) ) {
			$this->email = $fields['email'];
		}
		if( isset($fields['customFields']) ) {
			$this->customFields = $fields['customFields'];
		}
	}

	/**
	 * Gets a user’s information, limited to the caller’s permissions.
	 */
	public function info() {
		$response = Request::get( $this->api . 'users.info?userId=' . $this->id )->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->id = $response->body->user->_id;
			$this->nickname = $response->body->user->name;
			$this->email = $response->body->user->emails[0]->address;
			return $response->body;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not get user's information");
		}
	}

	/**
	 * Authenticate with the REST API.
	 */
	public function login($save_auth = true) {
		$response = Request::post( $this->api . 'login' )
			->body(array( 'user' => $this->username, 'password' => $this->password ))
			->send();
		if( $response->code == 200 && isset($response->body->status) && $response->body->status == 'success' ) {
			if( $save_auth) {
				// save auth token for future requests
				$tmp = Request::init()
					->addHeader('X-Auth-Token', $response->body->data->authToken)
					->addHeader('X-User-Id', $response->body->data->userId);
				Request::ini( $tmp );
			}
			$this->id = $response->body->data->userId;
			return $response->body->data;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not authenticate with the REST API");
		}
	}

	/**
	 * Create a new user.
	 * for customFields we have to send json-object so we use json_decode with inner json_encode
	 */
	public function create() {
		$bodydata=array(
			'name' => $this->nickname,
			'email' => $this->email,
			'username' => $this->username,
			'password' => $this->password,
		);
		if (isset($this->customFields))
			$bodydata['customFields'] = json_decode(json_encode($this->customFields), JSON_FORCE_OBJECT);
		$response = Request::post( $this->api . 'users.create' )
			->body($bodydata)
			->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
//			$this->id = $response->body->user->_id;
			return $response->body->user->_id;
		} else {
//			echo( $response->body->error . "\n" );
			return false;
		}
	}

	/**
	 * Update a user.
	 * for data we have to send json-object so we use json_decode with inner json_encode
	 * using the attributes of this class as update-values
	 */
	public function update($rc_display_name, $rc_username, 	$id) {

		$response = Request::post( $this->api . 'users.update' )
			->body(array(
				'userId' => $id,
				'data' => json_decode(json_encode(array('name' => $rc_display_name, 'username' => $rc_username), JSON_FORCE_OBJECT)),
				//'data' => json_decode(json_encode(array('name' => $this->nickname,'email'=>$this->email,'username'=>$this->username,'password'=>$this->password), JSON_FORCE_OBJECT)),
			))
			->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->id = $response->body->user->_id;
			return $response->body->user;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not create new user");
		}
	}

	/**
	 * Deletes an existing user.
	 */
	public function delete($args) {
		// get user ID if needed
		/*if( !isset($this->id) ){
			$this->me();
		}*/
		$response = Request::post( $this->api . 'users.delete' )
			->body(array('username' => $args['realName']))
			->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not delete user");
		}
	}


	public static function getStatus($user) {
		// Get User Presence
		$response = Request::get( 'http://localhost:3000/api/v1/users.getPresence?username='.$user )->send();
		$presence = $response->body->presence;

		// Get User Info u5KDkZsLEpHr8JrRc
		// $response = Request::get( 'http://localhost:3000/api/v1/users.info?userId='.$user )->send();
		// $presence = $response->body->user->statusText;

		return $presence;
	}

	public static function setStatus($rc_user, $set) {
		$response = Request::post( 'http://localhost:3000/api/v1/users.setStatus' )
			->body(array(
				'userId' => $rc_user,
				'status' => $set,
				'message' => $set,
			))
			->send();
		return $response;
	}

	public function userid($uname) {
		$response = Request::get( 'http://localhost:3000/api/v1/users.info?username=' . $uname )->send();
		return $response->body->user->_id;
	}

	public function get_group($args) {
		$response = Request::get( $this->api . 'groups.info?roomName=' . $args )->send();
		if(isset($response->body->group->_id))
			return $response->body->group->_id;
		else {
			return $this->create_group($args);
		}
	}

	public function create_group($args) {
		$bodydata=array(
			'name' => $args
		);
		if (isset($this->customFields))
			$bodydata['customFields'] = json_decode(json_encode($this->customFields), JSON_FORCE_OBJECT);
		$response = Request::post( $this->api . 'groups.create' )
			->body($bodydata)
			->send();
//		echo json_encode($response);
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
//			$this->id = $response->body->group->_id;
			return $response->body->group->_id;
		} else {
//			echo( $response->body->error . "\n" );
			return false;
		}
	}




	public static function users() {
		// Get User Presence
		$rc_users = array();
		$users = Request::get( 'http://localhost:3000/api/v1/users.list' )->send();
		foreach($users->body->users AS $user) {
			if ($user->username != 'admin' && $user->username != 'rocket.cat') {
				/*echo $user->username;
				echo $user->name;
				print_r($user->emails[0]->address);
				echo "\n";*/
				$rc_users[] = $user->name;
			}
		}
		return $rc_users;
	}



}
