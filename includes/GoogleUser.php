<?php
namespace GoogleLogin;

class GoogleUser {
	/**
	 * @var string The Google ID of this GoogleUser object
	 */
	private $googleId = '';
	private $userData = null;

	/**
	 * GoogleUser constructor.
	 * @param string $googleId The Google ID which this GoogleUser object represents
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
	 * @param string $data
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
}
