<?php
namespace GoogleLogin;

/**
 * Same as HTMLSubmitField, the only difference is, that the style module to
 * style the Google button (according Googles guidelines) is added.
 */
class HTMLGoogleLoginButtonField extends \HTMLSubmitField {
	public function getInputHTML( $value ) {
		$this->addGoogleButtonStyleModule();
		return parent::getInputHTML( $value );
	}

	public function getInputOOUI( $value ) {
		$this->addGoogleButtonStyleModule();
		return parent::getInputOOUI( $value );
	}

	private function addGoogleButtonStyleModule() {
		if ( $this->mParent instanceof HTMLForm ) {
			$out = $this->mParent->getOutput();
		} else {
			$out = \RequestContext::getMain()->getOutput();
		}
		$out->addModuleStyles( 'ext.GoogleLogin.userlogincreate.style' );
	}
}
