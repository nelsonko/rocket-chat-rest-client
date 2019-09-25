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
		$response = Request::get( $this->api . 'users.info?username=' . $this->username )->send();
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
	 * Invalidates current token and optionally additional token
	 * @param string $token Provides additional token to invalidate, a session token usually
	 * @return bool
	 * @throws \Httpful\Exception\ConnectionErrorException
	 */
	public function logout($token = '') {
		$response = Request::post( $this->api . 'logout' )->send();
		if ($token) {
			$tmp = Request::init()
				->addHeader('X-Auth-Token', $token)
				->addHeader('X-User-Id', $this->id);
			Request::ini( $tmp );
			$response = Request::post( $this->api . 'logout' )->send();
		}
		if( $response->code == 200 && isset($response->body->status) && $response->body->status == 'success' ) {
			return true;
		} else {
			return false;
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
			return $response->body->user;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not create new user");
		}
	}

	/**
	 * Update a user.
	 * for data we have to send json-object so we use json_decode with inner json_encode
	 * using the attributes of this class as update-values
	 */
	public function update($new_display_name, $new_username, $new_email) {
		if (empty($this->id)) {
			$this->info();
		}
		$response = Request::post( $this->api . 'users.update' )
			->body(array(
				'userId' => $this->id,
				'data' => json_decode(json_encode(array('name' => $new_display_name, 'username' => $new_username, 'email' => $new_email), JSON_FORCE_OBJECT)),
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
	public function delete() {
		$response = Request::post( $this->api . 'users.delete' )
			->body(array('username' => $this->username))
			->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not delete user");
		}
	}

	public function getStatus() {
		// Get User Presence
		$response = Request::get( $this->api . 'users.getPresence?username='.$this->username )->send();
		$presence = $response->body->presence;
		return $presence;
	}

	/**
	 * Set status for currently logged in user (Warning: does not take userId param, not yet anyway)
	 * @param $presence Either online, offline, away or busy
	 * @param string $message Custom status message to add
	 * @return \Httpful\Response
	 * @throws \Httpful\Exception\ConnectionErrorException
	 */
	public function setStatus($presence, $message = '') {
		if (empty($this->id)) {
			$this->info();
		}
		$response = Request::post( $this->api  . 'users.setStatus' )
			->body(array(
				'status' => $presence,
				'message' => $message
			))
			->send();
		return $response;
	}

	public function invite($groupName)
	{
		$response = Request::get($this->api . 'groups.info?roomName=' . $groupName)->send();
		if (isset($response->body->group->_id)) {
			$groupId = $response->body->group->_id;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not get info about the group");
		}
		if (empty($this->id)) {
			$this->info();
		}
		$response = Request::post( $this->api . 'groups.invite' )
			->body(array(
				'userId' => $this->id,
				'roomId' => $groupId
			))
			->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not invite user to group");
		}
	}

	public function kick($groupName) {
		$response = Request::get($this->api . 'groups.info?roomName=' . $groupName)->send();
		if (isset($response->body->group->_id)) {
			$groupId = $response->body->group->_id;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not get info about the group");
		}
		if (empty($this->id)) {
			$this->info();
		}
		$response = Request::post( $this->api . 'groups.kick' )
			->body(array(
				'userId' => $this->id,
				'roomId' => $groupId
			))
			->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not kick user from group");
		}
	}

	/**
	 * Become owner of the private group.
	 */
	public function beGroupOwner( $groupId ) {
		if (empty($this->id)) {
			$this->info();
		}

		$response = Request::post( $this->api . 'groups.addOwner' )
			->body(array('roomId' => $groupId, 'userId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not add user as owner of private group");
		}
	}

	public function unread_count() {
		$response = Request::get( $this->api . 'subscriptions.get' )->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response->body->update;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not get user's information");
		}
	}
	
}
