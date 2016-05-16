<?php
/**
 * Created by PhpStorm.
 * User: Florian
 * Date: 19.05.2016
 * Time: 20:41
 */

namespace GoogleLogin\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthManager;

class GoogleRemoveAuthenticationRequest extends AuthenticationRequest {
	private $googleId = null;
	private $name = null;

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
