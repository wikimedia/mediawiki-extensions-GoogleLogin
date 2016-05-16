<?php

namespace GoogleLogin\Auth;

use Exception;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationResponse;
use User;

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
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-error-no-authentication-workflow' ) );
		}
		$client = $this->getGoogleClient();
		$client->authenticate( $request->accessToken );
		$plus = new Google_Service_Plus( $client );
		try {
			$userInfo = $plus->people->get( "me" );
			$user = GoogleUser::getUserFromGoogleId( $userInfo['id'] );
			if ( $user ) {
				if ( !GoogleLogin::isValidDomain( $userInfo['emails'][0]['value'] ) ) {
					return AuthenticationResponse::newFail(
						wfMessage( 'googlelogin-unallowed-domain', GoogleLogin::getHost() )
					);
				}
				return AuthenticationResponse::newPass( $user->getName() );
			} else {
				return AuthenticationResponse::newPass( null );
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error', $e->getMessage() ) );
		}
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [ new GoogleAuthenticationRequest(
					self::GOOGLELOGIN_BUTTONREQUEST_NAME, wfMessage( 'googlelogin' ),
					wfMessage( 'googlelogin-loginbutton-help' )
				) ];
				break;
			case AuthManager::ACTION_LINK:
			case AuthManager::ACTION_REMOVE:
				// TODO: Probably not the best message currently.
				return [ new GoogleAuthenticationRequest(
					self::GOOGLELOGIN_BUTTONREQUEST_NAME,
					wfMessage( 'googlelogin-form-merge' ),
					wfMessage( 'googlelogin-link-help' )
				) ];
				break;
			case AuthManager::ACTION_CREATE:
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [ new GoogleAuthenticationRequest(
					self::GOOGLELOGIN_BUTTONREQUEST_NAME,
					wfMessage( 'googlelogin-create' ),
					wfMessage( 'googlelogin-link-help' )
				) ];
				break;
			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function testUserCanAuthenticate( $username ) {
		return User::newFromName( $username )->exists();
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if ( !$req === GoogleAuthenticationRequest::class || $req->action !== AuthManager::ACTION_REMOVE ) {
			return StatusValue::newGood( 'ignored' );
		}

		$user = User::newFromName( $req->username );
		if ( $user && GoogleUser::hasConnectedGoogleAccount( $user ) ) {
			return StatusValue::newGood();
		} else {
			return StatusValue::newFatal( 'ignored' );
		}
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		if ( !$req === GoogleAuthenticationRequest::class || $req->action !== AuthManager::ACTION_REMOVE ) {
			return StatusValue::newGood( 'ignored' );
		}
		
		$user = User::newFromName( $req->username);
		GoogleUser::terminateGoogleConnection( $user );
	}

	public function providerNormalizeUsername( $username ) {
		return null;
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-error-no-authentication-workflow' ) );
		}
		$client = $this->getGoogleClient();
		$client->authenticate( $request->accessToken );
		$plus = new Google_Service_Plus( $client );
		try {
			$userInfo = $plus->people->get( "me" );
			$isGoogleIdFree = GoogleUser::isGoogleIdFree( $userInfo['id'] );
			if ( $isGoogleIdFree ) {
				if ( !GoogleLogin::isValidDomain( $userInfo['emails'][0]['value'] ) ) {
					return AuthenticationResponse::newFail(
						wfMessage( 'googlelogin-unallowed-domain', GoogleLogin::getHost() )
					);
				}
				$this->manager->setAuthenticationSessionData(
					'google_access_token',
					$client->getAccessToken()['access_token']
				);
				return AuthenticationResponse::newPass();
			}
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-link-other' ) );
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error', $e->getMessage() ) );
		}
	}

	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$client = $this->getGoogleClient();
		$client->authenticate( $this->manager->getAuthenticationSessionData( 'google_access_token' ) );
		$plus = new Google_Service_Plus( $client );
		try {
			$userInfo = $plus->people->get( "me" );
			$user->setEmail( $userInfo['emails'][0]['value'] );
			$user->saveSettings();
			GoogleUser::connectWithGoogle( $user, $userInfo['id'] );
		} catch ( Exception $e ) {
			// TODO: need to decide what to do in this case
		}
	}

	public function beginPrimaryAccountLink( $user, array $reqs ) {
		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request = AuthenticationRequest::getRequestByClass( $reqs, GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-error-no-authentication-workflow' ) );
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
				if ( !GoogleLogin::isValidDomain( $userInfo['emails'][0]['value'] ) ) {
					return AuthenticationResponse::newFail(
						wfMessage( 'googlelogin-unallowed-domain', GoogleLogin::getHost() )
					);
				}
				$result = GoogleUser::connectWithGoogle( $user, $googleId );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		} catch ( Exception $e ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error', $e->getMessage() ) );
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
		$req = GoogleAuthenticationRequest::getRequestByName( $reqs, $buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$client = $this->getGoogleClient();
		$this->manager->setAuthenticationSessionData( self::RETURNURL_SESSION_KEY, $req->returnToUrl );

		return AuthenticationResponse::newRedirect( [
			new GoogleServerAuthenticationRequest()
		], $client->createAuthUrl() );
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
