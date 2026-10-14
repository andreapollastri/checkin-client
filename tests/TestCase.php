<?php

namespace Checkin\Tests;

use Checkin\CheckinServiceProvider;
use Checkin\Tests\Fixtures\User;
use Firebase\JWT\JWT;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const ISSUER = 'https://iam.test';

    protected const CLIENT_ID = 'app-client-id';

    protected const KID = 'test-key-1';

    protected string $privateKey;

    protected string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->privateKey, $this->publicKey] = $this->generateKeyPair();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('checkin_id', 64)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('member');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [CheckinServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('checkin.enabled', true);
        $app['config']->set('checkin.issuer', self::ISSUER);
        $app['config']->set('checkin.client_id', self::CLIENT_ID);
        $app['config']->set('checkin.client_secret', 'app-secret');
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function generateKeyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $private);
        $public = openssl_pkey_get_details($resource)['key'];

        return [$private, $public];
    }

    /**
     * @return array<string, mixed>
     */
    protected function discoveryDocument(): array
    {
        return [
            'issuer' => self::ISSUER,
            'authorization_endpoint' => self::ISSUER.'/oauth/authorize',
            'token_endpoint' => self::ISSUER.'/oauth/token',
            'userinfo_endpoint' => self::ISSUER.'/oauth/userinfo',
            'jwks_uri' => self::ISSUER.'/oauth/jwks',
            'end_session_endpoint' => self::ISSUER.'/oauth/logout',
            'scopes_supported' => ['openid', 'profile', 'email'],
            'response_types_supported' => ['code'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'code_challenge_methods_supported' => ['S256'],
        ];
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    protected function jwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicKey));

        $encode = fn (string $binary): string => rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');

        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::KID,
            'n' => $encode($details['rsa']['n']),
            'e' => $encode($details['rsa']['e']),
        ]]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function idToken(array $overrides = [], ?string $kid = self::KID): string
    {
        $claims = array_merge([
            'iss' => self::ISSUER,
            'sub' => 'user-uuid-1',
            'aud' => self::CLIENT_ID,
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => 'nonce-value',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'name' => 'Ada Lovelace',
        ], $overrides);

        return JWT::encode(array_filter($claims, fn ($value) => $value !== null), $this->privateKey, 'RS256', $kid);
    }

    /**
     * @param  array<string, mixed>  $userInfo
     * @param  array<string, mixed>  $tokenResponse
     */
    protected function fakeServer(?string $idToken = null, array $userInfo = [], array $tokenResponse = []): void
    {
        $idToken ??= $this->idToken();

        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response($this->discoveryDocument()),
            self::ISSUER.'/oauth/jwks' => Http::response($this->jwks()),
            self::ISSUER.'/oauth/token' => Http::response(array_merge([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'id_token' => $idToken,
            ], $tokenResponse)),
            self::ISSUER.'/oauth/userinfo' => Http::response(array_merge([
                'sub' => 'user-uuid-1',
                'email' => 'ada@example.com',
                'email_verified' => true,
                'name' => 'Ada Lovelace',
                'preferred_username' => 'ada',
            ], $userInfo)),
        ]);
    }

    /**
     * Put a pending sign-in in the session, as the redirect step would.
     *
     * @return array{state: string, nonce: string, verifier: string}
     */
    protected function pendingSignIn(): array
    {
        $pending = ['state' => 'state-value', 'nonce' => 'nonce-value', 'verifier' => str_repeat('v', 64)];

        $this->withSession([
            'checkin.state' => $pending['state'],
            'checkin.nonce' => $pending['nonce'],
            'checkin.verifier' => $pending['verifier'],
        ]);

        return $pending;
    }
}
