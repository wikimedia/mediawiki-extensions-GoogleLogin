<?php

namespace GoogleLogin;

use MediaWiki\Notification\AgentAware;
use MediaWiki\Notification\Notification;
use MediaWiki\User\UserIdentity;

/**
 * A Notification about a change to a user's linked Google accounts, with no associated page.
 */
class GoogleLoginAgentNotification extends Notification implements AgentAware {

	private UserIdentity $agent;

	public function __construct( string $type, UserIdentity $agent, array $extra ) {
		parent::__construct( $type, $extra );
		$this->agent = $agent;
	}

	public function getAgent(): UserIdentity {
		return $this->agent;
	}

}
