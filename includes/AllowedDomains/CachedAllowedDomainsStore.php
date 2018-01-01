<?php

namespace GoogleLogin\AllowedDomains;

use BagOStuff;

class CachedAllowedDomainsStore implements AllowedDomainsStore, MutableAllowedDomainsStore {
	/**
	 * @var AllowedDomainsStore
	 */
	private $rawStore;
	/**
	 * @var BagOStuff
	 */
	private $cache;
	/**
	 * @var string|null
	 */
	private $cacheKey;
	private $allowedDomains;

	const CACHE_VERSION = '1';

	public function __construct( AllowedDomainsStore $rawStore, BagOStuff $cache ) {
		$this->cache = $cache;
		$this->rawStore = $rawStore;
	}

	/**
	 * Constructs a cache key to use for caching the list of allowed domains.
	 *
	 * @return string The cache key.
	 */
	private function getCacheKey() {
		if ( $this->cacheKey === null ) {
			$type = 'CachedAllowedDomainsStore#' . self::CACHE_VERSION;
			$this->cacheKey = $this->cache->makeKey( "googlelogin/allowedDomains/$type" );
		}
		return $this->cacheKey;
	}

	/**
	 * @return array
	 */
	public function getAllowedDomains() {
		if ( $this->allowedDomains === null ) {
			$this->allowedDomains = $this->cache->get( $this->getCacheKey() );
			if ( !is_object( $this->allowedDomains ) ) {
				$this->allowedDomains = $this->rawStore->getAllowedDomains();
				$this->cache->set( $this->getCacheKey(), $this->allowedDomains, 3600 );
			}
		}
		return $this->allowedDomains;
	}

	public function contains( EmailDomain $domain ) {
		return in_array( $domain->getHost(), $this->getAllowedDomains() );
	}

	public function add( EmailDomain $domain ) {
		if ( !$this->rawStore instanceof MutableAllowedDomainsStore ) {
			throw new \InvalidArgumentException(
				'The backend domain store does not support to change the store data.' );
		}
		return $this->rawStore->add( $domain );
	}

	public function clear() {
		$this->cache->delete( $this->getCacheKey() );
		$this->allowedDomains = null;
	}

	public function remove( EmailDomain $domain ) {
		if ( !$this->rawStore instanceof MutableAllowedDomainsStore ) {
			throw new \InvalidArgumentException(
				'The backend domain store does not support to remove stored data.' );
		}
		$ok = $this->rawStore->remove( $domain );
		$this->clear();

		return $ok;
	}
}
