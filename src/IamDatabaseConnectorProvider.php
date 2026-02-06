<?php

namespace EcoOnline\DBAuth;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class IamDatabaseConnectorProvider extends ServiceProvider
{
    /**
     * Register the application services.
     * Swap out the default connector and bind our custom one.
     *
     * @return void
     */
    public function register(): void
    {
        $connections = Config::get('database.connections', []);
        
        foreach ($connections as $key => $connection) {
            if (!Arr::get($connection, 'use_iam_auth', false)) {
                continue;
            }

            $driver = Arr::get($connection, 'driver');
            
            match ($driver) {
                'mysql' => $this->registerMySqlConnector(),
                'pgsql' => $this->registerPostgresConnector($key),
                default => null,
            };
        }
    }

    /**
     * Register the MySQL IAM connector.
     *
     * @return void
     */
    protected function registerMySqlConnector(): void
    {
        $this->app->bind('db.connector.mysql', \EcoOnline\DBAuth\Database\MySqlConnector::class);
    }

    /**
     * Register the PostgreSQL IAM connector and configure SSL settings.
     *
     * @param  string  $connectionName
     * @return void
     */
    protected function registerPostgresConnector(string $connectionName): void
    {
        $this->configurePostgresSsl($connectionName);
        $this->app->bind('db.connector.pgsql', \EcoOnline\DBAuth\Database\PostgresConnector::class);
    }

    /**
     * Configure SSL settings for PostgreSQL connection.
     *
     * @param  string  $connectionName
     * @return void
     */
    protected function configurePostgresSsl(string $connectionName): void
    {
        $configKey = "database.connections.{$connectionName}";
        
        // Set SSL mode if not already configured
        $sslMode = Config::get("{$configKey}.sslmode", 'verify-full');
        Config::set("{$configKey}.sslmode", $sslMode);

        // Set SSL certificate path
        $defaultCertPath = realpath(base_path('vendor/ecoonline/laravel-iam-db-auth/certs/global-bundle.pem'));
        $certPath = Config::get("{$configKey}.sslrootcert", $defaultCertPath);
        
        // Windows requires escaped backslashes in the path
        if (PHP_OS_FAMILY === 'Windows') {
            $certPath = str_replace('\\', '\\\\\\\\', $certPath);
        }
        
        Config::set("{$configKey}.sslrootcert", "'{$certPath}'");
    }
}
