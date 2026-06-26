<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\UnsupportedAssetException;

/**
 * Picks the right release asset out of a GitHub release's asset list.
 */
class ReleaseAssetSelector
{
    private const SUPPORTED_EXTENSIONS = ['.zip', '.tar.gz', '.tgz'];

    public static function isSupportedAssetName(string $name): bool
    {
        $name = strtolower($name);
        foreach (self::SUPPORTED_EXTENSIONS as $ext) {
            if (str_ends_with($name, $ext)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array{id:int,name:string,size:int,content_type:?string,browser_download_url:string}> $assets
     * @return array{id:int,name:string,size:int,content_type:?string,browser_download_url:string}
     */
    public static function selectAsset(array $assets, ?string $preferredNamePattern = null): array
    {
        $candidates = array_values(array_filter(
            $assets,
            fn (array $asset) => self::isSupportedAssetName($asset['name'] ?? '')
        ));

        if (empty($candidates)) {
            throw new UnsupportedAssetException('This release has no .zip or .tar.gz assets.');
        }

        if ($preferredNamePattern !== null && $preferredNamePattern !== '') {
            foreach ($candidates as $asset) {
                if (@preg_match($preferredNamePattern, $asset['name']) === 1) {
                    return $asset;
                }
            }

            throw new UnsupportedAssetException(
                "No asset name matched the configured pattern '{$preferredNamePattern}'."
            );
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        // Prefer .zip over .tar.gz/.tgz when multiple candidates exist and
        // no pattern was configured to disambiguate.
        usort($candidates, fn (array $a, array $b) => self::rank($a['name']) <=> self::rank($b['name']));

        return $candidates[0];
    }

    private static function rank(string $name): int
    {
        return str_ends_with(strtolower($name), '.zip') ? 0 : 1;
    }
}
