<?php

namespace RocketChat;

use Httpful\Request;
use RocketChat\Client;

class Group extends Client {

	public $id;
	public $name;
	public $members = array();

	public function __construct($url, $name, $members = array()){
		parent::__construct($url);
		if( is_array($name) ) {
			$name = (object) $name;
		}
		if( is_string($name) ) {
			$this->name = $name;
		} else {
			if( isset($name->_id) ) {
				$this->id = $name->_id;
			}
			if( isset($name->name) ) {
				$this->name = $name->name;
			}
		}
		foreach($members as $member){
			if( is_a($member, '\RocketChat\User') ) {
				$this->members[] = $member;
			} else if( is_string($member) ) {
				$this->members[] = new User($url, $member, '');
			}
		}
	}

	/**
	* Creates a new private group.
	*/
	public function create(){
		// get user ids for members
		$members_id = array();
		foreach($this->members as $member) {
			if( isset($member->username) && is_string($member->username) ) {
				$members_id[] = $member->username;
			}
		}

		$response = Request::post( $this->api . 'groups.create' )
			->body(array('name' => $this->name, 'members' => $members_id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->id = $response->body->group->_id;
			return $response->body->group;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not create a private group");
		}
	}

	/**
	* Retrieves the information about the private group, only if you’re part of the group.
	*/
	public function info() {
		$response = Request::get( $this->api . 'groups.info?roomName=' . $this->name )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$this->id = $response->body->group->_id;
			return $response->body;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not get info about the group");
		}
	}

	/**
	* Post a message in this group, as the logged-in user
	*/
	public function postMessage( $text ) {
		$message = is_string($text) ? array( 'text' => $text ) : $text;
		if( !isset($message['attachments']) ){
			$message['attachments'] = array();
		}

		$response = Request::post( $this->api . 'chat.postMessage' )
			->body( array_merge(array('channel' => '#'.$this->name), $message) )
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not post message");
        }
	}

	/**
	* Removes the private group from the user’s list of groups, only if you’re part of the group.
	*/
	public function close(){
		$response = Request::post( $this->api . 'groups.close' )
			->body(array('roomId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not remove private group");
		}
	}

	/**
	* Deletes the private group.
	*/
	public function delete(){
		if (!empty($this->id)) {
			$response = Request::post( $this->api . 'groups.delete' )
				->body(array('roomId' => $this->id))
				->send();
		} else {
			$response = Request::post( $this->api . 'groups.delete' )
				->body(array('roomName' => $this->name))
				->send();
		}
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not delete a private group");
		}
	}

	/**
	* Removes a user from the private group.
	*/
	public function kick( $user ){
		if (empty($this->id)) {
			$this->info();
		}

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.kick' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not kick user $user from group");
		}
	}

	/**
	 * Adds user to the private group.
	 */
	public function invite( $user ) {
		if (empty($this->id)) {
			$this->info();
		}

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.invite' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not invite user $user to the private group");
        }
	}

    /**
	 * Adds owner to the private group.
	 */
	public function addOwner( $user ) {
		if (empty($this->id)) {
			$this->info();
		}

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.addOwner' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not add user $user as owner of private group");
		}
	}

	/**
	 * Removes owner of the private group.
	 */
	public function removeOwner( $user ) {
		if (empty($this->id)) {
			$this->info();
		}

		$userId = is_string($user) ? $user : $user->id;

		$response = Request::post( $this->api . 'groups.removeOwner' )
			->body(array('roomId' => $this->id, 'userId' => $userId))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not remove user $user as owner of private group");
		}
	}

	/**
	 * Renames a group
	 */
	public function rename( $newname ) {
		if (empty($this->id)) {
			$this->info();
		}

		$response = Request::post( $this->api . 'groups.rename' )
			->body(array('roomId' => $this->id, 'name' => $newname))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not rename private group");
		}
	}

	/**
	 * Set Topic
	 */
	public function setTopic( $topic ) {
		if (empty($this->id)) {
			$this->info();
		}

		$response = Request::post( $this->api . 'groups.setTopic' )
			->body(array('roomId' => $this->id, 'topic' => $topic))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not set the name of a private group");
		}
	}

	/**
	 * Leave group (of calling user)
	 */
	public function leave() {
		if (empty($this->id)) {
			$this->info();
		}

		$response = Request::post( $this->api . 'groups.leave' )
			->body(array('roomId' => $this->id))
			->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return true;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not leave private group");
		}
	}
}

