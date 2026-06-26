<?php

declare(strict_types=1);

namespace Piwik\Plugins\GitHubPluginInstaller\Service;

use Piwik\Plugins\GitHubPluginInstaller\Exception\SecurityException;
use Piwik\Plugins\GitHubPluginInstaller\Exception\UnsupportedAssetException;

/**
 * Safely extracts .zip and .tar.gz/.tgz archives.
 *
 * Every entry is validated *before* anything is extracted: entries with
 * traversal sequences, absolute paths, or symlinks abort the whole
 * extraction (zip-slip protection), and cumulative size/count are checked
 * against limits derived from archive metadata before any bytes are
 * written (zip-bomb protection).
 */
class ArchiveExtractor
{
    public const DEFAULT_MAX_FILES = 5000;
    public const DEFAULT_MAX_UNCOMPRESSED_BYTES = 250 * 1024 * 1024; // 250 MB

    public function extract(
        string $archivePath,
        string $destDir,
        int $maxFiles = self::DEFAULT_MAX_FILES,
        int $maxUncompressedBytes = self::DEFAULT_MAX_UNCOMPRESSED_BYTES
    ): void {
        $lower = strtolower($archivePath);

        if (str_ends_with($lower, '.zip')) {
            $this->extractZip($archivePath, $destDir, $maxFiles, $maxUncompressedBytes);
            return;
        }

        if (str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz')) {
            $this->extractTarGz($archivePath, $destDir, $maxFiles, $maxUncompressedBytes);
            return;
        }

        throw new UnsupportedAssetException("Unsupported archive type: {$archivePath}");
    }

    private function extractZip(string $archivePath, string $destDir, int $maxFiles, int $maxBytes): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new SecurityException("Could not open zip archive: {$archivePath}");
        }

        $entries = [];
        $totalSize = 0;
        $count = $zip->numFiles;

        if ($count > $maxFiles) {
            $zip->close();
            throw new SecurityException("Archive contains {$count} entries, exceeding limit of {$maxFiles}.");
        }

        for ($i = 0; $i < $count; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $zip->close();
                throw new SecurityException('Could not read archive entry metadata.');
            }

            $name = (string) $stat['name'];
            $this->assertSafeEntryName($name);

            if ($this->isZipEntrySymlink($zip, $i)) {
                $zip->close();
                throw new SecurityException("Archive entry '{$name}' is a symlink, which is not allowed.");
            }

            $totalSize += (int) $stat['size'];
            if ($totalSize > $maxBytes) {
                $zip->close();
                throw new SecurityException(
                    "Archive's uncompressed size exceeds the limit of {$maxBytes} bytes (possible zip bomb)."
                );
            }

            $entries[] = $name;
        }

        if (!$zip->extractTo($destDir, $entries)) {
            $zip->close();
            throw new SecurityException("Failed to extract archive to {$destDir}.");
        }

        $zip->close();
    }

    private function isZipEntrySymlink(\ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attr = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }

        // Unix mode lives in the high 16 bits of the external attributes
        // when the archive was created on a Unix system (opsys === 3).
        $unixMode = ($attr >> 16) & 0xFFFF;
        return ($unixMode & 0170000) === 0120000; // S_IFLNK
    }

    private function extractTarGz(string $archivePath, string $destDir, int $maxFiles, int $maxBytes): void
    {
        try {
            $phar = new \PharData($archivePath);
        } catch (\Exception $e) {
            throw new SecurityException("Could not open tar.gz archive: {$e->getMessage()}");
        }

        $entries = [];
        $totalSize = 0;
        $count = 0;

        foreach (new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::SELF_FIRST) as $fileInfo) {
            /** @var \PharFileInfo $fileInfo */
            $count++;
            if ($count > $maxFiles) {
                throw new SecurityException("Archive contains more than {$maxFiles} entries.");
            }

            $entryName = $this->relativePharEntryName($phar, $fileInfo);
            if ($entryName === '') {
                continue;
            }

            $this->assertSafeEntryName($entryName);

            if ($fileInfo->isLink()) {
                throw new SecurityException("Archive entry '{$entryName}' is a symlink, which is not allowed.");
            }

            if ($fileInfo->isFile()) {
                $totalSize += $fileInfo->getSize();
                if ($totalSize > $maxBytes) {
                    throw new SecurityException(
                        "Archive's uncompressed size exceeds the limit of {$maxBytes} bytes (possible tar bomb)."
                    );
                }
            }

            $entries[] = $entryName;
        }

        if (!$phar->extractTo($destDir, $entries, true)) {
            throw new SecurityException("Failed to extract archive to {$destDir}.");
        }
    }

    private function relativePharEntryName(\PharData $phar, \PharFileInfo $fileInfo): string
    {
        $pharRoot = 'phar://' . $phar->getPath() . '/';
        $full = $fileInfo->getPathname();

        return strpos($full, $pharRoot) === 0 ? substr($full, strlen($pharRoot)) : ltrim($full, '/');
    }

    private function assertSafeEntryName(string $name): void
    {
        if ($name === '') {
            throw new SecurityException('Archive contains an entry with an empty name.');
        }

        if (str_contains($name, "\0")) {
            throw new SecurityException("Archive entry '{$name}' contains a NUL byte.");
        }

        $normalized = str_replace('\\', '/', $name);

        if (str_starts_with($normalized, '/') || preg_match('#^[A-Za-z]:#', $normalized)) {
            throw new SecurityException("Archive entry '{$name}' uses an absolute path, which is not allowed.");
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new SecurityException("Archive entry '{$name}' contains a path traversal segment.");
            }
        }
    }
}
