<?php

namespace Checkin;

use Illuminate\Support\Facades\Facade as BaseFacade;

/**
 * @method static bool isEnabled()
 * @method static \Checkin\Oidc\Provider provider()
 * @method static \Checkin\Contracts\ResolvesUsers users()
 * @method static string label()
 * @method static \Illuminate\Contracts\Auth\StatefulGuard guard()
 * @method static string redirectUrl()
 * @method static string callbackUrl()
 * @method static string logoutUrl()
 * @method static \Illuminate\Http\RedirectResponse redirect(\Illuminate\Http\Request $request)
 * @method static \Illuminate\Contracts\Auth\Authenticatable callback(\Illuminate\Http\Request $request)
 * @method static \Illuminate\Http\RedirectResponse logout(\Illuminate\Http\Request $request)
 * @method static string failureRedirect()
 * @method static string successRedirect()
 *
 * @see Checkin
 */
class Facade extends BaseFacade
{
    protected static function getFacadeAccessor(): string
    {
        return Checkin::class;
    }
}
