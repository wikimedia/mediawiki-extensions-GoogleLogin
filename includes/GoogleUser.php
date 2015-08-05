<?php
namespace GoogleLogin;

class GoogleUser extends \User {
	/** @var Integer */
	public $mGoogleId;

	/** @var Boolean */
	private $mFromGoogleId = false;

	public static function isGoogleIdFree( $id ) {
		$u = self::newFromGoogleId( $id );
		return $u->getId() === 0;
	}

	/**
	 * Create a new GoogleUser object from a Google ID.
	 *
	 * @param int $id Google ID to load from
	 * @return GoogleLogin::GoogleUser
	 */
	public static function newFromGoogleId( $id ) {
		$u = new self;
		$u->mGoogleId = $id;
		// isn't really the truth, but will be handled later
		$u->mFrom = 'id';
		$u->mFromGoogleId = true;
		$u->setItemLoaded( 'gid' );
		return $u;
	}

	/**
	 * Static factory method for creation from username.
	 *
	 * This is slightly less efficient than newFromId(), so use newFromId() if
	 * you have both an ID and a name handy.
	 *
	 * @param string $name Username, validated by Title::newFromText()
	 * @param string|Bool $validate Validate username. Takes the same parameters as
	 *    User::getCanonicalName(), except that true is accepted as an alias
	 *    for 'valid', for BC.
	 *
	 * @return GoogleUser|bool GoogleUser object, or false if the
	 *    username is invalid (e.g. if it contains illegal characters or is an IP address).
	 *    If the username is not present in the database, the result will be a user object
	 *    with a name, zero user ID and default settings.
	 */
	public static function newFromName( $name, $validate = 'valid' ) {
		if ( $validate === true ) {
			$validate = 'valid';
		}
		$name = self::getCanonicalName( $name, $validate );
		if ( $name === false ) {
			return false;
		} else {
			# Create unloaded user object
			$u = new self;
			$u->mName = $name;
			$u->mFrom = 'name';
			$u->setItemLoaded( 'name' );
			return $u;
		}
	}

	/**
	 * Static factory method for creation from a given user ID.
	 *
	 * @param int $id Valid user ID
	 * @return User The corresponding User object
	 */
	public static function newFromId( $id ) {
		$u = new self;
		$u->mId = $id;
		$u->mFrom = 'id';
		$u->setItemLoaded( 'id' );
		return $u;
	}

	/**
	 * Same as User::loadFromId(), but if this object is created from a GoogleId,
	 * the function will try to load the Google ID first, before the other data
	 * is loaded.
	 *
	 * @param integer $flags User::READ_* constant bitfield
	 * @return bool False if the ID does not exist, true otherwise
	 */
	public function loadFromId( $flags = self::READ_NORMAL ) {
		if ( $this->mFromGoogleId === true ) {
			if ( $this->mGoogleId == 0 ) {
				$this->loadDefaults();
				return false;
			}
			$this->loadFromGoogleId( $flags );
		}

		return parent::loadFromId( $flags );
	}

	/**
	 * Helper function for load* functions. Loads the UserId from a
	 * GoogleId set to this object.
	 *
	 * @param integer $flags User::READ_* constant bitfield
	 * @return bool False, if no User ID connected with this GoogleId, true otherwise
	 */
	private function loadFromGoogleId( $flags = self::READ_LATEST ) {
		$db = ( $flags & self::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_SLAVE );

		$s = $db->selectRow(
			'user_google_user',
			array( 'user_id' ),
			array( 'user_googleid' => $this->mGoogleId ),
			__METHOD__,
			( ( $flags & self::READ_LOCKING ) == self::READ_LOCKING )
				? array( 'LOCK IN SHARE MODE' )
				: array()
		);

		$this->queryFlagsUsed = $flags;

		if ( $s !== false ) {
			// Initialise user table data
			$this->mId = $s->user_id;
			return true;
		} else {
			// Invalid user_id
			$this->mId = 0;
			$this->loadDefaults();
			return false;
		}
	}

	/**
	 * Helper function for load* functions. Loads the Google Id from a
	 * User Id set to this object.
	 *
	 * @param integer $flags User::READ_* constant bitfield
	 * @return bool False, if no Google ID connected with this User ID, true otherwise
	 */
	private function loadGoogleIdFromId( $flags = self::READ_LATEST ) {
		$db = ( $flags & self::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_SLAVE );

		$s = $db->selectRow(
			'user_google_user',
			array( 'user_googleid' ),
			array( 'user_id' => $this->mId ),
			__METHOD__,
			( ( $flags & self::READ_LOCKING ) == self::READ_LOCKING )
				? array( 'LOCK IN SHARE MODE' )
				: array()
		);

		$this->queryFlagsUsed = $flags;

		if ( $s !== false ) {
			// Initialise user table data
			$this->mGoogleId = $s->user_googleid;
			$this->setItemLoaded( 'gid' );
			return true;
		} else {
			// Invalid user_id
			$this->mGoogleId = null;
			return false;
		}
	}

	/**
	 * Load the user table data for this object from the source given by mFrom.
	 *
	 * @param integer $flags User::READ_* constant bitfield
	 */
	public function load( $flags = self::READ_NORMAL ) {
		// check, if Google ID is already loaded
		$loadGoogleId = $this->isItemLoaded( 'gid' );

		// load all data (google id isn't loaded there)
		$retval = parent::load( $flags );

		// if Google Id wasn't loaded, load it now
		if ( !$loadGoogleId ) {
			$retval = $this->loadGoogleIdFromId( $flags ) && $retval;
		}

		return $retval;
	}

	/**
	 * Get the Google Id for this user
	 *
	 * @return int
	 */
	public function getGoogleId() {
		if ( !$this->isItemLoaded( 'gid' ) ) {
			// Don't load if this was initialized from a GoogleID
			$this->load();
		}
		return $this->mGoogleId;
	}

	/**
	 * Returns true, if this user object is connected with a google account,
	 * otherwise false.
	 *
	 * @return bool
	 */
	public function hasConnectedGoogleAccount() {
		// clear the cache, if this instance was loaded from a Google ID
		// otherwise, this will be true everytime
		if ( $this->mFromGoogleId !== false ) {
			$this->clearInstanceCache( 'id' );
		}

		if ( !$this->isItemLoaded( 'gid' ) ) {
			$this->load();
		}
		return $this->mGoogleId !== null;
	}

	/**
	 * Terminates a connection between this wiki account and the
	 * connected Google account.
	 *
	 * @return bool
	 */
	public function terminateGoogleConnection() {
		if ( $this->isItemLoaded( 'gid' ) ) {
			$this->load();
		}

		// make sure, that the user has a connected user account
		if ( !$this->hasConnectedGoogleAccount() ) {
			// already terminate
			return true;
		}

		// get DD master
		$dbr = wfGetDB( DB_MASTER );
		// try to delete the row with this google id
		if (
			$dbr->delete(
				"user_google_user",
				"user_googleid = " . $this->mGoogleId,
				__METHOD__
			)
		) {
			return true;
		}

		// something went wrong
		return false;
	}
}