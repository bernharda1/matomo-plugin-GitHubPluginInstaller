<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\GitHubApiException;
use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;

/**
 * Minimal cURL wrapper used by GitHubClient/Downloader.
 *
 * Self-contained (no dependency on Piwik\Http) so it stays testable in
 * isolation and so every outbound request can be checked against an
 * explicit host allowlist before it is made - this plugin must never let
 * user-controlled input (repo names, asset names) cause a request to an
 * arbitrary host (SSRF).
 */
class HttpFetcher
{
    private const CONNECT_TIMEOUT = 10;
    private const TIMEOUT = 30;
    private const MAX_REDIRECTS = 5;

    /**
     * @param string[] $headers
     * @param string[] $allowedHosts exact hostnames or "*.suffix" wildcards
     */
    public function requestJson(string $url, array $headers, array $allowedHosts): array
    {
        $body = $this->request('GET', $url, $headers, $allowedHosts);

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new GitHubApiException('Expected a JSON response but could not decode one.');
        }

        return $decoded;
    }

    /**
     * Streams a response body to a local file, aborting once $maxBytes is
     * exceeded (defense against oversized / zip-bomb-style assets before
     * extraction even starts).
     *
     * @param string[] $headers
     * @param string[] $allowedHosts
     */
    public function downloadToFile(string $url, array $headers, array $allowedHosts, string $destPath, int $maxBytes): void
    {
        $fh = fopen($destPath, 'wb');
        if ($fh === false) {
            throw new GitHubApiException("Could not open destination file for writing: {$destPath}");
        }

        $written = 0;

        try {
            $this->request(
                'GET',
                $url,
                $headers,
                $allowedHosts,
                function (string $chunk) use ($fh, &$written, $maxBytes): int {
                    $written += strlen($chunk);
                    if ($written > $maxBytes) {
                        throw new SecurityException(sprintf(
                            'Download exceeded the configured maximum size of %d bytes.',
                            $maxBytes
                        ));
                    }

                    return fwrite($fh, $chunk) === false ? 0 : strlen($chunk);
                }
            );
        } finally {
            fclose($fh);
        }
    }

    /**
     * @param string[] $headers
     * @param string[] $allowedHosts
     * @param callable|null $writeCallback if set, response body is streamed
     *        to it instead of being buffered/returned
     */
    private function request(
        string $method,
        string $url,
        array $headers,
        array $allowedHosts,
        ?callable $writeCallback = null
    ): string {
        $hop = 0;
        $buffer = '';

        while (true) {
            $this->assertUrlAllowed($url, $allowedHosts);

            $ch = curl_init($url);
            if ($ch === false) {
                throw new GitHubApiException('Failed to initialise HTTP request.');
            }

            $responseHeaders = [];
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $this->withUserAgent($headers),
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADERFUNCTION => function ($curlHandle, string $line) use (&$responseHeaders): int {
                    $responseHeaders[] = $line;
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($curlHandle, string $chunk) use ($writeCallback, &$buffer): int {
                    if ($writeCallback !== null) {
                        return $writeCallback($chunk);
                    }
                    $buffer .= $chunk;
                    return strlen($chunk);
                },
            ]);

            $success = curl_exec($ch);
            $errNo = curl_errno($ch);
            $error = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($success === false || $errNo !== 0) {
                throw new GitHubApiException("HTTP request to {$url} failed: {$error}");
            }

            if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
                $location = $this->extractLocationHeader($responseHeaders);
                if ($location === null) {
                    throw new GitHubApiException("Received redirect status {$statusCode} without a Location header.");
                }

                if (++$hop > self::MAX_REDIRECTS) {
                    throw new SecurityException('Too many redirects while following GitHub response.');
                }

                $url = $location;
                $buffer = '';
                continue;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new GitHubApiException("GitHub returned unexpected HTTP status {$statusCode} for {$url}.");
            }

            return $buffer;
        }
    }

    private function withUserAgent(array $headers): array
    {
        foreach ($headers as $header) {
            if (stripos($header, 'User-Agent:') === 0) {
                return $headers;
            }
        }

        $headers[] = 'User-Agent: Matomo-GitHubPluginInstaller';
        return $headers;
    }

    /**
     * @param string[] $allowedHosts
     */
    private function assertUrlAllowed(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new SecurityException("Refusing to request malformed URL: {$url}");
        }

        if ($parts['scheme'] !== 'https') {
            throw new SecurityException("Refusing non-HTTPS URL: {$url}");
        }

        $host = strtolower($parts['host']);
        foreach ($allowedHosts as $allowed) {
            $allowed = strtolower($allowed);
            if ($allowed === $host) {
                return;
            }
            if (strpos($allowed, '*.') === 0 && str_ends_with($host, substr($allowed, 1))) {
                return;
            }
        }

        throw new SecurityException("Refusing to request disallowed host: {$host}");
    }

    /**
     * @param string[] $responseHeaderLines
     */
    private function extractLocationHeader(array $responseHeaderLines): ?string
    {
        foreach ($responseHeaderLines as $line) {
            if (stripos($line, 'Location:') === 0) {
                return trim(substr($line, strlen('Location:')));
            }
        }

        return null;
    }
}
