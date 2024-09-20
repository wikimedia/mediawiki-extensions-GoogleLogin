<?php

namespace GoogleLogin\AllowedDomains;

/**
 * Represents a single E-Mail address.
 *
 * @package GoogleLogin\AllowedDomains
 */
class EmailDomain {
	private $emailAddress;
	private $domainHost = '';

	/**
	 * @var PublicSuffixLookup
	 */
	private $publicSuffixLookup;

	/**
	 * EmailDomain constructor.
	 *
	 * @param string $mail The whole e-mail address, which is represented by this object
	 * @param PublicSuffixLookup $publicSuffixLookup Lookup for public suffix checks.
	 * @param bool $strict If the domain should be parsed strictly or not (e.g.
	 *  test@test.example.com will be converted to example.com if this is false)
	 */
	public function __construct( $mail, PublicSuffixLookup $publicSuffixLookup, $strict = false ) {
		$this->publicSuffixLookup = $publicSuffixLookup;

		$this->emailAddress = $mail;
		$domain = explode( '@', $mail );
		if ( isset( $domain[1] ) ) {
			$this->domainHost = $this->parseHost( $domain[1], $strict );
		}
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
	 * @return string The raw e-mail address which was passed to this object during it's creation.
	 */
	public function getEmail() {
		return $this->emailAddress;
	}

	/**
	 * Returns the domain and tld (without subdomains) of the provided mail address
	 * @param string $domain The domain part of the email address to extract from.
	 * @param bool $strict
	 * @return string The Tld and domain of $domain without subdomains
	 */
	private function parseHost( $domain, $strict ) {
		if ( $strict ) {
			// we can trust google to give us only valid email address, so give the last element
			return $domain;
		}

		$url = explode( '.', $domain );

		return $this->getDomainPart( $url );
	}

	/**
	 * @param array $url
	 * @return string
	 */
	private function getDomainPart( $url ) {
		$parts = array_reverse( $url );
		foreach ( $parts as $key => $part ) {
			$tld = implode( '.', $parts );
			if ( $this->publicSuffixLookup->contains( $tld ) ) {
				return implode( '.', array_slice( $url, $key - 1 ) );
			}
			array_pop( $parts );
		}

		return implode( '.', $url );
	}
}
