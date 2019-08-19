<?php
/**
 * Remove invalid events from echo_event and echo_notification
 *
 * @ingroup Maintenance
 */

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script that updates the public suffix array.
 *
 * @ingroup Maintenance
 */
class UpdatePublicSuffixArray extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Updates the list of public suffixes used for domain recognition.' );
		$this->requireExtension( 'GoogleLogin' );
	}

	public function execute() {
		$arrayDirectory = __DIR__ . '/../';
		if ( !is_writable( $arrayDirectory ) ) {
			throw new MWException( $arrayDirectory . ' is not writeable!' );
		}
		$publicSuffixList = file(
			'https://publicsuffix.org/list/public_suffix_list.dat'
		);
		if ( $publicSuffixList === false ) {
			throw new MWException( 'Domainlist can not be downloaded!' );
		}
		$publicSuffixes = [];

		foreach ( $publicSuffixList as $suffix ) {
			$suffix = trim( $suffix );

			if ( !$suffix || strpos( $suffix, '/' ) === 0 ) {
				continue;
			}
			if ( strpos( $suffix, '*.' ) !== false ) {
				$suffix = substr( $suffix, 2 );
			}
			if ( strpos( $suffix, '!' ) === 0 ) {
				$suffix = substr( $suffix, 1 );
			}
			$suffix = implode( '.', array_reverse(
				explode(
					'.',
					$suffix
				)
			) );
			$publicSuffixes[] = $suffix;
		}

		file_put_contents(
			$arrayDirectory . \GoogleLogin\Constants::PUBLIC_SUFFIX_ARRAY_FILE,
			"<?php\n" . 'return [ "' . implode( "\",\n\"", $publicSuffixes ) . '" ];'
		);
	}
}
$maintClass = 'UpdatePublicSuffixArray';
require_once RUN_MAINTENANCE_IF_MAIN;
