<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

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

        MenuAdmin::getInstance()->addManageItem(
            'GitHubPluginInstaller_MenuTitle',
            ['module' => 'GitHubPluginInstaller', 'action' => 'index'],
            30
        );
    }

    public function scheduleUpdateCheckTask(): void
    {
        new CheckForUpdatesTask();
    }
}
