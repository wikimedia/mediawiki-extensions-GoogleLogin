<?php
/**
 * GoogleUserInfoAuthenticationRequest implementation
 */

namespace GoogleLogin\Auth;

use GoogleLogin\GoogleUser;
use MediaWiki\Auth\AuthenticationRequest;

/**
 * An AuthenticationRequest that holds Google user information.
 */
class GoogleUserInfoAuthenticationRequest extends AuthenticationRequest {

	/** @var array An array of infos (provided by Google)
	 * about a user.
	 */
	public $userInfo;

	public function __construct( $userInfo ) {
		$this->userInfo = $userInfo;
		$this->required = self::OPTIONAL;
	}

	public function getFieldInfo() {
		return [];
	}

	public function describeCredentials() {
		$googleUser = GoogleUser::newFromUserInfo( $this->userInfo );
		return [
			'provider' => wfMessage( 'googlelogin-auth-service-name' ),
			'account' =>
				$googleUser ? new \RawMessage( '$1', [ $googleUser->getEmailWithId() ] ) :
					wfMessage( 'googlelogin-auth-service-unknown-account' )
		];
	}
}
