<?php

namespace Checkin\Http\Controllers;

use Checkin\Checkin;
use Checkin\Events\LoginFailed;
use Checkin\Exceptions\AuthorizationDeniedException;
use Checkin\Exceptions\CheckinException;
use Checkin\Exceptions\InvalidStateException;
use Checkin\Exceptions\UserNotProvisionedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET {prefix}/callback — Checkin sends the browser back here with a code.
 */
class CallbackController
{
    public function __invoke(Request $request, Checkin $checkin): RedirectResponse
    {
        abort_unless($checkin->isEnabled(), 404);

        try {
            $checkin->callback($request);
        } catch (CheckinException $e) {
            LoginFailed::dispatch($request, $e);

            // Expected outcomes are not bugs; everything else deserves the log.
            if (! $e instanceof AuthorizationDeniedException
                && ! $e instanceof UserNotProvisionedException
                && ! $e instanceof InvalidStateException) {
                report($e);
            }

            return redirect()->to($checkin->failureRedirect())
                ->withErrors(['checkin' => $e->getMessage()]);
        }

        return redirect()->intended($checkin->successRedirect());
    }
}
