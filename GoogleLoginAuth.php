<?php
	// handles only the redirect to Special:GoogleLogin if needed
	class GoogleLoginAuth extends AuthPlugin {
		/**
		 * Normally this handles an auth request with username and password, mediawiki can not identify,
		 * so it try to use some external sources, if available. We use this to redirect the request
		 * to our special page, if some parameters in WebRequest are set.
		 *
		 * @param string $username Username
		 * @param string $password Password of the USer
		 * @return boolean Always false, we don't authenticate a user here
		 */
		public function authenticate( $username, $password ) {
			global $wgOut, $wgRequest;
			if ( $wgRequest->getVal( 'googlelogin-submit' ) !== null ) {
				// FIXME: Use direct redirect to Google Login page (after logic is in own class)
				$wgOut->redirect(
					Title::makeTitle( -1, 'GoogleLogin' )->getLocalUrl() .
					'?keep=' . $wgRequest->getVal( 'google-keep-loggedin' ) .
					'&returnto=' . $wgRequest->getVal( 'returnto' ) .
					'&returntoquery=' . $wgRequest->getVal( 'returntoquery' )
				);
			}
			return false;
		}
	}
