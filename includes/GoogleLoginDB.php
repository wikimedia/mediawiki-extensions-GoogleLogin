<?php
	class GoogleLoginDB {
		public function googleIdExists( $googleId, $db = DB_SLAVE ) {
			$dbr = wfGetDB( $db, array(), self::sharedDB() );
			$prefix = self::getPrefix();
			$res = $dbr->select(
				"user_google_user",
				array( 'user_id' ),
				'user_googleid = ' . $googleId,
				__METHOD__
			);
			// $res might be null if the table user_fbconnect wasn't created
			$userId = array();
			if ( $res === 0 ) {
				return false;
			} else {
				foreach( $res as $row ) {
					$userId['id'] = $row->user_id;
				}
				$res->free();
				return $userId;
			}
			return true;
		}

		/**
		 * Returns if the userID is connected with a GoogleId
		 * @todo FIXME: Merge this function with self::googleIdExists()?
		 */
		public function userIdExists( $userId, $db = DB_SLAVE ) {
			$dbr = wfGetDB( $db, array(), self::sharedDB() );
			$prefix = self::getPrefix();
			$res = $dbr->select(
				"user_google_user",
				array( 'user_googleid' ),
				'user_id = "' . $userId . '"',
				__METHOD__
			);
			// $res might be null if the table user_fbconnect wasn't created
			$googleId = array();
			if ( $res === 0 ) {
				return false;
			} else {
				foreach( $res as $row ) {
					$googleId['id'] = $row->user_googleid;
				}
				$res->free();
				return $googleId;
			}
			return true;
		}

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

		public function terminateConnection( $googleId ) {
			$dbr = wfGetDB( DB_MASTER );
			$prefix = self::getPrefix();
			if (
				$dbr->delete(
					"{$prefix}user_google_user",
					"user_googleid = {$googleId}",
					__METHOD__
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
		 * Returns the table prefix name, either $wgDBprefix, $wgSharedPrefix
		 * depending on whether a shared database is in use.
		 */
		private static function getPrefix() {
			global $wgDBprefix, $wgSharedPrefix;
			return self::sharedDB() ? $wgSharedPrefix : ""; // bugfix for $wgDBprefix;
		}
	}
