<?php

namespace Checkin\Events;

use Checkin\Oidc\Identity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a local user was created from a Checkin identity.
 */
class UserCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly Identity $identity,
    ) {}
}
