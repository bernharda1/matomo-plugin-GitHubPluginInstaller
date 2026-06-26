<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\View;

/**
 * Admin-only UI. Every state-changing action verifies the Matomo CSRF
 * nonce (checkTokenInUrl) on top of the Super User check already enforced
 * in API.php, since browser form submits are vulnerable to CSRF in a way
 * authenticated API token calls are not.
 */
final class Controller extends ControllerAdmin
{
    public function index(): string
    {
        Piwik::checkUserHasSuperUserAccess();

        $view = new View('@GitHubPluginInstaller/index');
        $this->setBasicVariablesView($view);
        $view->repositories = API::getInstance()->listRepositories();
        $view->nonce = Common::getRequestVar('nonce', '', 'string');

        return $view->render();
    }

    public function addRepository(): void
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->checkTokenInUrl();

        $owner = Common::getRequestVar('owner', '', 'string');
        $repo = Common::getRequestVar('repo', '', 'string');
        $token = Common::getRequestVar('token', '', 'string') ?: null;
        $assetPattern = Common::getRequestVar('assetPattern', '', 'string') ?: null;

        API::getInstance()->addRepository($owner, $repo, $token, $assetPattern);

        $this->redirectToIndex('GitHubPluginInstaller', 'index');
    }

    public function removeRepository(): void
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->checkTokenInUrl();

        $idRepo = Common::getRequestVar('idRepo', 0, 'int');
        API::getInstance()->removeRepository($idRepo);

        $this->redirectToIndex('GitHubPluginInstaller', 'index');
    }

    public function installRelease(): void
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->checkTokenInUrl();

        $idRepo = Common::getRequestVar('idRepo', 0, 'int');
        $tagName = Common::getRequestVar('tagName', '', 'string');
        $assetName = Common::getRequestVar('assetName', '', 'string') ?: null;

        API::getInstance()->installRelease($idRepo, $tagName, $assetName);

        $this->redirectToIndex('GitHubPluginInstaller', 'index');
    }
}
