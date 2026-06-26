<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Plugin;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations\Migration_1_0_0_CreateTrackedReposTable;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations\Migration_1_0_1_CreateInstallLogTable;
use Piwik\Plugins\GitHubPluginInstaller\Tasks\CheckForUpdatesTask;

class GitHubPluginInstaller extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'ScheduledTaskScheduler.scheduleTask' => 'scheduleUpdateCheckTask',
        ];
    }

    /**
     * Called by Matomo when the plugin is activated for the first time.
     * Creates the plugin's tables; each migration is idempotent (checks
     * tableExists() before creating), so re-running this is harmless.
     */
    public function install(): void
    {
        foreach ($this->migrations() as $migration) {
            $migration->up();
        }
    }

    /**
     * Called by Matomo when the plugin is uninstalled (not merely
     * deactivated) via the Plugins admin page or console plugin:uninstall.
     */
    public function uninstall(): void
    {
        foreach (array_reverse($this->migrations()) as $migration) {
            $migration->down();
        }
    }

    public function scheduleUpdateCheckTask(): void
    {
        new CheckForUpdatesTask();
    }

    /**
     * @return array<int, \Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations\Migration>
     */
    private function migrations(): array
    {
        return [
            new Migration_1_0_0_CreateTrackedReposTable(),
            new Migration_1_0_1_CreateInstallLogTable(),
        ];
    }
}
