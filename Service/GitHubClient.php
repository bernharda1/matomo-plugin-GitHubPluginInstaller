<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\GitHubApiException;
use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;

/**
 * Thin client around the parts of the GitHub REST API needed to list
 * releases and download release assets, for both public and private
 * repositories.
 */
class GitHubClient
{
    private const API_HOST = 'api.github.com';
    private const API_BASE = 'https://api.github.com';

    /**
     * Only the GitHub API host is ever requested directly by this client.
     * Asset downloads to githubusercontent.com/S3 redirect targets are
     * handled separately by Downloader, with its own allowlist.
     */
    private const ALLOWED_HOSTS = [self::API_HOST];

    private const OWNER_REPO_PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9._-]{0,98}[A-Za-z0-9])?$/';

    private HttpFetcher $http;

    public function __construct(?HttpFetcher $http = null)
    {
        $this->http = $http ?? new HttpFetcher();
    }

    /**
     * @return array<int, array{tag_name:string, name:?string, published_at:?string, prerelease:bool, assets:array<int, array{id:int, name:string, size:int, content_type:?string, browser_download_url:string}>}>
     */
    public function listReleases(string $owner, string $repo, ?string $token = null, int $perPage = 10): array
    {
        $this->assertValidIdentifier($owner, 'owner');
        $this->assertValidIdentifier($repo, 'repo');

        $perPage = max(1, min(100, $perPage));
        $url = self::API_BASE . '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
            . '/releases?per_page=' . $perPage;

        $headers = $this->buildHeaders($token, 'application/vnd.github+json');

        $releases = $this->http->requestJson($url, $headers, self::ALLOWED_HOSTS);

        return array_map(
            fn (array $release) => [
                'tag_name' => (string) ($release['tag_name'] ?? ''),
                'name' => $release['name'] ?? null,
                'published_at' => $release['published_at'] ?? null,
                'prerelease' => (bool) ($release['prerelease'] ?? false),
                'assets' => array_map(
                    fn (array $asset) => [
                        'id' => (int) ($asset['id'] ?? 0),
                        'name' => (string) ($asset['name'] ?? ''),
                        'size' => (int) ($asset['size'] ?? 0),
                        'content_type' => $asset['content_type'] ?? null,
                        'browser_download_url' => (string) ($asset['browser_download_url'] ?? ''),
                    ],
                    $release['assets'] ?? []
                ),
            ],
            $releases
        );
    }

    /**
     * Downloads a release asset by id via the GitHub API asset endpoint.
     * This path works for both public and private repositories: for
     * private repos the Authorization header is required and GitHub
     * streams the binary directly; for public repos GitHub may redirect
     * to its CDN, which Downloader follows under its own host allowlist.
     */
    public function downloadAsset(
        string $owner,
        string $repo,
        int $assetId,
        ?string $token,
        string $destinationPath,
        int $maxBytes
    ): void {
        $this->assertValidIdentifier($owner, 'owner');
        $this->assertValidIdentifier($repo, 'repo');

        if ($assetId <= 0) {
            throw new GitHubApiException('Invalid asset id.');
        }

        $url = self::API_BASE . '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
            . '/releases/assets/' . $assetId;

        $headers = $this->buildHeaders($token, 'application/octet-stream');

        $this->http->downloadToFile($url, $headers, Downloader::ALLOWED_DOWNLOAD_HOSTS, $destinationPath, $maxBytes);
    }

    private function buildHeaders(?string $token, string $accept): array
    {
        $headers = [
            'Accept: ' . $accept,
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }

    private function assertValidIdentifier(string $value, string $field): void
    {
        if (!preg_match(self::OWNER_REPO_PATTERN, $value)) {
            throw new SecurityException("Invalid GitHub {$field}: '{$value}'");
        }
    }
}
