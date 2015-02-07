<?php
	class GoogleLogin extends ContextSource {
		/** @var $mSpecialPageTitle stores the Title object for GL special page */
		private $mSpecialPageTitle;
		/** @var $mRequestToken saves the request token for forms created with this instance */
		private $mRequestToken;
		/** @var $mGoogleClient Stores an instance of GoogleClient */
		private $mGoogleClient;
		/** @var $mPlusClient Stores an instance of Google Service Plus */
		private $mPlusClient;
		/** @var $mHost The Host of E-Mail provided by Google */
		private $mHost;
		/** @var $mConfig Config object created for GoogleLogin extension */
		private $mConfig;

		/**
		 * Returns an prepared instance of Google client to do requests with to Google API
		 * @return Google_Client
		 */
		public function getClient() {
			if ( empty( $this->mGoogleClient ) ) {
				$client = $this->includeAPIFiles();
				$this->mGoogleClient = $this->prepareClient(
					$client,
					$this->getSpecialPageUri()
				);
			}
			return $this->mGoogleClient;
		}

		/**
		 * Returns Config object for use in GoogleLogin.
		 *
		 * @return Config
		 */
		public function getGLConfig() {
			if ( $this->mConfig === null ) {
				$this->mConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'googlelogin' );
			}
			return $this->mConfig;
		}

		/**
		 * Returns a Google_Service_Plus object from the Google_Client object
		 * @return Google_Service_Plus
		 */
		public function getPlus() {
			if ( empty( $this->mPlusClient ) ) {
				$client = $this->mGoogleClient;
				$this->mPlusClient = new Google_Service_Plus( $client );
			}
			return $this->mPlusClient;
		}

		/**
		 * Returns a full login URL to Special:GoogleLogin like normal Login link
		 * @param SkinTemplate $skin The SkinTemplate Object to create link from
		 * @param Title|null $title Title object of the actual page or null
		 * @return string
		 */
		public function getLoginUrl( SkinTemplate $skin, $title = null ) {
			$user = $this->getUser();
			$request = $this->getRequest();
			if ( $title === null ) {
				$title = $this->getTitle();
			}
			if ( $user->isAllowed( 'read' ) ) {
				$page = $title;
			} else {
				$page = Title::newFromText( $request->getVal( 'title', '' ) );
			}
			$page = $request->getVal( 'returnto', $page );
			$a = array();
			if ( strval( $page ) !== '' ) {
				$a['returnto'] = $page;
				$query = array();
				if ( !$request->wasPosted() ) {
					$query = $request->getValues();
					unset( $query['title'] );
					unset( $query['returnto'] );
					unset( $query['returntoquery'] );
				}
				$thisQuery = wfArrayToCgi( $query );
				$query = $request->getVal( 'returntoquery', $thisQuery );
				if ( $query != '' ) {
					$a['returntoquery'] = $query;
				}
			}
			$returnto = wfArrayToCgi( $a );
			return $skin->makeSpecialUrl( 'GoogleLogin', $returnto );
		}

		/**
		 * Provide the URL to navigate to the GL special page
		 * @return string GL special page local url
		 */
		public function getSpecialPageUri() {
			return $this->getSpecialPageTitle()->getLocalUrl();
		}

		/**
		 * Returns, if the user want to keep his login (the value of keep login will be deleted!)
		 * @return boolean
		 */
		public function getKeepLogin() {
			if ( $this->getGLConfig()->get( 'GLForceKeepLogin' ) ) {
				return true;
			}
			$request = $this->getRequest();
			$status = $request->getSessionData( 'wpGoogleLoginRemember' );
			$request->setSessionData( 'wpGoogleLoginRemember', null );
			return ( $status ? true : false );
		}

		/**
		 * Generates a request token to send as securehash and save this token as session variable
		 * @return string
		 */
		public function getRequestToken() {
			$request = $this->getRequest();
			if ( !isset( $this->mRequestToken ) ) {
				$this->mRequestToken = md5( rand() );
			}
			$request->setSessionData( 'googlelogin_secure_token', $this->mRequestToken );
			return $this->mRequestToken;
		}

		/**
		 * Get recirect location data
		 * @return array Array with title and query for redirect location
		 */
		public function getReturnTo() {
			$request = $this->getRequest();
			$returnTo = array();
			$returnTo['title'] = $request->getSessionData( 'google-returnto' );
			$query = $request->getSessionData( 'google-returntoquery' );
			$returnTo['query'] = '';
			if ( $query ) {
				$returnTo['query'] = $request->getSessionData( 'google-returntoquery' );
			}
			$request->setSessionData( 'google-returnto', null );
			return $returnTo;
		}

		/**
		 * Helps to set the correct values for post login redirect, e.g. keep login
		 */
		public function setLoginParameter( $request ) {
			if ( $request->getVal( 'wpGoogleLoginRemember' ) === "1" ) {
				$this->setKeepLogin( true );
			}
			$returnTo = $request->getVal( 'returnto' );
			if ( $returnTo !== null ) {
				$this->setReturnTo( $returnTo, $request->getVal( 'returntoquery' ) );
			}
		}

		/**
		 * Set redirect location, if provided
		 * @param string $returnTo Location to redirect after Login
		 * @param string $returnToQuery Query for the Location URL
		 */
		public function setReturnTo( $returnTo, $returnToQuery ) {
			$request = $this->getRequest();
			$request->setSessionData( 'google-returnto', $returnTo );
			if ( isset( $returnToQuery ) ) {
				$request->setSessionData( 'google-returntoquery', $returnToQuery );
			}
		}

		/**
		 * Set the value, if the user want to keep his login
		 * @param boolean $status True, if the user wants to keep
		 */
		public function setKeepLogin( $status = false ) {
			$request = $this->getRequest();
			$request->setSessionData( 'wpGoogleLoginRemember', $status );
		}

		/**
		 * Checks if the user is allowed to create a new account with Google Login.
		 */
		public function isCreateAllowed() {
			$glConfig = $this->getGLConfig();
			return $glConfig->get( 'GLAllowAccountCreation' );
		}

		/**
		 * Checks, if the submitted secure token is valid (check before do any
		 * "write" action after form submit)
		 * @return boolean
		 */
		public function isValidRequest() {
			$request = $this->getRequest();
			if (
				( $request->getSessionData( 'googlelogin_secure_token' ) ===
					$request->getVal( 'wpSecureHash' ) ) &&
				( $request->getSessionData( 'googlelogin_secure_token' ) !== null &&
					$request->getVal( 'wpSecureHash' ) !== null )
			) {
				$request->setSessionData( 'googlelogin_secure_token', '' );
				return true;
			} else {
				return false;
			}
		}

		/**
		 * If restriction of domains is enabled, check if the user E-Mail is valid before do anything.
		 * @param string $mailDomain The domain of email address
		 * @return boolean
		 */
		public function isValidDomain( $mailDomain ) {
			$glConfig = $this->getGLConfig();
			if ( is_array( $glConfig->get( 'GLAllowedDomains' ) ) ) {
				if (
					in_array(
						$this->getHost( $mailDomain ),
						$glConfig->get( 'GLAllowedDomains' )
					)
				) {
					return true;
				}
				return false;
			}
			return true;
		}

		/**
		 * Checks, if the Username is valid to register
		 * @param string $name The username to check
		 * @return boolean
		 */
		public static function isValidUserName( $name ) {
			if (
				User::isCreatableName( $name ) &&
				User::isValidUserName( $name ) &&
				User::idFromName( $name ) === null
			) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Checks if a User in the given user object exists or not.
		 * @param User $user the User object to check
		 * @return boolean
		 */
		private function userExist( User $user ) {
			$userName = $user->getName();
			$checkUser = User::newFromName( $userName );
			if ( $checkUser ? $checkUser->getId() !== 0 : false ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Handles the login for an authenticated Google User and redirect to the Main page
		 * @param UserId $id The User id to login
		 * @param Google-ID $googleId Id f the Googleuser
		 * @return Status Object of Status with the status of login (and value of redirectto if Ok)
		   otherwise (in case of Fatal) an error message.
		 */
		public function loginGoogleUser( $id, $googleId ) {
			$status = new Status;
			$out = $this->getOutput();
			$user = User::newFromId( $id );
			if ( !$this->userExist( $user ) ) {
				$status = Status::newFatal( 'googlelogin-error-unknownconnected' );
				$db = new GoogleLoginDB;
				$db->terminateConnection( $googleId );
				return $status;
			}
			if ( $user->isBlocked() ) {
				$status = Status::newFatal( 'login-userblocked' );
				return $status;
			}
			$user->setCookies( null, null, $this->getKeepLogin() );
			if ( $user->isLoggedIn() ) {
				$returnTo = $this->getReturnTo();
				$title = Title::newFromText( $returnTo['title'] );
				if ( empty( $returnTo ) || !$title ) {
					$returnTo = Title::newMainPage()->getFullURL();
				} else {
					$returnTo = $title->getFullURL( $returnTo['query'] );
				}
				$status = Status::newGood( $returnTo );
			} else {
				$status = Status::newFatal( 'googlelogin-generic-error', 'Unknown' );
			}

			return $status;
		}

		/**
		 * Returns the title object of the GL special page.
		 * @return Title
		 */
		private function getSpecialPageTitle() {
			if ( empty( $this->mSpecialPageTitle ) ) {
				$this->mSpecialPageTitle = SpecialPage::getTitleFor( 'GoogleLogin' );
			}
			return $this->mSpecialPageTitle;
		}

		/**
		 * Returns the domain and tld (without subdomains) of the provided E-Mailadress
		 * @param string $domain The domain part of the email address to extract from.
		 * @return string The Tld and domain of $domain without subdomains
		 * @see http://www.programmierer-forum.de/domainnamen-ermitteln-t244185.htm
		 */
		public function getHost( $domain = '' ) {
			$glConfig = $this->getGLConfig();
			if ( !empty( $this->mHost ) ) {
				return $this->mHost;
			}
			$dir = __DIR__ . "/..";
			if ( $glConfig->get( 'GLAllowedDomainsStrict' ) ) {
				$domain = explode( '@', $domain );
				// we can trust google to give us only valid email address, so give the last element
				$this->mHost = array_pop( $domain );
				return $this->mHost;
			}
			// for parse_url()
			$domain =
				!isset($domain[5]) ||
				(
					$domain[3] != ':' &&
					$domain[4] != ':' &&
					$domain[5] != ':'
				) ? 'http://' . $domain : $domain;
			// remove "/path/file.html", "/:80", etc.
			$domain = parse_url( $domain, PHP_URL_HOST );
			// separate domain level
			$lvl = explode('.', $domain); // 0 => www, 1 => example, 2 => co, 3 => uk
			// set levels
			krsort( $lvl ); // 3 => uk, 2 => co, 1 => example, 0 => www
			$lvl = array_values( $lvl ); // 0 => uk, 1 => co, 2 => example, 3 => www
			$_1st = $lvl[0];
			$_2nd = isset( $lvl[1] ) ? $lvl[1] . '.' . $_1st : false;
			$_3rd = isset( $lvl[2] ) ? $lvl[2] . '.' . $_2nd : false;
			$_4th = isset( $lvl[3] ) ? $lvl[3] . '.' . $_3rd : false;

			// tld extract
			if ( !file_exists( "$dir/cache/tld.txt" ) ) {
				$this->createTLDCache( "$dir/cache/tld.txt" );
			}
			require ( "$dir/cache/tld.txt" );
			$tlds = array_flip( $tlds );
			if ( // fourth level is TLD
				$_4th &&
				!isset( $tlds[ '!' . $_4th ] ) &&
				(
					isset( $tlds[ $_4th ] ) ||
					isset( $tlds[ '*.' . $_3rd ] )
				)
			) {
				$domain = isset( $lvl[4] ) ? $lvl[4] . '.' . $_4th : false;
			} elseif ( // third level is TLD
				$_3rd &&
				!isset( $tlds[ '!' . $_3rd ] ) &&
				(
					isset($tlds[ $_3rd ]) ||
					isset( $tlds[ '*.' . $_2nd ] )
				)
			) {
				$domain = $_4th;
			} elseif ( // second level is TLD
				!isset( $tlds[ '!' . $_2nd ] ) &&
				(
					isset( $tlds[ $_2nd ] ) ||
					isset( $tlds[ '*.' . $_1st ] )
				)
			) {
				$domain = $_3rd;
			} else { // first level is TLD
				$domain = $_2nd;
			}
			$this->mHost = $domain;
			return $this->mHost;
		}

		/**
		 * Creates the TLD cache from which the valid tld of mail domain comes from.
		 * @param string $cacheFile The file to create the cache too (must be writeable for the
		 * webserver!)
		 * @param int $max_tl How deep the domain list is (enclude example.co.uk (2) or
		 * example.lib.wy.us (3)?)
		 * @see http://www.programmierer-forum.de/domainnamen-ermitteln-t244185.htm
		 */
		private function createTLDCache( $cacheFile, $max_tl = 2 ) {
			$cacheFolder = str_replace( basename( $cacheFile ), '', $cacheFile );
			if ( !is_writable( $cacheFolder ) ) {
				throw new MWException( $cacheFolder . ' is not writeable!' );
			}
			$tlds = file(
				'http://mxr.mozilla.org/mozilla-central/source/netwerk/dns/effective_tld_names.dat?raw=1'
			);
			if ( $tlds === false ) {
				throw new MWException( 'Domainlist can not be downloaded!' );
			}
			$i = 0;
			// remove unnecessary lines
			foreach ( $tlds as $tld ) {
				$tlds[ $i ] = trim( $tld );
				/**
					empty
					comments
					top level domains
					is overboard
				*/
				if (
					!$tlds[ $i ] ||
					$tld[0] == '/' ||
					strpos( $tld, '.' ) === false ||
					substr_count( $tld, '.' ) >= $max_tl
				) {
					unset( $tlds[ $i ] );
				}
				$i++;
			}
			$tlds = array_values( $tlds );
			file_put_contents(
				$cacheFile,
				"<?php\n" . '$tlds = ' . str_replace(
					array( ' ', "\n" ),
					'',
					var_export( $tlds, true )
				) . ";\n?" . ">"
			);
		}

		/**
		 * Sets some standard parameters to the Google Client provided, so the client is able to request
		 * the data from Google.
		 * @param Google_Client $client The Google_Client Object to prepare for
		 * @return Google_Client A prepared instance of Google Client class
		 */
		private function prepareClient( $client, $redirectURI ) {
			$glConfig = $this->getGLConfig();
			$client->setClientId( $glConfig->get( 'GLAppId' ) );
			$client->setClientSecret( $glConfig->get( 'GLSecret' ) );
			$client->setRedirectUri( WebRequest::detectServer().$redirectURI );
			$client->addScope( "https://www.googleapis.com/auth/userinfo.profile" );
			$client->addScope( "https://www.googleapis.com/auth/userinfo.email" );
			return $client;
		}

		/**
		 * Includes and initiates the needed Google API Files and classes.
		 * @return Google_Client
		 * @throw MWException if required files does not exist
		 */
		private function includeAPIFiles() {
			$dir = __DIR__;
			if (
				file_exists( $dir . '/external/Google/Client.php' ) &&
				file_exists( $dir . '/external/Google/Service/Plus.php' )
			) {
				// Add location of Google api in include path to enable includes from Google Client
				set_include_path(get_include_path() . PATH_SEPARATOR . $dir . '/external/');
				require_once ( $dir . '/external/Google/Client.php' );
				require_once ( $dir . '/external/Google/Service/Plus.php' );
				// yes, we initiate Google client here ;)
				$client = new Google_Client();
				return $client;
			} else {
				throw new MWException( 'Not all required files exist, please reinstall GoogleLogin' );
			}
		}

		/**
		 * Callback-function for create form to check, if needed data is set.
		 * @see SpecialGoogleLogin::createGoogleUserForm()
		 * @param array $data The form data submitted to check
		 * @return string|boolean Returns a string with error message, when check fails, and boolean
		 *	for success.
		 */
		public static function submitChooseName( $data ) {
			if (
				!isset( $data['ChooseName'] ) ||
				empty( $data['ChooseName'] )
			) {
				return wfMessage( 'googlelogin-form-choosename-error' )->text();
			} else {
				if ( $data['ChooseName'] == 'wpOwn' && empty( $data['ChooseOwn'] ) ) {
					return wfMessage( 'googlelogin-form-chooseown-error' )->text();
				}
				if (
					$data['ChooseName'] == 'wpOwn' &&
					!empty( $data['ChooseOwn'] ) &&
					!GoogleLogin::isValidUserName( $data['ChooseOwn'] )
				) {
					return wfMessage( 'googlelogin-form-choosename-existerror', $data['ChooseOwn'] )->text();
				}
			}
			return true;
		}

		/**
		 * Generic callback button (always returns true)
		 * @param array $data The form data submitted to check
		 * @return string|boolean Returns a string with error message, when check fails, and boolean
		 *	for success.
		 */
		public static function submitGeneric( $data ) {
			return true;
		}

		/**
		 * Handles all catchable fatal errors
		 *
		 * @param integer $errorNo error Level
		 * @param string $errorString error message
		 * @param string $errorFile in which file the error raised
		 * @param integer $errorLine the line in $errorFile the error raised in
		 * @return boolean Always true
		 */
		public static function catchableFatalHandler(
			$errorNo, $errorString, $errorFile, $errorLine
		) {
			global $wgOut;
			$wgOut->addWikiMsg( 'googlelogin-generic-error', $errorString );
			return true;
		}

		/**
		 * Redirects to GoogleLogin login page if called. Actually used for AuthPlugin
		 * @todo: kill if better solution as AuthPlugin is found
		 */
		public static function externalLoginAttempt() {
			global $wgOut, $wgRequest;
			if (
				$wgRequest->getVal( 'googlelogin-submit' ) !== null &&
				$wgRequest->getVal( 'wpPassword' ) === ''
			) {
				$googleLogin = new GoogleLogin;
				$googleLogin->setLoginParameter( $wgRequest );
				$client = $googleLogin->getClient();
				$authUrl = $client->createAuthUrl();
				$wgOut->redirect( $authUrl );
				$wgOut->output();
			}
		}
	}
