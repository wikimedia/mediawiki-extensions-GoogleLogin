<?php
	/**
	GoogleLogin License
	Copyright (c) 2014 Florian Schmidt

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in all
	copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
	SOFTWARE.
	*/

	if ( !defined( 'MEDIAWIKI' ) ) {
		die( 'This is an extension for Mediawiki and can not run standalone.' );
	}

	// master and v2.x requires MediaWiki 1.24
	if ( version_compare( $wgVersion, '1.24c', '<' ) ) {
		echo "This version of GoogleLogin requires MediaWiki 1.24, you have $wgVersion.<br>
		You can <a href='https://www.mediawiki.org/wiki/Upgrade'>upgrade your MediaWiki Installation</a>
		or <a href='https://www.mediawiki.org/wiki/Special:ExtensionDistributor/GoogleLogin'>download a
		version of GoogleLogin</a> which supports your MediaWiki version.";
		die( -1 );
	}

	$wgExtensionCredits['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'GoogleLogin',
		'author' => 'Florian Schmidt',
		'url' => 'https://www.mediawiki.org/wiki/Extension:GoogleLogin',
		'descriptionmsg' => 'googlelogin-desc',
		'version'  => '0.2.0',
		'license-name' => "MIT",
	);

	$dir = __DIR__;

	// Autoload Classes
	$wgAutoloadClasses[ 'GoogleLogin' ] = $dir . '/includes/GoogleLogin.body.php';
	$wgAutoloadClasses[ 'SpecialGoogleLogin' ] = $dir . '/includes/specials/SpecialGoogleLogin.php';
	$wgAutoloadClasses[ 'GoogleLoginHooks' ] = $dir . '/includes/GoogleLogin.hooks.php';
	$wgAutoloadClasses[ 'GoogleLoginDB' ] = $dir . '/includes/GoogleLoginDB.php';
	$wgAutoloadClasses[ 'GoogleLoginAuth' ] = $dir . '/includes/GoogleLoginAuth.php';

	$wgExtensionFunctions[] = 'efGoogleLoginSetup';

	function efGoogleLoginSetup() {
		global $wgAuth;
		// check if we can load wgAuth automatically
		if ( class_exists( 'GoogleLoginAuth' ) ) {
			// load $wgAuth
			$wgAuth = new GoogleLoginAuth;
		}
	}

	// i18n directory and aliases
	$wgExtensionMessagesFiles[ 'GoogleLoginAlias' ] = $dir . '/GoogleLogin.alias.php';
	$wgMessagesDirs['GoogleLogin'] = $dir . '/i18n';

	// Special Page
	$wgSpecialPageGroups[ 'GoogleLogin' ] = 'login';
	$wgSpecialPages[ 'GoogleLogin' ] = 'SpecialGoogleLogin';

	// Hooks
	$wgHooks['UserLogoutComplete'][] = 'GoogleLoginHooks::onUserLogoutComplete';
	$wgHooks['LoadExtensionSchemaUpdates'][] = 'GoogleLoginHooks::onLoadExtensionSchemaUpdates';
	$wgHooks['UserLoginForm'][] = 'GoogleLoginHooks::onUserLoginForm';
	$wgHooks['PersonalUrls'][] = 'GoogleLoginHooks::onPersonalUrls';
	$wgHooks['SpecialPage_initList'][] = 'GoogleLoginHooks::onSpecialPage_initList';
	$wgHooks['GetPreferences'][] = 'GoogleLoginHooks::onGetPreferences';
	$wgHooks['BeforePageDisplay'][] = 'GoogleLoginHooks::onBeforePageDisplay';

	// ResourceLoader modules
	$wgResourceModules['ext.GoogleLogin.style'] = array(
		'styles' => 'style/ext.GoogleLogin.css',
		'position' => 'top',
		'targets' => array( 'desktop', 'mobile' ),
		'localBasePath' => __DIR__,
		'remoteExtPath' => 'GoogleLogin'
	);

	// Create own instance of Config
	$wgConfigRegistry['googlelogin'] = 'GlobalVarConfig::newInstance';

	// Configuration settings defaults

	/**
	 * The Secret key of Google developer console
	 */
	$wgGLSecret = '';

	/**
	 * The App ID of the web application to use for GoogleLogin
	 */
	$wgGLAppId = '';

	/**
	 * Which domains are allowed to login (or create/merge an account) with GoogleLogin
	 * default: empty string -> all domains allowed
	 * to allow special domains, create an array with all allowed domains, example:
	 * array( 'example.com' );
	 */
	$wgGLAllowedDomains = '';

	/**
	 * If $wgGoogleAllowedDomains restrict to specified domains, use strict mode? Means:
	 * Only the exact specified domains are allowed, e.g. if test.example.com is allowed and strict
	 * mode is enabled, example.com isn't allowed (if strict mode is of, it is allowed)
	 */
	$wgGLAllowedDomainsStrict = false;

	/**
	 * If the user creates an account via GoogleLogin, show this as a reason in log?
	 */
	$wgGLShowCreateReason = false;

	/**
	 * Variable to control if there is an "Keep login" checkbox for GoogleLogin right above the
	 * "Login with Google" button on the user login form.
	 */
	$wgGLShowKeepLogin = true;

	/**
	 * Control, if "Keep login" is always enabled (even if the user doesn't checked the keep login box!).
	 */
	$wgGLForceKeepLogin = false;

	/**
	 * If the creation of wiki accounts is allowed with GoogleLogin or not, is handled by this
	 * variable. Default is the value of $wgGroupPermissions['*']['createaccount'] (user right
	 * to create a wiki account).
	 */
	$wgGLAllowAccountCreation = $wgGroupPermissions['*']['createaccount'];

	/**
	 * If true, GoogleLogin replaces the MediaWiki Login function (personal links and
	 * Special:UserLogin) and replace it with GoogleLogin values.
	 */
	$wgGLReplaceMWLogin = false;
