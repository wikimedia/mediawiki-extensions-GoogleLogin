<?php
class SpecialGoogleLogin extends SpecialPage {
	/** @var $mGoogleLogin saves an instance of GoogleLogin class */
	private $mGoogleLogin;

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
		$request = $this->getRequest();
		$out = $this->getOutput();
		$out->enableOOUI();

		if ( session_id() == '' ) {
			wfSetupSession();
		}

		// Check, if a user clicked the login button on the login/create form
		if ( $request->getVal( 'googlelogin-submit' ) !== null ) {
			$googleLogin->setLoginParameter( $request );
			$client = $googleLogin->getClient();
			$authUrl = $client->createAuthUrl();
			$out->redirect( $authUrl );
			$out->output();
			return;
		}

		$config = $this->getConfig();
		// first set our own handler for catchable fatal errors
		set_error_handler( 'GoogleLogin::catchableFatalHandler', E_RECOVERABLE_ERROR );

		$this->setHeaders();
		$db = new GoogleLoginDB;

		// add module styles
		$out->addModules( 'ext.GoogleLogin.style' );

		$par = ( empty( $par ) ? $request->getVal( 'action' ) : $par );
		$googleLogin->setLoginParameter( $request );
		$client = $googleLogin->getClient();
		$plus = $googleLogin->getPlus();

		if ( $request->getVal( 'code' ) !== null ) {
			try {
				$client->authenticate( $request->getVal( 'code' ) );
				$request->setSessionData( 'access_token', $client->getAccessToken() );
				try {
					$userInfo = $plus->people->get( "me" );
				} catch ( Exception $e ) {
					$this->createError( $e->getMessage() );
					return;
				}
				$this->createOrMerge( $userInfo, $db, true );
			} catch ( Google_Auth_Exception $e ) {
				$this->createError( $e->getMessage() );
			}
		} elseif ( $request->getVal( 'error' )  !== null ) {
			$this->createError( 'Authentication failed' );
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
					try {
						$userInfo = $plus->people->get( "me" );
					} catch ( Exception $e ) {
						$this->createError( $e->getMessage() );
						return;
					}
					$googleIdExists = $db->googleIdExists( $userInfo['id'] );
					// data that will be added to the account information box
					$data = array(
						'Google-ID' => $userInfo['id'],
						$this->msg( 'googlelogin-googleuser' )->text() => $userInfo['displayName'],
						$this->msg( 'googlelogin-email' )->text() => $userInfo['emails'][0]['value'],
						$this->msg( 'googlelogin-linkstatus' )->text() => ( $googleIdExists ?
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
					$this->createOrMerge( $userInfo, $db );
				}
			} else {
				$authUrl = $client->createAuthUrl();
				$out->redirect( $authUrl );
			}
		}
		$this->addBacklink( $par );
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
	 * @param DatabaseBase $db DBAL to use for all other actions against db.
	 * @param boolean $redirect If true, the function will redirect to Special:GoogleLogin if
	 * 	nothing to display.
	 */
	private function createOrMerge( $userInfo, $db, $redirect = false ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$googleIdExists = $db->googleIdExists( $userInfo['id'] );
		if (
			$this->mGoogleLogin->isValidDomain(
				$userInfo['emails'][0]['value']
			)
		) {
			if ( !$googleIdExists ) {
				if ( !$user->isLoggedIn() ) {
					if ( $this->mGoogleLogin->isCreateAllowed() ) {
						$this->createGoogleUserForm( $userInfo, $db );
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
					if ( $user->getId() != $googleIdExists['id'] ) {
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
						$googleIdExists['id'],
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
	 * @param DatabaseBase $db The DBAL to request db data from
	 */
	private function createGoogleUserForm( $userInfo, $db ) {
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
	private function finishAction( $par, $client, $plus, $db ) {
		$glConfig = $this->mGoogleLogin->getGLConfig();
		// prepare MediaWiki variables/classes we need
		$out = $this->getOutput();
		$request = $this->getRequest();
		// get userinfos of google plus api result
		try {
			$userInfo = $plus->people->get( "me" );
		} catch ( Exception $e ) {
			$this->createError( $e->getMessage() );
			return;
		}
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
				if ( $this->mGoogleLogin->isValidRequest() && $this->mGoogleLogin->isCreateAllowed() ) {
					$userName = '';
					$chooseOwn = $request->getVal( 'wpChooseName' );
					if ( $chooseOwn === null ) {
						$this->createGoogleUserForm( $userInfo, $db );
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
							$this->createGoogleUserForm( $userInfo, $db );
						} elseif(
							$request->getVal( 'wpChooseOwn' ) !== '' &&
							!GoogleLogin::isValidUsername( $request->getVal( 'wpChooseOwn' ) )
						) {
							$this->createGoogleUserForm( $userInfo, $db );
						} else {
							$userName = $request->getVal( 'wpChooseOwn' );
						}
					}
					if ( $chooseOwn === 'wpDisplayName' ) {
						if ( GoogleLogin::isValidUsername( $userInfo['displayName'] ) ) {
							$userName = $userInfo['displayName'];
						} else {
							$this->createGoogleUserForm( $userInfo, $db );
						}
					}
					if ( $chooseOwn === 'wpGivenName' ) {
						if ( GoogleLogin::isValidUsername( $userInfo['name']['givenName'] ) ) {
							$userName = $userInfo['name']['givenName'];
						} else {
							$this->createGoogleUserForm( $userInfo, $db );
						}
					}
					if ( !empty( $userName ) ) {
						$out->setPageTitle( $this->msg( 'googlelogin-form-choosename-finish-title' )->text() );
						$userParam = array(
							'password' => md5( Rand() ),
							'email' => $userInfo['emails'][0]['value'],
							'real_name' => $userInfo['name']['givenName']
						);
						if ( !$db->googleIdExists( $userInfo['id'] ) ) {
							$user = User::createNew( $userName, $userParam );
							$user->sendConfirmationMail();
							$user->setCookies();
							// create a log entry for the created user - bug 67245
							if ( $glConfig->get( 'GLShowCreateReason' ) ) {
								self::$performer = $userName;
							}
							$logEntry = $user->addNewUserLogEntry( 'create' );
							$db->createConnection( $userInfo['id'], $user->getId() );
							$out->addWikiMsg( 'googlelogin-form-choosename-finish-body', $userName );
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
						} else {
							$this->createError( $this->msg( 'googlelogin-link-other' )->text() );
						}
					}
				} else {
					$this->createError(
						( $this->mGoogleLogin->isCreateAllowed() ? 'Token failure' : 'not allowed' )
					);
				}
			break;
			case 'Merge':
				// To merge an account simply create a connection between the wiki user id and the
				// Google id.
				if ( $this->mGoogleLogin->isValidRequest() ) {
					$user = $this->getUser();
					$userId = $user->getId();
					if ( !empty( $userInfo['id'] ) && !empty( $userId ) ) {
						if ( $db->createConnection( $userInfo['id'], $user->getId() ) ) {
							$out->addWikiMsg( 'googlelogin-success-merge' );
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
				if ( $this->mGoogleLogin->isValidRequest() && !$db->googleIdExists( $userInfo['id'] ) ) {
					$request->setSessionData( 'access_token', '' );
					$out->redirect( $this->getPageTitle()->getLocalUrl() );
				} else {
					$this->createError( 'Token failure' );
				}
			break;
			case 'Unlink':
				// Remove the connection between user id and google id
				if ( $this->mGoogleLogin->isValidRequest() ) {
					if ( $db->terminateConnection( $userInfo['id'] ) ) {
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
				$this->createOrMerge( $userInfo, $db );
			break;
		}
	}

	protected function getGroupName() {
		return 'login';
	}
}