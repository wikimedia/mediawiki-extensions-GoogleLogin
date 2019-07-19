<?php

namespace GoogleLogin;

use User;
use Wikimedia\Rdbms\ILoadBalancer;

class GoogleIdProvider {
	private $loadBalancer;

	public function __construct( ILoadBalancer $loadBalancer ) {
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @param User $user
	 * @return array An array of associated Google account IDs
	 */
	public function getFromUser( User $user ) {
		if ( $user->isAnon() ) {
			return [];
		}
		$db = $this->loadBalancer->getConnection( DB_MASTER );

		$result = $db->select(
			'user_google_user',
			[ 'user_googleid' ],
			[ 'user_id' => $user->getId() ],
			__METHOD__
		);

		if ( $result === false ) {
			return [];
		}

		$ids = [];
		foreach ( $result as $obj ) {
			$ids[] = $obj->user_googleid;
		}
		return $ids;
	}

	/**
	 * @param int $id A Google ID to check if it's associated
	 * @return bool Returns false, if the ID is not associated already, true otherwise
	 */
	public function isAssociated( $id ) {
		$db = $this->loadBalancer->getConnection( DB_MASTER );
		$result = $db->selectRowCount(
			'user_google_user',
			'user_googleid',
			[ 'user_googleid' => $id ],
			__METHOD__
		);

		return $result !== 0;
	}
}
