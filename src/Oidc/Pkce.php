<?php

namespace Checkin\Oidc;

use Illuminate\Support\Str;

/**
 * Proof Key for Code Exchange (RFC 7636), S256 method.
 */
final class Pkce
{
    public static function verifier(): string
    {
        // 64 unreserved characters: within the 43..128 range required by the RFC.
        return Str::random(64);
    }

    public static function challenge(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    public static function base64url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
