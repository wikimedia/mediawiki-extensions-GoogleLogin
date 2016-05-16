<?php

namespace GoogleLogin\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

use GoogleLogin\GoogleUser;
use GoogleLogin;

use StatusValue;

/**
 * Created by PhpStorm.
 * User: Florian
 * Date: 16.05.2016
 * Time: 23:50
 */
class GooglePrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {
	public function beginPrimaryAuthentication( array $reqs ) {
		$req = ButtonAuthenticationRequest::getRequestByName( $reqs, 'googlelogin' );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$googleLogin = new GoogleLogin;
		$client = $googleLogin->getClient();
		$plus = $googleLogin->getPlus();

		return AuthenticationResponse::newRedirect( [ $req ], $client->createAuthUrl() );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		return AuthenticationResponse::FAIL;
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new ButtonAuthenticationRequest( 'googlelogin', wfMessage( 'googlelogin' ), new \RawMessage( 'no help yet' ) ) ];
				break;
			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return GoogleUser::newFromName( $username )->exists();
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		// TODO: Is this for changing the google id, too?
		return StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		// TODO: Remove google id from user here?
	}

	public function accountCreationType() {
		return self::TYPE_CREATE;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}
}
