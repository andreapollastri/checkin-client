<?php

namespace Checkin\Events;

use Checkin\Oidc\Identity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an existing local user (matched by e-mail) was linked to a Checkin identity.
 */
class UserLinked
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly Identity $identity,
    ) {}
}
