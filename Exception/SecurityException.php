<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Exception;

/**
 * Thrown when a security invariant is violated (e.g. zip-slip attempt,
 * disallowed outbound URL, token tampering).
 */
class SecurityException extends \RuntimeException
{
}
