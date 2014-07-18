<?php
	class GoogleLoginHooks {
		public static function onUserLogoutComplete() {
			global $wgRequest;
			if ( $wgRequest->getSessionData( 'access_token' ) !== null ) {
				$wgRequest->setSessionData( 'access_token', '' );
			}
		}

		public static function onLoadExtensionSchemaUpdates( $updater = null ) {
			global $wgSharedDB, $wgDBname, $wgDBtype;
			// Don't create tables on a shared database
			if( !empty( $wgSharedDB ) && $wgSharedDB !== $wgDBname ) {
				return true;
			}
			// Tables to add to the database
			$tables = array( 'user_google_user' );
			// Sql directory inside the extension folder
			$sql = dirname( __FILE__ ) . '/sql';
			// Extension of the table schema file (depending on the database type)
			switch ( $updater !== null ? $updater->getDB()->getType() : $wgDBtype ) {
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
			global $wgGLShowKeepLogin;
			// we don't want to delete the output of other extensions, so "extend" header
			$header = $tpl->get( 'header' );

			$keepLogin = '';
			if ( $wgGLShowKeepLogin ) {
				$keepLogin = Html::input(
					'google-keep-loggedin',
					'1',
					'checkbox'
				) .
				' ' . wfMessage( 'userlogin-remembermypassword' )->text();
			}

			$header .=
				Html::openelement( 'div', array( 'class' => 'mw-ui-vform-field' ) ) .
				Html::openElement(
					'label',
					array( 'class' => 'mw-ui-checkbox-label' )
				) .
				$keepLogin .
				Html::closeElement( 'label' ) .
				Html::closeElement( 'div') .
				Html::openElement( 'div', array( 'class' => 'mw-ui-vform-field' ) ) .
				Html::element( 'input', array(
						'class' => 'mw-ui-button mw-ui-destructive',
						'style' => 'width:100%;',
						'type' => 'submit',
						'name' => 'googlelogin-submit',
						'value' => wfMessage( 'googlelogin' )->text()
					), ''
				) .
				Html::closeElement( 'div' );

			$tpl->set( 'header', $header );
		}

		/**
		 * Handles the replace of Loginlink and deletion of Create account link in personal tools
		 * if Loginreplacement is configured.
		 */
		public static function onPersonalUrls( array &$personal_urls, Title $title, SkinTemplate $skin ) {
			global $wgGLReplaceMWLogin, $wgUser, $wgRequest;
			if ( $wgGLReplaceMWLogin && array_key_exists( 'login', $personal_urls ) ) {
				// unset the create account link
				if ( array_key_exists( 'createaccount', $personal_urls ) ) {
					unset( $personal_urls['createaccount'] );
				}

				// Replace login link with GoogleLogin link
				$googleLogin = new GoogleLogin;
				$personal_urls['login']['text'] = wfMessage( 'googlelogin' )->text();
				$personal_urls['login']['href'] = $googleLogin->getLoginUrl( $skin, $title );
			}
		}

		/**
		 * Handles the replace of Special:UserLogin with Special:GoogleLogin if Loginreplacement is
		 * configured.
		 */
		public static function onSpecialPage_initList( &$list ) {
			global $wgGLReplaceMWLogin;
			// Replaces the UserLogin special page if configured
			if ( $wgGLReplaceMWLogin ) {
				$list['Userlogin'] = 'SpecialGoogleLogin';
			}
		}
	}
