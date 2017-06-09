<?php

namespace GoogleLogin;

use ConfigFactory;

use Google_Client;
use GoogleLogin\AllowedDomains\EmailDomain;

class GoogleLogin {
	/** @var $mGoogleClient Stores an instance of GoogleClient */
	private static $mGoogleClient;
	/** @var $mConfig Config object created for GoogleLogin extension */
	private static $mConfig;

	/**
	 * Returns an prepared instance of Google client to do requests with to Google API
	 * @return Google_Client
	 */
	public static function getClient( $returnToUrl, $token ) {
		if ( empty( self::$mGoogleClient ) ) {
			$glConfig = self::getGLConfig();
			$client = new Google_Client();
			$client->setClientId( $glConfig->get( 'GLAppId' ) );
			$client->setClientSecret( $glConfig->get( 'GLSecret' ) );
			$client->setRedirectUri( $returnToUrl );
			$client->addScope( 'profile' );
			$client->addScope( 'email' );
			$client->setState( $token );
			self::$mGoogleClient = $client;
		}
		return self::$mGoogleClient;
	}

	/**
	 * Returns Config object for use in GoogleLogin.
	 *
	 * @return \Config
	 */
	public static function getGLConfig() {
		if ( self::$mConfig === null ) {
			self::$mConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'googlelogin' );
		}
		return self::$mConfig;
	}

	/**
	 * If restriction of domains is enabled, check if the user E-Mail is valid before do anything.
	 * @param string $mailDomain The domain of email address
	 * @return boolean
	 */
	public static function isValidDomain( $mailDomain ) {
		$glConfig = self::getGLConfig();
		if ( is_array( $glConfig->get( 'GLAllowedDomains' ) ) ) {
			$domain = new EmailDomain( $mailDomain, $glConfig->get( 'GLAllowedDomainsStrict' ) );
			if (
				in_array(
					$domain->getHost(),
					$glConfig->get( 'GLAllowedDomains' )
				)
			) {
				return true;
			}
			return false;
		}
		return true;
	}
}
