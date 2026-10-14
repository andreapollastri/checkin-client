<?php

namespace Checkin\Tests\Feature;

use Checkin\Oidc\Pkce;
use Checkin\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class RedirectTest extends TestCase
{
    public function test_it_sends_the_browser_to_the_authorization_endpoint_with_pkce_state_and_nonce(): void
    {
        $this->fakeServer();

        $response = $this->get('/auth/checkin/redirect');

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith(self::ISSUER.'/oauth/authorize?', $location);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame('https://app.test/auth/checkin/callback', $query['redirect_uri']);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);

        $response->assertSessionHas('checkin.state', $query['state']);
        $response->assertSessionHas('checkin.nonce', $query['nonce']);
        $response->assertSessionHas('checkin.verifier');

        $verifier = session('checkin.verifier');
        $this->assertSame(Pkce::challenge($verifier), $query['code_challenge']);
    }

    public function test_the_routes_vanish_when_the_package_is_disabled(): void
    {
        config()->set('checkin.enabled', false);

        $this->get('/auth/checkin/redirect')->assertNotFound();
        $this->get('/auth/checkin/callback')->assertNotFound();
    }

    public function test_an_unreachable_server_sends_the_person_back_with_an_error(): void
    {
        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response('', 503),
        ]);

        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/redirect')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');
    }

    public function test_a_discovery_document_from_another_issuer_is_rejected(): void
    {
        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response(array_merge(
                $this->discoveryDocument(),
                ['issuer' => 'https://evil.test'],
            )),
        ]);

        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/redirect')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');
    }
}
