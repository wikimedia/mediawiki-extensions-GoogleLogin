<?php
	class SpecialManageGoogleLogin extends SpecialPage {
		/** @var $mGoogleLogin saves an instance of GoogleLogin class */
		private $mGoogleLogin;

		/** @var User $manageableUser User object of the user to manage */
		private $manageableUser = null;

		function __construct() {
			parent::__construct( 'ManageGoogleLogin', 'managegooglelogin' );
			$this->listed = true;
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
			$formFields = array(
				'username' => array(
					'type' => 'user',
					'name' => 'username',
					'label-message' => 'googlelogin-username',
					'id' => 'mw-gl-username',
					'autofocus' => true,
					'exists' => true,
				),
				'submit' => array(
					'type' => 'submit',
					'default' => $this->msg( 'googlelogin-manage-usersubmit' )->text(),
					'flags' => array( 'progressive', 'primary' ),
				),
			);
			$htmlForm = HTMLForm::factory( 'ooui', $formFields, $this->getContext(), 'googlelogin-manage' );
			$htmlForm->setWrapperLegendMsg( $this->msg( 'googlelogin-managelegend' ) );
			$htmlForm->suppressDefaultSubmit();
			$htmlForm->setSubmitCallback( array( $this, 'submitUserName' ) );
			$htmlForm->show();

			if ( $this->manageableUser ) {
				$this->manageUser( $this->manageableUser );
			}
		}

		/**
		 * Checks, if a user with the entered username exists.
		 *
		 * @param array $data Formdata
		 * @return boolean
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
			$request = $this->getRequest();
			$out = $this->getOutput();

			$out->addModules(
				array(
					'ext.GoogleLogin.specialManage.scripts',
					'ext.GoogleLogin.style',
				)
			);
			$out->addBackLinkSubtitle( $this->getPageTitle() );
			$db = new GoogleLoginDB;
			$id = $db->userIdExists( $user->getId() );
			$googleId = $request->getVal( 'googleid' );
			$terminateLink = $request->getVal( 'terminate-link' );
			if ( isset( $terminateLink ) && $id ) {
				// terminate the connection
				if ( $db->terminateConnection( $id['id'] ) ) {
					$out->addWikiMsg( 'googlelogin-manage-terminatesuccess' );
					$id = false;
				} else {
					$out->addWikiMsg( 'googlelogin-manage-changederror' );
				}
			} elseif ( $googleId ) {
				if ( !is_numeric( $googleId ) ) {
					$out->wrapWikiMsg( '<div class="error">$1</div>', 'googlelogin-manage-invalidid' );
				} else {
					// check, if the google id has a google plus profile
					$glConfig = ConfigFactory::getDefaultInstance()->makeConfig( 'googlelogin' );
					$plusCheck = Http::get(
						'https://www.googleapis.com/plus/v1/people/' .
						$googleId .
						'?key=' .
						$glConfig->get( 'GLAPIKey' )
					);
					if ( !$plusCheck ) {
						// it seems, that the google id doesn't have a plus profile (or another error occur). Add a notice message for it.
						$out->addWikiMsg( 'googlelogin-manage-noplus' );
					}
					// FIXME: Really need to terminate and then create a new connection, how about an update routine?
					if ( !empty( $id ) ) {
						$db->terminateConnection( $id['id'] );
					}
					if ( $db->createConnection( $googleId, $user->getId() ) ) {
						$out->addWikiMsg( 'googlelogin-manage-changedsuccess' );
						$id = $db->userIdExists( $user->getId() );
					} else {
						$out->addWikiMsg( 'googlelogin-manage-changederror' );
					}
				}
			}
			$out->addWikiMsg( 'googlelogin-manage-user', $user->getName() );
			if ( $id ) {
				$out->addHtml(
					Html::openElement( 'div' ) .
					$this->msg( 'googlelogin-manage-linked' )->escaped() .
					Html::openElement( 'strong' ) .
					Html::element( 'span',
						array(
							'class' => 'googlelogin-googleid',
						),
						$id['id']
					) .
					Html::element( 'a',
						array(
							'href' => 'javascript:void(0)',
							'class' => 'googlelogin-googleid hidden',
							'data-googleid' => $id['id'],
						),
						$id['id']
					) .
					Html::closeElement( 'strong' ) .
					Html::closeElement( 'div' )
				);
				$formId = $id['id'];
			} else {
				$out->addWikiMsg( 'googlelogin-manage-notlinked' );
				$formId = '';
			}
			$formFields = array(
				'googleid' => array(
					'type' => 'text',
					'name' => 'googleid',
					'label-raw' => 'Google-ID:',
					'default' => $formId,
					'id' => 'mw-gl-username',
				)
			);
			$htmlForm = HTMLForm::factory( 'ooui', $formFields, $this->getContext(), 'googlelogin-change' );
			$htmlForm->addHiddenField( 'username', $user->getName() );
			$htmlForm->setWrapperLegendMsg( $this->msg( 'googlelogin-manage-changelegend' ) );
			$htmlForm->setSubmitCallback( array( $this, 'submitForm' ) );
			if ( $id ) {
				$htmlForm->addButton(
					'terminate-link',
					$this->msg( 'googlelogin-manage-terminatebutton' )->escaped(),
					null,
					array(
						'flags' => array( 'destructive' ),
					)
				);
			}
			$htmlForm->show();
		}

		/**
		 * Submithandler for new google id
		 *
		 * @param array $data Formdata
		 * @param boolean $checkSession If true, checks, if the form was submitted by the user itself
		 * @return boolean
		 */
		public function submitForm( array $data, $checkSession = true ) {
			$user = $this->getUser();
			$request = $this->getRequest();
			$name = ( isset( $data['username'] ) ? $data['username'] : '' );
			if ( $checkSession && !$user->matchEditToken( $request->getVal( 'wpEditToken' ), $name ) ) {
				throw new ErrorPageError( 'sessionfailure-title', 'sessionfailure' );
			}
			$this->manageableUser = User::newFromName( $name );
			return false;
		}

		protected function getGroupName() {
			return 'users';
		}
	}
