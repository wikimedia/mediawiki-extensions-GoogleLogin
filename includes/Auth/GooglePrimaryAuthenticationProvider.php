<?php
/**
 * GooglePrimaryAuthenticationProvider implementation
 */

namespace GoogleLogin\Auth;

use Exception;
use GoogleLogin\Constants;
use GoogleLogin\GoogleIdProvider;
use GoogleLogin\GoogleLogin;
use GoogleLogin\GoogleUserMatching;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use MWException;
use SpecialPage;
use StatusValue;
use User;

/**
 * Implements a primary authentication provider to authenticate an user using a Google account where
 * this user has access, too. On beginning of the authentication, the provider maybe redirects the
 * user to an external authentication provider (Google) to authenticate and permit the access to
 * the data of the foreign account, before it actually authenticates the user.
 */
class GooglePrimaryAuthenticationProvider extends AbstractPrimaryAuthenticationProvider {
	/** Session inside of the auth session data where the original redirect URL is saved */
	const RETURNURL_SESSION_KEY = 'googleLoginReturnToUrl';
	/** Token salt for CSRF token used by GoogleLogin when a user gets
	 * redirected from Google
	 */
	const TOKEN_SALT = 'GooglePrimaryAuthenticationProvider:redirect';
	/** Name of the button of the GoogleAuthenticationRequest */
	const GOOGLELOGIN_BUTTONREQUEST_NAME = 'googlelogin';
	/** @var string Session data key to identify a saved Google ID token between requests */
	const GOOGLE_ACCOUNT_TOKEN_KEY = 'googlelogin:account:token';

	public function beginPrimaryAuthentication( array $reqs ) {
		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAuthentication( array $reqs ) {
		$request =
			AuthenticationRequest::getRequestByClass( $reqs,
				GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'googlelogin-error-no-authentication-workflow' ) );
		}

		try {
			$verifiedToken = $this->getVerifiedToken( $request );
			if ( $verifiedToken instanceof AuthenticationResponse ) {
				return $verifiedToken;
			}

			/** @var GoogleUserMatching $userMatchingService */
			$userMatchingService =
				MediaWikiServices::getInstance()
					->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );
			$user = $userMatchingService->getUserFromToken( $verifiedToken );

			$email = $verifiedToken['email'];
			if ( !GoogleLogin::isValidDomain( $email ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'googlelogin-unallowed-domain',
					$email ) );
			}

			if ( $user ) {
				return AuthenticationResponse::newPass( $user->getName() );
			} else {
				$resp = $this->nonExistingUserResponse( $verifiedToken );
				$resp->linkRequest = new GoogleUserInfoAuthenticationRequest( $verifiedToken );
				$resp->createRequest = $resp->linkRequest;

				return $resp;
			}
		}
		catch ( Exception $e ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error',
				$e->getMessage() ) );
		}
	}

	public function autoCreatedAccount( $user, $source ) {
		/** @var GoogleUserMatching $userMatchingService */
		$userMatchingService =
			MediaWikiServices::getInstance()->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );

		$verifiedToken =
			$this->manager->getAuthenticationSessionData( self::GOOGLE_ACCOUNT_TOKEN_KEY );
		$userMatchingService->match( $user, $verifiedToken );
		$this->manager->removeAuthenticationSessionData( self::GOOGLE_ACCOUNT_TOKEN_KEY );
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
				return [
					new GoogleAuthenticationRequest( wfMessage( 'googlelogin' ),
						wfMessage( 'googlelogin-loginbutton-help' ) ),
				];
				break;
			case AuthManager::ACTION_LINK:
				if ( $this->isAuthoritative() ) {
					return [];
				}

				return [
					new GoogleAuthenticationRequest( wfMessage( 'googlelogin-form-merge' ),
						wfMessage( 'googlelogin-link-help' ) ),
				];
				break;
			case AuthManager::ACTION_REMOVE:
				if ( $this->isAuthoritative() ) {
					return [];
				}
				$user = User::newFromName( $options['username'] );

				if ( $user === false || $user->isAnon() ) {
					return [];
				}
				/** @var GoogleIdProvider $googleIdProvider */
				$googleIdProvider =
					MediaWikiServices::getInstance()
						->getService( Constants::SERVICE_GOOGLE_ID_PROVIDER );
				$googleIds = $googleIdProvider->getFromUser( $user );

				if ( !$user || empty( $googleIds ) ) {
					return [];
				}

				$reqs = [];
				foreach ( $googleIds as $key => $id ) {
					$reqs[] = new GoogleRemoveAuthenticationRequest( $id );
				}

				return $reqs;
				break;
			case AuthManager::ACTION_CREATE:
				// TODO: ACTION_CREATE doesn't really need all
				// the things provided by inheriting
				// ButtonAuthenticationRequest, so probably it's better
				// to create it's own Request
				return [
					new GoogleAuthenticationRequest( wfMessage( 'googlelogin-create' ),
						wfMessage( 'googlelogin-link-help' ) ),
				];
				break;
			default:
				return [];
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		return false;
	}

	public function testUserCanAuthenticate( $username ) {
		$user = \User::newFromName( $username );

		/** @var GoogleIdProvider $googleIdProvider */
		$googleIdProvider =
			MediaWikiServices::getInstance()->getService( Constants::SERVICE_GOOGLE_ID_PROVIDER );

		return $user && !empty( $googleIdProvider->getFromUser( $user ) );
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		if ( get_class( $req ) === GoogleRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			/** @var GoogleIdProvider $googleIdProvider */
			$googleIdProvider =
				MediaWikiServices::getInstance()
					->getService( Constants::SERVICE_GOOGLE_ID_PROVIDER );
			if ( $user &&
				in_array( $req->getGoogleId(), $googleIdProvider->getFromUser( $user ) ) ) {
				return StatusValue::newGood();
			} else {
				return StatusValue::newFatal( wfMessage( 'googlelogin-change-account-not-linked' ) );
			}
		}

		if ( get_class( $req ) === GoogleUserInfoAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_CHANGE ) {
			$user = User::newFromName( $req->username );
			/** @var GoogleUserMatching $userMatchingService */
			$userMatchingService =
				MediaWikiServices::getInstance()
					->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );
			$potentialUser = $userMatchingService->getUserFromToken( $req->userInfo );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return StatusValue::newFatal( 'googlelogin-link-other1' );
			} elseif ( $potentialUser ) {
				return StatusValue::newFatal( 'googlelogin-link-same' );
			}
			if ( $user ) {
				return StatusValue::newGood();
			}
		}

		return StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		/** @var GoogleUserMatching $userMatchingService */
		$userMatchingService =
			MediaWikiServices::getInstance()->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );
		if ( get_class( $req ) === GoogleRemoveAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_REMOVE ) {
			$user = User::newFromName( $req->username );
			$userMatchingService->unmatch( $user, [ 'sub' => $req->getGoogleId() ] );
		}

		if ( get_class( $req ) === GoogleUserInfoAuthenticationRequest::class &&
			$req->action === AuthManager::ACTION_CHANGE ) {
			$user = User::newFromName( $req->username );
			$userMatchingService->match( $user, $req->userInfo );
		}
	}

	public function providerNormalizeUsername( $username ) {
		return null;
	}

	public function accountCreationType() {
		return self::TYPE_LINK;
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request =
			AuthenticationRequest::getRequestByClass( $reqs,
				GoogleUserInfoAuthenticationRequest::class );

		/** @var GoogleIdProvider $googleIdProvider */
		$googleIdProvider =
			MediaWikiServices::getInstance()->getService( Constants::SERVICE_GOOGLE_ID_PROVIDER );
		if ( $request ) {
			if ( !$googleIdProvider->isAssociated( $request->userInfo['sub'] ) ) {
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = $request;

				return $resp;
			}
		}

		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountCreation( $user, $creator, array $reqs ) {
		$request =
			AuthenticationRequest::getRequestByClass( $reqs,
				GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'googlelogin-error-no-authentication-workflow' ) );
		}

		try {
			$verifiedToken = $this->getVerifiedToken( $request );
			if ( $verifiedToken instanceof AuthenticationResponse ) {
				return $verifiedToken;
			}

			/** @var GoogleIdProvider $googleIdProvider */
			$googleIdProvider =
				MediaWikiServices::getInstance()
					->getService( Constants::SERVICE_GOOGLE_ID_PROVIDER );
			if ( !$googleIdProvider->isAssociated( $verifiedToken['sub'] ) ) {
				$email = $verifiedToken['email'];
				if ( !GoogleLogin::isValidDomain( $email ) ) {
					return AuthenticationResponse::newFail( wfMessage( 'googlelogin-unallowed-domain',
						$email ) );
				}
				$resp = AuthenticationResponse::newPass();
				$resp->linkRequest = new GoogleUserInfoAuthenticationRequest( $verifiedToken );

				return $resp;
			}

			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-link-other' ) );
		}
		catch ( Exception $e ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error',
				$e->getMessage() ) );
		}
	}

	public function finishAccountCreation( $user, $creator, AuthenticationResponse $response ) {
		$userInfo = $response->linkRequest->userInfo;
		$user->setEmail( $userInfo['email'] );
		$user->saveSettings();

		/** @var GoogleUserMatching $userMatchingService */
		$userMatchingService =
			MediaWikiServices::getInstance()->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );
		$userMatchingService->match( $user, $userInfo );

		return null;
	}

	public function beginPrimaryAccountLink( $user, array $reqs ) {
		return $this->beginGoogleAuthentication( $reqs, self::GOOGLELOGIN_BUTTONREQUEST_NAME );
	}

	public function continuePrimaryAccountLink( $user, array $reqs ) {
		$request =
			AuthenticationRequest::getRequestByClass( $reqs,
				GoogleServerAuthenticationRequest::class );
		if ( !$request ) {
			return AuthenticationResponse::newFail(
				wfMessage( 'googlelogin-error-no-authentication-workflow' ) );
		}
		$client = $this->getGoogleClient();
		$client->fetchAccessTokenWithAuthCode( $request->accessToken );
		$verifiedToken = $client->verifyIdToken();

		try {
			if ( $verifiedToken === false ) {
				throw new MWException( 'access_token could not be verified.' );
			}

			/** @var GoogleUserMatching $userMatchingService */
			$userMatchingService =
				MediaWikiServices::getInstance()
					->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );
			$potentialUser = $userMatchingService->getUserFromToken( $verifiedToken );
			if ( $potentialUser && !$potentialUser->equals( $user ) ) {
				return AuthenticationResponse::newFail( wfMessage( 'googlelogin-link-other' ) );
			} elseif ( $potentialUser ) {
				return AuthenticationResponse::newFail( wfMessage( 'googlelogin-link-same' ) );
			} else {
				$email = $verifiedToken['email'];
				if ( !GoogleLogin::isValidDomain( $email ) ) {
					return AuthenticationResponse::newFail( wfMessage( 'googlelogin-unallowed-domain',
						$email ) );
				}
				$result = $userMatchingService->match( $user, $verifiedToken );
				if ( $result ) {
					return AuthenticationResponse::newPass();
				} else {
					// TODO: Better error message
					return AuthenticationResponse::newFail( new \RawMessage( 'Database error' ) );
				}
			}
		}
		catch ( Exception $e ) {
			return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error',
				$e->getMessage() ) );
		}
	}

	/**
	 * Handler for a primary authentication, which currently begins. Checks, if the Authentication
	 * request can be handled by GoogleLogin and, if so, returns an AuthenticationResponse that
	 * redirects to the external authentication site of Google, otherwise returns an abstain response.
	 * @param array $reqs
	 * @param string $buttonAuthenticationRequestName
	 * @return AuthenticationResponse
	 */
	private function beginGoogleAuthentication( array $reqs, $buttonAuthenticationRequestName ) {
		$req =
			GoogleAuthenticationRequest::getRequestByName( $reqs,
				$buttonAuthenticationRequestName );
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}
		$client = $this->getGoogleClient();
		$this->manager->setAuthenticationSessionData( self::RETURNURL_SESSION_KEY,
			$req->returnToUrl );

		return AuthenticationResponse::newRedirect( [
			new GoogleServerAuthenticationRequest(),
		], $client->createAuthUrl() );
	}

	/**
	 * Returns an instance of Google_Client, which is set up for the use in an authentication workflow.
	 *
	 * @return \Google_Client
	 */
	public function getGoogleClient() {
		$client =
			GoogleLogin::getClient( SpecialPage::getTitleFor( 'GoogleLoginReturn' )
				->getFullURL( '', false, PROTO_CURRENT ), $this->manager->getRequest()
				->getSession()
				->getToken( self::TOKEN_SALT )
				->toString() );

		return $client;
	}

	/**
	 * Creates a new authenticated Google Plus Service from a GoogleServerAuthenticationRequest.
	 *
	 * @param GoogleServerAuthenticationRequest $request
	 * @return array|AuthenticationResponse
	 * @throws MWException
	 */
	private function getVerifiedToken( GoogleServerAuthenticationRequest $request ) {
		if ( !$request->accessToken || $request->errorCode ) {
			switch ( $request->errorCode ) {
				case 'access_denied':
					return AuthenticationResponse::newFail( wfMessage( 'googlelogin-access-denied' ) );
					break;
				default:
					return AuthenticationResponse::newFail( wfMessage( 'googlelogin-generic-error',
						$request->errorCode ? $request->errorCode : 'unknown' ) );
			}
		}
		$client = $this->getGoogleClient();
		$client->fetchAccessTokenWithAuthCode( $request->accessToken );
		$verifiedToken = $client->verifyIdToken();

		if ( $verifiedToken === false ) {
			throw new MWException( 'The access_token could not be verified.' );
		}

		return $verifiedToken;
	}

	/**
	 * @param array $verifiedToken
	 * @return AuthenticationResponse
	 */
	private function nonExistingUserResponse( $verifiedToken ) {
		if ( $this->isAuthoritative() ) {
			$this->manager->setAuthenticationSessionData( self::GOOGLE_ACCOUNT_TOKEN_KEY,
				$verifiedToken );
			$resp = AuthenticationResponse::newPass( $verifiedToken['email'] );
		} else {
			$resp = AuthenticationResponse::newPass( null );
		}

		return $resp;
	}

	/**
	 * @return bool
	 */
	private function isAuthoritative() {
		return MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'googlelogin' )
			->get( 'GLAuthoritativeMode' );
	}
}
