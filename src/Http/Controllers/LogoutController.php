<?php

namespace Checkin\Http\Controllers;

use Checkin\Checkin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET|POST {prefix}/logout — end the local session and, optionally, the Checkin one.
 */
class LogoutController
{
    public function __invoke(Request $request, Checkin $checkin): RedirectResponse
    {
        abort_unless($checkin->isEnabled(), 404);

        return $checkin->logout($request);
    }
}
