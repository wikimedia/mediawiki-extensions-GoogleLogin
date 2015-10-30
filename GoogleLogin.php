<?php
/**
GoogleLogin License
Copyright (c) 2015 Florian Schmidt

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

// master and v2.x requires MediaWiki 1.26
if ( version_compare( $wgVersion, '1.26c', '<' ) ) {
	echo "This version of GoogleLogin requires MediaWiki 1.26, you have $wgVersion.<br>
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
	'version'  => '0.3.1-git',
	'license-name' => "MIT",
);

// Autoload Classes
$wgAutoloadClasses[ 'GoogleLogin' ] = __DIR__ . '/includes/GoogleLogin.body.php';
$wgAutoloadClasses[ 'SpecialGoogleLogin' ] = __DIR__ . '/includes/specials/SpecialGoogleLogin.php';
$wgAutoloadClasses[ 'SpecialManageGoogleLogin' ] =
	__DIR__ . '/includes/specials/SpecialManageGoogleLogin.php';
$wgAutoloadClasses[ 'GoogleLoginHooks' ] = __DIR__ . '/includes/GoogleLogin.hooks.php';
$wgAutoloadClasses[ 'GoogleLogin\\GoogleUser' ] = __DIR__ . '/includes/GoogleUser.php';
$wgAutoloadClasses[ 'ApiGoogleLoginInfo' ] = __DIR__ . '/includes/api/ApiGoogleLoginInfo.php';

// i18n directory and aliases
$wgExtensionMessagesFiles[ 'GoogleLoginAlias' ] = __DIR__ . '/GoogleLogin.alias.php';
$wgMessagesDirs['GoogleLogin'] = __DIR__ . '/i18n';

// new user rights for this extension
$wgGroupPermissions['sysop']['managegooglelogin'] = true;
$wgAvailableRights[] = 'managegooglelogin';

// Special Page
$wgSpecialPages[ 'GoogleLogin' ] = 'SpecialGoogleLogin';
$wgSpecialPages[ 'ManageGoogleLogin' ] = 'SpecialManageGoogleLogin';

// API Modules
$wgAPIModules['googleplusprofileinfo'] = 'ApiGoogleLoginInfo';

// Hooks
$wgHooks['UserLogoutComplete'][] = 'GoogleLoginHooks::onUserLogoutComplete';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'GoogleLoginHooks::onLoadExtensionSchemaUpdates';
$wgHooks['UserLoginForm'][] = 'GoogleLoginHooks::onUserLoginForm';
$wgHooks['UserCreateForm'][] = 'GoogleLoginHooks::onUserCreateForm';
$wgHooks['PersonalUrls'][] = 'GoogleLoginHooks::onPersonalUrls';
$wgHooks['SpecialPage_initList'][] = 'GoogleLoginHooks::onSpecialPage_initList';
$wgHooks['GetPreferences'][] = 'GoogleLoginHooks::onGetPreferences';
$wgHooks['RecentChange_save'][] = 'GoogleLoginHooks::onRecentChange_save';
$wgHooks['ListDefinedTags'][] = 'GoogleLoginHooks::onListDefinedAndActiveTags';
$wgHooks['ChangeTagsListActive'][] = 'GoogleLoginHooks::onListDefinedAndActiveTags';
$wgHooks['LoginFormValidErrorMessages'][] = 'GoogleLoginHooks::onLoginFormValidErrorMessages';
$wgHooks['UnitTestsList'][] = 'GoogleLoginHooks::onUnitTestsList';

// ResourceLoader modules
// path template
$wgGLResourcePath = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'GoogleLogin'
);

$wgResourceModules += array(
	'ext.GoogleLogin.style' => $wgGLResourcePath + array(
		'styles' => 'style/ext.GoogleLogin.css',
		'position' => 'top',
		'targets' => array( 'desktop', 'mobile' ),
	),
	'ext.GoogleLogin.right.style' => $wgGLResourcePath + array(
		'styles' => 'style/ext.GoogleLogin.right.css',
		'position' => 'top',
	),
);

$wgResourceModules['ext.GoogleLogin.specialManage.scripts'] = $wgGLResourcePath + array(
	'dependencies' => array(
		'mediawiki.api',
		'oojs-ui'
	),
	'scripts' => array(
		'javascripts/specialpages/ext.GoogleLogin.specialManage.js'
	),
	'styles' => array(
		'style/ext.GoogleLogin.specialManage.css'
	),
	'messages' => array(
		'googlelogin-googleuser',
		'googlelogin-manage-isplusser',
		'googlelogin-manage-orgname',
		'googlelogin-manage-orgtitle',
		'googlelogin-manage-orgsince',
		'googlelogin-manage-yes',
		'googlelogin-manage-no',
		'googlelogin-manage-errorloading',
		'googlelogin-manage-dismiss',
		'googlelogin-manage-openpluslink',
		'googlelogin-manage-unknownerror',
		'googlelogin-manage-plusinfo-title',
	),
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
 * Control, if "Keep login" is always enabled (even if
 * the user doesn't checked the keep login box!).
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

/**
 * Key for public API access. Used only for admin actions to check,
 * if the user has a plus profile or not.
 */
$wgGLAPIKey = '';

/**
 * If set to true, the Google Login button will be added to
 * the right side of the login form, instead above the normal login form.
 */
$wgGLShowRight = false;

/**
 * Whether the user needs to confirm the google mail adress after registration
 * of a new local MediaWiki account, or not.
 */
$wgGLNeedsConfirmEmail = true;
