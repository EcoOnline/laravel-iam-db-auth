<?php

namespace EcoOnline\DBAuth\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use EcoOnline\DBAuth\Auth\RDSTokenProvider;

class GetTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:gettoken
                            {--connection= : The database connection name (defaults to DB_CONNECTION)}
                            {--force : Force a fresh token, bypassing the cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get an IAM authentication token for the database connection';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $connectionName = $this->option('connection')
            ?? config('database.default');

        $config = Config::get("database.connections.{$connectionName}");

        if (!$config) {
            $this->error("Database connection [{$connectionName}] not found.");
            return self::FAILURE;
        }

        if (!Arr::get($config, 'use_iam_auth', false)) {
            $this->error("IAM auth is not enabled for connection [{$connectionName}].");
            return self::FAILURE;
        }

        $tokenProvider = new RDSTokenProvider($config);
        $token = $tokenProvider->getToken((bool) $this->option('force'));

        // Output raw token only — no newline decoration — so it works in shell substitution:
        // export PGPASSWORD=$(php artisan db:gettoken)
        $this->output->write($token);

        return self::SUCCESS;
    }
}
