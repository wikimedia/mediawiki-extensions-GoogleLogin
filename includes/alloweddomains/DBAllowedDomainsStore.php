<?php

namespace GoogleLogin\AllowedDomains;

use Wikimedia\Rdbms\LoadBalancer;

/**
 * Class DBAllowedDomainsStore
 *
 * Implementation of the AllowedDomainsStore interface, which is backed by the database. The
 * allowed domain list is stored and manageable in the database of MediaWiki. For performance
 * reasons, you should use the CachedAllowedDomainsStore implementation and wrap an instance of
 * this class into it.
 *
 * @package GoogleLogin\AllowedDomains
 */
class DBAllowedDomainsStore implements AllowedDomainsStore, MutableAllowedDomainsStore {
	private $dbLoadBalancer;
	private $allowedDomains;

	public function __construct( LoadBalancer $dbLoadBalancer ) {
		$this->dbLoadBalancer = $dbLoadBalancer;
	}

	private function loadDomains() {
		if ( $this->allowedDomains !== null ) {
			return;
		}
		$dbr = $this->dbLoadBalancer->getConnection( DB_REPLICA );

		$res = $dbr->select(
			'googlelogin_allowed_domains',
			[
				'gl_allowed_domain_id',
				'gl_allowed_domain',
			],
			'',
			__METHOD__
		);

		foreach ( $res as $row ) {
			$this->allowedDomains[$row->gl_allowed_domain_id] = $row->gl_allowed_domain;
		}
	}

	public function getAllowedDomains() {
		$this->loadDomains();

		return $this->allowedDomains;
	}

	public function contains( EmailDomain $domain ) {
		$this->loadDomains();

		return in_array( $domain->getHost(), $this->allowedDomains );
	}

	public function add( EmailDomain $domain ) {
		if ( $this->contains( $domain ) ) {
			return -1;
		}
		$dbw = $this->dbLoadBalancer->getConnection( DB_MASTER );
		$dbw->insert(
			'googlelogin_allowed_domains',
			[
				'gl_allowed_domain' => $domain->getHost()
			],
			__METHOD__
		);

		return $dbw->insertId();
	}

	public function clear() {
		$dbw = $this->dbLoadBalancer->getConnection( DB_MASTER );

		$ok = $dbw->delete( 'googlelogin_allowed_domains', '*', __METHOD__ );
		$this->allowedDomains = null;

		return $ok;
	}

	public function remove( EmailDomain $domain ) {
		if ( !$this->contains( $domain ) ) {
			return true;
		}
		$dbw = $this->dbLoadBalancer->getConnection( DB_MASTER );
		return (bool)$dbw->delete( 'googlelogin_allowed_domains', [ 'gl_allowed_domain' =>
			$domain->getHost() ], __METHOD__ );
	}
}
