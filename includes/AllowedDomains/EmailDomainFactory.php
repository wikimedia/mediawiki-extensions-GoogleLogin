<?php

namespace GoogleLogin\AllowedDomains;

use Config;

class EmailDomainFactory {
	/** @var Config */
	private $config;
	/** @var PublicSuffixLookup|null */
	private $publicSuffixLookup;

	/**
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * @param string $mail
	 * @param bool $strict
	 * @return EmailDomain
	 */
	public function newFromEmail( $mail, $strict = false ) {
		return new EmailDomain( $mail, $this->getPublicSuffixLookup(), $strict );
	}

	/**
	 * @return PublicSuffixLookup
	 */
	private function getPublicSuffixLookup() {
		if ( $this->publicSuffixLookup === null ) {
			$path = PublicSuffixArrayPath::fromDirectory(
				$this->config->get( 'GLPublicSuffixArrayDir' )
			);
			$this->publicSuffixLookup = PublicSuffixLookup::fromSuffixList(
				$this->readPublicSuffixArrayFromFile( $path )
			);
		}
		return $this->publicSuffixLookup;
	}

	/**
	 * @param string $file
	 * @return array
	 */
	private function readPublicSuffixArrayFromFile( $file ) {
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
		return $content;
	}
}
