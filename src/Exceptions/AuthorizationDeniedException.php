<?php

namespace Checkin\Exceptions;

/**
 * Checkin answered the authorization request with an error
 * (the person denied access, has no access to this app, ...).
 */
class AuthorizationDeniedException extends CheckinException
{
    public function __construct(
        public readonly string $error,
        public readonly ?string $description = null,
    ) {
        parent::__construct($description ?: "Checkin refused the sign-in ({$error}).");
    }
}
