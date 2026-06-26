<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Infrastructure;

use Piwik\Common;
use Piwik\Db;

final class InstallLogRepository
{
    private function table(): string
    {
        return Common::prefixTable('plugin_githubplugininstaller_installs');
    }

    public function record(
        int $repoId,
        string $tagName,
        string $assetName,
        ?string $pluginName,
        ?string $version,
        string $status,
        ?string $errorMessage,
        string $installedBy
    ): void {
        Db::query(
            sprintf(
                'INSERT INTO %s
                    (repo_id, tag_name, asset_name, installed_plugin_name, installed_version,
                     status, error_message, installed_by, installed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                $this->table()
            ),
            [$repoId, $tagName, $assetName, $pluginName, $version, $status, $errorMessage, $installedBy]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findForRepo(int $repoId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return Db::fetchAll(
            sprintf(
                'SELECT * FROM %s WHERE repo_id = ? ORDER BY installed_at DESC LIMIT %d',
                $this->table(),
                $limit
            ),
            [$repoId]
        );
    }
}
