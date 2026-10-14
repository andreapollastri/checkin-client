# Checkin Client

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20|%2012%20|%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-4%20|%205-F59E0B)](https://filamentphp.com/)
[![License](https://img.shields.io/badge/License-MIT-005f73)](LICENSE)

> **Single sign-on for Laravel applications** — add a "Continue with Checkin" button to any login page (Blade or Filament) and let people sign in with their [Checkin](https://github.com/andreapollastri/checkin) account through OpenID Connect.

The native login of your application keeps working as before: Checkin is an **additional** way in. The package handles the whole OpenID Connect dance (discovery, PKCE, state, nonce, ID token verification, UserInfo), finds or creates the local user, logs them in and fires events you can listen to.

---

## Contents

- [Requirements](#requirements)
- [How it works](#how-it-works)
- [Installation](#installation)
- [Configuration](#configuration)
- [The login button](#the-login-button)
- [Filament panels](#filament-panels)
- [How users are resolved](#how-users-are-resolved)
- [Events](#events)
- [Logout](#logout)
- [Artisan commands](#artisan-commands)
- [Recipes](#recipes)
- [Security notes](#security-notes)
- [Testing](#testing)
- [License](#license)

---

## Requirements

- **PHP** 8.2+
- **Laravel** 11, 12 or 13
- A **Checkin** server (or any OpenID Connect provider exposing discovery, PKCE and RS256 ID tokens)
- Optional: **Filament** 4 or 5 for the panel login button

---

## How it works

```
Browser                 Your app (this package)                 Checkin
  │  click button           │                                      │
  │ ───────────────────────▶│ GET /auth/checkin/redirect           │
  │                         │  state + nonce + PKCE in session     │
  │ ◀───────────────────────│ 302 → /oauth/authorize?code_challenge│
  │ ───────────────────────────────────────────────────────────────▶│ login (passkey / 2FA)
  │ ◀───────────────────────────────────────────────────────────────│ 302 → /auth/checkin/callback?code&state
  │ ───────────────────────▶│ GET /auth/checkin/callback           │
  │                         │  check state                         │
  │                         │  POST /oauth/token (code + verifier) ─▶│
  │                         │ ◀── access_token + id_token ─────────│
  │                         │  verify id_token (JWKS, iss, aud,    │
  │                         │  exp, nonce), GET /oauth/userinfo   ─▶│
  │                         │  resolve local user, Auth::login()   │
  │ ◀───────────────────────│ 302 → intended URL                   │
```

Authorization Code flow with **PKCE (S256)**, a **nonce** bound to the ID token and a **state** bound to the session. Tokens are never exposed to the browser.

---

## Installation

```bash
composer require andreapollastri/checkin-client
php artisan checkin:install
php artisan migrate
```

`checkin:install` publishes `config/checkin.php` and a migration adding a nullable, unique `checkin_id` column to your users table (the Checkin subject identifier).

Register the application on your Checkin server (admin panel → Applications, or `php artisan checkin:client`) with the redirect URI printed by the installer, then add the credentials to `.env`:

```dotenv
CHECKIN_URL=https://iam.ap.it
CHECKIN_CLIENT_ID=9d1f...
CHECKIN_CLIENT_SECRET=...
```

Check everything with `php artisan checkin:status`.

The package is **enabled only when `CHECKIN_CLIENT_ID` is set**: projects without these variables behave exactly as before (the button renders nothing and the routes answer 404).

---

## Configuration

`config/checkin.php`:

| Key | Default | Role |
| :--- | :--- | :--- |
| `enabled` | `CHECKIN_ENABLED` or "client id present" | Master switch |
| `issuer` | `CHECKIN_URL` (`https://iam.ap.it`) | Checkin base URL; must equal the `iss` claim exactly |
| `client_id`, `client_secret` | `CHECKIN_CLIENT_ID`, `CHECKIN_CLIENT_SECRET` | Credentials issued by Checkin. Without a secret the client is *public* (PKCE only) |
| `scopes` | `openid profile email` | `openid` is always added |
| `label` | `CHECKIN_LABEL` (`Continue with Checkin`) | Button label |
| `guard` | `CHECKIN_GUARD` (default guard) | Stateful guard to log into |
| `remember` | `false` | Issue a "remember me" cookie |
| `redirect` | `CHECKIN_REDIRECT` (`/`) | Where to go after login when nothing was intended |
| `failure_redirect` | `CHECKIN_FAILURE_REDIRECT` (login route or `/`) | Where to go after an error (with `$errors->first('checkin')`) |
| `routes.prefix` | `auth/checkin` | Route prefix (`redirect`, `callback`, `logout`) |
| `routes.middleware` | `['web']` | Middleware of the package routes |
| `logout.remote` | `true` | The package's logout route also ends the Checkin session |
| `logout.redirect` | `/` | Where to land after logging out |
| `user.model` | guard's provider model | The user model |
| `user.column` | `checkin_id` | Column holding the Checkin subject |
| `user.auto_create` | `CHECKIN_AUTO_CREATE_USERS` (`true`) | Create unknown people |
| `user.link_by_email` | `CHECKIN_LINK_BY_EMAIL` (`true`) | Link an existing user with the same e-mail |
| `user.require_verified_email` | `true` | Only link by e-mail when Checkin marks it verified |
| `user.sync` | `['name']` | Attributes refreshed at every login (`name`, `email`, or any claim) |
| `user.defaults` | `[]` | Extra attributes for created users (e.g. a role) |
| `user.resolver` | `null` | Your own `Checkin\Contracts\ResolvesUsers` implementation |
| `http.timeout`, `http.cache_ttl`, `http.leeway` | `10`, `3600`, `60` | HTTP timeout, discovery/JWKS cache, clock skew tolerance |
| `filament.*` | see below | Defaults for the Filament plugin |

---

## The login button

Drop the Blade component in your login view and give it your own classes — it ships no CSS framework:

```blade
{{-- Tailwind --}}
<x-checkin-button class="inline-flex w-full items-center justify-center rounded-lg border px-4 py-2 text-sm font-medium" />

{{-- Bootstrap --}}
<x-checkin-button class="btn btn-outline-secondary w-100" />

{{-- Custom label, no icon --}}
<x-checkin-button label="Accedi con AP ID" :icon="false" class="..." />
```

It renders an `<a href="{{ route('checkin.redirect') }}">` with a key icon and the label, and **renders nothing when the package is disabled**. Show failures next to it:

```blade
@error('checkin') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
```

With Livewire Flux you may prefer a Flux button pointing at the same route:

```blade
@if (\Checkin\Facade::isEnabled())
    <flux:button :href="route('checkin.redirect')" variant="outline" icon="key" class="w-full">
        {{ \Checkin\Facade::label() }}
    </flux:button>
@endif
```

Publish and edit the views with `php artisan vendor:publish --tag=checkin-views`.

---

## Filament panels

```php
use Checkin\Filament\CheckinPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->login()
        ->plugin(CheckinPlugin::make());
}
```

The plugin adds the button under (or above) the panel's login form through Filament's render hooks. Fluent options, with defaults in `config('checkin.filament')`:

```php
CheckinPlugin::make()
    ->label('Accedi con AP ID')
    ->before()                 // or ->after() (default)
    ->color('primary')         // Filament color (default: gray, outlined)
    ->icon('heroicon-o-finger-print') // or ->icon(null)
    ->divider(false);          // hide the "or" separator
```

After the callback the person is logged into the panel's guard and sent to the intended page. Filament's own multi-factor challenge is **not** triggered for these sign-ins: the second factor was verified by Checkin. `FilamentUser::canAccessPanel()` is still enforced.

---

## How users are resolved

The default resolver (`Checkin\UserResolver`) runs, in order:

1. **Subject match** — a user whose `checkin_id` equals the Checkin `sub` claim.
2. **E-mail link** — if `link_by_email` is on and Checkin reports the e-mail as verified, a user with the same e-mail is linked (its `checkin_id` is filled) and `UserLinked` is fired.
3. **Creation** — if `auto_create` is on, a user is created with `name`, `email`, `checkin_id`, `email_verified_at` (when verified), a random unusable password when the table has a `password` column, plus `user.defaults`; `UserCreated` is fired. Otherwise `UserNotProvisionedException` ends the sign-in with a clear message.

At every login the attributes listed in `user.sync` are refreshed from Checkin.

Need roles, team assignment or a different lookup? Extend the resolver or implement the contract:

```php
namespace App\Auth;

use Checkin\Oidc\Identity;
use Checkin\UserResolver;
use Illuminate\Database\Eloquent\Model;

class CheckinUserResolver extends UserResolver
{
    protected function create(Identity $identity): Model
    {
        $user = parent::create($identity);
        $user->forceFill(['role' => 'member'])->save();

        return $user;
    }
}
```

```php
// config/checkin.php
'user' => ['resolver' => App\Auth\CheckinUserResolver::class, /* ... */],
```

`Identity` exposes `sub()`, `email()`, `emailVerified()`, `name()`, `preferredUsername()`, `picture()`, `amr()` (authentication methods used at Checkin: `pwd`, `otp`, `hwk`) and `claim($name)`.

---

## Events

| Event | When | Payload |
| :--- | :--- | :--- |
| `Checkin\Events\LoggedIn` | A person signed in through Checkin | `$user`, `$identity`, `$tokens` (`TokenSet`) |
| `Checkin\Events\UserCreated` | A local user was created | `$user`, `$identity` |
| `Checkin\Events\UserLinked` | An existing user was linked by e-mail | `$user`, `$identity` |
| `Checkin\Events\LoginFailed` | The callback failed | `$request`, `$exception` |

Listeners in `app/Listeners` with a typed `handle(LoggedIn $event)` are discovered automatically by Laravel 11+.

---

## Logout

Your application's native logout is untouched. The package adds `GET|POST /auth/checkin/logout` (`route('checkin.logout')`) which ends the local session and, when `logout.remote` is true, redirects to Checkin's `end_session_endpoint` with the ID token hint, so the Checkin session ends too and the browser comes back to `logout.redirect`.

---

## Artisan commands

| Command | Role |
| :--- | :--- |
| `checkin:install` | Publish the config and the users migration, print the next steps |
| `checkin:status` | Show the effective configuration, check the `checkin_id` column, reach the discovery document and the signing keys |

`php artisan about` also lists the Checkin section.

---

## Recipes

### Fortify / Breeze / Jetstream

Add the button to `resources/views/auth/login.blade.php` (or the Livewire page). Nothing else: the package logs into the `web` guard like Fortify does. Fortify's own two-factor challenge only runs on password logins, which is what you want: Checkin already verified the second factor.

### A custom login with its own 2FA gate

If your app keeps a session flag after its two-factor challenge, set it when Checkin signs someone in:

```php
namespace App\Listeners;

use Checkin\Events\LoggedIn;

class MarkCheckinLoginAsTwoFactorPassed
{
    public function handle(LoggedIn $event): void
    {
        session()->put('auth.2fa.passed', true);
    }
}
```

### Invite-only applications

Set `CHECKIN_AUTO_CREATE_USERS=false`: only people who already exist locally (matched by subject or verified e-mail) can sign in. Better still, keep creation on and restrict the application on the Checkin side ("assigned people only"): Checkin then decides who gets in, and local accounts appear on first sign-in.

### IdP-initiated sign-in

Point the application's *launch URL* on Checkin to `https://your-app/auth/checkin/redirect`: clicking the app on the Checkin launcher signs the person straight in.

---

## Security notes

- **PKCE S256** on every request, including confidential clients.
- **State** is a 40-character random string bound to the session and compared in constant time.
- **Nonce** is bound to the ID token; a token without the expected nonce is rejected.
- **ID tokens** are verified against the provider's JWKS (`RS256` only; `alg=none`/HMAC are refused), with `iss`, `aud`/`azp`, `exp`/`iat` (with `http.leeway`) and `sub` checked. Unknown `kid` triggers one key refresh (rotation).
- **UserInfo** `sub` must match the ID token `sub`.
- Linking by e-mail requires `email_verified = true` by default.
- Discovery is refused when the document's `issuer` differs from `CHECKIN_URL`.
- The session is regenerated at login; tokens are not stored except the ID token (for logout) in the session.

---

## Testing

```bash
composer install
composer test
```

The suite (Orchestra Testbench + PHPUnit) fakes a provider with an RSA key generated on the fly and covers the redirect, the callback (creation, subject match, e-mail link, every rejection path), logout and the Blade component.

In your own application tests, `Http::fake()` the four endpoints (`/.well-known/openid-configuration`, `/oauth/token`, `/oauth/jwks`, `/oauth/userinfo`) as `tests/TestCase.php` in this repository does, or simply log the user in with `actingAs()`.

---

## License

MIT © [Andrea Pollastri](https://andreapollastri.net)
