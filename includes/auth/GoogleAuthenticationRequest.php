<?php
/**
 * Created by PhpStorm.
 * User: Florian
 * Date: 19.05.2016
 * Time: 18:17
 */

namespace GoogleLogin\Auth;

use GoogleLogin\GoogleUser;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;

class GoogleAuthenticationRequest extends ButtonAuthenticationRequest {
	public function getFieldInfo() {
		if ( $this->action === AuthManager::ACTION_REMOVE ) {
			return [ ];
		}
		return parent::getFieldInfo();
	}

	public function describeCredentials() {
		return [
			'provider' => wfMessage( 'googlelogin-auth-service-name' ),
			'account' =>
				new \RawMessage( '$1', [ GoogleUser::getGoogleIdFromUser( \User::newFromName( $this->username ) )[0] ] ),
		];
	}
}
