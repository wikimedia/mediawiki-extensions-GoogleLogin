<?php

namespace GoogleLogin\UserMatching;

use LoadBalancer;
use User;

class UserIdMatcher implements IUserMatcher {
	private $loadBalancer;

	public function __construct( LoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function match( array $token ) {
		if ( !isset( $token['sub'] ) ) {
			return null;
		}
		$db = $this->loadBalancer->getConnection( DB_MASTER );

		$s = $db->selectRow(
			'user_google_user',
			[ 'user_id' ],
			[ 'user_googleid' => $token['sub'] ],
			__METHOD__
		);

		if ( $s !== false ) {
			return User::newFromId( $s->user_id );
		}
		return null;
	}
}
