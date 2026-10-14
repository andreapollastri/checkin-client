<?php

namespace Checkin;

use Checkin\Contracts\ResolvesUsers;
use Checkin\Events\UserCreated;
use Checkin\Events\UserLinked;
use Checkin\Exceptions\UserNotProvisionedException;
use Checkin\Oidc\Identity;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The default strategy: match by subject, then by verified e-mail, then create.
 */
class UserResolver implements ResolvesUsers
{
    public function __construct(protected Config $config) {}

    public function resolve(Identity $identity): Authenticatable
    {
        $column = $this->column();

        $user = $this->query()->where($column, $identity->sub())->first();

        if ($user === null && $this->shouldLinkByEmail($identity)) {
            $user = $this->query()->where('email', $identity->email())->first();

            if ($user !== null) {
                $user->forceFill([$column => $identity->sub()])->save();

                UserLinked::dispatch($user, $identity);
            }
        }

        if ($user === null) {
            if (! $this->config->get('checkin.user.auto_create', true)) {
                throw new UserNotProvisionedException($identity);
            }

            $user = $this->create($identity);

            UserCreated::dispatch($user, $identity);

            return $user;
        }

        $this->sync($user, $identity);

        return $user;
    }

    /**
     * Create a local user from the identity. Override to set roles or extra columns.
     */
    protected function create(Identity $identity): Model
    {
        $user = $this->newModel();
        $columns = $this->columns($user);

        $attributes = array_merge($this->config->get('checkin.user.defaults', []), [
            'name' => $identity->name() ?? $identity->preferredUsername() ?? (string) $identity->email(),
            'email' => $identity->email(),
            $this->column() => $identity->sub(),
        ]);

        if (in_array('password', $columns, true)) {
            // Nobody knows this password: the person signs in through Checkin.
            $attributes['password'] = Hash::make(Str::random(64));
        }

        if (in_array('email_verified_at', $columns, true) && $identity->emailVerified()) {
            $attributes['email_verified_at'] = now();
        }

        $user->forceFill($attributes)->save();

        return $user;
    }

    /**
     * Refresh the attributes listed in checkin.user.sync when they changed.
     */
    protected function sync(Model $user, Identity $identity): void
    {
        $changes = [];

        foreach ((array) $this->config->get('checkin.user.sync', []) as $attribute) {
            $value = match ($attribute) {
                'name' => $identity->name(),
                'email' => $identity->emailVerified() ? $identity->email() : null,
                default => $identity->claim($attribute),
            };

            if ($value !== null && $user->getAttribute($attribute) !== $value) {
                $changes[$attribute] = $value;
            }
        }

        if ($changes !== []) {
            $user->forceFill($changes)->save();
        }
    }

    protected function shouldLinkByEmail(Identity $identity): bool
    {
        if (! $this->config->get('checkin.user.link_by_email', true) || $identity->email() === null) {
            return false;
        }

        return ! $this->config->get('checkin.user.require_verified_email', true) || $identity->emailVerified();
    }

    /**
     * @return Builder<Model>
     */
    protected function query()
    {
        return $this->newModel()->newQuery();
    }

    protected function newModel(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        if ($model = $this->config->get('checkin.user.model')) {
            return $model;
        }

        $guard = $this->config->get('checkin.guard') ?: $this->config->get('auth.defaults.guard');
        $provider = $this->config->get("auth.guards.{$guard}.provider");

        return $this->config->get("auth.providers.{$provider}.model") ?: 'App\\Models\\User';
    }

    public function column(): string
    {
        return $this->config->get('checkin.user.column', 'checkin_id');
    }

    /**
     * @return list<string>
     */
    protected function columns(Model $model): array
    {
        return Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
    }
}
