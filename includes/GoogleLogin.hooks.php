<?php
use GoogleLogin\GoogleUser as User;

class GoogleLoginHooks {
	public static function onUserLogoutComplete() {
		$googleLogin = new GoogleLogin;
		$request = $googleLogin->getRequest();
		if ( $request->getSessionData( 'access_token' ) !== null ) {
			$request->setSessionData( 'access_token', '' );
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
		$tables = array( 'user_google_user' );
		// Sql directory inside the extension folder
		$sql = dirname( __FILE__ ) . '/sql';
		// Extension of the table schema file (depending on the database type)
		switch ( $updater !== null ? $updater->getDB()->getType() : $config->get( 'DBtype' ) ) {
			default:
				$ext = 'sql';
		}
		// Do the updating
		foreach ( $tables as $table ) {
			// Location of the table schema file
			$schema = "$sql/$table.$ext";
			$updater->addExtensionUpdate( array( 'addTable', $table, $schema, true ) );
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
		if ( !$wgUser->isLoggedIn() && $glConfig->get( 'GLReplaceMWLogin' ) ) {
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
		// GoogleLoginDB instance to check if user is connected
		$db = new GoogleLoginDB;

		// check if the userid is linked with a google id
		$user = User::newFromId( $user->getId() );
		$userIdExists = $user->hasConnectedGoogleAccount();

		// generate the content for Special:Preferences
		$status = ( $userIdExists ? wfMessage( 'googlelogin-linked' )->text() :
			wfMessage( 'googlelogin-unlinked' )->text() );
		$manageLinkMsg = ( $userIdExists ? wfMessage( 'googlelogin-information-title' )->escaped() :
			wfMessage( 'googlelogin-form-merge' )->escaped() );
		$manageLink = Linker::linkKnown( SpecialPage::getTitleFor( 'GoogleLogin' ),
			$manageLinkMsg );
		$manageLink = wfMessage( 'parentheses', $manageLink )->text();

		$prefInsert =
		array( 'googleloginstatus' =>
			array(
				'section' => 'personal/info',
				'label-message' => 'googlelogin-prefs-status',
				'type' => 'info',
				'raw' => true,
				'default' => "<b>$status</b> $manageLink"
			),
		);

		// add the content
		if ( array_key_exists( 'registrationdate', $preferences ) ) {
			$preferences = wfArrayInsertAfter( $preferences, $prefInsert, 'registrationdate' );
		} elseif  ( array_key_exists( 'editcount', $preferences ) ) {
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
}
