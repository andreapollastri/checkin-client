<?php

namespace Checkin;

use Checkin\Console\InstallCommand;
use Checkin\Console\StatusCommand;
use Checkin\Contracts\ResolvesUsers;
use Checkin\Oidc\Provider;
use Checkin\View\Components\Button;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class CheckinServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/checkin.php', 'checkin');

        $this->app->singleton(Provider::class, function (Application $app): Provider {
            /** @var Config $config */
            $config = $app->make(Config::class);

            return new Provider(
                issuer: (string) $config->get('checkin.issuer'),
                clientId: (string) $config->get('checkin.client_id'),
                clientSecret: $config->get('checkin.client_secret') ?: null,
                scopes: (array) $config->get('checkin.scopes', ['openid', 'profile', 'email']),
                http: $app->make(Http::class),
                cache: $app->make(CacheFactory::class)->store($config->get('checkin.http.cache_store') ?: null),
                cacheTtl: (int) $config->get('checkin.http.cache_ttl', 3600),
                timeout: (int) $config->get('checkin.http.timeout', 10),
                leeway: (int) $config->get('checkin.http.leeway', 60),
            );
        });

        $this->app->bind(ResolvesUsers::class, function (Application $app): ResolvesUsers {
            $resolver = $app->make(Config::class)->get('checkin.user.resolver') ?: UserResolver::class;

            return $app->make($resolver);
        });

        $this->app->singleton(Checkin::class);
        $this->app->alias(Checkin::class, 'checkin');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'checkin');

        Blade::component('checkin-button', Button::class);

        if ($this->app['config']->get('checkin.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/checkin.php' => $this->app->configPath('checkin.php'),
            ], 'checkin-config');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/checkin'),
            ], 'checkin-views');

            $this->commands([
                InstallCommand::class,
                StatusCommand::class,
            ]);

            AboutCommand::add('Checkin', fn (): array => [
                'Enabled' => $this->app->make(Checkin::class)->isEnabled() ? 'Yes' : 'No',
                'Issuer' => (string) $this->app['config']->get('checkin.issuer'),
                'Client ID' => (string) ($this->app['config']->get('checkin.client_id') ?: '—'),
            ]);
        }
    }
}
