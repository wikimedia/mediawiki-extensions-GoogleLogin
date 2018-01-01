<?php

namespace GoogleLogin\AllowedDomains;

use MWException;

/**
 * Represents a single E-Mail address.
 *
 * @package GoogleLogin\AllowedDomains
 */
class EmailDomain {
	private $emailAddress;
	private $domainHost;

	/**
	 * EmailDomain constructor.
	 *
	 * @param String $mail The whole e-mail address, which is represented by this object
	 * @param bool $strict If the domain should be parsed strictly or not (e.g.
	 *  test@test.example.com will be converted to example.com if this is false)
	 */
	public function __construct( $mail, $strict = false ) {
		$this->emailAddress = $mail;
		$this->domainHost = $this->parseHost( $mail, $strict );
	}

	/**
	 * Returns the host part of the e-mail-address, including the subdomain, if this object was
	 * created in strict mode.
	 *
	 * @return string
	 */
	public function getHost() {
		return $this->domainHost;
	}

	/**
	 * @return String The raw e-mail address which was passed to this object during it's creation.
	 */
	public function getEmail() {
		return $this->emailAddress;
	}

	/**
	 * Returns the domain and tld (without subdomains) of the provided E-Mailadress
	 * @param string $domain The domain part of the email address to extract from.
	 * @return string The Tld and domain of $domain without subdomains
	 * @see http://www.programmierer-forum.de/domainnamen-ermitteln-t244185.htm
	 */
	private function parseHost( $domain = '', $strict ) {
		$dir = __DIR__ . "/../..";
		if ( $strict ) {
			$domain = explode( '@', $domain );
			// we can trust google to give us only valid email address, so give the last element
			return array_pop( $domain );
		}
		// for parse_url()
		$domain =
			!isset( $domain[5] ) ||
			(
				$domain[3] != ':' &&
				$domain[4] != ':' &&
				$domain[5] != ':'
			) ? 'http://' . $domain : $domain;
		// remove "/path/file.html", "/:80", etc.
		$domain = parse_url( $domain, PHP_URL_HOST );
		// separate domain level
		// 0 => www, 1 => example, 2 => co, 3 => uk
		$lvl = explode( '.', $domain );
		// set levels
		// 3 => uk, 2 => co, 1 => example, 0 => www
		krsort( $lvl );
		// 0 => uk, 1 => co, 2 => example, 3 => www
		$lvl = array_values( $lvl );
		$_1st = $lvl[0];
		$_2nd = isset( $lvl[1] ) ? $lvl[1] . '.' . $_1st : false;
		$_3rd = isset( $lvl[2] ) ? $lvl[2] . '.' . $_2nd : false;
		$_4th = isset( $lvl[3] ) ? $lvl[3] . '.' . $_3rd : false;

		// tld extract
		if ( !file_exists( "$dir/cache/tld.txt" ) ) {
			$this->createTLDCache( "$dir/cache/tld.txt" );
		}
		require "$dir/cache/tld.txt";
		$tlds = array_flip( $tlds );
		// fourth level is TLD
		if (
			$_4th &&
			!isset( $tlds[ '!' . $_4th ] ) &&
			(
				isset( $tlds[ $_4th ] ) ||
				isset( $tlds[ '*.' . $_3rd ] )
			)
		) {
			$domain = isset( $lvl[4] ) ? $lvl[4] . '.' . $_4th : false;
			// third level is TLD
		} elseif (
			$_3rd &&
			!isset( $tlds[ '!' . $_3rd ] ) &&
			(
				isset( $tlds[ $_3rd ] ) ||
				isset( $tlds[ '*.' . $_2nd ] )
			)
		) {
			$domain = $_4th;
			// second level is TLD
		} elseif (
			!isset( $tlds[ '!' . $_2nd ] ) &&
			(
				isset( $tlds[ $_2nd ] ) ||
				isset( $tlds[ '*.' . $_1st ] )
			)
		) {
			$domain = $_3rd;
			// first level is TLD
		} else {
			$domain = $_2nd;
		}
		return $domain;
	}

	/**
	 * Creates the TLD cache from which the valid tld of mail domain comes from.
	 * @param string $cacheFile The file to create the cache too (must be writeable for the
	 * webserver!)
	 * @param int $max_tl How deep the domain list is (enclude example.co.uk (2) or
	 * example.lib.wy.us (3)?)
	 * @see http://www.programmierer-forum.de/domainnamen-ermitteln-t244185.htm
	 * @throws MWException
	 */
	private function createTLDCache( $cacheFile, $max_tl = 2 ) {
		$cacheFolder = str_replace( basename( $cacheFile ), '', $cacheFile );
		if ( !is_writable( $cacheFolder ) ) {
			throw new MWException( $cacheFolder . ' is not writeable!' );
		}
		$tlds = file(
			'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1'
		);
		if ( $tlds === false ) {
			throw new MWException( 'Domainlist can not be downloaded!' );
		}
		$i = 0;
		// remove unnecessary lines
		foreach ( $tlds as $tld ) {
			$tlds[ $i ] = trim( $tld );
			/**
			 *	empty
			 *	comments
			 *	top level domains
			 *	is overboard
			 */
			if (
				!$tlds[ $i ] ||
				$tld[0] == '/' ||
				strpos( $tld, '.' ) === false ||
				substr_count( $tld, '.' ) >= $max_tl
			) {
				unset( $tlds[ $i ] );
			}
			$i++;
		}
		$tlds = array_values( $tlds );
		file_put_contents(
			$cacheFile,
			"<?php\n" . '$tlds = ' . str_replace(
				[ ' ', "\n" ],
				'',
				var_export( $tlds, true )
			) . ";\n?" . ">"
		);
	}
}
