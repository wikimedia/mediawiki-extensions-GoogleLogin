<?php

namespace GoogleLogin\AllowedDomains;

use GoogleLogin\Constants;

/**
 * Represents a single E-Mail address.
 *
 * @package GoogleLogin\AllowedDomains
 */
class EmailDomain {
	private $emailAddress;
	private $domainHost = '';

	/**
	 * @var array
	 */
	private $publicSuffixes;

	/**
	 * EmailDomain constructor.
	 *
	 * @param String $mail The whole e-mail address, which is represented by this object
	 * @param bool $strict If the domain should be parsed strictly or not (e.g.
	 *  test@test.example.com will be converted to example.com if this is false)
	 * @param array $suffixes An optional array of suffixes to use parsing the domain part. If
	 *  not set, the Array will be optained from the publicSuffixArray file (see
	 *  Constants::PUBLIC_SUFFIX_ARRAY_FILE), which needs to be initialized with the
	 *  updatePublicSuffixArray.php maintenance script.
	 * @throws \UnexpectedValueException Thrown, when the publix suffix array file does not exist.
	 */
	public function __construct( $mail, $strict = false, array $suffixes = [] ) {
		if ( $suffixes ) {
			$this->publicSuffixes = array_flip( $suffixes );
		} else {
			$this->publicSuffixes = $this->getPublicSuffixArray();
		}

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
	 * @return String The raw e-mail address which was passed to this object during it's creation.
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
			if ( isset( $this->publicSuffixes[$tld] ) ) {
				return implode( '.', array_slice( $url, $key - 1 ) );
			}
			array_pop( $parts );
		}

		return implode( '.', $url );
	}

	/**
	 * @return array
	 */
	private function getPublicSuffixArray() {
		$file = __DIR__ . '/../../' . Constants::PUBLIC_SUFFIX_ARRAY_FILE;
		if ( !file_exists( $file ) ) {
			throw new \UnexpectedValueException( 'The public suffix array file does not exist at'
				. ' the expecte dlocation: ' . $file . '. Have you forgotten to run the '
				. 'updatePublicSuffixArray.php maintenance script to create it?' );
		}
		$content = include $file;
		if ( !is_array( $content ) ) {
			throw new \UnexpectedValueException( 'The content returned by the public suffix '
				. 'array file is expected to be an array, got ' . gettype( $content ) );
		}
		return array_flip( $content );
	}
}
