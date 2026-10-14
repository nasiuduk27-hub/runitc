<?php

namespace Tests\Unit\Services\Filing;

use App\Services\FilingSystem\FilingLinkService;
use PHPUnit\Framework\TestCase;

class FilingLinkServiceTest extends TestCase
{
    private FilingLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FilingLinkService;
    }

    public function test_token_is_43_char_base64url_and_unique(): void
    {
        $a = $this->service->generateToken();
        $b = $this->service->generateToken();

        $this->assertSame(43, strlen($a));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $a);
        $this->assertTrue($this->service->isTokenFormat($a));
        $this->assertNotSame($a, $b);
    }

    public function test_hash_matches_sha256_pattern_of_share_codes(): void
    {
        $token = $this->service->generateToken();
        $hash = $this->service->hashToken($token);

        $this->assertSame(hash('sha256', $token), $hash);
        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function test_preview_fits_share_code_preview_column(): void
    {
        $preview = $this->service->previewToken($this->service->generateToken());

        $this->assertStringStartsWith('LNK-****-', $preview);
        $this->assertLessThanOrEqual(20, strlen($preview));
    }

    public function test_rfs_codes_and_wrong_formats_are_rejected_by_format_gate(): void
    {
        $this->assertFalse($this->service->isTokenFormat('RFS-AB12-CD34'));
        $this->assertFalse($this->service->isTokenFormat(str_repeat('a', 42)));
        $this->assertFalse($this->service->isTokenFormat(str_repeat('a', 44)));
        $this->assertFalse($this->service->isTokenFormat(str_repeat('a', 43).'!'));
        $this->assertFalse($this->service->isTokenFormat(''));
    }

    public function test_mode_defaults_to_rights_when_missing_or_unknown(): void
    {
        $this->assertSame(FilingLinkService::MODE_RIGHTS, $this->service->modeOf([]));
        $this->assertSame(FilingLinkService::MODE_RIGHTS, $this->service->modeOf(['link_mode' => '1']));
        $this->assertSame(FilingLinkService::MODE_RIGHTS, $this->service->modeOf(['link_mode' => '9']));
        $this->assertSame(FilingLinkService::MODE_TOKEN, $this->service->modeOf(['link_mode' => '2']));
    }

    public function test_expiry_quota_revocation_and_download_flags(): void
    {
        $now = 1_700_000_000;

        $this->assertFalse($this->service->isExpired([], $now));
        $this->assertTrue($this->service->isExpired(['expired_at' => date('Y-m-d H:i:s', $now - 60)], $now));
        $this->assertFalse($this->service->isExpired(['expired_at' => date('Y-m-d H:i:s', $now + 60)], $now));

        $this->assertTrue($this->service->isQuotaReached(['max_access' => '3', 'access_count' => '3']));
        $this->assertFalse($this->service->isQuotaReached(['max_access' => '3', 'access_count' => '2']));
        $this->assertFalse($this->service->isQuotaReached(['max_access' => '0', 'access_count' => '999']));

        $this->assertTrue($this->service->isRevoked(['is_active' => '0']));
        $this->assertTrue($this->service->isRevoked(['is_active' => '1', 'revoked_at' => '2026-01-01 00:00:00']));
        $this->assertFalse($this->service->isRevoked(['is_active' => '1', 'revoked_at' => null]));

        $this->assertTrue($this->service->allowsDownload(['allow_download' => '1']));
        $this->assertFalse($this->service->allowsDownload(['allow_download' => '0']));
    }

    public function test_password_throttle_blocks_after_ten_failures(): void
    {
        $this->assertSame('link_pw_fail_abc123', $this->service->passwordAttemptsKey('abc123'));
        $this->assertFalse($this->service->isPasswordThrottled(9));
        $this->assertTrue($this->service->isPasswordThrottled(10));
        $this->assertTrue($this->service->isPasswordThrottled(11));
    }
}
