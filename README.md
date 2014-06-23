GoogleLogin MediaWiki Extension
=====================

Extension provide a Google Login Special page to login with Google account to a MediaWiki Installation.

Requirements
==
* MediaWiki Version 1.22+
* MySQL (sorry, no PostgreSQL or SQLite support for now)
* PHP 5.3+
* Google Developer Account with Google+ API access

Installation
==
1. Clone this repository or download zip from master
2. Extract the files into new "GoogleLogin" directory in {mediawiki-root}/extensions/
3. Add require_once( "$IP/extensions/GoogleLogin/GoogleLogin.php" ); to LocalSettings.php
4. Run update.php

Make sure, you see "Google Login" in Special:Version.

Configuration
==
You need a Google Developer Account with API access to Google+ API. Create a new (or use existing) Google developer keys and add following lines to LocalSettings.php (and fill in your keys!):
$wgGoogleSecret = <your client secret key>;
$wgGoogleAppId = <your google client id>;

Have fun with the new Special Page Special:GoogleLogin.

Google API PHP Client
==
This Extension uses the Google API PHP Client, a free software licensed under Apacha 2.0:
https://github.com/google/google-api-php-client
https://github.com/google/google-api-php-client/blob/master/LICENSE
