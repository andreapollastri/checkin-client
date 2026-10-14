<?php

namespace Checkin\Http\Controllers;

use Checkin\Checkin;
use Checkin\Exceptions\CheckinException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * GET {prefix}/redirect — send the browser to Checkin.
 */
class RedirectController
{
    public function __invoke(Request $request, Checkin $checkin): RedirectResponse
    {
        abort_unless($checkin->isEnabled(), 404);

        try {
            return $checkin->redirect($request);
        } catch (CheckinException $e) {
            report($e);

            return redirect()->to($checkin->failureRedirect())
                ->withErrors(['checkin' => $e->getMessage()]);
        }
    }
}
