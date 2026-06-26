<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\PluginValidationException;

/**
 * Locates and validates the plugin.json of an extracted archive.
 *
 * GitHub release archives commonly wrap their contents in a single
 * top-level directory (e.g. "MyPlugin-1.2.3/"). This resolves that down
 * to the actual plugin root, which must directly contain plugin.json and
 * a "<Name>.php" main class matching the manifest's "name" field.
 */
class PluginManifestValidator
{
    private const NAME_PATTERN = '/^[A-Za-z][A-Za-z0-9]*$/';

    /**
     * @return array{pluginRoot:string, manifest:array<string,mixed>}
     */
    public function locateAndValidate(string $extractedDir): array
    {
        $pluginRoot = $this->resolvePluginRoot($extractedDir);
        $manifestPath = $pluginRoot . '/plugin.json';

        if (!is_file($manifestPath)) {
            throw new PluginValidationException('No plugin.json found in the extracted archive.');
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            throw new PluginValidationException('Could not read plugin.json.');
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            throw new PluginValidationException('plugin.json is not valid JSON.');
        }

        $name = $manifest['name'] ?? null;
        if (!is_string($name) || !preg_match(self::NAME_PATTERN, $name)) {
            throw new PluginValidationException(
                "plugin.json 'name' is missing or contains characters outside [A-Za-z0-9]."
            );
        }

        if (empty($manifest['version']) || !is_string($manifest['version'])) {
            throw new PluginValidationException("plugin.json is missing a 'version' string.");
        }

        $mainClassFile = $pluginRoot . '/' . $name . '.php';
        if (!is_file($mainClassFile)) {
            throw new PluginValidationException(
                "Expected main plugin class file '{$name}.php' not found at the plugin root."
            );
        }

        $expectedNamespaceDeclaration = "namespace Piwik\\Plugins\\{$name}";
        $classSource = file_get_contents($mainClassFile) ?: '';
        if (!str_contains($classSource, $expectedNamespaceDeclaration)) {
            throw new PluginValidationException(
                "{$name}.php does not declare the expected namespace '{$expectedNamespaceDeclaration}'."
            );
        }

        return ['pluginRoot' => $pluginRoot, 'manifest' => $manifest];
    }

    private function resolvePluginRoot(string $extractedDir): string
    {
        if (is_file($extractedDir . '/plugin.json')) {
            return $extractedDir;
        }

        $entries = array_values(array_diff(scandir($extractedDir) ?: [], ['.', '..']));
        $directories = array_filter($entries, fn ($entry) => is_dir($extractedDir . '/' . $entry));

        if (count($entries) === 1 && count($directories) === 1) {
            $nested = $extractedDir . '/' . $entries[0];
            if (is_file($nested . '/plugin.json')) {
                return $nested;
            }
        }

        throw new PluginValidationException(
            'Could not locate plugin.json at the archive root or inside a single top-level directory.'
        );
    }
}
