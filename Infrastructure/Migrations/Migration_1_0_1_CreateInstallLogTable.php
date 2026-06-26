<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * Creates plugin_githubplugininstaller_installs: an audit trail of every
 * install/update attempt (success or failure) so super users can see what
 * was installed, when, by whom, and from which release/asset.
 */
class Migration_1_0_1_CreateInstallLogTable extends Migration
{
    protected string $version = '1.0.1';
    protected string $description = 'Create plugin_githubplugininstaller_installs table';

    public function up(): void
    {
        $tableName = Common::prefixTable('plugin_githubplugininstaller_installs');

        if ($this->tableExists($tableName)) {
            $this->log("Table {$tableName} already exists, skipping creation");
            return;
        }

        $reposTable = Common::prefixTable('plugin_githubplugininstaller_repos');

        $sql = "
            CREATE TABLE {$tableName} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                repo_id INT UNSIGNED NOT NULL,
                tag_name VARCHAR(100) NOT NULL,
                asset_name VARCHAR(255) NOT NULL,
                installed_plugin_name VARCHAR(100) DEFAULT NULL,
                installed_version VARCHAR(50) DEFAULT NULL,
                status ENUM('success', 'failed', 'rolled_back') NOT NULL,
                error_message TEXT DEFAULT NULL,
                installed_by VARCHAR(100) NOT NULL,
                installed_at DATETIME NOT NULL,
                KEY idx_repo_id (repo_id),
                KEY idx_installed_at (installed_at),
                CONSTRAINT fk_githubplugininstaller_installs_repo
                    FOREIGN KEY (repo_id) REFERENCES {$reposTable}(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Audit log of GitHubPluginInstaller install/update attempts'
        ";

        Db::exec($sql);
        $this->log("Successfully created table {$tableName}");

        if (!$this->tableExists($tableName)) {
            throw new \Exception("Failed to create table {$tableName}");
        }
    }

    public function down(): void
    {
        $tableName = Common::prefixTable('plugin_githubplugininstaller_installs');

        if (!$this->tableExists($tableName)) {
            $this->log("Table {$tableName} does not exist, skipping drop");
            return;
        }

        Db::exec("DROP TABLE IF EXISTS {$tableName}");
        $this->log("Successfully dropped table {$tableName}");
    }
}
