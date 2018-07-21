<?php

namespace GoogleLogin\UserMatching;

use User;

interface IUserMatcher {
	/**
	 * @param array $token
	 * @return User|null Returns a user when token matches, null otherwise.
	 */
	function match( array $token );
}
