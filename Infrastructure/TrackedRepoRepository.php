<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Infrastructure;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\GitHubPluginInstaller\Service\TokenVault;

/**
 * Persistence for tracked GitHub repositories. Tokens are always
 * encrypted via TokenVault before being written and decrypted only when
 * explicitly requested (callers needing to make an authenticated API call).
 */
final class TrackedRepoRepository
{
    private function table(): string
    {
        return Common::prefixTable('plugin_githubplugininstaller_repos');
    }

    public function add(string $owner, string $repo, ?string $token, ?string $assetPattern, string $createdBy): int
    {
        Db::query(
            sprintf(
                'INSERT INTO %s (owner, repo, asset_pattern, token_encrypted, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                $this->table()
            ),
            [
                $owner,
                $repo,
                $assetPattern,
                $token !== null && $token !== '' ? TokenVault::encrypt($token) : null,
                $createdBy,
            ]
        );

        return (int) Db::fetchOne('SELECT LAST_INSERT_ID()');
    }

    public function remove(int $id): void
    {
        Db::query(sprintf('DELETE FROM %s WHERE id = ?', $this->table()), [$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        return Db::fetchAll(sprintf('SELECT * FROM %s ORDER BY owner, repo', $this->table()));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = Db::fetchRow(sprintf('SELECT * FROM %s WHERE id = ?', $this->table()), [$id]);
        return $row ?: null;
    }

    public function getDecryptedToken(array $repoRow): ?string
    {
        if (empty($repoRow['token_encrypted'])) {
            return null;
        }

        return TokenVault::decrypt((string) $repoRow['token_encrypted']);
    }

    public function recordInstalledVersion(int $id, string $tagName, string $pluginName): void
    {
        Db::query(
            sprintf(
                'UPDATE %s SET last_installed_tag = ?, last_installed_plugin_name = ?, updated_at = NOW() WHERE id = ?',
                $this->table()
            ),
            [$tagName, $pluginName, $id]
        );
    }
}
