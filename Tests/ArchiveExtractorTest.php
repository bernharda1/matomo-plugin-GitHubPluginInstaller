<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Tests;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;
use Piwik\Plugins\GitHubPluginInstaller\Service\ArchiveExtractor;

/**
 * @group GitHubPluginInstaller
 * @group Security
 */
class ArchiveExtractorTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/githubplugininstaller-test-' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function testExtractsWellFormedZipSafely(): void
    {
        $zipPath = $this->workDir . '/good.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('MyPlugin/plugin.json', '{"name":"MyPlugin","version":"1.0.0"}');
        $zip->addFromString('MyPlugin/MyPlugin.php', '<?php namespace Piwik\\Plugins\\MyPlugin;');
        $zip->close();

        $destDir = $this->workDir . '/out';
        mkdir($destDir, 0700, true);

        (new ArchiveExtractor())->extract($zipPath, $destDir);

        $this->assertFileExists($destDir . '/MyPlugin/plugin.json');
        $this->assertFileExists($destDir . '/MyPlugin/MyPlugin.php');
    }

    public function testRejectsZipSlipPathTraversal(): void
    {
        $zipPath = $this->workDir . '/evil.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('../../etc/evil.txt', 'pwned');
        $zip->close();

        $destDir = $this->workDir . '/out';
        mkdir($destDir, 0700, true);

        $this->expectException(SecurityException::class);
        (new ArchiveExtractor())->extract($zipPath, $destDir);
    }

    public function testRejectsAbsolutePathEntries(): void
    {
        $zipPath = $this->workDir . '/evil-abs.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('/etc/evil.txt', 'pwned');
        $zip->close();

        $destDir = $this->workDir . '/out';
        mkdir($destDir, 0700, true);

        $this->expectException(SecurityException::class);
        (new ArchiveExtractor())->extract($zipPath, $destDir);
    }

    public function testRejectsArchiveExceedingUncompressedSizeLimit(): void
    {
        $zipPath = $this->workDir . '/large.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('MyPlugin/plugin.json', str_repeat('a', 1000));
        $zip->close();

        $destDir = $this->workDir . '/out';
        mkdir($destDir, 0700, true);

        $this->expectException(SecurityException::class);
        (new ArchiveExtractor())->extract($zipPath, $destDir, ArchiveExtractor::DEFAULT_MAX_FILES, 100);
    }

    public function testRejectsArchiveExceedingFileCountLimit(): void
    {
        $zipPath = $this->workDir . '/many.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        for ($i = 0; $i < 5; $i++) {
            $zip->addFromString("file{$i}.txt", 'x');
        }
        $zip->close();

        $destDir = $this->workDir . '/out';
        mkdir($destDir, 0700, true);

        $this->expectException(SecurityException::class);
        (new ArchiveExtractor())->extract($zipPath, $destDir, 2);
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
