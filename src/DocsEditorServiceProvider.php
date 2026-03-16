<?php

declare(strict_types=1);

namespace Maloun96\DocsEditor;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Maloun96\DocsEditor\Http\Controllers\DocsController;
use Maloun96\DocsEditor\Services\DocsFileService;
use Maloun96\DocsEditor\Services\GitHubDocsService;

final class DocsEditorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'docs-editor');

        $this->publishes([
            __DIR__ . '/../config/docs-editor.php' => config_path('docs-editor.php'),
        ], 'docs-editor-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/docs-editor'),
        ], 'docs-editor-views');

        $this->registerRoutes();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/docs-editor.php', 'docs-editor');

        $this->app->singleton(DocsFileService::class, function (): DocsFileService {
            return new DocsFileService(
                docsPath: base_path(config('docs-editor.docs_path')),
                mediaPath: base_path(config('docs-editor.media_path')),
            );
        });

        $this->app->singleton(GitHubDocsService::class, function (): GitHubDocsService {
            return new GitHubDocsService(
                token: config('docs-editor.github.token', ''),
                owner: config('docs-editor.github.owner', ''),
                repo: config('docs-editor.github.repo', ''),
                baseBranch: config('docs-editor.github.base_branch', 'main'),
            );
        });
    }

    private function registerRoutes(): void
    {
        $config = config('docs-editor.route');

        Route::middleware($config['middleware'])
            ->prefix($config['prefix'])
            ->name('docs-editor.')
            ->group(function () {
                Route::get('/', [DocsController::class, 'index'])->name('index');
                Route::get('/edit', [DocsController::class, 'edit'])->name('edit');
                Route::get('/create', [DocsController::class, 'create'])->name('create');
                Route::post('/store', [DocsController::class, 'store'])->name('store');
                Route::post('/upload-image', [DocsController::class, 'uploadImage'])->name('uploadImage');
                Route::post('/update', [DocsController::class, 'update'])->name('update');
                Route::post('/delete', [DocsController::class, 'destroy'])->name('destroy');
            });

        Route::get('docs-media/{path}', function (string $path) {
            $fullPath = base_path(config('docs-editor.media_path') . '/' . $path);

            if (! file_exists($fullPath)) {
                abort(404);
            }

            return response()->file($fullPath);
        })->where('path', '.*')->name('docs-editor.media');
    }
}
