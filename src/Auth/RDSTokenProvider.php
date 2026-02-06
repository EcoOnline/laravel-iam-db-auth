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
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $provider = CredentialProvider::defaultProvider();
        $this->rds_auth_generator = new AuthTokenGenerator($provider);
        
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
            return $this->rds_auth_generator->createToken(
                Arr::get($this->config, 'host').':'.Arr::get($this->config, 'port'),
                Arr::get($this->config, 'aws_region'),
                Arr::get($this->config, 'username')
            );
        });
    }
}