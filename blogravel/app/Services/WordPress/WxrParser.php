<?php

namespace App\Services\WordPress;

use Carbon\Carbon;
use SimpleXMLElement;
use Throwable;

class WxrParser
{
    private readonly string $xml;

    private const WP_POST_STATUS_MAP = [
        'publish' => 'published',
        'draft' => 'draft',
        'pending' => 'draft',
        'private' => 'published',
        'trash' => 'draft',
    ];

    private const WP_COMMENT_STATUS_MAP = [
        'approve' => 'approved',
        'approved' => 'approved',
        '1' => 'approved',
        'hold' => 'pending',
        '0' => 'pending',
        'spam' => 'spam',
    ];

    private const WP_NAMESPACES = [
        'content' => 'http://purl.org/rss/1.0/modules/content/',
        'wfw' => 'http://wellformedweb.org/CommentAPI/',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'wp' => 'http://wordpress.org/export/1.2/',
        'excerpt' => 'http://wordpress.org/export/1.2/excerpt/',
        'media' => 'http://search.yahoo.com/mrss/',
    ];

    public function __construct(string $xmlOrPath)
    {
        if (is_file($xmlOrPath)) {
            $this->xml = file_get_contents($xmlOrPath) ?: '';
        } else {
            $this->xml = $xmlOrPath;
        }
    }

    public function parse(): array
    {
        if ($this->xml === '' || trim($this->xml) === '') {
            return ['channel' => [], 'items' => []];
        }

        try {
            $xml = new SimpleXMLElement($this->xml);
        } catch (Throwable) {
            return ['channel' => [], 'items' => []];
        }

        $channel = $xml->channel ?? null;
        if ($channel === null) {
            return ['channel' => [], 'items' => []];
        }

        $this->registerNamespaces($xml);

        return [
            'channel' => $this->parseChannel($channel),
            'items' => $this->parseItems($channel),
        ];
    }

    private function registerNamespaces(SimpleXMLElement $xml): void
    {
        foreach (self::WP_NAMESPACES as $prefix => $uri) {
            try {
                $xml->registerXPathNamespace($prefix, $uri);
            } catch (Throwable) {
                // Namespace may already be registered or not present
            }
        }
    }

    private function parseChannel(SimpleXMLElement $channel): array
    {
        return [
            'title' => $this->getTextContent($channel, 'title'),
            'link' => $this->getTextContent($channel, 'link'),
            'description' => $this->getTextContent($channel, 'description'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function parseItems(SimpleXMLElement $channel): array
    {
        $items = [];

        foreach ($channel->item as $item) {
            $this->registerNamespaces($item);

            $postType = $this->getWpElement($item, 'post_type');
            if ($postType === 'attachment') {
                $items[] = $this->parseAttachmentItem($item);
            } elseif ($postType === 'page') {
                $items[] = $this->parsePageItem($item);
            } else {
                $items[] = $this->parsePostItem($item);
            }
        }

        return $items;
    }

    private function parsePostItem(SimpleXMLElement $item): array
    {
        return [
            'type' => 'post',
            'title' => $this->getTextContent($item, 'title'),
            'content' => $this->getWpContent($item),
            'excerpt' => $this->getExcerptContent($item),
            'slug' => $this->getWpElement($item, 'post_name'),
            'status' => $this->mapPostStatus($this->getWpElement($item, 'status')),
            'published_at' => $this->parseDate($this->getWpElement($item, 'post_date')),
            'author' => [
                'name' => $this->getDcElement($item, 'creator'),
                'email' => '',
            ],
            'categories' => $this->parseCategories($item),
            'tags' => $this->parseTags($item),
            'comments' => $this->parseComments($item),
            'attachments' => [],
        ];
    }

    private function parsePageItem(SimpleXMLElement $item): array
    {
        return [
            'type' => 'page',
            'title' => $this->getTextContent($item, 'title'),
            'content' => $this->getWpContent($item),
            'excerpt' => '',
            'slug' => $this->getWpElement($item, 'post_name'),
            'status' => $this->mapPostStatus($this->getWpElement($item, 'status')),
            'published_at' => $this->parseDate($this->getWpElement($item, 'post_date')),
            'author' => [
                'name' => $this->getDcElement($item, 'creator'),
                'email' => '',
            ],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [],
        ];
    }

    private function parseAttachmentItem(SimpleXMLElement $item): array
    {
        return [
            'type' => 'attachment',
            'title' => $this->getTextContent($item, 'title'),
            'content' => $this->getWpContent($item),
            'excerpt' => '',
            'slug' => $this->getWpElement($item, 'post_name'),
            'status' => $this->mapPostStatus($this->getWpElement($item, 'status')),
            'published_at' => $this->parseDate($this->getWpElement($item, 'post_date')),
            'author' => [
                'name' => $this->getDcElement($item, 'creator'),
                'email' => '',
            ],
            'categories' => [],
            'tags' => [],
            'comments' => [],
            'attachments' => [
                [
                    'url' => $this->getAttachmentUrl($item),
                    'name' => $this->getTextContent($item, 'title'),
                    'mime_type' => $this->getWpElement($item, 'post_mime_type'),
                ],
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function parseCategories(SimpleXMLElement $item): array
    {
        $categories = [];
        foreach ($item->category as $category) {
            $domain = (string) ($category['domain'] ?? '');
            if ($domain !== 'category') {
                continue;
            }
            $categories[] = [
                'name' => (string) $category,
                'slug' => (string) ($category['nicename'] ?? ''),
            ];
        }

        return $categories;
    }

    /** @return array<int, array<string, string>> */
    private function parseTags(SimpleXMLElement $item): array
    {
        $tags = [];
        foreach ($item->category as $category) {
            $domain = (string) ($category['domain'] ?? '');
            if ($domain !== 'post_tag') {
                continue;
            }
            $tags[] = [
                'name' => (string) $category,
                'slug' => (string) ($category['nicename'] ?? ''),
            ];
        }

        return $tags;
    }

    /** @return array<int, array<string, string>> */
    private function parseComments(SimpleXMLElement $item): array
    {
        $comments = [];
        $nsUri = $this->getNamespaceUri($item, 'wp');
        if ($nsUri === null) {
            return [];
        }

        foreach ($item->children($nsUri) as $child) {
            if ($child->getName() !== 'comment') {
                continue;
            }

            $comments[] = [
                'author_name' => $this->getWpElementFromChild($child, 'comment_author'),
                'author_email' => $this->getWpElementFromChild($child, 'comment_author_email'),
                'content' => $this->getWpElementFromChild($child, 'comment_content'),
                'status' => $this->mapCommentStatus($this->getWpElementFromChild($child, 'comment_approved')),
                'published_at' => $this->parseDate($this->getWpElementFromChild($child, 'comment_date')),
            ];
        }

        return $comments;
    }

    private function getTextContent(SimpleXMLElement $element, string $name): string
    {
        return (string) ($element->$name ?? '');
    }

    private function getWpElement(SimpleXMLElement $element, string $name): string
    {
        $nsUri = $this->getNamespaceUri($element, 'wp');
        if ($nsUri === null) {
            return '';
        }

        foreach ($element->children($nsUri) as $child) {
            if ($child->getName() === $name) {
                return (string) $child;
            }
        }

        return '';
    }

    private function getWpElementFromChild(SimpleXMLElement $child, string $name): string
    {
        $nsUri = $this->getNamespaceUri($child, 'wp');
        if ($nsUri === null) {
            return '';
        }

        foreach ($child->children($nsUri) as $wpChild) {
            if ($wpChild->getName() === $name) {
                return (string) $wpChild;
            }
        }

        return '';
    }

    private function getWpContent(SimpleXMLElement $item): string
    {
        $nsUri = $this->getNamespaceUri($item, 'content');
        if ($nsUri === null) {
            return '';
        }

        foreach ($item->children($nsUri) as $child) {
            if ($child->getName() === 'encoded') {
                return (string) $child;
            }
        }

        return '';
    }

    private function getExcerptContent(SimpleXMLElement $item): string
    {
        $nsUri = $this->getNamespaceUri($item, 'excerpt');
        if ($nsUri === null) {
            return '';
        }

        foreach ($item->children($nsUri) as $child) {
            if ($child->getName() === 'encoded') {
                return (string) $child;
            }
        }

        return '';
    }

    private function getDcElement(SimpleXMLElement $element, string $name): string
    {
        $namespaces = $element->getNamespaces(true);
        $dcNs = null;
        foreach ($namespaces as $prefix => $uri) {
            if ($prefix === 'dc') {
                $dcNs = $uri;
                break;
            }
        }

        if ($dcNs === null) {
            return '';
        }

        foreach ($element->children($dcNs) as $child) {
            if ($child->getName() === $name) {
                return (string) $child;
            }
        }

        return '';
    }

    private function getAttachmentUrl(SimpleXMLElement $item): string
    {
        $nsUri = $this->getNamespaceUri($item, 'wp');
        if ($nsUri === null) {
            return '';
        }

        foreach ($item->children($nsUri) as $child) {
            if ($child->getName() === 'attachment_url') {
                return (string) $child;
            }
        }

        return '';
    }

    private function getNamespaceUri(SimpleXMLElement $element, string $prefix): ?string
    {
        $namespaces = $element->getNamespaces(true);
        foreach ($namespaces as $nsPrefix => $uri) {
            if ($nsPrefix === $prefix) {
                return $uri;
            }
        }

        return null;
    }

    private function mapPostStatus(?string $wpStatus): string
    {
        if ($wpStatus === null || $wpStatus === '') {
            return 'draft';
        }

        return self::WP_POST_STATUS_MAP[$wpStatus] ?? 'draft';
    }

    private function mapCommentStatus(?string $wpStatus): string
    {
        if ($wpStatus === null || $wpStatus === '') {
            return 'pending';
        }

        return self::WP_COMMENT_STATUS_MAP[$wpStatus] ?? 'pending';
    }

    private function parseDate(?string $dateString): ?Carbon
    {
        if ($dateString === null || $dateString === '') {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (Throwable) {
            return null;
        }
    }
}
