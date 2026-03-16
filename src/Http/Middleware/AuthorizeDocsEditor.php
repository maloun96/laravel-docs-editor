<?php

declare(strict_types=1);

namespace Maloun96\DocsEditor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Maloun96\DocsEditor\DocsEditor;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizeDocsEditor
{
    public function handle(Request $request, Closure $next): Response
    {
        $callback = DocsEditor::authCallback();

        if ($callback && ! $callback($request)) {
            abort(403);
        }

        return $next($request);
    }
}
