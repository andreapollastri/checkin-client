<?php

namespace Checkin\Oidc;

/**
 * The verified claims of an ID token.
 */
final class IdToken
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public readonly string $raw,
        public readonly array $claims,
    ) {}

    public function sub(): string
    {
        return (string) $this->claims['sub'];
    }

    public function nonce(): ?string
    {
        return isset($this->claims['nonce']) ? (string) $this->claims['nonce'] : null;
    }

    public function expiresAt(): int
    {
        return (int) ($this->claims['exp'] ?? 0);
    }
}
