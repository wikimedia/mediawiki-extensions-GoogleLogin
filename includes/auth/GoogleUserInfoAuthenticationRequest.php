<?php
/**
 * GoogleUserInfoAuthenticationRequest implementation
 */

namespace GoogleLogin\Auth;

use GoogleLogin\GoogleUser;
use MediaWiki\Auth\AuthenticationRequest;

/**
 * An AUthenticationRequest that holds Google user information.
 */
class GoogleUserInfoAuthenticationRequest extends AuthenticationRequest {
	public $required = self::OPTIONAL;
	/** @var array An array of infos (provided by Google) about a user. */
	public $userInfo;

	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
	}

	public function getFieldInfo() {
		return [];
	}

	public function describeCredentials() {
		$googleUser = GoogleUser::newFromUserInfo( $this->userInfo );
		if ( !$googleUser ) {
			return [];
		}
		return [
			'provider' => wfMessage( 'googlelogin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $googleUser->getFullNameWithId() ] ),
		];
	}
}
