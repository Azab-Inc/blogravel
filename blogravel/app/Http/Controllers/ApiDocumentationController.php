<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class ApiDocumentationController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const DOCUMENTS = [
        'overview' => 'Overview',
        'authentication' => 'Authentication',
        'pagination' => 'Pagination',
        'errors' => 'Errors',
        'public-endpoints' => 'Public endpoints',
        'authenticated-endpoints' => 'Authenticated endpoints',
        'webhooks' => 'Webhooks',
    ];

    public function __invoke(): View
    {
        $environment = new Environment([
            'allow_unsafe_links' => false,
            'html_input' => 'strip',
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $converter = new MarkdownConverter($environment);

        $documents = collect(self::DOCUMENTS)
            ->map(fn (string $title, string $slug): array => [
                'slug' => $slug,
                'title' => $title,
                'html' => $converter->convert(
                    file_get_contents(base_path("docs/api/{$slug}.md")),
                )->getContent(),
            ])
            ->values();

        return view('docs.api', compact('documents'));
    }
}
