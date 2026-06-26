<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

/**
 * Registers the admin menu item the modern way (Piwik\Plugin\Menu /
 * configureAdminMenu), since the legacy 'Menu.Admin.addItems' event this
 * plugin previously relied on was deprecated and removed in current
 * Matomo versions - that's why the menu item never appeared.
 *
 * Placed in the "Platform" section (addPlatformItem), alongside tools
 * like the Marketplace, since installing plugins is a platform-level
 * administration task rather than a per-website "Manage" setting.
 */
class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu): void
    {
        if (!Piwik::hasUserSuperUserAccess()) {
            return;
        }

        $menu->addPlatformItem(
            'GitHubPluginInstaller_MenuTitle',
            $this->urlForDefaultAction(),
            30
        );
    }
}
