<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Plugin;
use Piwik\Plugins\GitHubPluginInstaller\Tasks\CheckForUpdatesTask;

class GitHubPluginInstaller extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'ScheduledTaskScheduler.scheduleTask' => 'scheduleUpdateCheckTask',
        ];
    }

    public function scheduleUpdateCheckTask(): void
    {
        new CheckForUpdatesTask();
    }
}
