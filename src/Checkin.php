<?php

namespace Checkin;

use Checkin\Contracts\ResolvesUsers;
use Checkin\Events\LoggedIn;
use Checkin\Exceptions\AuthorizationDeniedException;
use Checkin\Exceptions\CheckinException;
use Checkin\Exceptions\InvalidStateException;
use Checkin\Exceptions\TokenException;
use Checkin\Oidc\Identity;
use Checkin\Oidc\Pkce;
use Checkin\Oidc\Provider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The Laravel side of the sign-in: session state, guard, events, redirects.
 */
class Checkin
{
    public const SESSION_STATE = 'checkin.state';

    public const SESSION_NONCE = 'checkin.nonce';

    public const SESSION_VERIFIER = 'checkin.verifier';

    public const SESSION_ID_TOKEN = 'checkin.id_token';

    public const SESSION_SUB = 'checkin.sub';

    public function __construct(
        protected Config $config,
        protected Provider $provider,
        protected ResolvesUsers $users,
        protected AuthFactory $auth,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('checkin.enabled') && filled($this->config->get('checkin.client_id'));
    }

    public function provider(): Provider
    {
        return $this->provider;
    }

    public function users(): ResolvesUsers
    {
        return $this->users;
    }

    public function label(): string
    {
        return (string) $this->config->get('checkin.label', 'Continue with Checkin');
    }

    public function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = $this->auth->guard($this->config->get('checkin.guard') ?: null);

        return $guard;
    }

    public function redirectUrl(): string
    {
        return route('checkin.redirect');
    }

    public function callbackUrl(): string
    {
        return route('checkin.callback');
    }

    public function logoutUrl(): string
    {
        return route('checkin.logout');
    }

    /**
     * Start the sign-in: remember state, nonce and PKCE verifier, then go to Checkin.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Pkce::verifier();

        $request->session()->put([
            self::SESSION_STATE => $state,
            self::SESSION_NONCE => $nonce,
            self::SESSION_VERIFIER => $verifier,
        ]);

        $url = Provider::guard(fn () => $this->provider->authorizationUrl(
            redirectUri: $this->callbackUrl(),
            state: $state,
            nonce: $nonce,
            codeChallenge: Pkce::challenge($verifier),
        ));

        return new RedirectResponse($url);
    }

    /**
     * Finish the sign-in: validate the callback, exchange the code, log the user in.
     *
     * @throws CheckinException
     */
    public function callback(Request $request): Authenticatable
    {
        $session = $request->session();

        $expectedState = $session->pull(self::SESSION_STATE);
        $nonce = $session->pull(self::SESSION_NONCE);
        $verifier = $session->pull(self::SESSION_VERIFIER);

        if ($request->filled('error')) {
            throw new AuthorizationDeniedException(
                (string) $request->query('error'),
                $request->query('error_description') ? (string) $request->query('error_description') : null,
            );
        }

        $state = $request->query('state');

        if (! is_string($expectedState) || ! is_string($state) || ! hash_equals($expectedState, $state)) {
            throw new InvalidStateException('The sign-in attempt expired or was tampered with. Please try again.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || ! is_string($verifier) || ! is_string($nonce)) {
            throw new TokenException('Checkin did not return an authorization code.');
        }

        $tokens = Provider::guard(fn () => $this->provider->exchangeCode($code, $this->callbackUrl(), $verifier));
        $idToken = $this->provider->verifyIdToken($tokens->idToken, $nonce);

        $claims = $idToken->claims;

        if ($this->provider->discovery()->userInfoEndpoint() !== null) {
            $userInfo = Provider::guard(fn () => $this->provider->userInfo($tokens->accessToken));

            if ($userInfo !== [] && (string) ($userInfo['sub'] ?? '') !== $idToken->sub()) {
                throw new TokenException('The UserInfo subject does not match the ID token.');
            }

            $claims = array_merge($claims, $userInfo);
        }

        $identity = new Identity($claims);
        $user = $this->users->resolve($identity);

        $this->guard()->login($user, (bool) $this->config->get('checkin.remember', false));

        $session->regenerate();
        $session->put([
            self::SESSION_ID_TOKEN => $tokens->idToken,
            self::SESSION_SUB => $identity->sub(),
        ]);

        LoggedIn::dispatch($user, $identity, $tokens);

        return $user;
    }

    /**
     * End the local session and, when configured, the Checkin one too.
     */
    public function logout(Request $request): RedirectResponse
    {
        $session = $request->session();
        $idToken = $session->pull(self::SESSION_ID_TOKEN);

        $this->guard()->logout();

        $session->invalidate();
        $session->regenerateToken();

        $local = url((string) $this->config->get('checkin.logout.redirect', '/'));

        if ($this->config->get('checkin.logout.remote', true)) {
            $remote = $this->provider->endSessionUrl(
                is_string($idToken) ? $idToken : null,
                $local,
            );

            if ($remote !== null) {
                return new RedirectResponse($remote);
            }
        }

        return new RedirectResponse($local);
    }

    /**
     * Where to send the person when a sign-in fails.
     */
    public function failureRedirect(): string
    {
        if ($configured = $this->config->get('checkin.failure_redirect')) {
            return url($configured);
        }

        return Route::has('login') ? route('login') : url('/');
    }

    /**
     * The URL to send the person to after a successful sign-in, unless one was intended.
     */
    public function successRedirect(): string
    {
        return url((string) $this->config->get('checkin.redirect', '/'));
    }
}
