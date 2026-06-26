<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;
use Piwik\Plugins\GitHubPluginInstaller\Exception\UnsupportedAssetException;

/**
 * Downloads a single, already-selected release asset to a local temp file.
 */
class Downloader
{
    /**
     * GitHub's API redirects public asset downloads to one of these CDN
     * hosts. Private-repo assets are streamed directly from api.github.com
     * and never hit this allowlist.
     */
    public const ALLOWED_DOWNLOAD_HOSTS = [
        'api.github.com',
        'objects.githubusercontent.com',
        'github-releases.githubusercontent.com',
        '*.s3.amazonaws.com',
    ];

    public const DEFAULT_MAX_BYTES = 100 * 1024 * 1024; // 100 MB

    private GitHubClient $client;

    public function __construct(?GitHubClient $client = null)
    {
        $this->client = $client ?? new GitHubClient();
    }

    /**
     * @param array{id:int, name:string, size:int} $asset
     */
    public function download(
        string $owner,
        string $repo,
        array $asset,
        ?string $token,
        string $tmpDir,
        int $maxBytes = self::DEFAULT_MAX_BYTES
    ): string {
        if (!ReleaseAssetSelector::isSupportedAssetName($asset['name'])) {
            throw new UnsupportedAssetException("Asset '{$asset['name']}' is not a supported .zip/.tar.gz archive.");
        }

        if (($asset['size'] ?? 0) > $maxBytes) {
            throw new SecurityException(sprintf(
                "Asset '%s' (%d bytes) exceeds the configured maximum download size of %d bytes.",
                $asset['name'],
                $asset['size'],
                $maxBytes
            ));
        }

        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException("Could not create temp directory: {$tmpDir}");
        }

        $destination = rtrim($tmpDir, '/') . '/' . basename($asset['name']);

        $this->client->downloadAsset($owner, $repo, (int) $asset['id'], $token, $destination, $maxBytes);

        return $destination;
    }
}
