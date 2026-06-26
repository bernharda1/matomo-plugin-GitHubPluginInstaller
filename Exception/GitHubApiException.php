<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Exception;

/**
 * Thrown for any failure talking to the GitHub REST API (network error,
 * unexpected status code, malformed response).
 */
class GitHubApiException extends \RuntimeException
{
}
