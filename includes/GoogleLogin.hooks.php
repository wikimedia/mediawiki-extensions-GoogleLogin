<?php
use GoogleLogin\GoogleUser as User;

class GoogleLoginHooks {
	public static function onUserLogoutComplete() {
		$googleLogin = new GoogleLogin;
		$request = $googleLogin->getRequest();
		if ( $request->getSessionData( 'access_token' ) !== null ) {
			$request->setSessionData( 'access_token', null );
		}
	}

	public static function onLoadExtensionSchemaUpdates( $updater = null ) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		// Don't create tables on a shared database
		$sharedDB = $config->get( 'SharedDB' );
		if (
			!empty( $sharedDB ) &&
			$sharedDB !== $config->get( 'DBname' )
		) {
			return true;
		}
		// Tables to add to the database
		$tables = [ 'user_google_user' ];
		// Sql directory inside the extension folder
		$sql = __DIR__ . '/sql';
		// Extension of the table schema file (depending on the database type)
		switch ( $updater !== null ? $updater->getDB()->getType() : $config->get( 'DBtype' ) ) {
			default:
				$ext = 'sql';
		}
		// Do the updating
		foreach ( $tables as $table ) {
			// Location of the table schema file
			$schema = "$sql/$table.$ext";
			$updater->addExtensionUpdate( [ 'addTable', $table, $schema, true ] );
		}
		return true;
	}

	public static function onUserLoginForm( &$tpl ) {
		GoogleLogin::getLoginCreateForm( $tpl );
	}

	public static function onUserCreateForm( &$tpl ) {
		GoogleLogin::getLoginCreateForm( $tpl, false );
	}

	/**
	 * Handles the replace of Loginlink and deletion of Create account link in personal tools
	 * if Loginreplacement is configured.
	 */
	public static function onPersonalUrls( array &$personal_urls, Title $title, SkinTemplate $skin ) {
		$glConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'googlelogin' );
		if ( $glConfig->get( 'GLReplaceMWLogin' ) && array_key_exists( 'login', $personal_urls ) ) {
			// unset the create account link
			if ( array_key_exists( 'createaccount', $personal_urls ) ) {
				unset( $personal_urls['createaccount'] );
			}

			// Replace login link with GoogleLogin link
			$googleLogin = new GoogleLogin;
			$personal_urls['login']['text'] = $skin->msg( 'googlelogin' )->text();
			$personal_urls['login']['href'] = $googleLogin->getLoginUrl( $skin, $title );
		}
	}

	/**
	 * Handles the replace of Special:UserLogin with Special:GoogleLogin if Loginreplacement is
	 * configured.
	 */
	public static function onSpecialPage_initList( &$list ) {
		// FIXME: Find a better way to access the User object!
		global $wgUser;

		$glConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'googlelogin' );
		// Replaces the UserLogin special page if configured and user isn't logged in
		// TODO: The check for the MW_NO_SESSION constant is an ugly workaround for T135445
		// given, that the replacement of the user login special page isn't needed after GoogleLogin
		// was converted to to AuthManager (and the own Special page isn't needed anymore). Task T110294
		if (
			!defined( 'MW_NO_SESSION' ) &&
			!$wgUser->isLoggedIn() &&
			$glConfig->get( 'GLReplaceMWLogin' )
		) {
			$list['Userlogin'] = 'SpecialGoogleLogin';
		}
	}

	/**
	* Show the status in Preferences and add a link to SpecialPage
	*
	* @param $user User
	* @param $preferences array
	* @return bool
	*/
	static function onGetPreferences( $user, &$preferences ) {
		// check if the userid is linked with a google id
		$googleUser = User::newFromId( $user->getId() );
		$userIdExists = $googleUser->hasConnectedGoogleAccount();

		// generate the content for Special:Preferences
		$status = ( $userIdExists ? wfMessage( 'googlelogin-linked' )->text() :
			wfMessage( 'googlelogin-unlinked' )->text() );
		$manageLinkMsg = ( $userIdExists ? wfMessage( 'googlelogin-information-title' )->escaped() :
			wfMessage( 'googlelogin-form-merge' )->escaped() );
		$manageLink = Linker::linkKnown( SpecialPage::getTitleFor( 'GoogleLogin' ),
			$manageLinkMsg );
		$manageLink = wfMessage( 'parentheses', $manageLink )->text();

		$prefInsert =
		[ 'googleloginstatus' =>
			[
				'section' => 'personal/info',
				'label-message' => 'googlelogin-prefs-status',
				'type' => 'info',
				'raw' => true,
				'default' => "<b>$status</b> $manageLink"
			],
		];

		// add the content
		if ( array_key_exists( 'registrationdate', $preferences ) ) {
			$preferences = wfArrayInsertAfter( $preferences, $prefInsert, 'registrationdate' );
		} elseif ( array_key_exists( 'editcount', $preferences ) ) {
			$preferences = wfArrayInsertAfter( $preferences, $prefInsert, 'editcount' );
		} else {
			$preferences += $prefInsert;
		}

		return true;
	}

	/**
	 * Replaces the RC comment with a filterable RC tag
	 *
	 * @param RecentChange $recentChange The recentChange object
	 */
	public static function onRecentChange_save( $recentChange ) {
		$performer = $recentChange->getPerformer();
		$attribs = $recentChange->getAttributes();

		if (
			$performer->getName() === SpecialGoogleLogin::$performer &&
			$attribs['rc_log_action'] === 'create'
		) {
			ChangeTags::addTags( 'googlelogin', $attribs['rc_id'] );
		}
	}

	/**
	 * Register, and mark as active, the 'googlelogin' change tag
	 *
	 * @param array $tags
	 * @return bool
	 */
	public static function onListDefinedAndActiveTags( array &$tags ) {
		$tags[] = 'googlelogin';
		return true;
	}

	/**
	 * Adds a custom valid error message to the login page, used when the user want
	 * to link an account with the google account and isn't logged in so far.
	 * @param array $messages Already added messages
	 */
	public static function onLoginFormValidErrorMessages( array &$messages ) {
		$messages[] = 'googlelogin-login-merge-warning';
	}

	/**
	 * UnitTestsList hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @param array $files
	 * @return bool
	 */
	public static function onUnitTestsList( &$files ) {
		$files[] = __DIR__ . '/../tests/phpunit';

		return true;
	}

	/**
	 * Handles Updates to the UserMergeAccountFields of the UserMerge extension.
	 *
	 * @param array &$updateFields
	 */
	public static function onMergeAccountFromTo( &$fromUser, &$toUser ) {
		$oldUser = \GoogleLogin\GoogleUser::newFromId( $fromUser->getId() );
		$newUser = \GoogleLogin\GoogleUser::newFromId( $toUser->getId() );
		// check, if
		if (
			// the new user exists (e.g. is not Anonymous)
			!$newUser->isAnon() &&
			// the new user doesn't has a google connection already
			!$newUser->hasConnectedGoogleAccount() &&
			// the old user has a google connection
			$oldUser->hasConnectedGoogleAccount()
		) {
			// save the google id of the old account
			$googleId = $oldUser->getGoogleId();
			// delete the connection between the google and the old wiki account
			$oldUser->terminateGoogleConnection();
			// add the google id to the new account
			$newUser->connectWithGoogle( $googleId );
		}

		return true;
	}

	/**
	 * Handle, what data needs to be deleted from the GoogleLogin tables when a user is
	 * deleted through the UserMerge extension.
	 *
	 * @param array &$tablesToDelete
	 */
	public static function onUserMergeAccountDeleteTables( &$tablesToDelete ) {
		$tablesToDelete['user_google_user'] = 'user_id';

		return true;
	}
}
