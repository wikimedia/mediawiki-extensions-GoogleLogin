<?php
	class SpecialGoogleLogin extends SpecialPage {
		function __construct() {
			parent::__construct( 'GoogleLogin' );
		}

		/**
		 * First function after call of special page, handles what is need to do and is simply magic!
		 * @param SubPage $par Subpage submitted to this Special page.
		 */
		function execute( $par ) {
			global $wgScriptDir;
			$this->setHeaders();
			$request = $this->getRequest();
			$out = $this->getOutput();
			$out->addStyle( $wgScriptDir . '/extensions/GoogleLogin/style/style.css' );
			$db = new GoogleLoginDB;

			$client = $this->includeAPIFiles();
			$plus = $this->prepareClient( $client );

			if ( $request->getVal( 'code' ) !== null ) {
				$client->authenticate( $request->getVal( 'code' ) );
				$request->setSessionData( 'access_token', $client->getAccessToken() );
				$userInfo = $plus->people->get("me");
				$this->createOrMerge( $userInfo, $db );
			} elseif ( $request->getVal( 'error' )  !== null ) {
				$out->addWikiMsg( 'googlelogin-unexpected-error' );
				$out->addHtml(
					Html::element( 'br' ) .
					Html::element( 'a',
						array(
							'href' => $this->getPageTitle()->getLocalUrl()
						),
						wfMessage( 'googlelogin-form-backlink' )->text()
					)
				);
			} else {
				if (
					$request->getSessionData( 'access_token' ) !== null &&
					$request->getSessionData( 'access_token' )
				) {
					$client->setAccessToken( $request->getSessionData( 'access_token' ) );
					$request->setSessionData( 'access_token', $client->getAccessToken() );
					if ( !empty( $par ) ) {
						$this->finishAction( $par, $client, $plus, $db );
					} else {
						$userInfo = $plus->people->get("me");
						$googleIdExists = $db->GoogleIdExists( $userInfo['id'] );
						$buildTable = array(
							array(
								'Google-ID', $userInfo['id']
							),
							array(
								wfMessage( 'googlelogin-googleuser' )->text(), $userInfo['displayName']
							),
							array(
								wfMessage( 'googlelogin-email' )->text(), $userInfo['emails'][0]['value']
							),
							array(
								wfMessage( 'googlelogin-linkstatus' )->text(),
								( $googleIdExists ? wfMessage( 'googlelogin-linked' )->text() :
									wfMessage( 'googlelogin-unlinked' )->text() )
							)
						);
						$tableAttribs = array(
							'class' => 'googlelogin-summary'
						);
						if ( !$googleIdExists ) $this->GoogleUserForm( 'Logout' );
						$out->addHtml( Html::openelement( 'fieldset' ) .
							Html::element( 'legend', null, wfMessage( 'googlelogin-information-title' )->text() ) .
							Html::element( 'label', null, wfMessage( 'googlelogin-information-body' )->text() ) .
							Xml::buildTable( $buildTable, $tableAttribs ) .
							Html::closeelement( 'fieldset' )
						);
						$this->createOrMerge( $userInfo, $db );
					}
				} else {
					$authUrl = $client->createAuthUrl();
					$out->redirect( $authUrl );
				}
			}
		}

		/**
		 * Used to determine what is to do, merge, creata or login the authenticated google user.
		 * @param array $userInfo array of User information provided by Google OAuth2 G+ Api
		 * @param DatabaseBase $db DBAL to use for all other actions against db.
		 */
		private function createOrMerge( $userInfo, $db ) {
			$out = $this->getOutput();
			$request = $this->getRequest();
			$user = $this->getUser();
			$googleIdExists = $db->GoogleIdExists( $userInfo['id'] );
			if ( !$googleIdExists ) {
				if ( !$user->isLoggedIn() ) {
					$this->createGoogleUserForm( $userInfo, $db );
				} else {
					$this->GoogleUserForm( 'Merge' );
				}
			} else {
				if ( $user->isLoggedIn() ) {
					if ( $user->getId() != $googleIdExists['id'] ) {
						$out->addWikiMsg( 'googlelogin-link-other' );
					} else {
						if ( $request->getVal( 'code' ) !== null ) {
							// if user logged into google account and is already logged in and linked, show the whole special page, not only a button
							// bug 67486
							$out->redirect( $this->getPageTitle()->getLocalUrl() );
						} else {
							$this->GoogleUserForm( 'Unlink' );
						}
					}
				} else {
					$this->loginGoogleUser( $googleIdExists['id'] );
				}
			}
		}

		/**
		 * Includes and initiates the needed Google API Files and classes.
		 * @return Google_Client
		 * @throw MWException if required files does not exist
		 */
		private function includeAPIFiles() {
			$dir = __DIR__;
			if (
				file_exists( $dir . '/Google/Client.php' ) &&
				file_exists( $dir . '/Google/Service/Plus.php' )
			) {
				// Add location of Google api in include path to enable includes from Google Client
				set_include_path(get_include_path() . PATH_SEPARATOR . $dir . '/');
				require_once( $dir . '/Google/Client.php' );
				require_once( $dir . '/Google/Service/Plus.php' );
				// yes, we initiate Google client here ;)
				$client = new Google_Client();
				return $client;
			} else {
				throw new MWException();
			}
		}

		/**
		 * Sets some standard parameters to the Google Client provided, so the client is able to request
		 * the data from Google.
		 * @param Google_Client $client The Google_Client Object to prepare for
		 * @return Google_Service_Plus An instance of Google Plus Client
		 */
		private function prepareClient( $client ) {
			global $wgGoogleSecret, $wgGoogleAppId, $wgGoogleAppName;
			$client->setClientId( $wgGoogleAppId );
			$client->setClientSecret( $wgGoogleSecret );
			$client->setRedirectUri( WebRequest::detectServer().$this->getPageTitle()->getLocalUrl() );
			$client->addScope( "https://www.googleapis.com/auth/userinfo.profile" );
			$client->addScope( "https://www.googleapis.com/auth/userinfo.email" );

			$plus = new Google_Service_Plus($client);
			return $plus;
		}

		/**
		 * Handles the login for an authenticated Google User and redirect to the Main page
		 * @param UserId $id The User id to login
		 */
		private function loginGoogleUser( $id ) {
			$out = $this->getOutput();
			$user = User::newFromId( $id );
			$user->setCookies();
			$out->redirect( Title::newMainPage()->getFullURL() );
		}

		/**
		 * Create and show a Form to for the user to create a new Wiki user account. Called after
		 * Google user logged in and Google id not linked to an existing wiki user.
		 * @param array $userInfo An array of user information provided by Google Plus API
		 * @param DatabaseBase $db The DBAL to request db data from
		 */
		private function createGoogleUserForm( $userInfo, $db ) {
			$request = $this->getRequest();
			$this->getOutput()->setPageTitle( wfMessage( 'googlelogin-form-choosename-title' )->text() );
			$names = array();
			if ( SpecialGoogleLogin::isValidUserName( $userInfo['displayName'] ) ) {
				$names[$userInfo['displayName']] = 'wpDisplayName';
			}
			if ( SpecialGoogleLogin::isValidUserName( $userInfo['name']['givenName'] ) ) {
				$names[$userInfo['name']['givenName']] = 'wpGivenName';
			}
			$names[wfMessage( 'googlelogin-form-chooseown' )->text()] = 'wpOwn';
			$defaultName = ($request->getVal( 'wpChooseName' ) !== '' ? $request->getVal( 'wpChooseName' ) : 'wpOwn');
			$formElements = array(
				'ChooseName' => array(
					'section' => 'choosename',
					'class' => 'HTMLRadioField',
					'type' => 'radio',
					'options' => $names,
					'default' => $defaultName,
				),
				'ChooseOwn' => array(
					'section' => 'choosename',
					'class' => 'HTMLTextField',
					'default' => $request->getVal( 'wpChooseOwn' ),
					'label' => wfMessage( 'googlelogin-form-chooseown' )->text() . ':',
					'help' => wfMessage( 'googlelogin-form-choosename-help' )->text()
				),
            );
			$htmlForm = new HTMLForm( $formElements, $this->getContext(), 'googlelogin-form' );
			$htmlForm->addHiddenField( 'wpSecureHash', $this->getRequestToken() );
			$htmlForm->setSubmitText( wfMessage( 'googlelogin-form-create' )->text() );
			$htmlForm->setAction( $this->getPageTitle( 'Create' )->getLocalUrl() );
			$htmlForm->setSubmitCallback( array( 'SpecialGoogleLogin', 'submitChooseName' ) );
			#$htmlForm->setDisplayFormat( 'vform' );

			$htmlForm->show();
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
					!SpecialGoogleLogin::isValidUserName( $data['ChooseOwn'] )
				) {
					return wfMessage( 'googlelogin-form-choosename-existerror', $data['ChooseOwn'] )->text();
				}
			}
			return true;
		}

		/**
		 * Creates a standard form with only a button and hidden securehash field for one-button-actions
		 * @param string $action The action the button will link to (note: this string is the suffix for the Message
		 *	key for the buttons name)
		 */
		private function GoogleUserForm( $action ) {
			$htmlForm = new HTMLForm( array(), $this->getContext(), 'googlelogin-form' . strtolower( $action ) );

			$htmlForm->setSubmitText( wfMessage( 'googlelogin-form-' . strtolower( $action ) )->text() );
			$htmlForm->addHiddenField( 'wpSecureHash', $this->getRequestToken() );
			$htmlForm->setAction( $this->getPageTitle( $action )->getLocalUrl() );
			$htmlForm->show();
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
		 * Checks, if the submitted secure token is valid (check before do any "write" action after form submit)
		 * @return boolean
		 */
		private function isRequestValid() {
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
		 * Generates a request token to send as securehash and save this token as session variable
		 * @return string
		 */
		private function getRequestToken() {
			$request = $this->getRequest();
			if ( !isset( $this->requestToken ) ) {
				$this->requestToken = md5( rand() );
			}
			$request->setSessionData( 'googlelogin_secure_token', $this->requestToken );
			return $this->requestToken;
		}

		/**
		 * Handles action for subpages (submitted tasks to do), atm this tasks:
		 * - Create account (Create)
		 * - Merge Google<->Wiki accounts (Merge)
		 * - Login with another Google account (only unlinked google accounts!) (Logout)
		 * - Unlink Wiki and Google account (Unlink)
		 */
		private function finishAction( $par, $client, $plus, $db ) {
			// prepare MediaWiki variables/classes we need
			$out = $this->getOutput();
			$request = $this->getRequest();
			// get userinfos of google plus api result
			$userInfo = $plus->people->get("me");
			switch ( $par ) {
				default:
					// here is nothing to see!
					$out->addWikiMsg( 'googlelogin-parerror' );
				break;
				case 'Create':
					// Handles the creation of a new wikiuser, but before: check, if no-one changed the username
					// and is still valid
					// Finish with the creation of connection between user id and google id
					if ( $this->isRequestValid() ) {
						$userName = '';
						if ( $request->getVal( 'wpChooseName' ) === null ) {
							$this->createGoogleUserForm( $userInfo, $db );
						}
						if ( $request->getVal( 'wpChooseName' ) === 'wpOwn' ) {
							if ( $request->getVal( 'wpChooseOwn' ) === '' ) {
								$this->createGoogleUserForm( $userInfo, $db );
							} elseif(
								$request->getVal( 'wpChooseOwn' ) !== '' &&
								!SpecialGoogleLogin::isValidUserName( $request->getVal( 'wpChooseOwn' ) )
							) {
								$this->createGoogleUserForm( $userInfo, $db );
							} else {
								$userName = $request->getVal( 'wpChooseOwn' );
							}
						}
						if ( $request->getVal( 'wpChooseName' ) === 'wpDisplayName' ) {
							if ( SpecialGoogleLogin::isValidUserName( $userInfo['displayName'] ) ) {
								$userName = $userInfo['displayName'];
							} else {
								$this->createGoogleUserForm( $userInfo, $db );
							}
						}
						if ( $request->getVal( 'wpChooseName' ) === 'wpGivenName' ) {
							if ( SpecialGoogleLogin::isValidUserName( $userInfo['name']['givenName'] ) ) {
								$userName = $userInfo['name']['givenName'];
							} else {
								$this->createGoogleUserForm( $userInfo, $db );
							}
						}
						if ( !empty( $userName ) ) {
							$out->setPageTitle( wfMessage( 'googlelogin-form-choosename-finish-title' )->text() );
							$userParam = array(
								'password' => md5( Rand() ),
								'email' => $userInfo['emails'][0]['value'],
								'real_name' => $userInfo['name']['givenName']
							);
							$user = User::createNew( $userName, $userParam );
							$user->sendConfirmationMail();
							$user->setCookies();
							$db->createConnection( $userInfo['id'], $user->getId() );
							$out->addWikiMsg( 'googlelogin-form-choosename-finish-body', $userName );
						}
					} else {
						$out->addWikiMsg( 'googlelogin-unexpected-error' );
					}
				break;
				case 'Merge':
					// To merge an account simply create a connection between the wiki user id and the
					// Google id.
					if ( $this->isRequestValid() ) {
						$user = $this->getUser();
						$userId = $user->getId();
						if ( !empty( $userInfo['id'] ) && !empty( $userId ) ) {
							if ( $db->createConnection( $userInfo['id'], $user->getId() ) ) {
								$out->addWikiMsg( 'googlelogin-success-merge' );
							} else {
								$out->addWikiMsg( 'googlelogin-unexpected-error' );
							}
						}
					} else {
						$out->addWikiMsg( 'googlelogin-unexpected-error' );
					}
				break;
				case 'Logout':
					// Reset the access_token (from Google API) and redirect to Special:GoogleLogin
					// (which redirects to the Google Login page)
					if ( $this->isRequestValid() && !$db->GoogleIdExists( $userInfo['id'] ) ) {
						$request->setSessionData( 'access_token', '' );
						$out->redirect( $this->getPageTitle()->getLocalUrl() );
					} else {
						$out->addWikiMsg( 'googlelogin-unexpected-error' );
					}
				break;
				case 'Unlink':
					// Remove the connection between user id and google id
					if ( $this->isRequestValid() ) {
						if ( $db->terminateConnection( $userInfo['id'] ) ) {
							$out->addWikiMsg( 'googlelogin-success-unlink' );
						} else {
							$out->addWikiMsg( 'googlelogin-unexpected-error' );
						}
					} else {
						$out->addWikiMsg( 'googlelogin-unexpected-error' );
					}
				break;
			}
			// For the user: Always show him a way back :)
			$out->addHtml(
				Html::element( 'br' ) .
				Html::element( 'a',
					array(
						'href' => $this->getPageTitle()->getLocalUrl()
					),
					wfMessage( 'googlelogin-form-backlink' )->text()
				)
			);
		}
	}
