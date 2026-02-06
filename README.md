## laravel-iam-db-auth

This is an EcoOnline fork of the AWS RDS IAM authentication package for Laravel, with enhanced features and cleaner configuration.

## Features

- ✅ AWS RDS IAM authentication for MySQL and PostgreSQL
- ✅ Automatic token generation and caching
- ✅ Smart SSL certificate detection
- ✅ Clean, discoverable configuration
- ✅ Per-connection overrides
- ✅ Automatic token refresh on failures
- ✅ Windows compatibility

## Installation

Require this package with composer:

```shell
composer require ecoonline/laravel-iam-db-auth
```

Publish the configuration file (optional):

```shell
php artisan vendor:publish --tag=config --provider="EcoOnline\DBAuth\IamDatabaseConnectorProvider"
```

## Quick Start

### 1. Enable in Environment

Add to your `.env` file:

```bash
DB_USE_IAM_AUTH=true
AWS_REGION=eu-west-1
```

### 2. Configure Database Connection

Your `config/database.php` stays simple:

```php
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => 'prefer',
],
```

That's it! The package handles all IAM configuration automatically.

## Configuration

All settings are in `config/iam-db-auth.php`:

```php
return [
    'enabled' => env('DB_USE_IAM_AUTH', false),
    'aws_region' => env('AWS_REGION', env('AWS_DEFAULT_REGION', 'eu-west-1')),
    'ssl_cert_path' => env('DB_IAM_SSL_CERT_PATH', null),
    'ssl_mode' => env('DB_IAM_SSL_MODE', 'require'),
    'token_cache_ttl' => env('DB_IAM_TOKEN_CACHE_TTL', 10),
];
```

### Available Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_USE_IAM_AUTH` | `false` | Enable IAM authentication |
| `AWS_REGION` | `eu-west-1` | AWS region for RDS instance |
| `DB_IAM_SSL_CERT_PATH` | `null` | Custom SSL certificate path |
| `DB_IAM_SSL_MODE` | `require` | PostgreSQL SSL mode |
| `DB_IAM_TOKEN_CACHE_TTL` | `10` | Token cache duration (minutes) |

### Per-Connection Overrides

Override settings for specific connections in `config/database.php`:

```php
'pgsql' => [
    'driver' => 'pgsql',
    // ... standard config
    'use_iam_auth' => true,        // Override global setting
    'aws_region' => 'us-east-1',   // Override region
    'sslmode' => 'verify-full',    // Override SSL mode
],
```

## How It Works

When `DB_USE_IAM_AUTH=true`:

1. Package detects IAM requirement from config
2. Automatically configures AWS region and SSL settings
3. Generates temporary authentication tokens (valid 15 minutes)
4. Caches tokens for 10 minutes to reduce API calls
5. Automatically refreshes tokens on connection failures

## SSL Certificates

The package automatically finds SSL certificates in this order:

1. Connection-specific path (if configured)
2. Package config path (`DB_IAM_SSL_CERT_PATH`)
3. Storage path (`storage/app/global-bundle.pem`)
4. Package bundled certificate (fallback)

The package includes AWS RDS certificates at:
- `certs/global-bundle.pem` (recommended)
- `certs/rds-ca-2019-root.pem`
- `certs/rds-combined-ca-bundle.pem`

## Improvements Over Original

This EcoOnline fork includes:

1. **Config file** - All settings discoverable in `config/iam-db-auth.php`
2. **Auto-configuration** - Reads from environment, no ugly `array_merge` needed
3. **Smart cert detection** - Automatically finds certificates in multiple locations
4. **Better caching** - Unique cache keys per connection to avoid conflicts
5. **Enhanced error handling** - Automatic token refresh on auth failures
6. **Configurable cache TTL** - Adjust token cache duration via config
7. **Better logging** - Detailed error messages for troubleshooting
8. **Fixed activation bug** - Original pixelvide package would activate even when `use_iam_auth=false`, breaking local development by using wrong environment variable

### Critical Bug Fix

The original pixelvide package had a bug where if the `use_iam_auth` key existed in the database config (even if set to `false`), the package would activate and incorrectly use the `USERNAME` environment variable instead of `DB_USERNAME`. In Docker environments, this would use `"www"` as the database username, breaking local development.

**Our fix:** The package now properly checks the boolean value of `use_iam_auth` and only activates when explicitly `true`.

## AWS Setup

### IAM Policy

Your application's IAM role needs:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "rds-db:connect",
      "Resource": "arn:aws:rds-db:REGION:ACCOUNT:dbuser:RESOURCE_ID/DB_USERNAME"
    }
  ]
}
```

### Database User

Create IAM-enabled database user:

```sql
-- PostgreSQL
CREATE USER iam_user;
GRANT rds_iam TO iam_user;
GRANT ALL PRIVILEGES ON DATABASE your_database TO iam_user;

-- MySQL
CREATE USER iam_user IDENTIFIED WITH AWSAuthenticationPlugin AS 'RDS';
GRANT ALL PRIVILEGES ON your_database.* TO iam_user;
```

## License

GPL-3.0-or-later

