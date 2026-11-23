<?php

namespace EcoOnline\DBAuth\Tests;

use Aws\Rds\AuthTokenGenerator;
use EcoOnline\DBAuth\Auth\RDSTokenProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

class RDSTokenProviderTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        // A DST timezone is what exposed EPC-16321; Platform runs on Europe/Oslo.
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Oslo');

        $app = new Container();
        $app->instance('cache', new Repository(new ArrayStore()));
        $app->instance('config', new Config(['iam-db-auth' => ['token_cache_ttl' => 10]]));
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        date_default_timezone_set($this->originalTimezone);
    }

    public function testTokenIsGeneratedInUtcWhateverTheAppTimezone(): void
    {
        $generator = $this->recordingGenerator();

        $this->providerUsing($generator)->getToken();

        $this->assertSame('UTC', $generator->timezoneSeen);
    }

    public function testAppTimezoneIsRestoredAfterwards(): void
    {
        $this->providerUsing($this->recordingGenerator())->getToken();

        $this->assertSame('Europe/Oslo', date_default_timezone_get());
    }

    public function testAppTimezoneIsRestoredWhenSigningFails(): void
    {
        $provider = $this->providerUsing($this->recordingGenerator(new RuntimeException('no credentials')));

        try {
            $provider->getToken();
            $this->fail('Expected the generator failure to propagate');
        } catch (RuntimeException) {
            $this->assertSame('Europe/Oslo', date_default_timezone_get());
        }
    }

    private function providerUsing(AuthTokenGenerator $generator): RDSTokenProvider
    {
        return new RDSTokenProvider(
            ['host' => 'db.example', 'port' => 5432, 'username' => 'eco_platform-admin', 'aws_region' => 'eu-west-1'],
            $generator
        );
    }

    private function recordingGenerator(?Throwable $failure = null): AuthTokenGenerator
    {
        return new class($failure) extends AuthTokenGenerator {
            public ?string $timezoneSeen = null;

            public function __construct(private ?Throwable $failure)
            {
            }

            public function createToken($endpoint, $region, $username, $lifetime = 15)
            {
                $this->timezoneSeen = date_default_timezone_get();

                return $this->failure ? throw $this->failure : 'token';
            }
        };
    }
}
