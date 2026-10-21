<?php

namespace Tests\Unit\Services\Filing;

use App\Services\FilingSystem\FilingStorageService;
use ReflectionProperty;
use Tests\TestCase;

class FilingStorageHttpTimeoutTest extends TestCase
{
    private ?string $originalEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        $env = getenv('FILING_HTTP_UPLOAD_TIMEOUT');
        $this->originalEnv = $env !== false ? $env : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv !== null) {
            putenv("FILING_HTTP_UPLOAD_TIMEOUT={$this->originalEnv}");
        } else {
            putenv('FILING_HTTP_UPLOAD_TIMEOUT');
        }
        parent::tearDown();
    }

    private function getTimeout(FilingStorageService $service): int
    {
        $property = new ReflectionProperty(FilingStorageService::class, 'httpUploadTimeout');

        return $property->getValue($service);
    }

    public function test_timeout_clamps_to_1800_when_config_is_1800(): void
    {
        config(['runitc.filing_http_upload_timeout' => 1800]);

        $service = new FilingStorageService([]);

        $this->assertSame(1800, $this->getTimeout($service));
    }

    public function test_timeout_clamps_to_1800_when_config_exceeds_ceiling(): void
    {
        config(['runitc.filing_http_upload_timeout' => 99999]);

        $service = new FilingStorageService([]);

        $this->assertSame(1800, $this->getTimeout($service));
    }

    public function test_timeout_retains_300_when_config_is_300(): void
    {
        config(['runitc.filing_http_upload_timeout' => 300]);

        $service = new FilingStorageService([]);

        $this->assertSame(300, $this->getTimeout($service));
    }

    public function test_timeout_falls_back_to_default_30_when_config_is_null(): void
    {
        config(['runitc.filing_http_upload_timeout' => null]);
        putenv('FILING_HTTP_UPLOAD_TIMEOUT');

        $service = new FilingStorageService([]);

        $this->assertSame(30, $this->getTimeout($service));
    }
}
