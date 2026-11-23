<?php

namespace EcoOnline\DBAuth\Auth;

use Aws\Credentials\CredentialProvider;
use Aws\Rds\AuthTokenGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class RDSTokenProvider
{
    /**
     * AWS configuration values
     *
     * @var array
     */
    protected $config;

    /**
     * @var AuthTokenGenerator
     */
    private $rds_auth_generator;

    /**
     * @var string
     */
    private $cacheKey;

    /**
     * Class constructor
     *
     * @param  array $config - AWS configuration
     * @param  AuthTokenGenerator|null $generator - Defaults to the SDK's, on the default credential chain
     */
    public function __construct(array $config, ?AuthTokenGenerator $generator = null)
    {
        $this->config = $config;
        $this->rds_auth_generator = $generator ?? new AuthTokenGenerator(CredentialProvider::defaultProvider());
        
        // Generate unique cache key per connection to avoid token conflicts
        $this->cacheKey = $this->generateCacheKey();
    }

    /**
     * Generate a unique cache key for this database connection
     *
     * @return string
     */
    private function generateCacheKey(): string
    {
        $host = Arr::get($this->config, 'host', 'unknown');
        $port = Arr::get($this->config, 'port', 'unknown');
        $username = Arr::get($this->config, 'username', 'unknown');
        $region = Arr::get($this->config, 'aws_region', 'unknown');
        
        return 'db_token_' . md5("{$host}:{$port}:{$username}:{$region}");
    }

    /**
     * Get the DBs Auth token from the AWS Auth Token Generator
     *
     * @param  bool $refetch - Force refetch of cached token
     * @return string - Auth token
     */
    public function getToken(bool $refetch = false): string
    {
        if ($refetch) {
            Cache::forget($this->cacheKey);
        }
        
        $cacheTtl = config('iam-db-auth.token_cache_ttl', 10);
        
        return Cache::remember($this->cacheKey, $cacheTtl, function () {
            return $this->createTokenInUtc();
        });
    }

    /**
     * IAM auth tokens are always on UTC, so the application's timezone is deliberately ignored here.
     * The SDK resolves its '+15 minutes' expiry with strtotime() in PHP's default timezone, which in a DST
     * zone gives a 75-minute expiry for tokens signed in the hour before the clocks go back (EPC-16321).
     *
     * @return string - Auth token
     */
    private function createTokenInUtc(): string
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            return $this->rds_auth_generator->createToken(
                Arr::get($this->config, 'host').':'.Arr::get($this->config, 'port'),
                Arr::get($this->config, 'aws_region'),
                Arr::get($this->config, 'username')
            );
        } finally {
            date_default_timezone_set($timezone);
        }
    }
}