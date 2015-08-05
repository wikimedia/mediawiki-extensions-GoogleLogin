<?php
class GoogleLoginDB {
	public function createConnection( $googleId, $userId ) {
		$dbr = wfGetDB( DB_MASTER );
		$prefix = self::getPrefix();
		if (
			$dbr->insert(
				"{$prefix}user_google_user",
				array(
					'user_id' => $userId,
					'user_googleid' => $googleId
				),
				__METHOD__,
				array( 'IGNORE' )
			)
		) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Copied from Extension:Facebook
	 * Returns the name of the shared database, if one is in use for the Facebook
	 * Connect users table. Note that 'user_fbconnect' (without respecting
	 * $wgSharedPrefix) is added to $wgSharedTables in FacebookInit::init() by
	 * default. This function can also be used as a test for whether a shared
	 * database for Facebook users is in use.
	 *
	 * See also <http://www.mediawiki.org/wiki/Manual:Shared_database>
	 */
	public static function sharedDB() {
		global $wgExternalSharedDB;
		if ( !empty( $wgExternalSharedDB ) ) {
			return $wgExternalSharedDB;
		}
		return false;
	}

	/**
	 * Copied from Extension:Facebook
	 * Returns the table prefix name.
	 * depending on whether a shared database is in use.
	 */
	private static function getPrefix() {
		global $wgSharedPrefix;
		return self::sharedDB() ? $wgSharedPrefix : ""; // bugfix for;
	}
}
