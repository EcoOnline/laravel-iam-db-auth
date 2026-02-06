<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable IAM Database Authentication
    |--------------------------------------------------------------------------
    |
    | When enabled, the package will use AWS IAM authentication to generate
    | temporary database passwords instead of using static credentials.
    | This is recommended for production environments using AWS RDS.
    |
    */

    'enabled' => env('DB_USE_IAM_AUTH', false),

    /*
    |--------------------------------------------------------------------------
    | AWS Region
    |--------------------------------------------------------------------------
    |
    | The AWS region where your RDS instance is located. This is used to
    | generate the authentication token. Falls back to AWS_DEFAULT_REGION
    | if AWS_REGION is not set.
    |
    */

    'aws_region' => env('AWS_REGION', env('AWS_DEFAULT_REGION', 'eu-west-1')),

    /*
    |--------------------------------------------------------------------------
    | SSL Certificate Path
    |--------------------------------------------------------------------------
    |
    | Path to the AWS RDS SSL certificate bundle. The package will check
    | multiple locations in this order:
    | 1. This configured path (if set)
    | 2. storage/app/global-bundle.pem
    | 3. Package bundled certificate (vendor/ecoonline/laravel-iam-db-auth/certs/global-bundle.pem)
    |
    */

    'ssl_cert_path' => env('DB_IAM_SSL_CERT_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | SSL Mode
    |--------------------------------------------------------------------------
    |
    | SSL mode for PostgreSQL connections when using IAM authentication.
    | Options: disable, allow, prefer, require, verify-ca, verify-full
    | Default: 'require' for IAM connections
    |
    */

    'ssl_mode' => env('DB_IAM_SSL_MODE', 'require'),

    /*
    |--------------------------------------------------------------------------
    | Token Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) to cache the IAM authentication token.
    | AWS RDS tokens are valid for 15 minutes, so we cache for 10 minutes
    | to ensure we refresh before expiration.
    |
    */

    'token_cache_ttl' => env('DB_IAM_TOKEN_CACHE_TTL', 10),

    /*
    |--------------------------------------------------------------------------
    | Connection-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | You can override settings per database connection by adding them
    | directly to your database connection config in config/database.php:
    |
    | 'pgsql' => [
    |     'driver' => 'pgsql',
    |     // ... other settings
    |     'use_iam_auth' => true,  // Override global enabled setting
    |     'aws_region' => 'us-east-1',  // Override global region
    |     'sslmode' => 'verify-full',  // Override global SSL mode
    |     'sslrootcert' => '/path/to/cert.pem',  // Override cert path
    | ],
    |
    */

];
