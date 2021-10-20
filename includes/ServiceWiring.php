<?php

use GoogleLogin\AllowedDomains\ArrayAllowedDomainsStore;
use GoogleLogin\AllowedDomains\CachedAllowedDomainsStore;
use GoogleLogin\AllowedDomains\DBAllowedDomainsStore;
use GoogleLogin\Constants;
use GoogleLogin\GoogleIdProvider;
use GoogleLogin\GoogleLogin;
use GoogleLogin\GoogleUserMatching;
use MediaWiki\MediaWikiServices;

return [
	Constants::SERVICE_ALLOWED_DOMAINS_STORE => static function ( MediaWikiServices $services ) {
		$glConfig = GoogleLogin::getGLConfig();
		if (
			is_array( $glConfig->get( 'GLAllowedDomains' ) ) &&
			!$glConfig->get( 'GLAllowedDomainsDB' )
		) {
			return new ArrayAllowedDomainsStore( $glConfig->get( 'GLAllowedDomains' ) );
		} elseif ( $glConfig->get( 'GLAllowedDomainsDB' ) ) {
			$dbBackedStore = new DBAllowedDomainsStore( $services->getDBLoadBalancer() );
			$cache = ObjectCache::getInstance( CACHE_ACCEL );

			return new CachedAllowedDomainsStore( $dbBackedStore, $cache );
		}
		return null;
	},

	Constants::SERVICE_GOOGLE_USER_MATCHING => static function ( MediaWikiServices $services ) {
		return new GoogleUserMatching( $services->getDBLoadBalancer() );
	},

	Constants::SERVICE_GOOGLE_ID_PROVIDER => static function ( MediaWikiServices $services ) {
		return new GoogleIdProvider( $services->getDBLoadBalancer() );
	}
];
