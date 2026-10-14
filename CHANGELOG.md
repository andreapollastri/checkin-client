# Changelog

All notable changes to `andreapollastri/checkin-client` are documented here.

## [Unreleased]

### Added

- OpenID Connect relying party: discovery, Authorization Code + PKCE (S256), state, nonce, RS256 ID token verification against JWKS (with key-rotation refresh), UserInfo.
- `UserResolver` (subject → verified e-mail → create) with `ResolvesUsers` contract, `user.sync` and `user.defaults`.
- Routes `auth/checkin/{redirect,callback,logout}` and RP-initiated logout.
- `<x-checkin-button />` Blade component and `Checkin\Filament\CheckinPlugin` for Filament 4/5 panels.
- Events `LoggedIn`, `UserCreated`, `UserLinked`, `LoginFailed`.
- Artisan `checkin:install` and `checkin:status`; `about` section.
- Testbench suite.
