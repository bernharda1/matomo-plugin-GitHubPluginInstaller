<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\GitHubPluginInstaller\API;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\TrackedRepoRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InstallFromGithubCommand extends ConsoleCommand
{
    protected function configure(): void
    {
        $this->setName('githubplugininstaller:install');
        $this->setDescription('Installs a Matomo plugin from a tracked GitHub repository release. Does not activate it.');
        $this->addArgument('owner', InputArgument::REQUIRED, 'Repository owner');
        $this->addArgument('repo', InputArgument::REQUIRED, 'Repository name');
        $this->addOption('tag', null, InputOption::VALUE_REQUIRED, 'Release tag to install (defaults to latest)');
        $this->addOption('asset', null, InputOption::VALUE_REQUIRED, 'Specific asset filename to install');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $owner = (string) $input->getArgument('owner');
        $repo = (string) $input->getArgument('repo');

        $repoRepository = new TrackedRepoRepository();
        $idRepo = $this->findTrackedRepoId($repoRepository, $owner, $repo);

        if ($idRepo === null) {
            $output->writeln(sprintf(
                '<error>%s/%s is not tracked. Add it first via the admin UI or API:addRepository.</error>',
                $owner,
                $repo
            ));
            return 1;
        }

        $tag = $input->getOption('tag');
        if ($tag === null) {
            $releases = API::getInstance()->getReleases($idRepo, 1);
            if (empty($releases)) {
                $output->writeln('<error>This repository has no releases.</error>');
                return 1;
            }
            $tag = $releases[0]['tag_name'];
        }

        $result = API::getInstance()->installRelease($idRepo, (string) $tag, $input->getOption('asset'));

        $output->writeln(sprintf(
            '<info>Installed %s v%s to %s</info>',
            $result['pluginName'],
            $result['version'],
            $result['installedPath']
        ));
        $output->writeln('<comment>Not activated. Activate it via "php console plugin:activate ' . $result['pluginName'] . '" after review.</comment>');

        return 0;
    }

    private function findTrackedRepoId(TrackedRepoRepository $repository, string $owner, string $repo): ?int
    {
        foreach ($repository->findAll() as $row) {
            if ($row['owner'] === $owner && $row['repo'] === $repo) {
                return (int) $row['id'];
            }
        }

        return null;
    }
}
