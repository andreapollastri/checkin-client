<?php

namespace Checkin\Tests\Unit;

use Checkin\Oidc\Identity;
use PHPUnit\Framework\TestCase;

class IdentityTest extends TestCase
{
    public function test_claims_are_normalised(): void
    {
        $identity = new Identity([
            'sub' => 42,
            'email' => 'Ada@Example.com',
            'email_verified' => 'true',
            'name' => '',
            'amr' => ['pwd', 'otp'],
        ]);

        $this->assertSame('42', $identity->sub());
        $this->assertSame('ada@example.com', $identity->email());
        $this->assertTrue($identity->emailVerified());
        $this->assertNull($identity->name());
        $this->assertSame(['pwd', 'otp'], $identity->amr());
        $this->assertNull($identity->picture());
        $this->assertSame('fallback', $identity->claim('missing', 'fallback'));
    }
}
