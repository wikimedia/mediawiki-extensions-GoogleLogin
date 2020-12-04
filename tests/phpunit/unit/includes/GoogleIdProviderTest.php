<?php

namespace GoogleLogin;

use MediaWikiUnitTestCase;
use MediaWiki\User\UserIdentityValue;
use PHPUnit_Framework_MockObject_MockObject;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class GoogleIdProviderTest extends MediaWikiUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	private $dbConnection;
	private $loadBalancer;

	protected function setUp() : void {
		parent::setUp();

		$this->dbConnection = $this->getMockBuilder( IDatabase::class )
				->disableOriginalConstructor()
				->getMock();

		$this->loadBalancer = $this->getMockBuilder( ILoadBalancer::class )
			->disableOriginalConstructor()
			->getMock();
		$this->loadBalancer->method( 'getConnection' )->willReturn( $this->dbConnection );
	}

	/**
	 * @covers \GoogleLogin\GoogleIdProvider::getFromUser()
	 */
	public function testGetFromUserAnonymousUser() {
		$this->dbConnection->expects( $this->never() )->method( 'select' );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$this->assertEmpty( $googleIdProvider->getFromUser( new UserIdentityValue( 0, '127.0.0.1', 0 ) ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleIdProvider::getFromUser()
	 */
	public function testGetFromUserNoAssociatedIds() {
		$this->dbConnection->expects( $this->once() )
			->method( 'select' )
			->with( $this->anything(), $this->anything(), [ 'user_id' => 123 ] )
			->willReturn( false );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$this->assertEmpty( $googleIdProvider->getFromUser( new UserIdentityValue( 123, __FUNCTION__, 0 ) ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleIdProvider::getFromUser()
	 */
	public function testGetFromUserAssociatedIds() {
		$aResult = new StdClass();
		$aResult->user_googleid = 1;
		$anotherResult = new StdClass();
		$anotherResult->user_googleid = 2;
		$this->dbConnection->expects( $this->once() )
			->method( 'select' )
			->with( $this->anything(), $this->anything(), [ 'user_id' => 123 ] )
			->willReturn( [ $aResult, $anotherResult ] );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$this->assertEquals( [ 1, 2 ], $googleIdProvider->getFromUser( new UserIdentityValue( 123, __FUNCTION__, 0 ) ) );
	}

	/**
	 * @dataProvider provideIsAssociated
	 * @param $resultCount
	 * @param $expected
	 * @covers \GoogleLogin\GoogleIdProvider::isAssociated()
	 */
	public function testIsAssociated( $resultCount, $expected ) {
		$this->dbConnection->expects( $this->once() )
			->method( 'selectRowCount' )
			->with( $this->anything(), $this->anything(), [ 'user_googleid' => 123 ] )
			->willReturn( $resultCount );
		$googleIdProvider = new GoogleIdProvider( $this->loadBalancer );

		$this->assertEquals( $expected, $googleIdProvider->isAssociated( 123 ) );
	}

	public function provideIsAssociated() {
		return [
			[ 0, false ],
			[ 1, true ],
			[ 42, true ],
		];
	}
}
