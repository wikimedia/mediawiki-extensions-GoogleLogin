<?php
/**
 * Created by PhpStorm.
 * User: Florian
 * Date: 17.05.2016
 * Time: 18:42
 */

namespace GoogleLogin\Auth;

use MediaWiki\Auth\AuthenticationRequest;

class GoogleServerAuthenticationRequest extends AuthenticationRequest {
	/**
	 * Verification code provided by the server. Needs to be sent back in the last leg of the
	 * authorization process.
	 * @var string
	 */
	public $accessToken;

	/**
	 * An error code returned in case of Authentication failure
	 * @var string
	 */
	public $errorCode;

	public function getFieldInfo() {
		return array(
			'error' => array(
				'type' => 'string',
			),
			'code' => array(
				'type' => 'string',
			),
		);
	}

	/**
	 * Load data from query parameters in an OAuth return URL
	 * @param array $data Submitted data as an associative array
	 * @return AuthenticationRequest|null
	 */
	public function loadFromSubmission( array $data ) {
		if ( isset( $data['code'] ) ) {
			$this->accessToken = $data['code'];
			return true;
		}

		if ( isset( $data['error'] ) ) {
			$this->errorCode = $data['error'];
			return true;
		}
		return false;
	}
}
