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

	$wgExtensionCredits['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'GoogleLogin',
		'author' => 'Florian Schmidt',
		'url' => 'https://www.mediawiki.org/wiki/Extension:GoogleLogin',
		'descriptionmsg' => 'googlelogin-desc',
		'version'  => '0.1.0',
		'license-name' => "MIT",
	);

	// Autoload Classes
	$wgAutoloadClasses[ 'SpecialGoogleLogin' ] = __DIR__ . '/SpecialGoogleLogin.php';
	$wgAutoloadClasses[ 'GoogleLoginHooks' ] = __DIR__ . '/GoogleLogin.hooks.php';
	$wgAutoloadClasses[ 'GoogleLoginDB' ] = __DIR__ . '/GoogleLoginDB.php';

	// i18n directory and aliases
	$wgExtensionMessagesFiles[ 'GoogleLoginAlias' ] = __DIR__ . '/GoogleLogin.alias.php';
	$wgMessagesDirs['GoogleLogin'] = __DIR__ . '/i18n';

	// Special Page
	$wgSpecialPageGroups[ 'GoogleLogin' ] = 'login';
	$wgSpecialPages[ 'GoogleLogin' ] = 'SpecialGoogleLogin';

	// Hooks
	$wgHooks['UserLogoutComplete'][] = 'GoogleLoginHooks::onUserLogoutComplete';
	$wgHooks['LoadExtensionSchemaUpdates'][] = 'GoogleLoginHooks::onLoadExtensionSchemaUpdates';
	$wgHooks['UserLoginForm'][] = 'GoogleLoginHooks::onUserLoginForm';

	// Configuration settings defaults

	/**
	 * The Secret key of Google developer console
	 */
	$wgGoogleSecret = '';

	/**
	 * The App ID of the web application to use for GoogleLogin
	 */
	$wgGoogleAppId = '';

	/**
	 * Which domains are allowed to login (or create/merge an account) with GoogleLogin
	 * default: empty string -> all domains allowed
	 * to allow special domains, create an array with all allowed domains, example:
	 * array( 'example.com' );
	 */
	$wgGoogleAllowedDomains = '';

	/**
	 * If $wgGoogleAllowedDomains restrict to specified domains, use strict mode? Means:
	 * Only the exact specified domains are allowed, e.g. if test.example.com is allowed and strict
	 * mode is enabled, example.com isn't allowed (if strict mode is of, it is allowed)
	 */
	$wgGoogleAllowedDomainsStrict = false;

	/**
	 * If the user creates an account via GoogleLogin, show this as a reason in log?
	 */
	$wgGoogleShowCreateReason = false;
