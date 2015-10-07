<?php
use GoogleLogin\GoogleUser as User;
use \User as MWUser;

class SpecialGoogleLogin extends SpecialPage {
	/** @var $mGoogleLogin saves an instance of GoogleLogin class */
	private $mGoogleLogin;
	/** @var $mGLDB saves an instance of GoogleLoginDB class */
	private $mGLDB;

	/** @var $performer Saves the username (which is visible in RC) or false */
	public static $performer = false;

	function __construct() {
		parent::__construct( 'GoogleLogin' );
	}

	/**
	 * First function after call of special page, handles what is need to do and is simply magic!
	 * @param SubPage $par Subpage submitted to this Special page.
	 */
	function execute( $par ) {
		$this->mGoogleLogin = $googleLogin = new GoogleLogin;
		$db = $this->getGoogleLoginDB();
		$request = $this->getRequest();
		$out = $this->getOutput();
		$config = $this->getConfig();

		$this->setHeaders();

		// every time enable OOUI on this special page
		$out->enableOOUI();

		// add module styles
		$out->addModules( 'ext.GoogleLogin.style' );

		// it's possible, that the session isn't started yet (if GoogleLogin
		// replaces MediaWiki login, e.g.)
		if ( session_id() == '' ) {
			wfSetupSession();
		}

		// Check, if a user clicked the login button on the login/create form
		if ( $request->getVal( 'googlelogin-submit' ) !== null ) {
			// ... and redirect them directly to the auth url from google
			$googleLogin->setLoginParameter( $request );
			$client = $googleLogin->getClient();
			$authUrl = $client->createAuthUrl();
			$out->redirect( $authUrl );
			$out->output();
			return;
		}

		// first set our own handler for catchable fatal errors
		set_error_handler( 'GoogleLogin::catchableFatalHandler', E_RECOVERABLE_ERROR );

		// if there is no subpage, use the value of action
		$par = ( empty( $par ) ? $request->getVal( 'action' ) : $par );
		$googleLogin->setLoginParameter( $request );
		// initialize google api client and the client for google plus api
		$client = $googleLogin->getClient();
		$plus = $googleLogin->getPlus();

		// if the user is redirected back from google, try to authenticate
		$authCode = $request->getVal( 'code' );
		if ( $authCode !== null ) {
			$this->tryAuthenticate( $authCode, $client, $plus );
		} elseif ( $request->getVal( 'error' )  !== null ) {
			// if there was an error reported from google, show this to the user
			// FIXME: This should be a localized message!
			$this->createError( 'Authentication failed' );
		} else {
			$access_token = $request->getSessionData( 'access_token' );
			if (
				$access_token !== null &&
				$access_token
			) {
				$client->setAccessToken( $access_token );
				$request->setSessionData( 'access_token', $client->getAccessToken() );

				if ( !empty( $par ) ) {
					$this->finishAction( $par, $client, $plus );
				} else {
					$this->showSummary( $client, $plus );
				}
			} else {
				$authUrl = $client->createAuthUrl();
				$out->redirect( $authUrl );
			}
		}

		// always add a backlink to the subpage
		$this->addBacklink( $par );
	}

	/**
	 * Returns an instance of GoogleLoginDB.
	 *
	 * @return GoogleLoginDB
	 */
	private function getGoogleLoginDB() {
		if ( $this->mGLDB === null ) {
			$this->mGLDB = new GoogleLoginDB();
		}
		return $this->mGLDB;
	}

	/**
	 * Helper function to authenticate a user against google plus api
	 * @param String $code The auth code to use
	 * @param Google_Client $client
	 * @param Google_Service_Plus $plus
	 */
	private function tryAuthenticate( $authCode, Google_Client &$client, Google_Service_Plus &$plus ) {
		$request = $this->getRequest();
		try {
			$client->authenticate( $authCode );
			$request->setSessionData( 'access_token', $client->getAccessToken() );
			try {
				$userInfo = $plus->people->get( "me" );
			} catch ( Exception $e ) {
				$this->createError( $e->getMessage() );
				return;
			}
			$this->createOrMerge( $userInfo, true );
		} catch ( Google_Auth_Exception $e ) {
			$this->createError( $e->getMessage() );
		}
	}

	/**
	 * Show a summary about the actual logged in google user.
	 * @param Google_Client $client
	 * @param Google_Service_Plus $plus
	 */
	private function showSummary( Google_Client $client, Google_Service_Plus $plus ) {
		$request = $this->getRequest();
		$out = $this->getOutput();
		$db = $this->mGLDB;

		try {
			$userInfo = $plus->people->get( "me" );
		} catch ( Exception $e ) {
			$this->createError( $e->getMessage() );
			return;
		}
		$user = User::newFromGoogleId( $userInfo['id'] );

		// data that will be added to the account information box
		$data = array(
			'Google-ID' => $userInfo['id'],
			$this->msg( 'googlelogin-googleuser' )->text() => $userInfo['displayName'],
			$this->msg( 'googlelogin-email' )->text() => $userInfo['emails'][0]['value'],
			$this->msg( 'googlelogin-linkstatus' )->text() => ( $user->hasConnectedGoogleAccount() ?
				$this->msg( 'googlelogin-linked' )->text() : $this->msg( 'googlelogin-unlinked' )->text() ),
		);

		$items = array();
		// expand the data to ooui elements
		foreach ( $data as $label => $d ) {
			$items[] = new OOUI\FieldLayout(
				new OOUI\LabelWidget( array(
					'label' => $d
				) ),
				array(
					'align' => 'left',
					'label' => $label
				)
			);
		}

		// create a wrapper panel
		$container = new OOUI\PanelLayout( array(
			'padded' => true,
			'expanded' => false,
			'framed' => true,
			'infusable' => true,
		) );

		// add the fieldset to the wrapper panel and output it
		$container->appendContent(
			new OOUI\FieldsetLayout( array(
				'infusable' => true,
				'label' => $this->msg( 'googlelogin-information-title' )->text(),
				'items' => $items,
			) )
		);

		$out->addHtml( $container );
		$this->createOrMerge( $userInfo, false, $user );
	}

	/**
	 * Creates a generic error message with further information in $errorMessage.
	 * @param string $errorMessage short description or further information to the error
	 */
	private function createError( $errorMessage ) {
		$out = $this->getOutput();
		$out->addWikiMsg( 'googlelogin-generic-error', $errorMessage );
	}

	/**
	 * Used to determine what is to do, merge, creata or login the authenticated google user.
	 * @param array $userInfo array of User information provided by Google OAuth2 G+ Api
	 * @param boolean $redirect If true, the function will redirect to Special:GoogleLogin if
	 * 	nothing to display.
	 */
	private function createOrMerge( $userInfo, $redirect = false, $gluser = null ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $gluser === null ) {
			$gluser = User::newFromGoogleId( 0 );
		}
		$googleId = $gluser->getGoogleId();

		if (
			$this->mGoogleLogin->isValidDomain(
				$userInfo['emails'][0]['value']
			)
		) {
			if ( !$gluser->hasConnectedGoogleAccount() ) {
				if ( !$user->isLoggedIn() ) {
					if ( $this->mGoogleLogin->isCreateAllowed() ) {
						$this->createGoogleUserForm( $userInfo );
					} elseif ( $redirect ) {
						$out->redirect( $this->getPageTitle()->getLocalUrl() );
					} else {
						$out->addWikiMsg( 'googlelogin-createnotallowed' );
					}
				} else {
					$this->createSubmitButton( 'Merge' );
				}
			} else {
				if ( $user->isLoggedIn() ) {
					if ( $user->getId() != $gluser->getId() ) {
						$out->addWikiMsg( 'googlelogin-link-other' );
					} else {
						if ( $request->getVal( 'code' ) !== null ) {
							// if user logged into google account and is already logged in and linked,
							// show the whole special page, not only a button - bug 67486
							$out->redirect( $this->getPageTitle()->getLocalUrl() );
						} else {
							$this->createSubmitButton( 'Unlink', 'destructive' );
						}
					}
				} else {
					$loginUser = $this->mGoogleLogin->loginGoogleUser(
						$googleId,
						$userInfo['id']
					);
					if ( $loginUser->isOk() ) {
						$out->redirect( $loginUser->getValue() );
					} else {
						$out->addHtml( $loginUser->getHTML() );
					}
				}
			}
		} else {
			$out->addWikiMsg(
				'googlelogin-unallowed-domain',
				$this->mGoogleLogin->getHost(
					$userInfo['emails'][0]['value']
				)
			);
		}
	}

	/**
	 * Create and show a Form to for the user to create a new Wiki user account. Called after
	 * Google user logged in and Google id not linked to an existing wiki user.
	 * @param array $userInfo An array of user information provided by Google Plus API
	 */
	private function createGoogleUserForm( $userInfo ) {
		$request = $this->getRequest();
		$this->getOutput()->setPageTitle( $this->msg( 'googlelogin-form-choosename-title' )->text() );
		$names = array(
			$this->msg( 'googlelogin-login-already-registered' )->text() => 'wpAlreadyRegistered'
		);
		if ( GoogleLogin::isValidUsername( $userInfo['displayName'] ) ) {
			$names[$userInfo['displayName']] = 'wpDisplayName';
		}
		if ( GoogleLogin::isValidUsername( $userInfo['name']['givenName'] ) ) {
			$names[$userInfo['name']['givenName']] = 'wpGivenName';
		}
		$names[$this->msg( 'googlelogin-form-chooseown' )->text() . ':'] = 'wpOwn';
		$defaultName = ( $request->getVal( 'wpChooseName' ) !== null ?
			$request->getVal( 'wpChooseName' ) : 'wpOwn' );
		$formElements = array(
			'ChooseName' => array(
				'type' => 'radio',
				'options' => $names,
				'default' => $defaultName,
			),
			'ChooseOwn' => array(
				'class' => 'HTMLTextField',
				'default' => $request->getVal( 'wpChooseOwn' ),
				'placeholder' => $this->msg( 'googlelogin-form-choosename-placeholder' )->text()
			),
		);
		$htmlForm = HTMLForm::factory( 'ooui', $formElements, $this->getContext(), 'googlelogin-form' );
		$htmlForm->addHiddenField( 'action', 'Create' );
		$htmlForm->addHiddenField( 'wpSecureHash', $this->mGoogleLogin->getRequestToken() );
		$htmlForm->setWrapperLegendMsg( 'googlelogin-form-choosename' );
		$htmlForm->setSubmitText( $this->msg( 'googlelogin-form-next' )->text() );
		$htmlForm->setAction( $this->getPageTitle( 'Create' )->getLocalUrl() );
		$htmlForm->setSubmitCallback( array( 'GoogleLogin', 'submitChooseName' ) );

		$htmlForm->show();
	}

	/**
	 * Creates a standard form with only a button and hidden securehash field for one-button-actions
	 * @param string $action The action the button will link to (note: this string is
	 *	the suffix for the Message key for the buttons name)
	 */
	private function createSubmitButton( $action, $submitClass = null ) {
		$htmlForm = HTMLForm::factory(
			'ooui',
			array(),
			$this->getContext(),
			'googlelogin-form' . strtolower( $action )
		);
		switch ( $submitClass ) {
			case 'progressive':
				$htmlForm->setSubmitProgressive();
				break;
			case 'destructive':
				$htmlForm->setSubmitDestructive();
				break;
		}

		$htmlForm->setSubmitText( $this->msg( 'googlelogin-form-' . strtolower( $action ) )->text() );
		$htmlForm->addHiddenField( 'action', $action );
		$htmlForm->addHiddenField( 'wpSecureHash', $this->mGoogleLogin->getRequestToken() );
		$htmlForm->setAction( $this->getPageTitle( $action )->getLocalUrl() );
		$htmlForm->setSubmitCallback( array( 'GoogleLogin', 'submitGeneric' ) );
		$htmlForm->show();
	}

	/**
	 * Adds a backlink to Special:GoogleLogin if $par is not empty
	 * @param string|null $par Subpage
	 */
	public function addBacklink( $par ) {
		if ( !empty( $par ) ) {
			$out = $this->getOutput();
			$out->addHtml(
				Html::element( 'br' ) .
				Html::element( 'a',
					array(
						'href' => $this->getPageTitle()->getLocalUrl()
					),
					$this->msg( 'googlelogin-form-backlink' )->text()
				)
			);
		}
	}

	/**
	 * Handles action for subpages (submitted tasks to do), atm this tasks:
	 * - Create account (Create)
	 * - Merge Google<->Wiki accounts (Merge)
	 * - Login with another Google account (only unlinked google accounts!) (Logout)
	 * - Unlink Wiki and Google account (Unlink)
	 */
	private function finishAction( $par, $client, $plus ) {
		$glConfig = $this->mGoogleLogin->getGLConfig();
		$db = $this->getGoogleLoginDB();
		$out = $this->getOutput();
		$request = $this->getRequest();

		// get userinfos of google plus api result
		try {
			$userInfo = $plus->people->get( "me" );
		} catch ( Exception $e ) {
			$this->createError( $e->getMessage() );
			return;
		}
		$user = User::newFromGoogleId( $userInfo['id'] );
		$isGoogleIdFree = User::isGoogleIdFree( $userInfo['id'] );

		switch ( $par ) {
			default:
				// here is nothing to see!
				$out->addWikiMsg( 'googlelogin-parerror' );
			break;
			case 'Create':
				if ( !$this->mGoogleLogin->isValidRequest() ) {
					$this->createError( 'Token failure' );
				}
				// Handles the creation of a new wikiuser, but before: check, if no-one changed the username
				// and is still valid
				// Finish with the creation of connection between user id and google id
				if ( $this->mGoogleLogin->isCreateAllowed() ) {
					$userName = '';
					$chooseOwn = $request->getVal( 'wpChooseName' );
					if ( $chooseOwn === null ) {
						$this->createGoogleUserForm( $userInfo );
					}
					// if the user selected the "i have an account" option, redirect to the login page
					// with all required parameter
					if ( $chooseOwn === 'wpAlreadyRegistered' ) {
						// target page query
						$query = array(
							// to redirect the user to the link function directly after login
							'action' => 'Merge',
							// with this parameter, the user doesn't get an error message because of the
							// missing form token
							'fromLogin' => 1
						);

						$out->redirect(
							// redirect to Special:UserLogin
							SpecialPage::getTitleFor( 'UserLogin' )->getFullUrl( array(
								'returnto' => 'Special:GoogleLogin',
								'returntoquery' => wfArrayToCgi( $query ),
								// this parameter disables the "Login with Google" option, it would be
								// pointless here
								'loginmerge' => 1,
								// a custom warning message, if you change the mesasge key, change it in
								// GoogleLoginHooks::onLoginFormValidErrorMessages, too
								'warning' => 'googlelogin-login-merge-warning',
							) )
						);
					}
					if ( $chooseOwn === 'wpOwn' ) {
						if ( $request->getVal( 'wpChooseOwn' ) === '' ) {
							$this->createGoogleUserForm( $userInfo );
						} elseif(
							$request->getVal( 'wpChooseOwn' ) !== '' &&
							!GoogleLogin::isValidUsername( $request->getVal( 'wpChooseOwn' ) )
						) {
							$this->createGoogleUserForm( $userInfo );
						} else {
							$userName = $request->getVal( 'wpChooseOwn' );
						}
					}
					if ( $chooseOwn === 'wpDisplayName' ) {
						if ( GoogleLogin::isValidUsername( $userInfo['displayName'] ) ) {
							$userName = $userInfo['displayName'];
						} else {
							$this->createGoogleUserForm( $userInfo );
						}
					}
					if ( $chooseOwn === 'wpGivenName' ) {
						if ( GoogleLogin::isValidUsername( $userInfo['name']['givenName'] ) ) {
							$userName = $userInfo['name']['givenName'];
						} else {
							$this->createGoogleUserForm( $userInfo );
						}
					}
					if ( !empty( $userName ) ) {
						$out->setPageTitle( $this->msg( 'googlelogin-form-choosename-finish-title' )
							->text() );
						$userParam = array(
							'password' => md5( Rand() ),
							'email' => $userInfo['emails'][0]['value'],
							'real_name' => $userInfo['name']['givenName']
						);
						if ( !$isGoogleIdFree ) {
							// FIXME: Maybe report upstream, that User shouldn't use hardcoded class name for
							// factory methods
							$user = MWUser::createNew( $userName, $userParam );
							$user = User::newFromId( $user->getId() );

							$user->sendConfirmationMail();
							$user->setCookies();
							// create a log entry for the created user - bug 67245
							if ( $glConfig->get( 'GLShowCreateReason' ) ) {
								self::$performer = $userName;
							}
							$logEntry = $user->addNewUserLogEntry( 'create' );
							$user->connectWithGoogle( $userInfo['id'] );
							$out->addWikiMsg( 'googlelogin-form-choosename-finish-body', $userName );
							$this->addReturnTo();
						} else {
							$this->createError( $this->msg( 'googlelogin-link-other' )->text() );
						}
					}
				} else {
					$this->createError( 'not allowed' );
				}
			break;
			case 'Merge':
				// To merge an account simply create a connection between the wiki user id and the
				// Google id.
				if ( $this->mGoogleLogin->isValidRequest() ) {
					$userId = $this->getUser()->getId();
					$user = User::newFromId( $userId );
					if ( !empty( $userInfo['id'] ) && !empty( $userId ) ) {
						if ( $user->connectWithGoogle( $userInfo['id'] ) ) {
							$out->addWikiMsg( 'googlelogin-success-merge' );
							$this->addReturnTo();
						} else {
							$this->createError( 'Database error' );
						}
					}
				} elseif ( $request->getBool( 'fromLogin' ) === true ) {
					$this->createOrMerge( $userInfo, $db );
				} else {
					$this->createError( 'Token failure' );
				}
			break;
			case 'Logout':
				// Reset the access_token (from Google API) and redirect to Special:GoogleLogin
				// (which redirects to the Google Login page)
				if ( $this->mGoogleLogin->isValidRequest() && !$isGoogleIdFree ) {
					$request->setSessionData( 'access_token', '' );
					$out->redirect( $this->getPageTitle()->getLocalUrl() );
				} else {
					$this->createError( 'Token failure' );
				}
			break;
			case 'Unlink':
				// Remove the connection between user id and google id
				if ( $this->mGoogleLogin->isValidRequest() ) {
					if ( $user->terminateGoogleConnection() ) {
						$out->addWikiMsg( 'googlelogin-success-unlink' );
					} else {
						$this->createError( 'Database error' );
					}
				} else {
					$this->createError( 'Token failure' );
				}
			break;
			case 'signup':
				// When GoogleLogin replaces MW login, Special:CreateAccount will
				// redirect to Special:GoogleLogin/signup,
				// handle this here correctly.
				$this->createOrMerge( $userInfo );
			break;
		}
	}

	/**
	 * If there is a return to target, add a backlink to it.
	 */
	private function addReturnTo() {
		$out = $this->getOutput();

		$returnTo = $this->mGoogleLogin->getReturnTo();
		if ( empty( $returnTo['title'] ) ) {
			$redirectTo = Title::newMainPage();
		} else {
			$redirectTo = Title::newFromText(
				$returnTo['title']
			);
		}
		$redirectQuery = wfCgiToArray( $returnTo['query'] );
		$out->addReturnTo( $redirectTo, $redirectQuery );
	}

	protected function getGroupName() {
		return 'login';
	}
}
