<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Exception;

/**
 * Thrown when a release asset is not a supported .zip/.tar.gz archive.
 */
class UnsupportedAssetException extends \RuntimeException
{
}
