<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations\Migration_1_0_0_CreateTrackedReposTable;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\Migrations\Migration_1_0_1_CreateInstallLogTable;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Manually (re-)runs this plugin's table creation. Needed for instances
 * where the plugin was already activated before install() was wired up
 * (Matomo only calls Plugin::install() on the activation transition, not
 * retroactively for already-active plugins) - run this once to create the
 * missing tables without having to uninstall/reactivate via the UI.
 */
final class CreateTablesCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this->setName('githubplugininstaller:create-tables');
        $this->setDescription('Creates (or re-checks) this plugin\'s database tables. Safe to run multiple times.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrations = [
            new Migration_1_0_0_CreateTrackedReposTable(),
            new Migration_1_0_1_CreateInstallLogTable(),
        ];

        foreach ($migrations as $migration) {
            $migration->up();
            $output->writeln(sprintf('<info>OK</info> %s', $migration->getDescription()));
        }

        return 0;
    }
}
