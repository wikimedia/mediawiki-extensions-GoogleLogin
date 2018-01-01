<?php

namespace GoogleLogin\Api;

use ApiBase;
use GoogleLogin\AllowedDomains\EmailDomain;
use GoogleLogin\AllowedDomains\MutableAllowedDomainsStore;
use GoogleLogin\Constants;
use MediaWiki\MediaWikiServices;

/**
 * Class ApiGoogleLoginManageAllowedDomains
 *
 * Allows to manage the list of allowed domains by adding new ones and removing old ones.
 *
 * @package GoogleLogin\Api
 */
class ApiGoogleLoginManageAllowedDomains extends ApiBase {
	public function execute() {
		$apiResult = $this->getResult();
		$params = $this->extractRequestParams();
		$user = $this->getUser();

		if ( !isset( $params['domain'] ) ) {
			$this->dieWithError( 'Invalid domain', 'domain_invalid' );
		}

		if ( !$user->isAllowed( 'managegooglelogindomains' ) ) {
			$this->dieWithError(
				'Insufficient permissions. You need the managegooglelogindomains ' .
				'permission to use this API module',
				'insufficientpermissions'
			);
		}

		$allowedDomainsStore = MediaWikiServices::getInstance()->getService(
			Constants::SERVICE_ALLOWED_DOMAINS_STORE );
		if ( $allowedDomainsStore === null ) {
			$this->dieWithError(
				'The allowed domains feature of GoogleLogin is not enabled for this wiki.',
				'not_enabled'
			);
		}
		if ( !$allowedDomainsStore instanceof MutableAllowedDomainsStore ) {
			$this->dieWithError(
				'The configured backend for the allowed domains feature does' .
				' not allow changes through this api.',
				'not_mutable'
			);
		}
		$emailAddress = new EmailDomain( $params['domain'] );
		if ( $params['method'] === 'add' ) {
			$result = $allowedDomainsStore->add( $emailAddress );
		} else {
			$result = $allowedDomainsStore->remove( $emailAddress );
		}
		// build result array
		$r = [
			'result' => $result
		];
		// add result to API output
		$apiResult->addValue( null, $this->getModuleName(), $r );
	}

	public function getAllowedParams() {
		return [
			'method' => [
				ApiBase::PARAM_TYPE => [ 'add', 'remove' ],
				ApiBase::PARAM_DFLT => 'add',
			],
			'domain' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}
}
