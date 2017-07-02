<?php

namespace GoogleLogin\Api;

use ApiBase;
use GoogleLogin\AllowedDomains\EmailDomain;
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
	/**
	 * @throws \ApiUsageException
	 */
	public function execute() {
		$apiResult = $this->getResult();
		$params = $this->extractRequestParams();

		$this->checkUserRightsAny( 'managegooglelogindomains' );

		// this API module is not registered, if the AllowedDomain store is null or not mutable
		$allowedDomainsStore = MediaWikiServices::getInstance()->getService(
			Constants::SERVICE_ALLOWED_DOMAINS_STORE );

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
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}
}
