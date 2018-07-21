<?php

use GoogleLogin\GoogleIdProvider;
use GoogleLogin\UserMatching\GoogleUserMatching;
use MediaWiki\MediaWikiServices;
use GoogleLogin\GoogleLogin;
use GoogleLogin\AllowedDomains\CachedAllowedDomainsStore;
use GoogleLogin\AllowedDomains\DBAllowedDomainsStore;
use GoogleLogin\AllowedDomains\ArrayAllowedDomainsStore;
use GoogleLogin\Constants;

return [
	Constants::SERVICE_ALLOWED_DOMAINS_STORE => function ( MediaWikiServices $services ) {
		$glConfig = GoogleLogin::getGLConfig();
		if (
			is_array( $glConfig->get( 'GLAllowedDomains' ) ) &&
			!$glConfig->get( 'GLAllowedDomainsDB' )
		) {
			return new ArrayAllowedDomainsStore( $glConfig->get( 'GLAllowedDomains' ) );
		} elseif ( $glConfig->get( 'GLAllowedDomainsDB' ) ) {
			$dbBackedStore = new DBAllowedDomainsStore( $services->getDBLoadBalancer() );
			$cache = wfGetCache( wfIsHHVM() ? CACHE_ACCEL : CACHE_ANYTHING );

			return new CachedAllowedDomainsStore( $dbBackedStore, $cache );
		}
		return null;
	},

	Constants::SERVICE_GOOGLE_USER_MATCHING => function ( MediaWikiServices $services ) {
		return new GoogleUserMatching( $services->getDBLoadBalancer() );
	},

	Constants::SERVICE_GOOGLE_ID_PROVIDER => function ( MediaWikiServices $services ) {
		return new GoogleIdProvider( $services->getDBLoadBalancer() );
	}
];
