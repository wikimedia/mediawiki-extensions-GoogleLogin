<?php

namespace GoogleLogin;

use LoadBalancer;
use User;

class GoogleUserMatching {
	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	public function __construct( LoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param array $token A verified token provided from Google after authenticating a user
	 * @return User|null The user associated with the token or null if no user associated
	 */
	public function getUserFromToken( $token ) {
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

	/**
	 * @param User $user The user to match the token to
	 * @param array $token A verified token provided from Google after authenticating a user
	 * @return bool True, if matching was successful, false otehrwise
	 */
	public function match( User $user, $token ) {
		if ( $user->isAnon() ) {
			return false;
		}
		if ( !isset( $token['sub'] ) ) {
			return false;
		}

		$db = $this->loadBalancer->getConnection( DB_MASTER );
		return $db->insert(
			'user_google_user',
			[
				'user_id' => $user->getId(),
				'user_googleid' => $token['sub']
			]
		);
	}

	/**
	 * @param User $user
	 * @param array $token
	 * @return bool True, if unmatching was successful, false otherwise
	 */
	public function unmatch( User $user, $token ) {
		if ( $user->isAnon() ) {
			return false;
		}
		if ( !isset( $token['sub'] ) ) {
			return false;
		}

		$db = $this->loadBalancer->getConnection( DB_MASTER );

		return (bool) $db->delete(
			"user_google_user",
			[
				'user_id' => $user->getId(),
				'user_googleid' => $token['sub'],
			],
			__METHOD__
		);
	}
}
