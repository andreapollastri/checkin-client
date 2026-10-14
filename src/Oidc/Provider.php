<?php

namespace Checkin\Oidc;

use Checkin\Exceptions\DiscoveryException;
use Checkin\Exceptions\TokenException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * A minimal OpenID Connect relying party: discovery, authorization code
 * exchange with PKCE, ID token verification and UserInfo.
 */
class Provider
{
    protected const ALGORITHM = 'RS256';

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        protected string $issuer,
        protected string $clientId,
        protected ?string $clientSecret,
        protected array $scopes,
        protected Http $http,
        protected Cache $cache,
        protected int $cacheTtl = 3600,
        protected int $timeout = 10,
        protected int $leeway = 60,
    ) {
        $this->issuer = rtrim($issuer, '/');
    }

    public function issuer(): string
    {
        return $this->issuer;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function isConfidential(): bool
    {
        return filled($this->clientSecret);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return array_values(array_unique(['openid', ...$this->scopes]));
    }

    /**
     * The provider metadata, cached.
     */
    public function discovery(bool $fresh = false): Discovery
    {
        $key = $this->cacheKey('discovery');

        if ($fresh) {
            $this->cache->forget($key);
        }

        /** @var array<string, mixed> $metadata */
        $metadata = $this->cache->remember($key, $this->cacheTtl, function (): array {
            $response = $this->request()->get($this->issuer.'/.well-known/openid-configuration');

            if (! $response->successful() || ! is_array($response->json())) {
                throw new DiscoveryException(sprintf(
                    'Checkin discovery failed at %s (HTTP %d).',
                    $this->issuer.'/.well-known/openid-configuration',
                    $response->status(),
                ));
            }

            return $response->json();
        });

        return Discovery::fromArray($metadata, $this->issuer);
    }

    /**
     * The URL the browser is sent to in order to sign in.
     *
     * @param  array<string, string>  $extra
     */
    public function authorizationUrl(string $redirectUri, string $state, string $nonce, string $codeChallenge, array $extra = []): string
    {
        $query = http_build_query(array_merge([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], $extra), '', '&', PHP_QUERY_RFC3986);

        $endpoint = $this->discovery()->authorizationEndpoint();

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query;
    }

    /**
     * Exchange the authorization code for tokens.
     */
    public function exchangeCode(string $code, string $redirectUri, string $codeVerifier): TokenSet
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ];

        if ($this->isConfidential()) {
            $payload['client_secret'] = $this->clientSecret;
        }

        $response = $this->request()->asForm()->post($this->discovery()->tokenEndpoint(), $payload);

        if (! $response->successful()) {
            throw new TokenException($this->describeError($response, 'The token exchange with Checkin failed'));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new TokenException('Checkin returned an unreadable token response.');
        }

        return TokenSet::fromArray($json);
    }

    /**
     * Verify the signature and the claims of an ID token.
     *
     * @throws TokenException
     */
    public function verifyIdToken(string $idToken, ?string $expectedNonce = null): IdToken
    {
        $claims = $this->decode($idToken);

        if (($claims['iss'] ?? null) === null || rtrim((string) $claims['iss'], '/') !== $this->issuer) {
            throw new TokenException('The ID token was issued by an unexpected issuer.');
        }

        $audience = (array) ($claims['aud'] ?? []);

        if (! in_array($this->clientId, $audience, true)) {
            throw new TokenException('The ID token was not issued for this application.');
        }

        if (count($audience) > 1 && ($claims['azp'] ?? null) !== $this->clientId) {
            throw new TokenException('The ID token names several audiences but this application is not the authorized party.');
        }

        if (empty($claims['sub']) || ! is_scalar($claims['sub'])) {
            throw new TokenException('The ID token has no subject.');
        }

        if (! isset($claims['exp'])) {
            throw new TokenException('The ID token has no expiry.');
        }

        if ($expectedNonce !== null) {
            $nonce = $claims['nonce'] ?? null;

            if (! is_string($nonce) || ! hash_equals($expectedNonce, $nonce)) {
                throw new TokenException('The ID token nonce does not match this sign-in attempt.');
            }
        }

        return new IdToken($idToken, $claims);
    }

    /**
     * Fetch the UserInfo claims with an access token.
     *
     * @return array<string, mixed>
     */
    public function userInfo(string $accessToken): array
    {
        $endpoint = $this->discovery()->userInfoEndpoint();

        if ($endpoint === null) {
            return [];
        }

        $response = $this->request()->withToken($accessToken)->get($endpoint);

        if (! $response->successful() || ! is_array($response->json())) {
            throw new TokenException($this->describeError($response, 'Checkin refused the UserInfo request'));
        }

        return $response->json();
    }

    /**
     * The URL ending the Checkin session, when the provider supports it.
     */
    public function endSessionUrl(?string $idTokenHint = null, ?string $postLogoutRedirectUri = null, ?string $state = null): ?string
    {
        try {
            $endpoint = $this->discovery()->endSessionEndpoint();
        } catch (Throwable) {
            return null;
        }

        if ($endpoint === null) {
            return null;
        }

        $query = http_build_query(array_filter([
            'id_token_hint' => $idTokenHint,
            'client_id' => $this->clientId,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'state' => $state,
        ]), '', '&', PHP_QUERY_RFC3986);

        return $endpoint.($query === '' ? '' : (str_contains($endpoint, '?') ? '&' : '?').$query);
    }

    /**
     * The provider's signing keys, cached; refreshed on demand (key rotation).
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    public function jwks(bool $fresh = false): array
    {
        $key = $this->cacheKey('jwks');

        if ($fresh) {
            $this->cache->forget($key);
        }

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        $jwks = $this->cache->remember($key, $this->cacheTtl, function (): array {
            $response = $this->request()->get($this->discovery()->jwksUri());

            $json = $response->json();

            if (! $response->successful() || ! is_array($json) || empty($json['keys'])) {
                throw new TokenException('The Checkin signing keys could not be fetched.');
            }

            return $json;
        });

        return $jwks;
    }

    /**
     * Decode and verify the signature of a JWT against the provider's keys.
     *
     * @return array<string, mixed>
     */
    protected function decode(string $jwt): array
    {
        $attempt = function (bool $fresh) use ($jwt): array {
            JWT::$leeway = $this->leeway;

            $headers = new stdClass;
            $decoded = JWT::decode($jwt, JWK::parseKeySet($this->jwks($fresh), self::ALGORITHM), $headers);

            if (($headers->alg ?? null) !== self::ALGORITHM) {
                throw new TokenException('The ID token is not signed with '.self::ALGORITHM.'.');
            }

            return json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        };

        try {
            try {
                return $attempt(false);
            } catch (UnexpectedValueException $e) {
                // An unknown "kid" usually means the keys rotated: fetch them again once.
                if (str_contains($e->getMessage(), 'kid')) {
                    return $attempt(true);
                }

                throw $e;
            }
        } catch (ExpiredException) {
            throw new TokenException('The ID token has expired.');
        } catch (BeforeValidException) {
            throw new TokenException('The ID token is not valid yet (check the clocks).');
        } catch (SignatureInvalidException) {
            throw new TokenException('The ID token signature is invalid.');
        } catch (TokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TokenException('The ID token could not be verified: '.$e->getMessage(), previous: $e);
        }
    }

    protected function request(): PendingRequest
    {
        return $this->http->acceptJson()->timeout($this->timeout)->throwIf(false);
    }

    protected function describeError(Response $response, string $prefix): string
    {
        $json = $response->json();
        $error = is_array($json) ? ($json['error_description'] ?? $json['error'] ?? null) : null;

        return sprintf('%s (HTTP %d%s).', $prefix, $response->status(), $error ? ': '.$error : '');
    }

    protected function cacheKey(string $what): string
    {
        return 'checkin:'.$what.':'.sha1($this->issuer);
    }

    /**
     * Wrap connection failures in a package exception.
     */
    public static function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (ConnectionException $e) {
            throw new DiscoveryException('Checkin is unreachable: '.$e->getMessage(), previous: $e);
        }
    }
}
