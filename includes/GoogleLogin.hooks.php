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
		$extConfig = GoogleLogin::getGLConfig();
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
		if ( !$updater->getDB()->indexExists( 'user_google_user', 'user_id' ) ) {
			$updater->modifyExtensionField( 'user_google_user',
				'user_id',
				"$sql/user_google_user_user_id_index.sql" );
		}

		if ( $extConfig->get( 'GLAllowedDomainsDB' ) ) {
			$schema = "$sql/googlelogin_allowed_domains.sql";
			$updater->addExtensionUpdate( [ 'addTable', 'googlelogin_allowed_domains', $schema, true ] );
		}
		return true;
	}

	/**
	 * MergeAccountFromTo hook handler
	 *
	 * @param \User &$oldUser The user to "merge from"
	 * @param \User &$newUser The user to "merge to"
	 * @return bool
	 */
	public static function onMergeAccountFromTo( &$oldUser, &$newUser ) {
		// check, if
		if (
			// the new user exists (e.g. is not Anonymous)
			!$newUser->isAnon() &&
			// the new user doesn't has a google connection already
			!GoogleUser::hasConnectedGoogleAccount( $newUser ) &&
			// the old user has a google connection
			GoogleUser::hasConnectedGoogleAccount( $oldUser )
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
	 * @param array &$tablesToDelete Array of table => user_id_field to delete
	 * @return bool
	 */
	public static function onUserMergeAccountDeleteTables( &$tablesToDelete ) {
		$tablesToDelete['user_google_user'] = 'user_id';

		return true;
	}

	/**
	 * AuthChangeFormFields hook handler. Give the "Login with Google" button a larger
	 * weight as the LocalPasswordAuthentication Log in button.
	 *
	 * @param array $requests AuthenticationRequests for the current auth attempt
	 * @param array $fieldInfo Array of field information
	 * @param array &$formDescriptor Array of fields in a descriptor format
	 * @param string $action one of the AuthManager::ACTION_* constants.
	 */
	public static function onAuthChangeFormFields( array $requests, array $fieldInfo,
		array &$formDescriptor, $action
	) {
		if ( isset( $formDescriptor['googlelogin'] ) ) {
			$formDescriptor['googlelogin'] = array_merge( $formDescriptor['googlelogin'],
				[
					'weight' => 101,
					'flags' => [],
					'class' => HTMLGoogleLoginButtonField::class
				]
			);
			unset( $formDescriptor['googlelogin']['type'] );
		}
	}

	/**
	 * Add GoogleLogin management events to Echo
	 *
	 * @param array &$notifications Echo notifications
	 * @param array &$notificationCategories Echo categories
	 * @param array &$icons Echo icons
	 * @return bool
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		if ( GoogleLogin::getGLConfig()->get( 'GLEnableEchoEvents' ) ) {
			$notificationCategories['change-googlelogin'] = [
				'priority' => 1,
				'tooltip' => 'echo-pref-tooltip-change-googlelogin',
			];
			$notifications['change-googlelogin'] = [
				\EchoAttributeManager::ATTR_LOCATORS => [
					[ 'EchoUserLocator::locateFromEventExtra', [ 'user' ] ],
				],
				'category' => 'change-googlelogin',
				'group' => 'neutral',
				'section' => 'alert',
				'presentation-model' => 'GoogleLogin\\EchoGoogleLoginPresentationModel',
				'bundle' => [
					'web' => true,
					'expandable' => true,
				],
			];
		}
		return true;
	}

	/**
	 * Bundle GoogleLogin echo notifications if they're made from the same administrator.
	 *
	 * @param \EchoEvent $event The triggering event
	 * @param String &$bundleString The message of the bundle
	 * @return bool
	 */
	public static function onEchoGetBundleRules( \EchoEvent $event, &$bundleString ) {
		if (
			$event->getType() === 'change-googlelogin' &&
			GoogleLogin::getGLConfig()->get( 'GLEnableEchoEvents' )
		) {
			$bundleString = 'change-googlelogin';
			$agentUser = $event->getAgent();
			if ( $agentUser ) {
				$bundleString .= $agentUser->getId();
			}
		}

		return true;
	}
}
