<?php
namespace GoogleLogin;

class EchoGoogleLoginPresentationModel extends \EchoEventPresentationModel {
	public function getIconType() {
		return 'user-rights';
	}

	public function getPrimaryLink() {
		return [
			'url' => \SpecialPage::getTitleFor( 'ChangeCredentials' )->getLocalURL(),
			'label' => '',
		];
	}

	public function getBodyMessage() {
		return $this->getMessageWithAgent( 'notification-change-googlelogin' );
	}
}
