<?php

namespace GoogleLogin\AllowedDomains;

class PublicSuffixLookup {
	/** @var array<string,bool> */
	private $lookup;

	/**
	 * @param array<string,bool> $lookup
	 */
	public function __construct( array $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * @param array $suffixes
	 * @return self
	 */
	public static function fromSuffixList( array $suffixes ) {
		$lookup = array_fill_keys( $suffixes, true );
		return new self( $lookup );
	}

	/**
	 * @param string $tld
	 * @return bool
	 */
	public function contains( $tld ) {
		return isset( $this->lookup[$tld] );
	}
}
