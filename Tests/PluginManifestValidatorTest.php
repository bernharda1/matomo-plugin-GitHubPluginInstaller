<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Tests;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\GitHubPluginInstaller\Exception\PluginValidationException;
use Piwik\Plugins\GitHubPluginInstaller\Service\PluginManifestValidator;

/**
 * @group GitHubPluginInstaller
 */
class PluginManifestValidatorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/githubplugininstaller-manifest-' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function testAcceptsPluginAtArchiveRoot(): void
    {
        $this->writePlugin($this->workDir, 'MyPlugin');

        $result = (new PluginManifestValidator())->locateAndValidate($this->workDir);

        $this->assertSame($this->workDir, $result['pluginRoot']);
        $this->assertSame('MyPlugin', $result['manifest']['name']);
    }

    public function testResolvesSingleTopLevelWrapperDirectory(): void
    {
        $nested = $this->workDir . '/MyPlugin-1.0.0';
        mkdir($nested, 0700, true);
        $this->writePlugin($nested, 'MyPlugin');

        $result = (new PluginManifestValidator())->locateAndValidate($this->workDir);

        $this->assertSame($nested, $result['pluginRoot']);
    }

    public function testRejectsMissingManifest(): void
    {
        $this->expectException(PluginValidationException::class);
        (new PluginManifestValidator())->locateAndValidate($this->workDir);
    }

    public function testRejectsNameWithInvalidCharacters(): void
    {
        file_put_contents($this->workDir . '/plugin.json', json_encode(['name' => 'My-Plugin; rm -rf', 'version' => '1.0.0']));
        file_put_contents($this->workDir . '/MyPlugin.php', '<?php');

        $this->expectException(PluginValidationException::class);
        (new PluginManifestValidator())->locateAndValidate($this->workDir);
    }

    public function testRejectsMissingMainClassFile(): void
    {
        file_put_contents($this->workDir . '/plugin.json', json_encode(['name' => 'MyPlugin', 'version' => '1.0.0']));

        $this->expectException(PluginValidationException::class);
        (new PluginManifestValidator())->locateAndValidate($this->workDir);
    }

    public function testRejectsNamespaceMismatch(): void
    {
        file_put_contents($this->workDir . '/plugin.json', json_encode(['name' => 'MyPlugin', 'version' => '1.0.0']));
        file_put_contents($this->workDir . '/MyPlugin.php', '<?php namespace Piwik\\Plugins\\SomethingElse;');

        $this->expectException(PluginValidationException::class);
        (new PluginManifestValidator())->locateAndValidate($this->workDir);
    }

    private function writePlugin(string $dir, string $name): void
    {
        file_put_contents($dir . '/plugin.json', json_encode(['name' => $name, 'version' => '1.0.0']));
        file_put_contents($dir . '/' . $name . '.php', "<?php\nnamespace Piwik\\Plugins\\{$name};\n");
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
