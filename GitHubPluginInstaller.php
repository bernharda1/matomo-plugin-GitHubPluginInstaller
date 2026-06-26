<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Log;
use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\GitHubPluginInstaller\Tasks\CheckForUpdatesTask;

class GitHubPluginInstaller extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'Menu.Admin.addItems' => 'addAdminMenuItem',
            'ScheduledTaskScheduler.scheduleTask' => 'scheduleUpdateCheckTask',
        ];
    }

    public function addAdminMenuItem(): void
    {
        if (!Piwik::hasUserSuperUserAccess()) {
            return;
        }

        try {
            MenuAdmin::getInstance()->addItem(
                'CoreAdminHome_MenuManage',
                'GitHubPluginInstaller_MenuTitle',
                ['module' => 'GitHubPluginInstaller', 'action' => 'index'],
                true,
                30
            );
        } catch (\Throwable $e) {
            // If the admin menu API differs across Matomo versions, fail
            // loudly in the log instead of silently disappearing.
            Log::warning('[GitHubPluginInstaller] Could not register admin menu item: ' . $e->getMessage());
        }
    }

    public function scheduleUpdateCheckTask(): void
    {
        new CheckForUpdatesTask();
    }
}
