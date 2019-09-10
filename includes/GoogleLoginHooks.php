<?php

namespace GoogleLogin;

use Config;
use GoogleLogin\Api\ApiGoogleLoginManageAllowedDomains;
use GoogleLogin\Auth\GooglePrimaryAuthenticationProvider;
use GoogleLogin\HtmlForm\HTMLGoogleLoginButtonField;
use MediaWiki\MediaWikiServices;

class GoogleLoginHooks {
	public static function onUserLogoutComplete() {
		global $wgRequest;

		if ( $wgRequest->getSessionData( 'access_token' ) !== null ) {
			$wgRequest->setSessionData( 'access_token', null );
		}
	}

	public static function onLoadExtensionSchemaUpdates( \DatabaseUpdater $updater = null ) {
		$sql = __DIR__ . '/sql';
		$schema = "$sql/user_google_user.sql";
		$updater->addExtensionUpdate( [ 'addTable', 'user_google_user', $schema, true ] );
		if ( !$updater->getDB()->indexExists( 'user_google_user', 'user_id' ) ) {
			$updater->modifyExtensionField( 'user_google_user', 'user_id',
				"$sql/user_google_user_user_id_index.sql" );
		}
		$schema = "$sql/googlelogin_allowed_domains.sql";
		$updater->addExtensionUpdate( [
			'addTable',
			'googlelogin_allowed_domains',
			$schema,
			true,
		] );

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
		/** @var GoogleIdProvider $googleIdProvider */
		$googleIdProvider =
			MediaWikiServices::getInstance()->getService( Constants::SERVICE_GOOGLE_ID_PROVIDER );
		$oldUserGoogleIds = $googleIdProvider->getFromUser( $oldUser );
		$newUserGoogleIds = $googleIdProvider->getFromUser( $oldUser );
		if (
			// the new user exists (e.g. is not Anonymous)
			!$newUser->isAnon() &&
			// the new user doesn't has a google connection already
			empty( $newUserGoogleIds ) && !empty( $oldUserGoogleIds ) ) {
			foreach ( $oldUserGoogleIds as $i => $id ) {
				/** @var GoogleUserMatching $userMatchingService */
				$userMatchingService =
					MediaWikiServices::getInstance()
						->getService( Constants::SERVICE_GOOGLE_USER_MATCHING );
				$token = [ 'sub' => $id ];

				$userMatchingService->unmatch( $oldUser, $token );
				$userMatchingService->match( $newUser, $token );
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
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		if ( isset( $formDescriptor['googlelogin'] ) ) {
			$formDescriptor['googlelogin'] = array_merge( $formDescriptor['googlelogin'], [
				'weight' => 101,
				'flags' => [],
				'class' => HTMLGoogleLoginButtonField::class,
			] );
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
		if ( $event->getType() === 'change-googlelogin' &&
			GoogleLogin::getGLConfig()->get( 'GLEnableEchoEvents' ) ) {
			$bundleString = 'change-googlelogin';
			$agentUser = $event->getAgent();
			if ( $agentUser ) {
				$bundleString .= $agentUser->getId();
			}
		}

		return true;
	}

	public static function onApiMainModuleManager( \ApiModuleManager $moduleManager ) {
		if ( GoogleLogin::getGLConfig()->get( 'GLAllowedDomainsDB' ) ) {
			$moduleManager->addModule( 'googleloginmanagealloweddomain', 'action',
				ApiGoogleLoginManageAllowedDomains::class );
		}

		return true;
	}

	/**
	 * @throws ConfigurationError
	 */
	public static function onSetup() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'googlelogin' );
		if ( !$config->get( 'GLAuthoritativeMode' ) ) {
			return;
		}

		$mainConfig = $services->getMainConfig();
		if ( !self::isOnlyPrimaryProvider( self::authManagerConfig( $mainConfig ) ) ) {
			throw new ConfigurationError( "GoogleLogin runs in authoritative mode, " .
				"but multiple primary authentication providers where found. Found the following providers: " .
				self::primaryProviderNames( self::authManagerConfig( $mainConfig ) ) );
		}
		if ( strpos( $mainConfig->get( 'InvalidUsernameCharacters' ), '@' ) !== false ) {
			throw new ConfigurationError( "GoogleLogin runs in authoritative mode, " .
				"but the @ sign is not allowed to be used in usernames." );
		}
	}

	private static function isOnlyPrimaryProvider( $authManagerConfig ) {
		return count( $authManagerConfig['primaryauth'] ) === 1 ||
			self::firstPrimaryProviderClass( $authManagerConfig ) ===
			GooglePrimaryAuthenticationProvider::class;
	}

	private static function authManagerConfig( Config $mainConfig ) {
		return $mainConfig->get( 'AuthManagerConfig' )
			?: $mainConfig->get( 'AuthManagerAutoConfig' );
	}

	private static function primaryProviderNames( $authManagerConfig ) {
		return implode( ',', array_keys( $authManagerConfig['primaryauth'] ) );
	}

	private static function firstPrimaryProviderClass( $authManagerConfig ) {
		$primaryProviders = array_values( $authManagerConfig['primaryauth'] );

		if ( isset( $primaryProviders[0]['class'] ) ) {
			return $primaryProviders[0]['class'];
		}
		return null;
	}
}
