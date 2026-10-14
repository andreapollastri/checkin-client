<?php

namespace Checkin\Console;

use Checkin\UserResolver;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'checkin:install
        {--force : Overwrite the published configuration file}';

    protected $description = 'Publish the Checkin configuration and the users migration';

    public function handle(Filesystem $files, UserResolver $resolver): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'checkin-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $column = $resolver->column();
        $modelClass = $resolver->modelClass();
        $table = class_exists($modelClass) ? (new $modelClass)->getTable() : 'users';

        $this->publishMigration($files, $table, $column);

        $this->newLine();
        $this->components->info('Checkin client installed. Next steps:');
        $this->line('  1. Register this application on your Checkin server and copy its client id and secret.');
        $this->line('     Redirect URI: '.url(config('checkin.routes.prefix', 'auth/checkin').'/callback'));
        $this->line('  2. Add to .env: CHECKIN_URL, CHECKIN_CLIENT_ID, CHECKIN_CLIENT_SECRET');
        $this->line('  3. Run: php artisan migrate');
        $this->line('  4. Drop <x-checkin-button /> in your login view, or add CheckinPlugin::make() to your Filament panel.');
        $this->line('  5. Check everything with: php artisan checkin:status');

        return self::SUCCESS;
    }

    protected function publishMigration(Filesystem $files, string $table, string $column): void
    {
        $directory = $this->laravel->databasePath('migrations');
        $existing = collect($files->glob($directory.'/*_add_'.$column.'_to_'.$table.'_table.php'));

        if ($existing->isNotEmpty()) {
            $this->components->twoColumnDetail('Migration', 'already published ('.basename($existing->first()).')');

            return;
        }

        $target = $directory.'/'.date('Y_m_d_His').'_add_'.$column.'_to_'.$table.'_table.php';

        $contents = Str::of($files->get(__DIR__.'/../../database/migrations/add_checkin_id_to_users_table.php.stub'))
            ->replace('{{ table }}', $table)
            ->replace('{{ column }}', $column)
            ->toString();

        $files->ensureDirectoryExists($directory);
        $files->put($target, $contents);

        $this->components->twoColumnDetail('Migration', 'published ('.basename($target).')');
    }
}
