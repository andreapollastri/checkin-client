<?php

namespace Checkin\Tests\Feature;

use Checkin\Events\LoggedIn;
use Checkin\Events\LoginFailed;
use Checkin\Events\UserCreated;
use Checkin\Events\UserLinked;
use Checkin\Tests\Fixtures\User;
use Checkin\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class CallbackTest extends TestCase
{
    public function test_a_new_person_is_created_and_logged_in(): void
    {
        Event::fake([LoggedIn::class, UserCreated::class]);
        $this->fakeServer();
        $pending = $this->pendingSignIn();

        config()->set('checkin.redirect', '/dashboard');

        $response = $this->get('/auth/checkin/callback?code=the-code&state='.$pending['state']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::query()->sole();
        $this->assertSame('user-uuid-1', $user->checkin_id);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);

        Event::assertDispatched(UserCreated::class);
        Event::assertDispatched(LoggedIn::class, fn (LoggedIn $event) => $event->identity->preferredUsername() === 'ada');

        $response->assertSessionMissing('checkin.state');
        $response->assertSessionHas('checkin.sub', 'user-uuid-1');
        $response->assertSessionHas('checkin.id_token');

        Http::assertSent(fn ($request) => $request->url() === self::ISSUER.'/oauth/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'the-code'
            && $request['code_verifier'] === $pending['verifier']
            && $request['client_secret'] === 'app-secret'
            && $request['redirect_uri'] === 'https://app.test/auth/checkin/callback');
    }

    public function test_an_existing_person_is_found_by_subject(): void
    {
        $existing = User::query()->create([
            'name' => 'Old Name', 'email' => 'other@example.com', 'checkin_id' => 'user-uuid-1', 'password' => Hash::make('x'),
        ]);

        $this->fakeServer();
        $pending = $this->pendingSignIn();

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])->assertRedirect('/');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->count());
        $this->assertSame('Ada Lovelace', $existing->fresh()->name, 'name is synced at login');
        $this->assertSame('other@example.com', $existing->fresh()->email, 'email is not synced by default');
    }

    public function test_an_existing_person_is_linked_by_verified_email(): void
    {
        Event::fake([UserLinked::class]);

        $existing = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('x')]);

        $this->fakeServer();
        $pending = $this->pendingSignIn();

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])->assertRedirect('/');

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('user-uuid-1', $existing->fresh()->checkin_id);
        Event::assertDispatched(UserLinked::class);
    }

    public function test_an_unverified_email_never_links_an_existing_account(): void
    {
        User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('x')]);

        config()->set('checkin.user.auto_create', false);
        config()->set('checkin.failure_redirect', '/login');

        $this->fakeServer($this->idToken(['email_verified' => false]), ['email_verified' => false]);
        $pending = $this->pendingSignIn();

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
        $this->assertNull(User::query()->sole()->checkin_id);
    }

    public function test_nobody_is_created_when_auto_create_is_off(): void
    {
        Event::fake([LoginFailed::class]);
        config()->set('checkin.user.auto_create', false);
        config()->set('checkin.failure_redirect', '/login');

        $this->fakeServer();
        $pending = $this->pendingSignIn();

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        Event::assertDispatched(LoginFailed::class);
    }

    public function test_a_wrong_state_is_rejected(): void
    {
        $this->fakeServer();
        $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?code=c&state=forged')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
        Http::assertNotSent(fn ($request) => $request->url() === self::ISSUER.'/oauth/token');
    }

    public function test_an_error_from_the_server_is_shown(): void
    {
        $this->fakeServer();
        $pending = $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?error=access_denied&error_description=No+access+to+this+app&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['checkin' => 'No access to this app']);

        $this->assertGuest();
    }

    public function test_a_token_with_a_wrong_nonce_is_rejected(): void
    {
        $this->fakeServer($this->idToken(['nonce' => 'another']));
        $pending = $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
    }

    public function test_a_token_for_another_audience_is_rejected(): void
    {
        $this->fakeServer($this->idToken(['aud' => 'someone-else']));
        $pending = $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
    }

    public function test_a_token_signed_with_an_unknown_key_is_rejected(): void
    {
        [$otherPrivate] = $this->generateKeyPair();
        $this->privateKey = $otherPrivate;

        $this->fakeServer($this->idToken());
        $pending = $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $this->fakeServer($this->idToken(['exp' => time() - 3600, 'iat' => time() - 7200]));
        $pending = $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
    }

    public function test_a_userinfo_subject_mismatch_is_rejected(): void
    {
        $this->fakeServer(null, ['sub' => 'someone-else']);
        $pending = $this->pendingSignIn();
        config()->set('checkin.failure_redirect', '/login');

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('checkin');

        $this->assertGuest();
    }

    public function test_the_intended_url_wins_over_the_configured_redirect(): void
    {
        $this->fakeServer();
        $pending = $this->pendingSignIn();
        session()->put('url.intended', 'https://app.test/reports/42');

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state'])
            ->assertRedirect('https://app.test/reports/42');
    }

    public function test_defaults_are_applied_to_created_users(): void
    {
        config()->set('checkin.user.defaults', ['role' => 'admin']);

        $this->fakeServer();
        $pending = $this->pendingSignIn();

        $this->get('/auth/checkin/callback?code=c&state='.$pending['state']);

        $this->assertSame('admin', User::query()->sole()->role);
    }
}
