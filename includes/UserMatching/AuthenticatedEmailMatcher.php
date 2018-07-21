<?php

namespace GoogleLogin\UserMatching;

use LoadBalancer;
use ResultWrapper;
use User;

class AuthenticatedEmailMatcher implements IUserMatcher {
	private $loadBalancer;

	public function __construct( LoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @inheritDoc
	 */
	public function match( array $token ) {
		if ( !isset( $token['email'] ) ) {
			return null;
		}
		if ( !isset( $token['email_verified'] ) || $token['email_verified'] !== true ) {
			return null;
		}
		$db = $this->loadBalancer->getConnection( DB_MASTER );

		$s = $db->select(
			'user',
			[ 'user_id' ],
			[
				'user_email' => $token['email'],
				'user_email_authenticated IS NOT NULL',
			],
			__METHOD__
		);

		if ( $s instanceof ResultWrapper && $s->numRows() === 1 ) {
			return User::newFromId( $s->next()->user_id );
		}
		return null;
	}
}
