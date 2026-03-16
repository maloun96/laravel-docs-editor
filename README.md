# Laravel Docs Editor

Admin panel for editing markdown documentation with GitHub PR workflow. Edit docs in a browser, create PRs automatically.

## Features

- File tree sidebar with search
- Markdown editor with live preview (split view)
- SEO frontmatter management (title, description, keywords, noindex)
- Image upload (drag & drop, paste, file picker)
- GitHub PR workflow — every save creates a branch + PR
- Create and delete pages
- Fullscreen mode
- LocalStorage preferences (tab mode, SEO panel state)

## Installation

```bash
composer require maloun96/laravel-docs-editor
```

Publish the config:

```bash
php artisan vendor:publish --tag=docs-editor-config
```

## Configuration

Add to your `.env`:

```env
DOCS_EDITOR_GITHUB_TOKEN=ghp_xxx
DOCS_EDITOR_GITHUB_OWNER=your-org
DOCS_EDITOR_GITHUB_REPO=your-repo
DOCS_EDITOR_GITHUB_BRANCH=main
DOCS_EDITOR_DOCS_PATH=docs
DOCS_EDITOR_MEDIA_PATH=public/docs-media
DOCS_EDITOR_LIVE_URL=https://your-site.com/docs
DOCS_EDITOR_ROUTE_PREFIX=admin/docs
```

### Config options

| Key | Description | Default |
|-----|-------------|---------|
| `github.token` | GitHub personal access token (Contents + PRs read/write) | — |
| `github.owner` | GitHub org or user | — |
| `github.repo` | Repository name | — |
| `github.base_branch` | Branch to create PRs against | `main` |
| `docs_path` | Path to markdown files relative to `base_path()` | `docs` |
| `media_path` | Path to media/images relative to `base_path()` | `public/docs-media` |
| `live_url` | Base URL for "View Live" links (empty to hide) | — |
| `route.prefix` | URL prefix for the editor | `admin/docs` |
| `route.middleware` | Middleware for editor routes | `['web']` |

## Publish views (optional)

```bash
php artisan vendor:publish --tag=docs-editor-views
```

## GitHub Token

Create a fine-grained personal access token with:
- **Repository access**: Only the target repo
- **Permissions**: Contents (read/write), Pull requests (read/write)

## License

MIT
