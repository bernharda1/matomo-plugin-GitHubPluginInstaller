<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * Creates plugin_githubplugininstaller_repos: the list of GitHub repos a
 * super user has chosen to track for plugin installs/updates. token_encrypted
 * stores a per-repo PAT (see Service\TokenVault) and is null for public repos.
 */
class Migration_1_0_0_CreateTrackedReposTable extends Migration
{
    protected string $version = '1.0.0';
    protected string $description = 'Create plugin_githubplugininstaller_repos table';

    public function up(): void
    {
        $tableName = Common::prefixTable('plugin_githubplugininstaller_repos');

        if ($this->tableExists($tableName)) {
            $this->log("Table {$tableName} already exists, skipping creation");
            return;
        }

        $sql = "
            CREATE TABLE {$tableName} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                owner VARCHAR(100) NOT NULL,
                repo VARCHAR(100) NOT NULL,
                asset_pattern VARCHAR(255) DEFAULT NULL COMMENT 'optional regex to disambiguate release assets',
                token_encrypted TEXT DEFAULT NULL COMMENT 'per-repo GitHub PAT, encrypted via TokenVault',
                last_installed_tag VARCHAR(100) DEFAULT NULL,
                last_installed_plugin_name VARCHAR(100) DEFAULT NULL,
                created_by VARCHAR(100) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uniq_owner_repo (owner, repo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='GitHub repositories tracked for plugin install/update (GitHubPluginInstaller)'
        ";

        Db::exec($sql);
        $this->log("Successfully created table {$tableName}");

        if (!$this->tableExists($tableName)) {
            throw new \Exception("Failed to create table {$tableName}");
        }
    }

    public function down(): void
    {
        $tableName = Common::prefixTable('plugin_githubplugininstaller_repos');

        if (!$this->tableExists($tableName)) {
            $this->log("Table {$tableName} does not exist, skipping drop");
            return;
        }

        Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Successfully dropped table {$tableName}");
    }
}
