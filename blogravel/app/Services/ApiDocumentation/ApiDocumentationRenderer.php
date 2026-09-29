<?php

namespace App\Services\ApiDocumentation;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

final class ApiDocumentationRenderer
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

    public function render(?TenantDocumentationContext $context): Collection
    {
        if ($context === null) {
            return collect();
        }

        $environment = new Environment([
            'allow_unsafe_links' => false,
            'html_input' => 'strip',
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $converter = new MarkdownConverter($environment);

        return collect(self::DOCUMENTS)
            ->map(fn (string $title, string $slug): array => [
                'slug' => $slug,
                'title' => $title,
                'html' => $converter->convert(
                    str_replace(
                        '{{tenant_host}}',
                        $context->host(),
                        file_get_contents(base_path("docs/api/{$slug}.md")),
                    ),
                )->getContent(),
            ])
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function tenantOptions(): array
    {
        return Tenant::query()
            ->orderBy('name')
            ->pluck('name', 'slug')
            ->all();
    }
}
