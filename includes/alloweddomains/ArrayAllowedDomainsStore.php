<?php

namespace GoogleLogin\AllowedDomains;

use Config;

/**
 * Class GlobalAllowedDomainsStore
 *
 * An implementation of the AllowedDomainStore interface, which is backed by the an array passed
 * to it, e.g. a configured array of allowed domains.
 *
 * @package GoogleLogin\AllowedDomains
 */
class ArrayAllowedDomainsStore implements AllowedDomainsStore {
	private $allowedDomains;

	public function __construct( array $allowedDomains ) {
		$this->allowedDomains = $allowedDomains;
	}

	public function contains( EmailDomain $domain ) {
		return in_array(
			$domain->getHost(),
			$this->allowedDomains
		);
	}

	public function getAllowedDomains() {
		return $this->allowedDomains;
	}
}
