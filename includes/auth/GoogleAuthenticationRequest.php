<?php
/**
 * GoogleAuthenticationRequest implementation
 */

namespace GoogleLogin\Auth;

use GoogleLogin\GoogleUser;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;

/**
 * Implements a GoogleAuthenticationRequest by extending a ButtonAuthenticationRequest
 * and describes the credentials used/needed by this AuthenticationRequest.
 */
class GoogleAuthenticationRequest extends ButtonAuthenticationRequest {
	public function getFieldInfo() {
		if ( $this->action === AuthManager::ACTION_REMOVE ) {
			return [];
		}
		return parent::getFieldInfo();
	}

	public function describeCredentials() {
		$user = \User::newFromName( $this->username );
		if ( $user ) {
			$googleIds = GoogleUser::getGoogleIdFromUser( $user );
			if ( $googleIds ) {
				return [
					'provider' => wfMessage( 'googlelogin-auth-service-name' ),
					'account' => new \RawMessage( '$1', [ $googleIds[0] ] ),
				];
			}
		}
		return parent::describeCredentials();
	}
}
