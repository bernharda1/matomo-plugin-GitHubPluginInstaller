<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Exception;

/**
 * Thrown when a downloaded archive does not look like a valid Matomo plugin
 * (missing plugin.json, name mismatch, unsupported Matomo version, ...).
 */
class PluginValidationException extends \RuntimeException
{
}
