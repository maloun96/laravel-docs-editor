<?php

declare(strict_types=1);

namespace Maloun96\DocsEditor\Services;

use Illuminate\Support\Facades\File;

final class DocsFileService
{
    public function __construct(
        private string $docsPath,
        private string $mediaPath,
    ) {}

    /** @return array<int, array{path: string, name: string, published: bool}> */
    public function listDocs(): array
    {
        $files = [];
        $this->walkDirectory($this->docsPath, $files);

        return $files;
    }

    /** @return array{content: string, path: string} */
    public function getFileContent(string $relativePath): array
    {
        $fullPath = base_path($relativePath);

        if (! File::exists($fullPath)) {
            throw new \RuntimeException("File not found: {$relativePath}");
        }

        return [
            'content' => File::get($fullPath),
            'path' => $relativePath,
        ];
    }

    /** @return array<int, string> */
    public function listFolders(): array
    {
        $prefix = $this->relativeDocsPath();
        $folders = [$prefix];
        $this->walkFolders($this->docsPath, $prefix, $folders);
        sort($folders);

        return $folders;
    }

    public function writeFile(string $relativePath, string $content): void
    {
        $fullPath = base_path($relativePath);
        $dir = dirname($fullPath);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($fullPath, $content);
    }

    public function getMediaPath(): string
    {
        return $this->mediaPath;
    }

    public function relativeDocsPath(): string
    {
        return ltrim(str_replace(base_path(), '', $this->docsPath), '/');
    }

    private function walkDirectory(string $dir, array &$files): void
    {
        if (! File::isDirectory($dir)) {
            return;
        }

        $prefix = $this->relativeDocsPath();

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relativePath = $prefix . '/' . ltrim(str_replace($this->docsPath, '', $file->getPathname()), '/');

            $published = false;
            $content = File::get($file->getPathname());
            if (preg_match('/\A---\s*\n(.+?)\n---/s', $content, $m)) {
                $published = (bool) preg_match('/^published:\s*true\s*$/m', $m[1]);
            }

            $files[] = [
                'path' => $relativePath,
                'name' => $file->getFilename(),
                'published' => $published,
            ];
        }

        foreach (File::directories($dir) as $subDir) {
            $dirName = basename($subDir);

            if ($dirName[0] === '_' || $dirName[0] === '.' || str_starts_with($dirName, 'media_')) {
                continue;
            }

            $this->walkDirectory($subDir, $files);
        }
    }

    private function walkFolders(string $dir, string $prefix, array &$folders): void
    {
        if (! File::isDirectory($dir)) {
            return;
        }

        foreach (File::directories($dir) as $subDir) {
            $dirName = basename($subDir);

            if ($dirName[0] === '_' || $dirName[0] === '.' || str_starts_with($dirName, 'media_')) {
                continue;
            }

            $relativePath = $prefix . '/' . $dirName;
            $folders[] = $relativePath;
            $this->walkFolders($subDir, $relativePath, $folders);
        }
    }
}
