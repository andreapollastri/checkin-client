<?php

namespace Checkin\Exceptions;

use Checkin\Oidc\Identity;

/**
 * The identity is valid but no local user matches it and automatic
 * creation is disabled.
 */
class UserNotProvisionedException extends CheckinException
{
    public function __construct(public readonly Identity $identity)
    {
        parent::__construct('Your Checkin account is not linked to a user of this application.');
    }
}
