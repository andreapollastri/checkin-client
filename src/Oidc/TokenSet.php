<?php

namespace Checkin\Oidc;

use Checkin\Exceptions\TokenException;

/**
 * The token endpoint response.
 */
final class TokenSet
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $idToken,
        public readonly ?string $refreshToken,
        public readonly ?int $expiresIn,
        public readonly string $tokenType,
        public readonly ?string $scope,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromArray(array $response): self
    {
        if (empty($response['access_token']) || ! is_string($response['access_token'])) {
            throw new TokenException('The token response does not contain an access token.');
        }

        if (empty($response['id_token']) || ! is_string($response['id_token'])) {
            throw new TokenException('The token response does not contain an ID token: make sure the "openid" scope is requested and granted.');
        }

        return new self(
            accessToken: $response['access_token'],
            idToken: $response['id_token'],
            refreshToken: isset($response['refresh_token']) ? (string) $response['refresh_token'] : null,
            expiresIn: isset($response['expires_in']) ? (int) $response['expires_in'] : null,
            tokenType: (string) ($response['token_type'] ?? 'Bearer'),
            scope: isset($response['scope']) ? (string) $response['scope'] : null,
        );
    }
}
