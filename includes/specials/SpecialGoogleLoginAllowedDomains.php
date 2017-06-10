<?php

namespace GoogleLogin\Specials;

use GoogleLogin\AllowedDomains\AllowedDomainsStore;
use GoogleLogin\AllowedDomains\EmailDomain;
use GoogleLogin\AllowedDomains\MutableAllowedDomainsStore;
use GoogleLogin\Constants;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use HTMLForm;

class SpecialGoogleLoginAllowedDomains extends SpecialPage {

	function __construct() {
		parent::__construct( 'GoogleLoginAllowedDomains', 'managegooglelogindomains' );
		$this->listed = true;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Special page executer
	 * @param string $par Subpage
	 */
	function execute( $par ) {
		$user = $this->getUser();
		$out = $this->getOutput();
		if ( !$this->userCanExecute( $user ) ) {
			$this->displayRestrictionError();
			return;
		}
		$this->setHeaders();

		$formFields = [];
		/** @var AllowedDomainsStore $allowedDomains */
		$allowedDomains = MediaWikiServices::getInstance()->getService(
			Constants::SERVICE_ALLOWED_DOMAINS_STORE );
		if ( !$allowedDomains || !$allowedDomains instanceof MutableAllowedDomainsStore ) {
			$out->addWikiMsg( 'googlelogin-alloweddomains-notmanageable' );
			return;
		}
		if ( $allowedDomains->getAllowedDomains() ) {
			$formFields['alloweddomains'] = [
				'type' => 'checkmatrix',
				'columns' => [
					$this->msg( 'googlelogin-alloweddomain-terminatebutton' )->escaped() => 'ad'
				],
				'rows' => array_flip( $allowedDomains->getAllowedDomains() ),
				'id' => 'mw-gl-alloweddomain',
			];
		}
		$formFields['addallowedomain'] = [
			'type' => 'text',
			'label-message' => 'googlelogin-alloweddomain-addlabel',
		];
		$htmlForm = HTMLForm::factory(
			'ooui',
			$formFields,
			$this->getContext(),
			'googlelogin-allowedomain'
		);
		$htmlForm->addHiddenField( 'username', $user->getName() );
		$htmlForm->setWrapperLegend( '' );
		$htmlForm->setSubmitCallback( [ $this, 'submitForm' ] );
		$htmlForm->show();
	}

	public function submitForm( array $data ) {
		$out = $this->getOutput();
		/** @var MutableAllowedDomainsStore $allowedDomains */
		$allowedDomainsStore = MediaWikiServices::getInstance()->getService(
			Constants::SERVICE_ALLOWED_DOMAINS_STORE );

		$requestAdd = null;
		$requestRemove = null;
		if ( isset( $data['alloweddomains'] ) ) {
			$requestRemove = $data['alloweddomains'];
		}
		if ( isset( $data['addallowedomain'] ) ) {
			$requestAdd = $data['addallowedomain'];
		}
		if ( isset( $requestRemove ) ) {
			// terminate the connection
			$allowedDomains = $allowedDomainsStore->getAllowedDomains();
			$error = false;
			foreach ( $requestRemove as $count => $allowedDomain ) {
				$id = str_replace( 'ad-', '', $allowedDomain );

				if (
					isset( $allowedDomains[$id] ) &&
					$allowedDomainsStore->remove( new EmailDomain( $allowedDomains[$id] ) )
				) {
					$out->addWikiMsg( 'googlelogin-alloweddomain-removed-success', $allowedDomains[$id] );
				} else {
					$out->addWikiMsg( 'googlelogin-alloweddomain-change-error' );
					$error = true;
				}
			}
			if ( $error ) {
				return false;
			}
		}

		if ( $requestAdd ) {
			$status = $allowedDomainsStore->add( new EmailDomain( $requestAdd ) );
			if ( $status !== -1 ) {
				$out->addWikiMsg( 'googlelogin-alloweddomain-added-success', $requestAdd );
			} else {
				$out->addWikiMsg( 'googlelogin-alloweddomain-change-error' );
				return false;
			}
		}
		$out->addReturnTo( $this->getPageTitle() );
		return true;
	}

	protected function getGroupName() {
		return 'users';
	}
}
