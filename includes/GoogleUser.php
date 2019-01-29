<?php
namespace GoogleLogin;

use User;

class GoogleUser {
	/**
	 * @var string The Google ID of this GoogleUser object
	 */
	private $googleId = '';
	private $userData = null;

	/**
	 * GoogleUser constructor.
	 * @param $googleId string The Google ID which this GoogleUser object represents
	 */
	private function __construct( $googleId ) {
		$this->googleId = $googleId;
	}

	/**
	 * Creates a new GoogleUser object based on the given Google ID. This function
	 * will start a request to the Google+ API to find out the information about
	 * the person who owns the given Google ID.
	 *
	 * @param string $googleId The Google ID for the new GoogleUser object
	 * @return GoogleUser
	 */
	public static function newFromGoogleId( $googleId ) {
		$user = new self( $googleId );
		$user->initGoogleUserFromPlus();

		return $user;
	}

	/**
	 * Creates a new GoogleUser object based on the given user data. This
	 * function will not start a request to the Google+ API and takes the
	 * information given in the $userInfo array as they are.
	 *
	 * @param array|\Google_Service_Plus_Person $userInfo An array or Google_Service_Plus_Person
	 * 	of information about the user returned by the Google+ sign in api
	 * @return GoogleUser|null Returns the GoogleUser object or null, if the
	 *  $userInfo array does not contain an "id" key.
	 */
	public static function newFromUserInfo( $userInfo ) {
		if ( !is_array( $userInfo ) ) {
			throw new \InvalidArgumentException( 'The first paramater of ' . __METHOD__ .
				' is required to be an array, ' . get_class( $userInfo ) . ' given.' );
		}
		if ( !isset( $userInfo['sub'] ) ) {
			return null;
		}
		$user = new self( $userInfo['sub'] );
		$user->userData = $userInfo;

		return $user;
	}

	/**
	 * Loads the data of the person represented by the Google ID.
	 */
	private function initGoogleUserFromPlus() {
		$glConfig = GoogleLogin::getGLConfig();
		if ( !$glConfig->get( 'GLAPIKey' ) ) {
			wfDebug( 'GoogleLogin: Requested to load data for Google ID without setting an' .
				' API key to access Google Plus data ($wgGLAPIKey).' );
			return;
		}
		$requestUrl = 'https://www.googleapis.com/plus/v1/people/' . $this->googleId;
		$requestUrl = wfAppendQuery( $requestUrl, [ 'key' => $glConfig->get( 'GLAPIKey' ) ] );
		$plusCheck = \Http::get( $requestUrl );
		if ( $plusCheck ) {
			$this->userData = json_decode( $plusCheck, true );
		}
	}

	/**
	 * Returns the requested user data of the person with the Google ID represented by this
	 * GoogleUser object or null, if the data is not available.
	 *
	 * @param string $data The data to retrieve
	 * @return null
	 */
	public function getData( $data ) {
		if ( $this->userData !== null && isset( $this->userData[$data] ) ) {
			return $this->userData[$data];
		}
		return null;
	}

	/**
	 * @return string The email address with the Google ID in parentheses, or the Google ID
	 * only
	 */
	public function getEmailWithId() {
		return $this->getWithGoogleId( 'email' );
	}

	/**
	 * @param $data string
	 * @return string
	 */
	private function getWithGoogleId( $data ) {
		if ( $this->getData( $data ) ) {
			return $this->getData( $data ) . ' ' . wfMessage( 'parentheses', $this->googleId );
		}

		return $this->googleId;
	}

	/**
	 * @return string The full name with the Google ID in parentheses, or the Google ID only
	 */
	public function getFullNameWithId() {
		return $this->getWithGoogleId( 'displayName' );
	}

	/**
	 * Check, if the data for the Google ID could be loaded.
	 * @return bool Returns true, if data could be loaded, false otherwise
	 */
	public function isDataLoaded() {
		return $this->userData !== null;
	}

	/**
	 * Check, if the Google ID is already connected to another wiki account or not.
	 *
	 * @param int $googleId The Google ID to check for
	 * @param int $flags A bit mask of flags, see User::READ_*
	 * @return bool
	 */
	public static function isGoogleIdFree( $googleId, $flags = User::READ_LATEST ) {
		return self::getUserFromGoogleId( $googleId, $flags ) === null;
	}

	/**
	 * Helper function for load* functions. Loads the Google Id from a
	 * User Id set to this object.
	 *
	 * @param User $user The user to get the Google Id for
	 * @param int $flags User::READ_* constant bitfield
	 * @return null|array An array of user IDs, null if an error occurred or no IDs was found
	 */
	public static function getGoogleIdFromUser( User $user, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_REPLICA );

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
	 * @param int $flags User::READ_* constant bitfield
	 * @return null|User The local User account connected with the Google ID if
	 * the Google ID is connected to an User, null otherwise.
	 */
	public static function getUserFromGoogleId( $googleId, $flags = User::READ_LATEST ) {
		$db = ( $flags & User::READ_LATEST )
			? wfGetDB( DB_MASTER )
			: wfGetDB( DB_REPLICA );

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
		return (bool)self::getGoogleIdFromUser( $user );
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
