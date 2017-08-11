<?php
/**
 * SpecialManageGoogleLogin implementation
 */

namespace GoogleLogin\Specials;

use GoogleLogin\GoogleLogin;
use SpecialPage;
use Html;
use HTMLForm;
use User;
use ConfigFactory;
use Http;
use ErrorPageError;

use GoogleLogin\GoogleUser;

/**
 * Special page implementation that allows a permitted user to manage
 * connections between wiki accounts and Google accounts.
 */
class SpecialManageGoogleLogin extends SpecialPage {
	/** @var User $manageableUser User object of the user to manage */
	private $manageableUser = null;

	function __construct() {
		parent::__construct( 'ManageGoogleLogin', 'managegooglelogin' );
		$this->listed = true;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Special page executer
	 * @param SubPage $par Subpage
	 */
	function execute( $par ) {
		$user = $this->getUser();
		$out = $this->getOutput();
		$request = $this->getRequest();
		if ( !$this->userCanExecute( $user ) ) {
			$this->displayRestrictionError();
			return;
		}
		$this->setHeaders();
		$out->addModules( 'mediawiki.userSuggest' );
		$formFields = [
			'username' => [
				'type' => 'user',
				'name' => 'username',
				'label-message' => 'googlelogin-username',
				'id' => 'mw-gl-username',
				'autofocus' => true,
				'exists' => true,
			],
			'submit' => [
				'type' => 'submit',
				'default' => $this->msg( 'googlelogin-manage-usersubmit' )->text(),
				'flags' => [ 'progressive', 'primary' ],
			],
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formFields, $this->getContext(), 'googlelogin-manage' );
		$htmlForm->setWrapperLegendMsg( $this->msg( 'googlelogin-managelegend' ) );
		$htmlForm->suppressDefaultSubmit();
		$htmlForm->setSubmitCallback( [ $this, 'submitUserName' ] );
		$htmlForm->show();

		if ( $this->manageableUser ) {
			$this->manageUser( $this->manageableUser );
		}
	}

	/**
	 * Checks, if a user with the entered username exists.
	 *
	 * @param array $data Formdata
	 * @return bool
	 */
	public function submitUserName( array $data ) {
		$this->submitForm( $data, false );
		return true;
	}

	/**
	 * Renders a form to manage this user and handles all actions.
	 *
	 * @param User $user
	 */
	private function manageUser( User $user ) {
		$out = $this->getOutput();

		$out->addModules(
			[
				'ext.GoogleLogin.specialManage.scripts',
				'ext.GoogleLogin.style',
			]
		);
		$out->addBackLinkSubtitle( $this->getPageTitle() );

		$out->addWikiMsg( 'googlelogin-manage-user', $user->getName() );
		$googleIds = GoogleUser::getGoogleIdFromUser( $user );
		if ( $googleIds ) {
			$googleIdLinks = '';
			foreach ( $googleIds as $count => $googleId ) {
				$googleIdCount = $count + 1;
				if ( $count !== 0 ) {
					$googleIdLinks .= ', ';
				}
				$googleIdLinks .= Html::element( 'a',
					[
						'href' => 'javascript:void(0)',
						'data-googleid' => $googleId,
					],
					$googleId
				);
			}
			$out->addHTML(
				Html::openElement( 'div' ) .
				$this->msg( 'googlelogin-manage-linked', $googleIdCount )->escaped() .
				Html::openElement( 'strong' ) .
				$googleIdLinks .
				Html::closeElement( 'strong' ) .
				Html::closeElement( 'div' )
			);
		} else {
			$out->addWikiMsg( 'googlelogin-manage-notlinked' );
		}
		$formFields = [];
		if ( $googleIds ) {
			$formFields['googleids'] = [
				'type' => 'checkmatrix',
				'columns' => [
					$this->msg( 'googlelogin-manage-terminatebutton' )->escaped() => 'google'
				],
				'rows' => array_combine( $googleIds, $googleIds ),
				'label-raw' => 'Google ID',
				'id' => 'mw-gl-username',
			];
		}
		$formFields['addgoogleid'] = [
			'type' => 'text',
			'label-message' => 'googlelogin-manage-addlabel',
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formFields, $this->getContext(), 'googlelogin-change' );
		$htmlForm->addHiddenField( 'username', $user->getName() );
		$htmlForm->setWrapperLegendMsg( $this->msg( 'googlelogin-manage-changelegend' ) );
		$htmlForm->setSubmitCallback( [ $this, 'submitForm' ] );
		$htmlForm->show();
	}

	/**
	 * Submithandler for new google id
	 *
	 * @param array $data Formdata
	 * @param bool $checkSession If true, checks, if the form was submitted by the user itself
	 * @return bool
	 */
	public function submitForm( array $data, $checkSession = true ) {
		$user = $this->getUser();
		$request = $this->getRequest();
		$out = $this->getOutput();
		$glConfig = GoogleLogin::getGLConfig();
		$name = ( isset( $data['username'] ) ? $data['username'] : '' );
		if ( $checkSession && !$user->matchEditToken( $request->getVal( 'wpEditToken' ), $name ) ) {
			throw new ErrorPageError( 'sessionfailure-title', 'sessionfailure' );
		}
		if ( $name ) {
			$this->manageableUser = User::newFromName( $name );
		}
		if ( !$this->manageableUser ) {
			return false;
		}
		$requestAddGoogleId = null;
		$requestGoogleId = null;
		if ( isset( $data['googleids'] ) ) {
			$requestGoogleId = $data['googleids'];
		}
		if ( isset( $data['addgoogleid'] ) ) {
			$requestAddGoogleId = $data['addgoogleid'];
		}
		if (
			isset( $requestGoogleId ) &&
			GoogleUser::hasConnectedGoogleAccount( $this->manageableUser )
		) {
			// terminate the connection
			$success = [];
			foreach ( $requestGoogleId as $count => $googleId ) {
				$id = str_replace( 'google-', '', $googleId );
				if ( GoogleUser::terminateGoogleConnection( $this->manageableUser, $id ) ) {
					$out->addWikiMsg( 'googlelogin-manage-terminatesuccess' );
					$success[] = $id;
				} else {
					$out->addWikiMsg( 'googlelogin-manage-changederror' );
				}
			}
			if ( $success ) {
				$this->notifyUser( $glConfig, 'remove', $success );
			}
		}

		if ( $requestAddGoogleId ) {
			// try to create a new GoogleUser object with the given id to check, if there's
			// already an user with this google id.
			$newGoogleUser = GoogleUser::isGoogleIdFree( $requestAddGoogleId );
			if ( !is_numeric( $requestAddGoogleId ) ) {
				$out->wrapWikiMsg( '<div class="error">$1</div>', 'googlelogin-manage-invalidid' );
			} elseif ( !$newGoogleUser ) {
				// if the ID is already given to another user, it can't be associtated with this user
				$out->wrapWikiMsg( '<div class="error">$1</div>', 'googlelogin-manage-givenid' );
			} else {
				// check, if the google id has a google plus profile
				$plusCheck = Http::get(
					'https://www.googleapis.com/plus/v1/people/' .
					$requestAddGoogleId .
					'?key=' .
					$glConfig->get( 'GLAPIKey' )
				);
				if ( !$plusCheck ) {
					// it seems, that the google id doesn't have a plus profile
					// (or another error occur). Add a notice message for it.
					$out->addWikiMsg( 'googlelogin-manage-noplus' );
				}

				if ( GoogleUser::connectWithGoogle( $this->manageableUser, $requestAddGoogleId ) ) {
					$out->addWikiMsg( 'googlelogin-manage-changedsuccess' );
					$this->notifyUser( $glConfig, 'add', [ $requestAddGoogleId ] );
				} else {
					$out->addWikiMsg( 'googlelogin-manage-changederror' );
				}
			}
		}
		return false;
	}

	protected function getGroupName() {
		return 'users';
	}

	protected function notifyUser( \Config $config, $action, array $googleId ) {
		if ( $config->get( 'GLEnableEchoEvents' ) && class_exists( 'EchoEvent' ) ) {
			\EchoEvent::create( [
				'type' => 'change-googlelogin',
				'extra' => [
					'user' => $this->manageableUser->getID(),
					'action' => $action,
					'googleId' => $googleId,
				],
				'agent' => $this->getUser(),
			] );
		}
	}
}
