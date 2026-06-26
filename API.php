<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Piwik;
use Piwik\Plugin\API as PluginApi;
use Piwik\Plugins\GitHubPluginInstaller\Exception\PluginValidationException;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\InstallLogRepository;
use Piwik\Plugins\GitHubPluginInstaller\Infrastructure\TrackedRepoRepository;
use Piwik\Plugins\GitHubPluginInstaller\Service\GitHubClient;
use Piwik\Plugins\GitHubPluginInstaller\Service\PluginInstaller;
use Piwik\Plugins\GitHubPluginInstaller\Service\ReleaseAssetSelector;

/**
 * Every method here requires Super User access: this plugin installs and
 * executes arbitrary code on the Matomo server, so it must never be
 * reachable by anyone below that privilege level.
 */
final class API extends PluginApi
{
    private static ?self $instance = null;

    private TrackedRepoRepository $repoRepository;
    private InstallLogRepository $installLogRepository;
    private GitHubClient $githubClient;

    public function __construct()
    {
        $this->repoRepository = new TrackedRepoRepository();
        $this->installLogRepository = new InstallLogRepository();
        $this->githubClient = new GitHubClient();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array<string, mixed>
     */
    public function addRepository(string $owner, string $repo, ?string $token = null, ?string $assetPattern = null): array
    {
        Piwik::checkUserHasSuperUserAccess();

        $id = $this->repoRepository->add($owner, $repo, $token, $assetPattern, Piwik::getCurrentUserLogin());

        return ['idRepo' => $id, 'owner' => $owner, 'repo' => $repo];
    }

    public function removeRepository(int $idRepo): bool
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->repoRepository->remove($idRepo);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRepositories(): array
    {
        Piwik::checkUserHasSuperUserAccess();

        return array_map(
            function (array $row): array {
                unset($row['token_encrypted']);
                $row['hasToken'] = !empty($row['token_encrypted']);
                return $row;
            },
            $this->repoRepository->findAll()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReleases(int $idRepo, int $limit = 10): array
    {
        Piwik::checkUserHasSuperUserAccess();

        $repoRow = $this->getRepoOrFail($idRepo);
        $token = $this->repoRepository->getDecryptedToken($repoRow);

        return $this->githubClient->listReleases((string) $repoRow['owner'], (string) $repoRow['repo'], $token, $limit);
    }

    /**
     * Downloads, validates and installs (but does not activate) the given
     * release asset as a Matomo plugin under plugins/<Name>.
     *
     * @return array<string, mixed>
     */
    public function installRelease(int $idRepo, string $tagName, ?string $assetName = null): array
    {
        Piwik::checkUserHasSuperUserAccess();

        $repoRow = $this->getRepoOrFail($idRepo);
        $token = $this->repoRepository->getDecryptedToken($repoRow);
        $owner = (string) $repoRow['owner'];
        $repo = (string) $repoRow['repo'];

        $releases = $this->githubClient->listReleases($owner, $repo, $token, 50);
        $release = $this->findRelease($releases, $tagName);

        $pattern = $assetName !== null
            ? '/^' . preg_quote($assetName, '/') . '$/'
            : ($repoRow['asset_pattern'] ?? null);

        $asset = ReleaseAssetSelector::selectAsset($release['assets'], $pattern);

        $installer = $this->buildInstaller();
        $installedBy = Piwik::getCurrentUserLogin();

        try {
            $result = $installer->install($owner, $repo, $asset, $token);

            $this->installLogRepository->record(
                $idRepo,
                $tagName,
                $asset['name'],
                $result['pluginName'],
                $result['version'],
                'success',
                null,
                $installedBy
            );
            $this->repoRepository->recordInstalledVersion($idRepo, $tagName, $result['pluginName']);

            return $result + ['tagName' => $tagName, 'assetName' => $asset['name']];
        } catch (\Throwable $e) {
            $this->installLogRepository->record(
                $idRepo,
                $tagName,
                $asset['name'],
                null,
                null,
                'failed',
                $e->getMessage(),
                $installedBy
            );

            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getInstallLog(int $idRepo, int $limit = 50): array
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->getRepoOrFail($idRepo);

        return $this->installLogRepository->findForRepo($idRepo, $limit);
    }

    /**
     * Compares the latest release tag of each tracked repo against the
     * last installed tag. Never installs anything - surfaces availability
     * only, leaving the install step to an explicit, separate call.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkForUpdates(): array
    {
        Piwik::checkUserHasSuperUserAccess();

        $updates = [];

        foreach ($this->repoRepository->findAll() as $repoRow) {
            $token = $this->repoRepository->getDecryptedToken($repoRow);

            try {
                $releases = $this->githubClient->listReleases(
                    (string) $repoRow['owner'],
                    (string) $repoRow['repo'],
                    $token,
                    1
                );
            } catch (\Throwable $e) {
                continue;
            }

            if (empty($releases)) {
                continue;
            }

            $latestTag = $releases[0]['tag_name'];
            if ($latestTag !== '' && $latestTag !== ($repoRow['last_installed_tag'] ?? null)) {
                $updates[] = [
                    'idRepo' => (int) $repoRow['id'],
                    'owner' => $repoRow['owner'],
                    'repo' => $repoRow['repo'],
                    'installedTag' => $repoRow['last_installed_tag'],
                    'latestTag' => $latestTag,
                ];
            }
        }

        return $updates;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRepoOrFail(int $idRepo): array
    {
        $row = $this->repoRepository->find($idRepo);
        if ($row === null) {
            throw new PluginValidationException("No tracked repository found with id {$idRepo}.");
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $releases
     * @return array<string, mixed>
     */
    private function findRelease(array $releases, string $tagName): array
    {
        foreach ($releases as $release) {
            if ($release['tag_name'] === $tagName) {
                return $release;
            }
        }

        throw new PluginValidationException("No release with tag '{$tagName}' was found.");
    }

    private function buildInstaller(): PluginInstaller
    {
        $pluginsDir = defined('PIWIK_INCLUDE_PATH') ? PIWIK_INCLUDE_PATH . '/plugins' : 'plugins';
        $tmpDir = defined('PIWIK_INCLUDE_PATH')
            ? PIWIK_INCLUDE_PATH . '/tmp/githubplugininstaller'
            : sys_get_temp_dir() . '/githubplugininstaller';

        return new PluginInstaller($pluginsDir, $tmpDir);
    }
}
