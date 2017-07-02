<?php

namespace GoogleLogin;

use MediaWiki\MediaWikiServices;

class GoogleLoginHooksTest extends \MediaWikiTestCase {

	/**
	 * @var \ApiModuleManager
	 */
	private $moduleManager;

	public function setup() {
		parent::setUp();
		$this->moduleManager = new \ApiModuleManager( new \ApiMain() );
	}

	public function testOnApiMainModuleManagerDefault() {
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertFalse( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

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

	public function testOnApiMainModuleManagerDB() {
		$this->setMwGlobals( [
			'wgGLAllowedDomainsDB' => true,
		] );
		GoogleLoginHooks::onApiMainModuleManager( $this->moduleManager );
		$this->assertTrue( $this->moduleManager->isDefined( 'googleloginmanagealloweddomain' ) );
	}

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
