<?php

namespace Checkin\Oidc;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The verified identity of the person who just signed in at Checkin:
 * the ID token claims merged with the UserInfo response.
 *
 * @implements Arrayable<string, mixed>
 */
final class Identity implements Arrayable
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(public readonly array $claims) {}

    /** The stable, unique subject identifier assigned by Checkin. */
    public function sub(): string
    {
        return (string) $this->claims['sub'];
    }

    public function email(): ?string
    {
        $email = $this->claims['email'] ?? null;

        return is_string($email) && $email !== '' ? strtolower($email) : null;
    }

    public function emailVerified(): bool
    {
        return filter_var($this->claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function name(): ?string
    {
        $name = $this->claims['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    public function preferredUsername(): ?string
    {
        $username = $this->claims['preferred_username'] ?? null;

        return is_string($username) && $username !== '' ? $username : null;
    }

    public function picture(): ?string
    {
        $picture = $this->claims['picture'] ?? null;

        return is_string($picture) && $picture !== '' ? $picture : null;
    }

    /**
     * Authentication methods used at Checkin ("pwd", "otp", "hwk"...), when published.
     *
     * @return list<string>
     */
    public function amr(): array
    {
        return array_values(array_map('strval', (array) ($this->claims['amr'] ?? [])));
    }

    public function claim(string $name, mixed $default = null): mixed
    {
        return $this->claims[$name] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->claims;
    }
}
