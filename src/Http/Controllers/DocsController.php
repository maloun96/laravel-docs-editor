<?php

declare(strict_types=1);

namespace Maloun96\DocsEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maloun96\DocsEditor\Services\DocsFileService;
use Maloun96\DocsEditor\Services\GitHubDocsService;

final class DocsController extends Controller
{
    public function __construct(
        private DocsFileService $files,
        private GitHubDocsService $github,
    ) {}

    public function index(): View
    {
        $tree = '';

        try {
            $docs = $this->files->listDocs();
            $tree = $this->buildTreeHtml($docs);
        } catch (\Throwable $e) {
            session()->flash('error', "Error: {$e->getMessage()}");
        }

        return view('docs-editor::index', [
            'tree' => $tree,
            'docsPrefix' => $this->files->relativeDocsPath(),
        ]);
    }

    public function edit(Request $request): View|JsonResponse
    {
        $path = $request->query('path');
        $file = $this->files->getFileContent($path);
        $parsed = $this->parseFrontmatter($file['content']);

        $data = [
            'path' => $path,
            'title' => $parsed['meta']['title'] ?? '',
            'description' => $parsed['meta']['description'] ?? '',
            'keywords' => $parsed['meta']['keywords'] ?? '',
            'noindex' => $parsed['meta']['noindex'] ?? false,
            'published' => $parsed['meta']['published'] ?? false,
            'content' => $parsed['body'],
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('docs-editor::edit', $data);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'doc_path' => ['required', 'string'],
        ]);

        $docPath = $request->input('doc_path');
        $docsPrefix = $this->files->relativeDocsPath();
        $slug = str_replace($docsPrefix . '/', '', $docPath);
        $parts = explode('/', $slug);
        $section = $parts[0];
        $pageSlug = pathinfo($slug, PATHINFO_FILENAME);

        $file = $request->file('image');
        $randomId = Str::random(10);
        $fileName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext = $file->getClientOriginalExtension();
        $imageName = "{$randomId}-{$fileName}.{$ext}";

        $mediaRelative = config('docs-editor.media_path');
        $mediaDir = "{$mediaRelative}/{$section}/media-{$pageSlug}";
        $fullDir = base_path($mediaDir);

        if (! File::isDirectory($fullDir)) {
            File::makeDirectory($fullDir, 0755, true);
        }

        $file->move($fullDir, $imageName);

        $repoPath = "{$mediaDir}/{$imageName}";
        $publicPath = str_replace(config('docs-editor.media_path'), '/docs-media', $repoPath);

        return response()->json([
            'markdown' => "![{$fileName}]({$publicPath})",
            'repo_path' => $repoPath,
        ]);
    }

    public function create(): JsonResponse
    {
        $folders = $this->files->listFolders();

        return response()->json(['folders' => $folders]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'folder' => ['required', 'string'],
            'filename' => ['required', 'string', 'regex:/^[a-z0-9-]+$/'],
            'content' => ['required', 'string'],
        ]);

        $meta = array_filter([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'keywords' => $request->input('keywords'),
            'noindex' => $request->boolean('noindex') ?: null,
            'published' => $request->boolean('published') ?: null,
        ]);

        $body = $request->input('content');
        $fileContent = $this->buildMarkdown($meta, $body);

        $folder = rtrim($request->input('folder'), '/');
        $filename = $request->input('filename') . '.md';
        $path = "{$folder}/{$filename}";

        $docsPrefix = $this->files->relativeDocsPath();
        $slug = str_replace($docsPrefix . '/', '', $path);
        $section = explode('/', $slug)[0];
        $commitMessage = "docs({$section}): add " . $request->input('filename');

        try {
            $this->files->writeFile($path, $fileContent);

            $imagePaths = array_filter(explode(',', $request->input('uploaded_images') ?? ''));
            $prUrl = $this->github->createPullRequestForNewFile($path, $fileContent, $commitMessage, $imagePaths);

            return redirect()
                ->route('docs-editor.index')
                ->with('success', "File created and PR opened: <a href=\"{$prUrl}\" target=\"_blank\" class=\"underline font-medium\">{$prUrl}</a>");
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', "Failed: {$e->getMessage()}");
        }
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'content' => ['required', 'string'],
        ]);

        $meta = array_filter([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'keywords' => $request->input('keywords'),
            'noindex' => $request->boolean('noindex') ?: null,
            'published' => $request->boolean('published') ?: null,
        ]);

        $body = $request->input('content');
        $fileContent = $this->buildMarkdown($meta, $body);

        $docsPrefix = $this->files->relativeDocsPath();
        $slug = str_replace($docsPrefix . '/', '', $request->input('path'));
        $section = explode('/', $slug)[0];
        $commitMessage = "docs({$section}): update " . basename($request->input('path'), '.md');

        try {
            $imagePaths = array_filter(explode(',', $request->input('uploaded_images') ?? ''));
            $prUrl = $this->github->createPullRequestForUpdate(
                $request->input('path'),
                $fileContent,
                $commitMessage,
                $imagePaths,
            );

            return redirect()
                ->route('docs-editor.index')
                ->with('success', "PR created: <a href=\"{$prUrl}\" target=\"_blank\" class=\"underline font-medium\">{$prUrl}</a>");
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', "Failed to create PR: {$e->getMessage()}");
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $docsPrefix = $this->files->relativeDocsPath();
        $slug = str_replace($docsPrefix . '/', '', $request->input('path'));
        $section = explode('/', $slug)[0];
        $commitMessage = "docs({$section}): delete " . basename($request->input('path'), '.md');

        try {
            $prUrl = $this->github->createPullRequestForDelete(
                $request->input('path'),
                $commitMessage,
            );

            return redirect()
                ->route('docs-editor.index')
                ->with('success', "Delete PR created: <a href=\"{$prUrl}\" target=\"_blank\" class=\"underline font-medium\">{$prUrl}</a>");
        } catch (\Throwable $e) {
            return back()
                ->with('error', "Failed to create delete PR: {$e->getMessage()}");
        }
    }

    private function buildTreeHtml(array $docs): string
    {
        $tree = [];
        $docsPrefix = $this->files->relativeDocsPath();

        foreach ($docs as $doc) {
            $relative = str_replace($docsPrefix . '/', '', $doc['path']);
            $parts = explode('/', $relative);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current['__files'][] = [
                        'name' => $part,
                        'path' => $doc['path'],
                        'published' => $doc['published'] ?? false,
                    ];
                } else {
                    if (! isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }

            unset($current);
        }

        return $this->renderTree($tree);
    }

    private function renderTree(array $node, int $depth = 0): string
    {
        $html = '';
        $padding = $depth * 16;

        $folders = array_filter($node, fn ($key) => $key !== '__files', ARRAY_FILTER_USE_KEY);
        ksort($folders);

        foreach ($folders as $name => $children) {
            $displayName = str_replace('-', ' ', $name);
            $html .= "<li class=\"tree-folder\">";
            $html .= "<div class=\"tree-toggle flex items-center gap-1 px-2 py-1 rounded-md hover:bg-gray-100 cursor-pointer select-none\" style=\"padding-left: {$padding}px\">";
            $html .= "<svg class=\"toggle-icon w-3.5 h-3.5 text-gray-400 transition rotate-90 flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M9 5l7 7-7 7\"/></svg>";
            $html .= "<svg class=\"w-4 h-4 text-amber-500 flex-shrink-0\" fill=\"currentColor\" viewBox=\"0 0 20 20\"><path d=\"M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z\"/></svg>";
            $html .= "<span class=\"text-xs font-medium text-gray-700 truncate\">{$displayName}</span>";
            $html .= "</div>";
            $html .= "<ul class=\"list-none\">" . $this->renderTree($children, $depth + 1) . "</ul>";
            $html .= "</li>";
        }

        $files = $node['__files'] ?? [];
        usort($files, fn ($a, $b) => strcmp($a['name'], $b['name']));
        $filePadding = $padding + 20;

        foreach ($files as $file) {
            $displayName = str_replace('-', ' ', basename($file['name'], '.md'));
            $escapedPath = e($file['path']);
            $published = $file['published'] ?? false;
            $textColor = $published ? 'text-gray-600' : 'text-gray-400';
            $iconColor = $published ? 'text-gray-400' : 'text-gray-300';
            $html .= "<li>";
            $html .= "<a href=\"#\" class=\"tree-file flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-blue-50 hover:text-blue-700 {$textColor} transition\" style=\"padding-left: {$filePadding}px\" data-path=\"{$escapedPath}\">";
            $html .= "<svg class=\"w-3.5 h-3.5 {$iconColor} flex-shrink-0\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z\"/></svg>";
            $html .= "<span class=\"text-xs truncate\">{$displayName}</span>";
            if (! $published) {
                $html .= "<span class=\"text-[9px] font-medium text-orange-400 uppercase tracking-wide flex-shrink-0\">Draft</span>";
            }
            $html .= "</a>";
            $html .= "</li>";
        }

        return $html;
    }

    private function parseFrontmatter(string $raw): array
    {
        if (! str_starts_with(trim($raw), '---')) {
            return ['meta' => [], 'body' => $raw];
        }

        $parts = preg_split('/^---\s*$/m', $raw, 3);

        if (count($parts) < 3) {
            return ['meta' => [], 'body' => $raw];
        }

        $meta = [];
        foreach (explode("\n", trim($parts[1])) as $line) {
            if (preg_match('/^(\w+):\s*"?(.+?)"?\s*$/', $line, $m)) {
                $value = $m[2];
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                $meta[$m[1]] = $value;
            }
        }

        return ['meta' => $meta, 'body' => ltrim($parts[2], "\n")];
    }

    private function buildMarkdown(array $meta, string $body): string
    {
        if (empty($meta)) {
            return $body;
        }

        $frontmatter = "---\n";
        foreach ($meta as $key => $value) {
            if (is_bool($value)) {
                $frontmatter .= "{$key}: " . ($value ? 'true' : 'false') . "\n";
            } else {
                $frontmatter .= "{$key}: \"{$value}\"\n";
            }
        }
        $frontmatter .= "---\n\n";

        return $frontmatter . $body;
    }
}
