<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Tests;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\GitHubPluginInstaller\Exception\UnsupportedAssetException;
use Piwik\Plugins\GitHubPluginInstaller\Service\ReleaseAssetSelector;

/**
 * @group GitHubPluginInstaller
 */
class ReleaseAssetSelectorTest extends TestCase
{
    public function testIsSupportedAssetNameAcceptsZipAndTarGz(): void
    {
        $this->assertTrue(ReleaseAssetSelector::isSupportedAssetName('MyPlugin-1.0.0.zip'));
        $this->assertTrue(ReleaseAssetSelector::isSupportedAssetName('MyPlugin-1.0.0.tar.gz'));
        $this->assertTrue(ReleaseAssetSelector::isSupportedAssetName('MyPlugin-1.0.0.tgz'));
    }

    public function testIsSupportedAssetNameRejectsOtherExtensions(): void
    {
        $this->assertFalse(ReleaseAssetSelector::isSupportedAssetName('checksums.txt'));
        $this->assertFalse(ReleaseAssetSelector::isSupportedAssetName('MyPlugin.exe'));
        $this->assertFalse(ReleaseAssetSelector::isSupportedAssetName('MyPlugin.sh'));
    }

    public function testSelectAssetThrowsWhenNoArchiveAssets(): void
    {
        $this->expectException(UnsupportedAssetException::class);
        ReleaseAssetSelector::selectAsset([
            ['id' => 1, 'name' => 'checksums.txt', 'size' => 10, 'content_type' => 'text/plain', 'browser_download_url' => 'https://example.test/checksums.txt'],
        ]);
    }

    public function testSelectAssetPicksSingleCandidate(): void
    {
        $asset = ReleaseAssetSelector::selectAsset([
            ['id' => 1, 'name' => 'checksums.txt', 'size' => 10, 'content_type' => null, 'browser_download_url' => ''],
            ['id' => 2, 'name' => 'MyPlugin-1.0.0.zip', 'size' => 1000, 'content_type' => null, 'browser_download_url' => ''],
        ]);

        $this->assertSame('MyPlugin-1.0.0.zip', $asset['name']);
    }

    public function testSelectAssetPrefersZipOverTarGzWithoutPattern(): void
    {
        $asset = ReleaseAssetSelector::selectAsset([
            ['id' => 1, 'name' => 'MyPlugin-1.0.0.tar.gz', 'size' => 1000, 'content_type' => null, 'browser_download_url' => ''],
            ['id' => 2, 'name' => 'MyPlugin-1.0.0.zip', 'size' => 1000, 'content_type' => null, 'browser_download_url' => ''],
        ]);

        $this->assertSame('MyPlugin-1.0.0.zip', $asset['name']);
    }

    public function testSelectAssetUsesPreferredNamePattern(): void
    {
        $asset = ReleaseAssetSelector::selectAsset(
            [
                ['id' => 1, 'name' => 'MyPlugin-linux.tar.gz', 'size' => 1000, 'content_type' => null, 'browser_download_url' => ''],
                ['id' => 2, 'name' => 'MyPlugin-source.zip', 'size' => 1000, 'content_type' => null, 'browser_download_url' => ''],
            ],
            '/^MyPlugin-linux\\.tar\\.gz$/'
        );

        $this->assertSame('MyPlugin-linux.tar.gz', $asset['name']);
    }

    public function testSelectAssetThrowsWhenPatternMatchesNothing(): void
    {
        $this->expectException(UnsupportedAssetException::class);
        ReleaseAssetSelector::selectAsset(
            [['id' => 1, 'name' => 'MyPlugin.zip', 'size' => 1000, 'content_type' => null, 'browser_download_url' => '']],
            '/^DoesNotMatch\\.zip$/'
        );
    }
}
