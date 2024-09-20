<?php

namespace GoogleLogin\AllowedDomains;

use GoogleLogin\Constants;

class PublicSuffixArrayPath {
	/**
	 * @param string $dir
	 * @return string
	 */
	public static function fromDirectory( $dir ) {
		if ( !$dir ) {
			$dir = __DIR__ . '/../../';
		} elseif ( $dir[-1] !== '/' ) {
			$dir .= '/';
		}
		return $dir . Constants::PUBLIC_SUFFIX_ARRAY_FILE;
	}
}
