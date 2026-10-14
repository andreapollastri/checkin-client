<?php

namespace Checkin\Tests\Unit;

use Checkin\Oidc\Pkce;
use PHPUnit\Framework\TestCase;

class PkceTest extends TestCase
{
    public function test_the_verifier_follows_rfc_7636(): void
    {
        $verifier = Pkce::verifier();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier);
    }

    public function test_the_challenge_is_the_url_safe_sha256_of_the_verifier(): void
    {
        // Example from RFC 7636 appendix B.
        $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Pkce::challenge($verifier));
    }
}
