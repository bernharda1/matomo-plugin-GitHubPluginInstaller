<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Common;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin\ControllerAdmin;
use Piwik\View;

/**
 * Admin-only UI. Every state-changing action verifies a Matomo nonce on
 * top of the Super User check already enforced in API.php, since browser
 * form submits are vulnerable to CSRF in a way authenticated API token
 * calls are not.
 */
final class Controller extends ControllerAdmin
{
    private const NONCE_NAME = 'GitHubPluginInstaller.nonce';

    public function index(): string
    {
        Piwik::checkUserHasSuperUserAccess();

        $view = new View('@GitHubPluginInstaller/index');
        $this->setBasicVariablesView($view);
        $view->repositories = API::getInstance()->listRepositories();
        $view->nonce = Nonce::getNonce(self::NONCE_NAME);

        return $view->render();
    }

    public function addRepository(): void
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->checkNonce();

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
        $this->checkNonce();

        $idRepo = Common::getRequestVar('idRepo', 0, 'int');
        API::getInstance()->removeRepository($idRepo);

        $this->redirectToIndex('GitHubPluginInstaller', 'index');
    }

    public function installRelease(): void
    {
        Piwik::checkUserHasSuperUserAccess();
        $this->checkNonce();

        $idRepo = Common::getRequestVar('idRepo', 0, 'int');
        $tagName = Common::getRequestVar('tagName', '', 'string');
        $assetName = Common::getRequestVar('assetName', '', 'string') ?: null;

        API::getInstance()->installRelease($idRepo, $tagName, $assetName);

        $this->redirectToIndex('GitHubPluginInstaller', 'index');
    }

    /**
     * Verifies the nonce submitted with the request and discards it
     * immediately (single use), so it cannot be replayed.
     */
    private function checkNonce(): void
    {
        $nonce = Common::getRequestVar('nonce', '', 'string');

        if (!Nonce::verifyNonce(self::NONCE_NAME, $nonce)) {
            throw new \Exception(Piwik::translate('General_ExceptionNonceMismatch'));
        }

        Nonce::discardNonce(self::NONCE_NAME);
    }
}
