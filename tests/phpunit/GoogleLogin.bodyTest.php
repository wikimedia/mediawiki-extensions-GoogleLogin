<?php

class GoogleLoginTest extends MediaWikiTestCase {
	/**
	 * Only some basic things, other tests are run in
	 * UserTest::testGetCanonicalName().
	 *
	 * @covers GoogleLogin::isValidUserName
	 * @dataProvider provideTestNames
	 */
	public function testIsValidUserName( $name, $expected ) {
		$retval = GoogleLogin::isValidUserName( $name );
		$this->assertEquals( $expected, $retval );
	}

	public function provideTestNames() {
		return [
			[ 'ValidTestUser', true ],
			[ 'lowerCaseBegin', true ],
			[ 'InvalidTestUser#', false ],
			[ ' trailing ', true ],
			[ ' back / slash ', false ],
		];
	}
}
