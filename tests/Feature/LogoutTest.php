<?php

namespace Checkin\Tests\Feature;

use Checkin\Tests\Fixtures\User;
use Checkin\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class LogoutTest extends TestCase
{
    public function test_it_ends_the_local_session_and_the_checkin_one(): void
    {
        $this->fakeServer();
        $user = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('x')]);

        $response = $this->actingAs($user)
            ->withSession(['checkin.id_token' => 'the-id-token'])
            ->get('/auth/checkin/logout');

        $this->assertGuest();

        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith(self::ISSUER.'/oauth/logout?', $location);
        $this->assertSame('the-id-token', $query['id_token_hint']);
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame('https://app.test', $query['post_logout_redirect_uri']);
    }

    public function test_remote_logout_can_be_turned_off(): void
    {
        $this->fakeServer();
        config()->set('checkin.logout.remote', false);
        config()->set('checkin.logout.redirect', '/bye');
        $user = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('x')]);

        $this->actingAs($user)->post('/auth/checkin/logout')->assertRedirect('/bye');

        $this->assertGuest();
    }
}
