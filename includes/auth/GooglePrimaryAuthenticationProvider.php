<?php

namespace GoogleLogin\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

use GoogleLogin\GoogleUser;
use GoogleLogin;

use Google_Service_Plus;

use StatusValue;
use SpecialPage;

/**
 * Created by PhpStorm.
 * User: Florian
 * Date: 16.05.2016
 * Time: 23:50
 */
class GooglePrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {
	const RETURNURL_SESSION_KEY = 'googleLoginReturnToUrl';
	const TOKEN_SALT = 'GooglePrimaryAuthenticationProvider:redirect';

	public function beginPrimaryAuthentication( array $reqs ) {
		$req = ButtonAuthenticationRequest::getRequestByName( $reqs, 'googlelogin' );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$client = $this->getGoogleClient();
		$this->manager->setAuthenticationSessionData( self::RETURNURL_SESSION_KEY, $req->returnToUrl );

		return AuthenticationResponse::newRedirect( [ new GoogleServerAuthenticationRequest() ], $client->createAuthUrl() );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			throw new \LogicException( 'Continue called without appropriate AuthenticationRequest' );
		}
		$client = $this->getGoogleClient();
		$client->authenticate( $request->accessToken );
		$plus = new Google_Service_Plus( $client );
		try {
			$userInfo = $plus->people->get( "me" );
			$glUser = GoogleUser::newFromGoogleId( $userInfo['id'] );
			if ( $glUser->hasConnectedGoogleAccount() ) {
				return AuthenticationResponse::newPass( $glUser->getName() );
			} else {
				// TODO Should be a newUI call to ask for the username to create an account or to link to an
				// existing one.
				return AuthenticationResponse::newFail( new \RawMessage( 'not implemented yet' ) );
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail( new \RawMessage( $e->getMessage() ) );
		}
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
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return AuthenticationResponse::newAbstain();
	}

	public function getGoogleClient() {
		// TODO Probably remove this class after the extension is broken down to authentication only
		$googleLogin = new GoogleLogin;
		$client = $googleLogin->getClient(
			SpecialPage::getTitleFor( 'GoogleLoginReturn' )->getFullURL(),
			$this->manager->getRequest()->getSession()->getToken( self::TOKEN_SALT )->toString()
		);

		return $client;
	}
}
