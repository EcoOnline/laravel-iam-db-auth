<?php

namespace EcoOnline\DBAuth;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use EcoOnline\DBAuth\Console\GetTokenCommand;

class IamDatabaseConnectorProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/iam-db-auth.php' => config_path('iam-db-auth.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GetTokenCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     * Swap out the default connector and bind our custom one.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/iam-db-auth.php', 'iam-db-auth'
        );

        $connections = Config::get('database.connections', []);
        
        foreach ($connections as $key => $connection) {
            // Auto-configure IAM auth from environment variable if not explicitly set
            $useIamAuth = $this->shouldUseIamAuth($connection);
            
            if (!$useIamAuth) {
                continue;
            }

            // Set IAM auth configuration
            $this->configureIamAuth($key, $connection);

            $driver = Arr::get($connection, 'driver');
            
            match ($driver) {
                'mysql' => $this->registerMySqlConnector(),
                'pgsql' => $this->registerPostgresConnector($key),
                default => null,
            };
        }
    }

    /**
     * Determine if IAM auth should be used for this connection.
     *
     * @param  array  $connection
     * @return bool
     */
    protected function shouldUseIamAuth(array $connection): bool
    {
        // If explicitly set in connection config, use that value
        if (Arr::has($connection, 'use_iam_auth')) {
            return (bool) Arr::get($connection, 'use_iam_auth');
        }

        // Otherwise, check package config (which reads from DB_USE_IAM_AUTH env)
        return (bool) config('iam-db-auth.enabled', false);
    }

    /**
     * Configure IAM authentication settings for the connection.
     *
     * @param  string  $connectionName
     * @param  array  $connection
     * @return void
     */
    protected function configureIamAuth(string $connectionName, array $connection): void
    {
        $configKey = "database.connections.{$connectionName}";
        
        // Set use_iam_auth flag
        Config::set("{$configKey}.use_iam_auth", true);
        
        // Set AWS region if not already configured
        if (!Arr::has($connection, 'aws_region')) {
            $region = config('iam-db-auth.aws_region', 'eu-west-1');
            Config::set("{$configKey}.aws_region", $region);
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
        $connection = Config::get($configKey);
        
        // Set SSL mode - use connection config, package config, or default
        $sslMode = Arr::get($connection, 'sslmode') 
            ?? config('iam-db-auth.ssl_mode', 'require');
        Config::set("{$configKey}.sslmode", $sslMode);

        // Determine SSL certificate path
        $certPath = $this->resolveCertificatePath($connection);
        
        // Windows requires escaped backslashes in the path
        if (PHP_OS_FAMILY === 'Windows') {
            $certPath = str_replace('\\', '\\\\\\\\', $certPath);
        }
        
        Config::set("{$configKey}.sslrootcert", "'{$certPath}'");
    }

    /**
     * Resolve the SSL certificate path from multiple possible locations.
     *
     * @param  array  $connection
     * @return string
     */
    protected function resolveCertificatePath(array $connection): string
    {
        // Priority 1: Connection-specific config
        $certPath = Arr::get($connection, 'sslrootcert');
        if ($certPath && file_exists($certPath)) {
            return $certPath;
        }

        // Priority 2: Package config
        $certPath = config('iam-db-auth.ssl_cert_path');
        if ($certPath && file_exists($certPath)) {
            return $certPath;
        }

        // Priority 3: Storage path
        $storagePath = storage_path('app/global-bundle.pem');
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        // Priority 4: Package bundled certificate (fallback)
        return realpath(base_path('vendor/ecoonline/laravel-iam-db-auth/certs/global-bundle.pem'));
    }
}
