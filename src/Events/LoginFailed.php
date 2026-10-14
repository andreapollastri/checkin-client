<?php

namespace Checkin\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Fired when the callback could not complete a sign-in.
 */
class LoginFailed
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly Throwable $exception,
    ) {}
}
