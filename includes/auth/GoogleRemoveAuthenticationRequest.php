<?php
/**
 * GoogleRemoveAuthenticationRequest implementation
 */

namespace GoogleLogin\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;

/**
 * Implementation of an AuthenticationReuqest that is used to remove a
 * connection between a Google account and a local wiki account.
 */
class GoogleRemoveAuthenticationRequest extends AuthenticationRequest {
	private $googleId = null;

	public function __construct( $googleId ) {
		$this->googleId = $googleId;
	}

	public function getUniqueId() {
		return parent::getUniqueId() . ':' . $this->googleId;
	}

	public function getFieldInfo() {
		if ( $this->action === AuthManager::ACTION_REMOVE ) {
			return [ ];
		}
		return parent::getFieldInfo();
	}

	/**
	 * Returns the Google ID, that should be removed from the valid
	 * credentials of the user.
	 *
	 * @return String
	 */
	public function getGoogleId() {
		return $this->googleId;
	}

	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'googlelogin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ $this->googleId ] ),
		];
	}
}
