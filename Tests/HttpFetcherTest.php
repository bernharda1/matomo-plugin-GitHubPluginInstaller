<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Tests;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;
use Piwik\Plugins\GitHubPluginInstaller\Service\HttpFetcher;

/**
 * @group GitHubPluginInstaller
 * @group Security
 *
 * Verifies the host allowlist check rejects disallowed/malformed URLs
 * before any network I/O is attempted (SSRF protection).
 */
class HttpFetcherTest extends TestCase
{
    public function testRejectsHostNotOnAllowlist(): void
    {
        $this->expectException(SecurityException::class);
        (new HttpFetcher())->requestJson('https://evil.example.com/repos', [], ['api.github.com']);
    }

    public function testRejectsNonHttpsScheme(): void
    {
        $this->expectException(SecurityException::class);
        (new HttpFetcher())->requestJson('http://api.github.com/repos', [], ['api.github.com']);
    }

    public function testRejectsAttemptToUseAllowedHostAsPathTrick(): void
    {
        // A malicious owner/repo value cannot smuggle a different host via
        // the path, since the host allowlist check parses the URL itself.
        $this->expectException(SecurityException::class);
        (new HttpFetcher())->requestJson(
            'https://evil.example.com/api.github.com/repos',
            [],
            ['api.github.com']
        );
    }

    public function testWildcardSubdomainIsAccepted(): void
    {
        $caught = null;
        try {
            (new HttpFetcher())->requestJson('https://bucket.s3.amazonaws.com/asset', [], ['*.s3.amazonaws.com']);
        } catch (SecurityException $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            // Allowed past the host check; it fails later on the actual
            // network call in this offline test environment, which is fine.
        }

        $this->assertNull($caught, 'Wildcard-allowed host must not be rejected by the allowlist check.');
    }
}
