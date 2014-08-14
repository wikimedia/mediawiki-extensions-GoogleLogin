<?php
	// handles only the redirect to Special:GoogleLogin if needed
	class GoogleLoginAuth extends AuthPlugin {
		/**
		 * Normally this handles the check if a domain is valid. We use this
		 * to get into the login process in an early process to check, if the user
		 * clicks the Login with Google button or the MediaWiki login button.
		 *
		 * @param string $domain Domain name (unneeded)
		 * @return boolean Always false, we don't check anything here
		 */
		public function validDomain( $domain ) {
			GoogleLogin::externalLoginAttempt();
			return true;
		}
	}
