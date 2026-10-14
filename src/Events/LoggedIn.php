<?php

namespace Checkin\Events;

use Checkin\Oidc\Identity;
use Checkin\Oidc\TokenSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired right after a person signed in through Checkin.
 */
class LoggedIn
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly Identity $identity,
        public readonly TokenSet $tokens,
    ) {}
}
