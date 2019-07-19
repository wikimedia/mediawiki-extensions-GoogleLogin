<?php

namespace GoogleLogin;

use User;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

class GoogleUserMatching {
	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param array $token A verified token provided from Google after authenticating a user
	 * @return User|null The user associated with the token or null if no user associated
	 */
	public function getUserFromToken( array $token ) {
		$candidate = $this->userIdMatcher( $token );
		if ( $candidate instanceof User ) {
			return $candidate;
		}

		$candidate = $this->authenticatedEmailMatcher( $token );
		if ( $candidate instanceof User ) {
			return $candidate;
		}

		return null;
	}

	/**
	 * @param User $user The user to match the token to
	 * @param array $token A verified token provided from Google after authenticating a user
	 * @return bool True, if matching was successful, false otehrwise
	 */
	public function match( User $user, array $token ) {
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
				'user_googleid' => $token['sub'],
			]
		);
	}

	/**
	 * @param User $user
	 * @param array $token
	 * @return bool True, if unmatching was successful, false otherwise
	 */
	public function unmatch( User $user, array $token ) {
		if ( $user->isAnon() ) {
			return false;
		}
		if ( !isset( $token['sub'] ) ) {
			return false;
		}

		$db = $this->loadBalancer->getConnection( DB_MASTER );

		return (bool)$db->delete(
			"user_google_user",
			[
				'user_id' => $user->getId(),
				'user_googleid' => $token['sub'],
			],
			__METHOD__
		);
	}

	private function userIdMatcher( array $token ) {
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

	private function authenticatedEmailMatcher( array $token ) {
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

		if ( $s instanceof IResultWrapper && $s->numRows() === 1 ) {
			return User::newFromId( $s->current()->user_id );
		}
		return null;
	}
}
