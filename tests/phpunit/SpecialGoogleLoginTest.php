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

		public function testsubmitChooseName() {
			$testData = array(
				'1' => array(
					'assert' => false,
					'msgkey' => 'googlelogin-form-choosename-error'
				),
				'2' => array(
					'ChooseName' => 'wpOwn',
					'assert' => false,
					'msgkey' => 'googlelogin-form-chooseown-error'
				),
				'3' => array(
					'ChooseName' => 'wpOwn',
					'ChooseOwn' => '[[]',
					'assert' => false,
					'msgkey' => 'googlelogin-form-choosename-existerror'
				),
				'4' => array(
					'ChooseName' => 'wpOwn',
					'ChooseOwn' => 'Testuseronly',
					'assert' => true
				)
			);
			foreach ( $testData as $data ) {
				if ( $data['assert'] ) {
					$this->assertTrue( $this->specialPage->submitChooseName( $data ) );
				} else {
					$this->assertEquals(
						wfMessage(
							$data['msgkey'],
							( isset( $data['ChooseOwn'] ) ? $data['ChooseOwn'] : '' )
						)->text(),
						$this->specialPage->submitChooseName( $data )
					);
				}
			}
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
