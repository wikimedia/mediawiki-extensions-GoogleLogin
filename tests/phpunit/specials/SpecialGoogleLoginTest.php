<?php

class SpecialGoogleLoginTest extends MediaWikiTestCase {
	private function redirect( $val ) {
		$request = new FauxRequest( array( 'googlelogin-submit' => $val, 'error' => '000' ) );
		$context = new DerivativeContext( RequestContext::getMain() );
		$output = new OutputPage( $context );
		$output->disable();
		$context->setOutput( $output );
		$context->setRequest( $request );
		$specialPage = new SpecialGoogleLogin();
		$specialPage->setContext( $context );
		$specialPage->execute( '' );

		return $specialPage->getOutput();
	}

	public function testRedirectFromLoginForm() {
		// test, if the special page redirects to the login form, if the user comes
		// from the login page
		$output = $this->redirect( '1' );
		$this->assertFalse( $output->getRedirect() === '' );

		// test, if the special page does not redirect to the login page
		$output = $this->redirect( '0' );
		$this->assertTrue( $output->getRedirect() === '' );
	}
}
