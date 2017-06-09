<?php

namespace GoogleLogin\AllowedDomains;

/**
 * Interface AllowedDomainsStore
 *
 * Defines, how a store should behave, which provides a list of allowed domains, which can be
 * used to login with a Google account.
 *
 * @package GoogleLogin\AllowedDomains
 */
interface AllowedDomainsStore {

	/**
	 * Checks, if the given EmailDomain is allowed to be used for login or not.
	 *
	 * @param EmailDomain $domain
	 * @return boolean
	 */
	function contains( EmailDomain $domain );

	/**
	 * Returns the complete list of allowed domains of this store as an array.
	 *
	 * @return array
	 */
	function getAllowedDomains();
}
