<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Tasks;

use Piwik\Log;
use Piwik\Scheduler\Schedule\Daily;
use Piwik\Scheduler\Task;
use Piwik\Plugins\GitHubPluginInstaller\API;

/**
 * Daily check of tracked repos' latest release tag vs. the last installed
 * tag. Deliberately notification-only: it never installs anything by
 * itself, it only logs so a super user can see "update available" and
 * decide to install it themselves via the admin UI/API.
 */
final class CheckForUpdatesTask extends Task
{
    public function __construct()
    {
        $this->setSchedule(new Daily(4));
    }

    public function getName(): string
    {
        return 'GitHubPluginInstaller_CheckForUpdatesTask';
    }

    public function getDescription(): string
    {
        return 'Checks tracked GitHub repositories for newer releases than what is currently installed.';
    }

    public function execute(): void
    {
        $updates = API::getInstance()->checkForUpdates();

        if (empty($updates)) {
            Log::info('[GitHubPluginInstaller] No updates available for tracked repositories.');
            return;
        }

        foreach ($updates as $update) {
            Log::info(sprintf(
                '[GitHubPluginInstaller] Update available: %s/%s has release %s (currently installed: %s)',
                $update['owner'],
                $update['repo'],
                $update['latestTag'],
                $update['installedTag'] ?? 'none'
            ));
        }
    }
}
