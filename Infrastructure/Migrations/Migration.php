<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations;

use Piwik\Db;
use Piwik\Common;

/**
 * Base migration class for GitHubPluginInstaller, mirroring the pattern
 * used by the other plugins in this codebase (e.g. GeoPrecision).
 */
abstract class Migration
{
    protected string $version;
    protected string $description;

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    abstract public function up(): void;

    public function down(): void
    {
        throw new \Exception("Rollback not implemented for migration {$this->version}");
    }

    protected function tableExists(string $tableName): bool
    {
        $tables = Db::fetchAll("SHOW TABLES LIKE '{$tableName}'");
        return !empty($tables);
    }

    protected function log(string $message): void
    {
        \Piwik\Log::info("[GitHubPluginInstaller Migration {$this->version}] {$message}");
    }
}
