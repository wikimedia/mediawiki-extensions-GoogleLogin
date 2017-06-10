<?php

namespace GoogleLogin\AllowedDomains;

/**
 * Interface MutableAllowedDomainsStore
 *
 * A domain store, which can be modified during runtime, where the changes persist between requests.
 *
 * @package GoogleLogin\AllowedDomains
 */
interface MutableAllowedDomainsStore {
	/**
	 * Adds the host of the given EmailDomain to the store.
	 *
	 * @param EmailDomain $domain
	 * @return integer The ID of the newly added entry (0 if IDs are not used, -1 on failure)
	 */
	function add( EmailDomain $domain );

	/**
	 * Removes the given host of the EmailDomain from the AllowedDomainsStore, if it is present.
	 * Will return true, when the domain is not present anymore (either not present before or
	 * successfully deleted), false otheriwse.
	 *
	 * @param EmailDomain $domain
	 * @return boolean
	 */
	function remove( EmailDomain $domain );

	/**
	 * Cleans the store and deletes all entries from it.
	 *
	 * @return void
	 */
	function clear();
}
