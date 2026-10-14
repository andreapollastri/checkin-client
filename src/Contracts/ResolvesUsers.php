<?php

namespace Checkin\Contracts;

use Checkin\Exceptions\UserNotProvisionedException;
use Checkin\Oidc\Identity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Turns a verified Checkin identity into the local user to log in.
 */
interface ResolvesUsers
{
    /**
     * @throws UserNotProvisionedException when no user matches and none may be created
     */
    public function resolve(Identity $identity): Authenticatable;
}
