<?php

namespace GoogleLogin\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\ButtonAuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

use GoogleLogin\GoogleUser;
use GoogleLogin\GoogleLogin;

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
	const GOOGLELOGIN_BUTTONREQUEST_NAME = 'googlelogin';

	public function beginPrimaryAuthentication( array $reqs ) {
		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
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
			$user = GoogleUser::getUserFromGoogleId( $userInfo['id'] );
			if ( $user ) {
				return AuthenticationResponse::newPass( $user->getName() );
			} else {
				// TODO NewPass isn't what we want here, see T134952
				return AuthenticationResponse::newPass();
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail( new \RawMessage( $e->getMessage() ) );
		}
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new ButtonAuthenticationRequest( self::GOOGLELOGIN_BUTTONREQUEST_NAME, wfMessage( 'googlelogin' ), new \RawMessage( 'no help yet' ) ) ];
				break;
			case AuthManager::ACTION_LINK:
				// TODO: Probably not the best message currently.
				return [ new ButtonAuthenticationRequest( self::GOOGLELOGIN_BUTTONREQUEST_NAME, wfMessage( 'googlelogin-form-merge' ), new \RawMessage( 'no help yet' ) ) ];
				break;
			case AuthManager::ACTION_REMOVE:
				// TODO: Probably not the best message currently.
				return [ new ButtonAuthenticationRequest( self::GOOGLELOGIN_BUTTONREQUEST_NAME, wfMessage( 'googlelogin-form-merge' ), new \RawMessage( 'no help yet' ) ) ];
				break;
			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
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

	public function beginPrimaryAccountLink( $user, array $reqs ) {
		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			throw new \LogicException( 'Continue called without appropriate AuthenticationRequest' );
		}
		$client = $this->getGoogleClient();
		$client->authenticate( $request->accessToken );
		$plus = new Google_Service_Plus( $client );
		try {
			$userInfo = $plus->people->get( "me" );
			$googleId = $userInfo['id'];
			if ( !GoogleUser::isGoogleIdFree( $googleId ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'googlelogin-link-other' ) );
			} else {
				$result = GoogleUser::connectWithGoogle( $user, $googleId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail( new \RawMessage( $e->getMessage() ) );
		}
	}

	/**
	 * Handler for a primary authentication, which currently begins. Checks, if the Authentication request
	 * can be handled by GoogleLogin and, if so, returns an AuthenticationResponse that redirects to the
	 * external authentication site of Google, otherwise returns an abstain response.
	 * @param array $reqs
	 * @param $buttonAuthenticationRequestName
	 * @return AuthenticationResponse
	 */
	private function beginGoogleAuthentication( array $reqs, $buttonAuthenticationRequestName ) {
		$req = ButtonAuthenticationRequest::getRequestByName( $reqs, $buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$client = $this->getGoogleClient();
		$this->manager->setAuthenticationSessionData( self::RETURNURL_SESSION_KEY, $req->returnToUrl );

		return AuthenticationResponse::newRedirect( [ new GoogleServerAuthenticationRequest() ], $client->createAuthUrl() );
	}

	/**
	 * Returns an instance of Google_Client, which is set up for the use in an authentication workflow.
	 *
	 * @return \Google_Client
	 */
	public function getGoogleClient() {
		$client = GoogleLogin::getClient(
			SpecialPage::getTitleFor( 'GoogleLoginReturn' )->getFullURL(),
			$this->manager->getRequest()->getSession()->getToken( self::TOKEN_SALT )->toString()
		);

		return $client;
	}
}
