<?php
namespace GoogleLogin;

use User;

class GoogleUser {
	/**
	 * Check, if the Google ID is already connected to another wiki account or not.
	 *
	 * @param $id
	 * @param int $flags
	 * @return bool
	 */
	public static function isGoogleIdFree( $googleId, $flags = User::READ_LATEST ) {
		return $user = self::getUserFromGoogleId( $googleId, $flags ) === null;
	}

	/**
	 * Helper function for load* functions. Loads the Google Id from a
	 * User Id set to this object.
	 *
	 * @param User $user The user to get the Google Id for
	 * @param integer $flags User::READ_* constant bitfield
	 * @return bool False, if no Google ID connected with this User ID, true otherwise
	 */
	public static function getGoogleIdFromUser( User $user, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_SLAVE );

		$s = $db->select(
			'user_google_user',
			[ 'user_googleid' ],
			[ 'user_id' => $user->getId() ],
			__METHOD__,
			( ( $flags & User::READ_LOCKING ) == User::READ_LOCKING )
				? [ 'LOCK IN SHARE MODE' ]
				: []
		);

		if ( $s !== false ) {
			$ids = [];
			foreach ( $s as $obj ) {
				$ids[] = $obj->user_googleid;
			}
			return $ids;
		}
		// Invalid user_id
		return null;
	}


	/**
	 * Helper function for load* functions. Loads the Google Id from a
	 * User Id set to this object.
	 *
	 * @param string $googleId The Google ID to get the user to
	 * @param integer $flags User::READ_* constant bitfield
	 * @return bool False, if no Google ID connected with this User ID, true otherwise
	 */
	public static function getUserFromGoogleId( $googleId, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_SLAVE );

		$s = $db->selectRow(
			'user_google_user',
			[ 'user_id' ],
			[ 'user_googleid' => $googleId ],
			__METHOD__,
			( ( $flags & User::READ_LOCKING ) == User::READ_LOCKING )
				? [ 'LOCK IN SHARE MODE' ]
				: []
		);

		if ( $s !== false ) {
			// Initialise user table data;
			return User::newFromId( $s->user_id );
		}
		// Invalid user_id
		return null;
	}

	/**
	 * Returns true, if this user object is connected with a google account,
	 * otherwise false.
	 *
	 * @param User $user The user to check
	 * @return bool
	 */
	public static function hasConnectedGoogleAccount( User $user ) {
		return self::getGoogleIdFromUser( $user ) !== null;
	}

	/**
	 * Terminates a connection between this wiki account and the
	 * connected Google account.
	 *
	 * @param User $user The user to connect from where to remove the connection
	 * @param string $googleId The Google ID to remove
	 * @return bool
	 */
	public static function terminateGoogleConnection( User $user, $googleId ) {
		$connectedIds = self::getGoogleIdFromUser( $user );
		// make sure, that the user has a connected user account
		if ( $connectedIds === null || !in_array( $googleId, $connectedIds ) ) {
			// already terminated
			return true;
		}

		// get DD master
		$dbr = wfGetDB( DB_MASTER );
		// try to delete the row with this google id
		if (
			$dbr->delete(
				"user_google_user",
				"user_googleid = " . $googleId,
				__METHOD__
			)
		) {
			return true;
		}

		// something went wrong
		return false;
	}

	/**
	 * Insert's or update's the Google ID connected with this user account.
	 *
	 * @param User $user The user to connect the Google ID with
	 * @param String $googleId The new Google ID
	 * @return bool Whether the insert/update statement was successful
	 */
	public static function connectWithGoogle( User $user, $googleId ) {
		$dbr = wfGetDB( DB_MASTER );

		return $dbr->insert(
			"user_google_user",
			[
				'user_id' => $user->getId(),
				'user_googleid' => $googleId
			]
		);
	}
}
