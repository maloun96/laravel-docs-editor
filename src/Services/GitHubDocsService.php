<?php

declare(strict_types=1);

namespace Maloun96\DocsEditor\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GitHubDocsService
{
    public function __construct(
        private string $token,
        private string $owner,
        private string $repo,
        private string $baseBranch = 'main',
    ) {}

    /** @param  array<int, string>  $imagePaths */
    public function createPullRequestForUpdate(string $path, string $content, string $commitMessage, array $imagePaths = []): string
    {
        $branchName = 'docs/' . Str::slug(pathinfo($path, PATHINFO_FILENAME)) . '-' . now()->format('Ymd-His');
        $sha = $this->getFileSha($path);

        $this->createBranch($branchName);
        $this->commitImages($branchName, $imagePaths);
        $this->commitFile($branchName, $path, $content, $sha, $commitMessage);

        return $this->openPullRequest($branchName, $commitMessage);
    }

    /** @param  array<int, string>  $imagePaths */
    public function createPullRequestForNewFile(string $path, string $content, string $commitMessage, array $imagePaths = []): string
    {
        $branchName = 'docs/' . Str::slug(pathinfo($path, PATHINFO_FILENAME)) . '-' . now()->format('Ymd-His');

        $this->createBranch($branchName);
        $this->commitImages($branchName, $imagePaths);
        $this->createNewFile($branchName, $path, $content, $commitMessage);

        return $this->openPullRequest($branchName, $commitMessage);
    }

    public function createPullRequestForDelete(string $path, string $commitMessage): string
    {
        $branchName = 'docs/delete-' . Str::slug(pathinfo($path, PATHINFO_FILENAME)) . '-' . now()->format('Ymd-His');
        $sha = $this->getFileSha($path);

        $this->createBranch($branchName);

        $response = $this->client()
            ->delete("/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'message' => $commitMessage,
                'sha' => $sha,
                'branch' => $branchName,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to delete file: {$response->body()}");
        }

        return $this->openPullRequest($branchName, $commitMessage);
    }

    /** @param  array<int, string>  $imagePaths */
    private function commitImages(string $branch, array $imagePaths): void
    {
        foreach ($imagePaths as $repoPath) {
            $fullPath = base_path($repoPath);

            if (! file_exists($fullPath)) {
                continue;
            }

            $imageContent = file_get_contents($fullPath);

            $response = $this->client()
                ->put("/repos/{$this->owner}/{$this->repo}/contents/{$repoPath}", [
                    'message' => "docs: add image " . basename($repoPath),
                    'content' => base64_encode($imageContent),
                    'branch' => $branch,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException("Failed to upload image {$repoPath}: {$response->body()}");
            }
        }
    }

    private function getFileSha(string $path): string
    {
        $response = $this->client()
            ->get("/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'ref' => $this->baseBranch,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to get SHA for: {$path}");
        }

        return $response->json('sha');
    }

    private function createBranch(string $branchName): void
    {
        $response = $this->client()
            ->get("/repos/{$this->owner}/{$this->repo}/git/ref/heads/{$this->baseBranch}");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to get base branch SHA");
        }

        $baseSha = $response->json('object.sha');

        $this->client()->post("/repos/{$this->owner}/{$this->repo}/git/refs", [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $baseSha,
        ]);
    }

    private function commitFile(string $branch, string $path, string $content, string $sha, string $message): void
    {
        $response = $this->client()
            ->put("/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'message' => $message,
                'content' => base64_encode($content),
                'sha' => $sha,
                'branch' => $branch,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to commit file: {$response->body()}");
        }
    }

    private function createNewFile(string $branch, string $path, string $content, string $message): void
    {
        $response = $this->client()
            ->put("/repos/{$this->owner}/{$this->repo}/contents/{$path}", [
                'message' => $message,
                'content' => base64_encode($content),
                'branch' => $branch,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to create file: {$response->body()}");
        }
    }

    private function openPullRequest(string $branch, string $title): string
    {
        $response = $this->client()
            ->post("/repos/{$this->owner}/{$this->repo}/pulls", [
                'title' => $title,
                'head' => $branch,
                'base' => $this->baseBranch,
                'body' => "Docs update via admin panel.",
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to create PR: {$response->body()}");
        }

        return $response->json('html_url');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken($this->token)
            ->withHeaders([
                'Accept' => 'application/vnd.github.v3+json',
            ]);
    }
}
