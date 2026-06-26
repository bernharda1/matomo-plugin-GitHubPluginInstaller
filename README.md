# GitHubPluginInstaller

Installs and updates Matomo plugins from GitHub repository releases
(`.zip` / `.tar.gz` assets), for both public and private repositories.

## Security model

Installing a plugin from a GitHub release is, by design, equivalent to
giving that repository's owner the ability to run arbitrary PHP code on
this Matomo server once the plugin is activated. This plugin is built
around that fact:

- Every API method requires **Super User** access (`Piwik::checkUserHasSuperUserAccess()`).
- Installing **never auto-activates** the plugin. Files are placed under
  `plugins/<Name>`, and activation is a separate, conscious step taken via
  Matomo's normal plugin management (UI or `php console plugin:activate`).
- The daily update check (`CheckForUpdatesTask`) only **logs/surfaces**
  that a newer release exists. It never installs or activates anything by
  itself.
- Outbound HTTP requests are restricted to an explicit host allowlist
  (`api.github.com` for API calls; `objects.githubusercontent.com`,
  `github-releases.githubusercontent.com`, `*.s3.amazonaws.com` as
  possible asset-redirect targets) to prevent SSRF via crafted repo/owner
  input. See `Service/HttpFetcher.php`.
- Archive extraction (`Service/ArchiveExtractor.php`) validates every
  entry path before extracting anything: absolute paths, `..` traversal
  segments, and symlink entries are rejected outright (zip-slip / tar-slip
  protection), and cumulative file count / uncompressed size are checked
  against limits before any bytes are written (zip-bomb protection).
- A downloaded asset must contain a valid `plugin.json` (with a `name`
  matching `[A-Za-z][A-Za-z0-9]*`) and a `<Name>.php` file declaring
  `namespace Piwik\Plugins\<Name>` before it is moved into `plugins/`.
  See `Service/PluginManifestValidator.php`.
- An existing plugin directory of the same name is backed up to
  `plugins/<Name>.bak-<timestamp>` before being overwritten, not deleted.
- Per-repo GitHub Personal Access Tokens (needed only for private repos)
  are stored encrypted at rest (AES-256-CBC + HMAC-SHA256, see
  `Service/TokenVault.php`) and are never logged.

**Recommendation:** use a fine-grained PAT scoped to read-only access on
the single private repository you are tracking, not a classic token with
broad `repo` scope.

## Usage

### Admin UI

`Administration > Platform > GitHub Plugin Installer`. Add a repository
(owner/repo, optional token for private repos, optional asset name
regex if a release ships more than one `.zip`/`.tar.gz`), browse its
releases, and install a chosen tag. The plugin is placed on disk; activate
it afterwards from the standard Plugins admin page once you've reviewed it.

### API

- `GitHubPluginInstaller.addRepository(owner, repo, token=null, assetPattern=null)`
- `GitHubPluginInstaller.removeRepository(idRepo)`
- `GitHubPluginInstaller.listRepositories()`
- `GitHubPluginInstaller.getReleases(idRepo, limit=10)`
- `GitHubPluginInstaller.installRelease(idRepo, tagName, assetName=null)`
- `GitHubPluginInstaller.getInstallLog(idRepo, limit=50)`
- `GitHubPluginInstaller.checkForUpdates()`

### CLI

```bash
php console githubplugininstaller:install <owner> <repo> --tag=v1.2.3 --asset=MyPlugin-1.2.3.zip
php console githubplugininstaller:check-updates
```

## Architecture

```
Service/
  GitHubClient.php             GitHub REST API: list releases, download an asset
  HttpFetcher.php               Host-allowlisted, redirect-validating cURL wrapper (SSRF guard)
  ReleaseAssetSelector.php      Picks the right .zip/.tar.gz from a release's assets
  Downloader.php                Streams the chosen asset to a temp file, enforcing size caps
  ArchiveExtractor.php          Safe zip/tar.gz extraction (zip-slip + zip-bomb protection)
  PluginManifestValidator.php   Locates/validates plugin.json + main class in the extracted tree
  PluginInstaller.php           Orchestrates the above; backs up & moves into plugins/<Name>
  TokenVault.php                AES-256-CBC + HMAC encryption for stored PATs
Infrastructure/
  TrackedRepoRepository.php     CRUD for tracked repos (plugin_githubplugininstaller_repos)
  InstallLogRepository.php      Install/update audit trail (plugin_githubplugininstaller_installs)
  Migrations/                   Table creation migrations
Tasks/CheckForUpdatesTask.php   Daily "is there a newer release?" check (log-only)
Commands/                       CLI equivalents of the admin actions
Tests/                          Framework-independent PHPUnit tests for the security-critical
                                 pieces (ArchiveExtractor, HttpFetcher allowlist, manifest
                                 validation, asset selection)
```

## Known limitations / next steps

- Database migrations in `Infrastructure/Migrations/` follow the same
  pattern as this codebase's other plugins (e.g. GeoPrecision) but, as
  with those, are not yet wired into an automatic runner - run/verify them
  against your actual Matomo installation's migration mechanism before
  relying on them in production.
- No UI affordance yet for picking an older release for rollback beyond
  what `getReleases` already returns to the admin page; the admin UI
  currently only renders "add repository" + remove, not yet a full
  release/asset picker widget (the API already supports it).
- Tests require PHP with `ext-zip`/`ext-phar` and PHPUnit; they were not
  executed in this environment (no PHP runtime available here) and should
  be run in a real Matomo dev environment before relying on them.
