<?php

namespace GoogleLogin;

class GoogleLoginHooksTest extends \MediaWikiTestCase {

	/**
	 * @var \ApiModuleManager
	 */
	private $moduleManager;

	public function setup() {
		parent::setUp();
		$this->moduleManager = new \ApiModuleManager( new \ApiMain() );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDefault() {
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerNonDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomains' => [
				'test.com',
			],
			'wgGLAllowedDomainsDB' => false,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

	/**
	 * @covers \GoogleLogin\GoogleLoginHooks::onApiMainModuleManager()
	 */
	public function testOnApiMainModuleManagerDBNonDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomains' => [
				'test.com',
			],
			'wgGLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}
}
