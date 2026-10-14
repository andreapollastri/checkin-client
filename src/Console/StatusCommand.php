<?php

namespace Checkin\Console;

use Checkin\Checkin;
use Checkin\Exceptions\CheckinException;
use Checkin\UserResolver;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Schema;

class StatusCommand extends Command
{
    protected $signature = 'checkin:status';

    protected $description = 'Check the Checkin configuration and reach the server';

    public function handle(Checkin $checkin, UserResolver $resolver): int
    {
        $ok = true;

        $this->components->twoColumnDetail('Enabled', $checkin->isEnabled() ? '<fg=green>yes</>' : '<fg=yellow>no</>');
        $this->components->twoColumnDetail('Issuer', config('checkin.issuer'));
        $this->components->twoColumnDetail('Client ID', config('checkin.client_id') ?: '<fg=red>missing</>');
        $this->components->twoColumnDetail('Client type', config('checkin.client_secret') ? 'confidential (secret set)' : 'public (PKCE only)');
        $this->components->twoColumnDetail('Scopes', implode(' ', $checkin->provider()->scopes()));
        $this->components->twoColumnDetail('Callback URL', $checkin->callbackUrl());
        $this->components->twoColumnDetail('Guard', config('checkin.guard') ?: config('auth.defaults.guard').' (default)');

        $model = $resolver->modelClass();
        $this->components->twoColumnDetail('User model', $model);

        if (class_exists($model)) {
            $table = (new $model)->getTable();

            try {
                $hasColumn = Schema::hasColumn($table, $resolver->column());
                $this->components->twoColumnDetail(
                    'Column '.$table.'.'.$resolver->column(),
                    $hasColumn ? '<fg=green>present</>' : '<fg=red>missing — run checkin:install and migrate</>',
                );
                $ok = $ok && $hasColumn;
            } catch (QueryException $e) {
                $this->components->twoColumnDetail('Column '.$table.'.'.$resolver->column(), '<fg=yellow>unknown — database unavailable</>');
            }
        } else {
            $this->components->twoColumnDetail('User model', '<fg=red>class not found</>');
            $ok = false;
        }

        if (! $checkin->isEnabled()) {
            $this->components->warn('Set CHECKIN_CLIENT_ID (and CHECKIN_CLIENT_SECRET) to enable the sign-in.');

            return self::FAILURE;
        }

        try {
            $discovery = $checkin->provider()->discovery(fresh: true);
            $this->components->twoColumnDetail('Discovery', '<fg=green>ok</> '.$discovery->issuer());
            $this->components->twoColumnDetail('Authorization endpoint', $discovery->authorizationEndpoint());
            $this->components->twoColumnDetail('Token endpoint', $discovery->tokenEndpoint());
            $this->components->twoColumnDetail('UserInfo endpoint', $discovery->userInfoEndpoint() ?? '—');
            $this->components->twoColumnDetail('End session endpoint', $discovery->endSessionEndpoint() ?? '—');

            $jwks = $checkin->provider()->jwks(fresh: true);
            $this->components->twoColumnDetail('Signing keys', '<fg=green>'.count($jwks['keys']).' key(s)</>');
        } catch (CheckinException|ConnectionException $e) {
            $this->components->twoColumnDetail('Server', '<fg=red>'.$e->getMessage().'</>');
            $ok = false;
        }

        $this->newLine();
        $ok
            ? $this->components->info('Checkin is ready.')
            : $this->components->error('Checkin is not ready yet; see the lines above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
