<?php
/**
 * Created by PhpStorm.
 * User: Florian
 * Date: 19.05.2016
 * Time: 23:00
 */

namespace GoogleLogin\Auth;

use GoogleLogin\GoogleUser;
use MediaWiki\Auth\AuthenticationRequest;

class GoogleUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;
	public $userInfo;

	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}

	public function getFieldInfo() {
		return [];
	}

	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'googlelogin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $this->userInfo['id'] ] ),
		];
	}
}
