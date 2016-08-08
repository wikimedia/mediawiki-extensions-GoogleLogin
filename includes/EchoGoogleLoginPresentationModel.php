<?php
namespace GoogleLogin;

class EchoGoogleLoginPresentationModel extends \EchoEventPresentationModel {
	public function getIconType() {
		return 'user-rights';
	}

	public function getPrimaryLink() {
		return [
			'url' => \SpecialPage::getTitleFor( 'ChangeCredentials' )->getLocalURL(),
			'label' => $this->msg( 'echo-learn-more' )->text(),
		];
	}

	public function getSecondaryLinks() {
		return [ $this->getAgentLink() ];
	}

	public function getBodyMessage() {
		$googleId = $this->event->getExtraParam( 'googleId' );
		switch ( $this->event->getExtraParam( 'action' ) ) {
			case 'remove':
				return $this->msg(
					'notification-remove-googlelogin',
					implode( ', ', $googleId ),
					count( $googleId )
				);
				break;
			case 'add':
				return $this->msg(
					'notification-add-googlelogin',
					implode( ', ', $googleId ),
					count( $googleId )
				);
				break;
			default:
				return $this->getMessageWithAgent( 'notification-change-googlelogin' );
		}
	}
}
