<?php
	/**
	 * @group GoogleLogin
	 */
	class SpecialGoogleLoginTest extends MediaWikiTestCase {
		private $specialPage;

		protected function setUp() {
			parent::setUp();
			$this->specialPage = new SpecialGoogleLogin;
		}

		protected function tearDown() {
			parent::tearDown();
		}

		public function testisValidUserName() {
			$this->assertTrue( $this->specialPage->isValidUserName( 'Testuser1234GoogleLogin' ) );
			$this->assertFalse( $this->specialPage->isValidUserName( '[<]' ) );
		}

		/**
		 * @dataProvider submitDataProvider
		 */
		public function testsubmitChooseName( $assert, $msgkey, $data ) {
			if ( $assert ) {
				$this->assertTrue( $this->specialPage->submitChooseName( $data ) );
			} else {
				$this->assertEquals(
					wfMessage(
						$msgkey,
						( isset( $data['ChooseOwn'] ) ? $data['ChooseOwn'] : '' )
					)->text(),
					$this->specialPage->submitChooseName( $data )
				);
			}
		}

		public function submitDataProvider() {
			return array(
				array(
					'assert' => false,
					'msgkey' => 'googlelogin-form-choosename-error',
					array(
						'ChooseName' => null,
						'ChooseOwn' => null
					)
				),
				array(
					'assert' => false,
					'msgkey' => 'googlelogin-form-chooseown-error',
					array(
						'ChooseName' => 'wpOwn',
						'ChooseOwn' => null
					)
				),
				array(
					'assert' => false,
					'msgkey' => 'googlelogin-form-choosename-existerror',
					array(
						'ChooseName' => 'wpOwn',
						'ChooseOwn' => '[[]'
					)
				),
				array(
					'assert' => true,
					'msgkey' => null,
					array(
						'ChooseName' => 'wpOwn',
						'ChooseOwn' => 'Testuseronly',
					)
				)
			);
		}

		public function testRequestValid() {
			// without any securehash this must always false
			$this->assertFalse( $this->specialPage->isRequestValid() );
			// set a request token to check against
			$hash = $this->specialPage->getRequestToken();
			// check if we become the same token like already set
			$this->assertEquals(
				$hash,
				$this->specialPage->getRequestToken()
			);

			// set the hash as a request variable
			$this->specialPage->getRequest()->setVal(
				'wpSecureHash',
				$hash
			);
			// check if the request is valid
			$this->assertTrue( $this->specialPage->isRequestValid() );
		}
	}
