<?php

namespace RocketChat;

use Httpful\Request;

class Client{

	public $api;

	function __construct($url){
		$this->api = $url;

		// set template request to send and expect JSON
		$tmp = Request::init()
			->sendsJson()
			->expectsJson();
		Request::ini( $tmp );
	}

	protected function createExceptionFromResponse($response, $prefix){
        if(!empty($response->body->error)){
            return new \Exception("$prefix: ".$response->body->error);
        } else if(!empty($response->body)){
            return new \Exception("$prefix: ".$response->body);
        } else {
            return new \Exception("$prefix: unknown error!");
        }
    }

	/**
	* Quick information about the authenticated user.
	*/
	public function me() {
		$response = Request::get( $this->api . 'me' )->send();

		if( $response->body->status != 'error' ) {
			if( isset($response->body->success) && $response->body->success == true ) {
				return $response->body;
			}
		}
        throw $this->createExceptionFromResponse($response, "Could not list channels");
	}

	/**
	* List all of the users and their information.
	*
	* Gets all of the users in the system and their information, the result is
	* only limited to what the callee has access to view.
	*/
	public function list_users(){
		$response = Request::get( $this->api . 'users.list?count=0' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response->body->users;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not list users");
		}
	}

	/**
	* List the private groups the caller is part of.
	*/
	public function list_groups() {
		$response = Request::get( $this->api . 'groups.list?count=0' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$groups = array();
			foreach($response->body->groups as $group){
				$groups[] = new Group($group);
			}
			return $groups;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not list groups");
		}
	}

	/**
	 * List all private groups
	 */
	public static function list_all_groups($url) {
		$response = Request::get( $url . 'groups.listAll?count=0' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$groups = array();
			foreach($response->body->groups as $group){
				$groups[] = new Group($url, $group);
			}
			return $groups;
		} else {
			throw self::createExceptionFromResponse($response, "Could not list groups");
		}
	}

	/**
	* List the channels the caller has access to.
	*/
	public function list_channels() {
		$response = Request::get( $this->api . 'channels.list?count=0' )->send();

		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			$groups = array();
			foreach($response->body->channels as $group){
				$groups[] = new Channel($group);
			}
			return $groups;
		} else {
            throw $this->createExceptionFromResponse($response, "Could not list channels");
		}
	}

	/**
	 * List all permissions
	 */
	public function list_permissions() {
		$response = Request::get( $this->api . 'permissions.listAll' )->send();
		if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
			return $response;
		} else {
			throw $this->createExceptionFromResponse($response, "Could not list permissions");
		}
	}
}
