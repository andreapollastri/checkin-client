<?php

namespace Checkin\Oidc;

use Checkin\Exceptions\DiscoveryException;

/**
 * The provider metadata published at /.well-known/openid-configuration.
 */
final class Discovery
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(private readonly array $metadata) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function fromArray(array $metadata, string $expectedIssuer): self
    {
        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
            if (empty($metadata[$key]) || ! is_string($metadata[$key])) {
                throw new DiscoveryException("The discovery document is missing \"{$key}\".");
            }
        }

        if (rtrim($metadata['issuer'], '/') !== rtrim($expectedIssuer, '/')) {
            throw new DiscoveryException(sprintf(
                'The discovery document declares issuer "%s" but "%s" was configured.',
                $metadata['issuer'],
                $expectedIssuer,
            ));
        }

        return new self($metadata);
    }

    public function issuer(): string
    {
        return rtrim($this->metadata['issuer'], '/');
    }

    public function authorizationEndpoint(): string
    {
        return $this->metadata['authorization_endpoint'];
    }

    public function tokenEndpoint(): string
    {
        return $this->metadata['token_endpoint'];
    }

    public function jwksUri(): string
    {
        return $this->metadata['jwks_uri'];
    }

    public function userInfoEndpoint(): ?string
    {
        return $this->metadata['userinfo_endpoint'] ?? null;
    }

    public function endSessionEndpoint(): ?string
    {
        return $this->metadata['end_session_endpoint'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function scopesSupported(): array
    {
        return array_values((array) ($this->metadata['scopes_supported'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->metadata;
    }
}
