<?php

namespace GoogleLogin\AllowedDomains;

/**
 * Interface MutableAllowedDomainsStore
 *
 * A domain store, which can be modified during runtime, where the changes persist between requests.
 *
 * @package GoogleLogin\AllowedDomains
 */
interface MutableAllowedDomainsStore extends AllowedDomainsStore {
	/**
	 * Adds the host of the given EmailDomain to the store.
	 *
	 * @param EmailDomain $domain
	 * @return void
	 */
	function add( EmailDomain $domain );

	/**
	 * Cleans the store and deletes all entries from it.
	 *
	 * @return void
	 */
	function clear();
}
