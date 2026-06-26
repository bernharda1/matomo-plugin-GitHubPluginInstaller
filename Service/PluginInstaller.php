<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\PluginValidationException;

/**
 * Orchestrates: download release asset -> extract to a staging dir ->
 * validate plugin.json -> move into plugins/<Name>, backing up any
 * existing installation so a failed/bad install can be rolled back.
 *
 * Deliberately does NOT activate the plugin (Piwik\Plugin\Manager::
 * activatePlugin) - by design, activation (i.e. executing the new code)
 * is a separate, conscious step taken by a super user via Matomo's
 * normal plugin management UI/console.
 */
class PluginInstaller
{
    private Downloader $downloader;
    private ArchiveExtractor $extractor;
    private PluginManifestValidator $validator;
    private string $pluginsDir;
    private string $tmpBaseDir;

    public function __construct(
        string $pluginsDir,
        string $tmpBaseDir,
        ?Downloader $downloader = null,
        ?ArchiveExtractor $extractor = null,
        ?PluginManifestValidator $validator = null
    ) {
        $this->pluginsDir = rtrim($pluginsDir, '/');
        $this->tmpBaseDir = rtrim($tmpBaseDir, '/');
        $this->downloader = $downloader ?? new Downloader();
        $this->extractor = $extractor ?? new ArchiveExtractor();
        $this->validator = $validator ?? new PluginManifestValidator();
    }

    /**
     * @param array{id:int,name:string,size:int} $asset
     * @return array{pluginName:string, version:string, installedPath:string, backedUpPath:?string}
     */
    public function install(string $owner, string $repo, array $asset, ?string $token): array
    {
        $jobDir = $this->tmpBaseDir . '/' . bin2hex(random_bytes(8));
        $downloadDir = $jobDir . '/download';
        $extractDir = $jobDir . '/extracted';

        try {
            $archivePath = $this->downloader->download($owner, $repo, $asset, $token, $downloadDir);

            mkdir($extractDir, 0700, true);
            $this->extractor->extract($archivePath, $extractDir);

            ['pluginRoot' => $pluginRoot, 'manifest' => $manifest] = $this->validator->locateAndValidate($extractDir);

            $pluginName = (string) $manifest['name'];
            $version = (string) $manifest['version'];
            $targetPath = $this->pluginsDir . '/' . $pluginName;

            $backedUpPath = null;
            if (is_dir($targetPath)) {
                $backedUpPath = $targetPath . '.bak-' . date('YmdHis');
                if (!rename($targetPath, $backedUpPath)) {
                    throw new PluginValidationException("Could not back up existing plugin directory at {$targetPath}.");
                }
            }

            if (!rename($pluginRoot, $targetPath)) {
                $this->rollback($targetPath, $backedUpPath);
                throw new PluginValidationException("Could not move extracted plugin into {$targetPath}.");
            }

            return [
                'pluginName' => $pluginName,
                'version' => $version,
                'installedPath' => $targetPath,
                'backedUpPath' => $backedUpPath,
            ];
        } finally {
            $this->removeDirectory($jobDir);
        }
    }

    private function rollback(string $targetPath, ?string $backedUpPath): void
    {
        if ($backedUpPath !== null && is_dir($backedUpPath)) {
            rename($backedUpPath, $targetPath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
