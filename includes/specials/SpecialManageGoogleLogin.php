<?php
	class SpecialManageGoogleLogin extends SpecialPage {
		/** @var $mGoogleLogin saves an instance of GoogleLogin class */
		private $mGoogleLogin;

		/** @var User $manageableUser User object of the user to manage */
		private static $manageableUser = null;

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
			if ( !$request->getVal( 'glManageableUser' ) ) {
				$out->addModules( 'mediawiki.userSuggest' );
				$formFields = array(
					'username' => array(
						'type' => 'text',
						'name' => 'username',
						'label-message' => 'googlelogin-username',
						'id' => 'mw-gl-username',
						'cssclass' => 'mw-autocomplete-user',
						'autofocus' => true,
					)
				);
				$htmlForm = new HTMLForm( $formFields, $this->getContext(), 'googlelogin-manage' );
				$htmlForm->setWrapperLegendMsg( $this->msg( 'googlelogin-managelegend' ) );
				$htmlForm->setSubmitText( $this->msg( 'googlelogin-manage-usersubmit' )->text() );
				$htmlForm->setSubmitProgressive();
				$htmlForm->setSubmitCallback( array( $this, 'submitUserName' ) );
				$htmlForm->show();
			} else {
				$this->submitUserName(
					array(
						'username' => $request->getVal( 'glManageableUser' )
					)
				);
			}

			if ( self::$manageableUser ) {
				$this->manageUser( self::$manageableUser );
			} else {

			}
		}

		/**
		 * Checks, if a user with the entered username exists.
		 *
		 * @param array $data Formdata
		 * @return boolean
		 */
		public function submitUserName( $data ) {
			if ( !isset( $data['username'] ) ) {
				return false;
			}
			$checkUser = User::newFromName( $data['username'] );
			if ( $checkUser->isAnon() ) {
				return wfMessage( 'googlelogin-manage-notexist', $data['username'] );
			}
			self::$manageableUser = $checkUser;
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
			$htmlForm = new HTMLForm( $formFields, $this->getContext(), 'googlelogin-change' );
			$htmlForm->addHiddenField( 'glManageableUser', $user->getName() );
			$htmlForm->setWrapperLegendMsg( $this->msg( 'googlelogin-manage-changelegend' ) );
			$htmlForm->setSubmitCallback( array( 'SpecialManageGoogleLogin', 'submitGoogleId' ) );
			if ( $id ) {
				$htmlForm->addButton(
					'terminate-link',
					$this->msg( 'googlelogin-manage-terminatebutton' )->escaped(),
					null,
					array(
						'class' => 'mw-ui-destructive',
					)
				);
			}
			$htmlForm->show();
		}

		/**
		 * Submithandler for new google id
		 *
		 * @param array $data Formdata
		 * @return boolean
		 */
		public static function submitGoogleId( $data ) {
			return false;
		}

		protected function getGroupName() {
			return 'users';
		}
	}
