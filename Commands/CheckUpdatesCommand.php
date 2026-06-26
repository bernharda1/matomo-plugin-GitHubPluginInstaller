<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\GitHubPluginInstaller\API;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckUpdatesCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this->setName('githubplugininstaller:check-updates');
        $this->setDescription('Lists tracked repositories whose latest release is newer than what is installed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updates = API::getInstance()->checkForUpdates();

        if (empty($updates)) {
            $output->writeln('<info>All tracked repositories are up to date.</info>');
            return 0;
        }

        foreach ($updates as $update) {
            $output->writeln(sprintf(
                '<comment>%s/%s</comment>: installed=%s latest=%s',
                $update['owner'],
                $update['repo'],
                $update['installedTag'] ?? 'none',
                $update['latestTag']
            ));
        }

        return 0;
    }
}
