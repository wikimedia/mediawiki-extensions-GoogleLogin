<?php

namespace GoogleLogin;

use ConfigFactory;
use Linker;
use SpecialPage;
use ChangeTags;

use GoogleLogin\Specials\SpecialGoogleLogin;

class GoogleLoginHooks {
	public static function onUserLogoutComplete() {
		global $wgRequest;

		if ( $wgRequest->getSessionData( 'access_token' ) !== null ) {
			$wgRequest->setSessionData( 'access_token', null );
		}
	}

	public static function onLoadExtensionSchemaUpdates( \DatabaseUpdater $updater = null ) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		// Don't create tables on a shared database
		$sharedDB = $config->get( 'SharedDB' );
		if (
			!empty( $sharedDB ) &&
			$sharedDB !== $config->get( 'DBname' )
		) {
			return true;
		}

		// Sql directory inside the extension folder
		$sql = __DIR__ . '/sql';
		$schema = "$sql/user_google_user.sql";
		$updater->addExtensionUpdate( [ 'addTable', 'user_google_user', $schema, true ] );
		if ( $updater->getDB()->indexExists( 'user_google_user', 'PRIMARY' ) ) {
			$updater->modifyExtensionField( 'user_google_user',
				'user_googleid',
				"$sql/user_google_id_no_primary.sql" );
		}
		return true;
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
		$userIdExists = GoogleUser::hasConnectedGoogleAccount( $user );

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
	public static function onMergeAccountFromTo( &$oldUser, &$newUser ) {
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
			$googleIds = GoogleUser::getGoogleIdFromUser( $oldUser );
			foreach ( $googleIds as $i => $id ) {
				// delete the connection between the google and the old wiki account
				GoogleUser::terminateGoogleConnection( $oldUser, $id );
				// add the google id to the new account
				GoogleUser::connectWithGoogle( $newUser, $id );
			}
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
