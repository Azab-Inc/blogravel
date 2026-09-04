<?php

use App\Services\WordPress\WxrParser;

function createWxrXml(array $items = [], array $channelMeta = []): string
{
    $defaultChannel = [
        'title' => 'Test Blog',
        'link' => 'https://example.com',
        'description' => 'A test blog',
    ];
    $channel = array_merge($defaultChannel, $channelMeta);

    $itemsXml = '';
    foreach ($items as $item) {
        $type = $item['type'] ?? 'post';
        $status = $item['status'] ?? 'publish';
        $title = $item['title'] ?? 'Test Title';
        $content = $item['content'] ?? '<p>Hello World</p>';
        $slug = $item['slug'] ?? 'test-title';
        $postDate = $item['post_date'] ?? '2025-01-15 10:30:00';
        $excerpt = $item['excerpt'] ?? '';
        $authorName = $item['author'] ?? 'admin';

        $postTypeXml = "<wp:post_type>{$type}</wp:post_type>";
        $attachmentUrl = $item['attachment_url'] ?? '';
        $postMimeType = $item['post_mime_type'] ?? '';
        $attachmentUrlXml = $attachmentUrl !== '' ? "<wp:attachment_url><![CDATA[{$attachmentUrl}]]></wp:attachment_url>" : '';
        $postMimeTypeXml = $postMimeType !== '' ? "<wp:post_mime_type>{$postMimeType}</wp:post_mime_type>" : '';
        $categoriesXml = '';
        foreach ($item['categories'] ?? [] as $cat) {
            $categoriesXml .= "<category domain=\"category\" nicename=\"{$cat['slug']}\">{$cat['name']}</category>";
        }
        $tagsXml = '';
        foreach ($item['tags'] ?? [] as $tag) {
            $tagsXml .= "<category domain=\"post_tag\" nicename=\"{$tag['slug']}\">{$tag['name']}</category>";
        }
        $commentsXml = '';
        foreach ($item['comments'] ?? [] as $comment) {
            $commentAuthorName = $comment['author_name'] ?? 'Commenter';
            $commentAuthorEmail = $comment['author_email'] ?? 'commenter@example.com';
            $commentContent = $comment['content'] ?? 'Great post!';
            $commentStatus = $comment['status'] ?? 'approve';
            $commentDate = $comment['date'] ?? '2025-01-16 08:00:00';
            $commentsXml .= <<<XML
        <wp:comment>
            <wp:comment_author><![CDATA[{$commentAuthorName}]]></wp:comment_author>
            <wp:comment_author_email><![CDATA[{$commentAuthorEmail}]]></wp:comment_author_email>
            <wp:comment_content><![CDATA[{$commentContent}]]></wp:comment_content>
            <wp:comment_approved><![CDATA[{$commentStatus}]]></wp:comment_approved>
            <wp:comment_date><![CDATA[{$commentDate}]]></wp:comment_date>
        </wp:comment>
XML;
        }
        $attachmentsXml = '';
        foreach ($item['attachments'] ?? [] as $attachment) {
            $attachmentsXml .= <<<XML
    <item>
        <title>{$attachment['name']}</title>
        <link>{$attachment['url']}</link>
        <wp:post_type>attachment</wp:post_type>
        <wp:post_mime_type>{$attachment['mime_type']}</wp:post_mime_type>
        <wp:attachment_url><![CDATA[{$attachment['url']}]]></wp:attachment_url>
    </item>
XML;
        }

        $itemsXml .= <<<XML
    <item>
        <title><![CDATA[{$title}]]></title>
        <link>https://example.com/{$slug}</link>
        <content:encoded><![CDATA[{$content}]]></content:encoded>
        <excerpt:encoded><![CDATA[{$excerpt}]]></excerpt:encoded>
        <wp:post_name><![CDATA[{$slug}]]></wp:post_name>
        <wp:status><![CDATA[{$status}]]></wp:status>
        <wp:post_date><![CDATA[{$postDate}]]></wp:post_date>
        <dc:creator><![CDATA[{$authorName}]]></dc:creator>
        {$postTypeXml}
        {$postMimeTypeXml}
        {$attachmentUrlXml}
        {$categoriesXml}
        {$tagsXml}
        {$commentsXml}
    </item>
{$attachmentsXml}
XML;
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wfw="http://wellformedweb.org/CommentAPI/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
>
    <channel>
        <title>{$channel['title']}</title>
        <link>{$channel['link']}</link>
        <description>{$channel['description']}</description>
        <language>en-US</language>
{$itemsXml}
    </channel>
</rss>
XML;
}

it('parses channel metadata', function () {
    $xml = createWxrXml([], [
        'title' => 'My Blog',
        'link' => 'https://myblog.com',
        'description' => 'My awesome blog',
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    expect($result['channel'])
        ->toBeArray()
        ->toHaveKeys(['title', 'link', 'description'])
        ->and($result['channel']['title'])->toBe('My Blog')
        ->and($result['channel']['link'])->toBe('https://myblog.com')
        ->and($result['channel']['description'])->toBe('My awesome blog');
});

it('parses a post item with all fields', function () {
    $xml = createWxrXml([
        [
            'type' => 'post',
            'title' => 'Hello World',
            'content' => '<p>This is my first post</p>',
            'excerpt' => 'A brief summary',
            'slug' => 'hello-world',
            'status' => 'publish',
            'post_date' => '2025-03-20 14:00:00',
            'author' => 'John Doe',
            'categories' => [['name' => 'Tech', 'slug' => 'tech']],
            'tags' => [['name' => 'Laravel', 'slug' => 'laravel']],
            'comments' => [
                [
                    'author_name' => 'Jane',
                    'author_email' => 'jane@example.com',
                    'content' => 'Nice post!',
                    'status' => 'approve',
                    'date' => '2025-03-21 09:00:00',
                ],
            ],
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    expect($result['items'])->toHaveCount(1);
    $item = $result['items'][0];

    expect($item)
        ->toBeArray()
        ->toHaveKeys(['type', 'title', 'content', 'excerpt', 'slug', 'status', 'published_at', 'author', 'categories', 'tags', 'comments', 'attachments'])
        ->and($item['type'])->toBe('post')
        ->and($item['title'])->toBe('Hello World')
        ->and($item['content'])->toBe('<p>This is my first post</p>')
        ->and($item['excerpt'])->toBe('A brief summary')
        ->and($item['slug'])->toBe('hello-world')
        ->and($item['status'])->toBe('published')
        ->and($item['published_at'])->format('Y-m-d H:i:s')->toBe('2025-03-20 14:00:00')
        ->and($item['author']['name'])->toBe('John Doe')
        ->and($item['categories'])->toHaveCount(1)
        ->and($item['categories'][0])->toBe(['name' => 'Tech', 'slug' => 'tech'])
        ->and($item['tags'])->toHaveCount(1)
        ->and($item['tags'][0])->toBe(['name' => 'Laravel', 'slug' => 'laravel'])
        ->and($item['comments'])->toHaveCount(1)
        ->and($item['comments'][0]['author_name'])->toBe('Jane')
        ->and($item['comments'][0]['content'])->toBe('Nice post!')
        ->and($item['comments'][0]['status'])->toBe('approved')
        ->and($item['comments'][0]['published_at'])->format('Y-m-d H:i:s')->toBe('2025-03-21 09:00:00')
        ->and($item['attachments'])->toHaveCount(0);
});

it('parses a page item', function () {
    $xml = createWxrXml([
        [
            'type' => 'page',
            'title' => 'About Us',
            'content' => '<p>We are a company</p>',
            'slug' => 'about',
            'status' => 'publish',
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    expect($result['items'])->toHaveCount(1);
    $item = $result['items'][0];

    expect($item['type'])->toBe('page')
        ->and($item['title'])->toBe('About Us')
        ->and($item['slug'])->toBe('about')
        ->and($item['status'])->toBe('published')
        ->and($item['categories'])->toHaveCount(0)
        ->and($item['tags'])->toHaveCount(0)
        ->and($item['comments'])->toHaveCount(0);
});

it('maps post statuses correctly', function () {
    $statuses = [
        'publish' => 'published',
        'draft' => 'draft',
        'pending' => 'draft',
        'private' => 'published',
        'trash' => 'draft',
    ];

    foreach ($statuses as $wpStatus => $expected) {
        $xml = createWxrXml([
            [
                'type' => 'post',
                'status' => $wpStatus,
            ],
        ]);

        $parser = new WxrParser($xml);
        $result = $parser->parse();

        expect($result['items'][0]['status'])->toBe($expected, "Failed mapping for WP status: {$wpStatus}");
    }
});

it('maps comment statuses correctly', function () {
    $xml = createWxrXml([
        [
            'type' => 'post',
            'comments' => [
                ['author_name' => 'A', 'content' => 'a', 'status' => 'approve'],
                ['author_name' => 'B', 'content' => 'b', 'status' => 'hold'],
                ['author_name' => 'C', 'content' => 'c', 'status' => 'spam'],
                ['author_name' => 'D', 'content' => 'd', 'status' => '1'],
                ['author_name' => 'E', 'content' => 'e', 'status' => '0'],
            ],
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    $comments = $result['items'][0]['comments'];

    expect($comments[0]['status'])->toBe('approved')
        ->and($comments[1]['status'])->toBe('pending')
        ->and($comments[2]['status'])->toBe('spam')
        ->and($comments[3]['status'])->toBe('approved')
        ->and($comments[4]['status'])->toBe('pending');
});

it('parses categories and tags separately', function () {
    $xml = createWxrXml([
        [
            'type' => 'post',
            'categories' => [
                ['name' => 'Tech', 'slug' => 'tech'],
                ['name' => 'Laravel', 'slug' => 'laravel'],
            ],
            'tags' => [
                ['name' => 'PHP', 'slug' => 'php'],
                ['name' => 'Backend', 'slug' => 'backend'],
            ],
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    $item = $result['items'][0];

    expect($item['categories'])->toHaveCount(2)
        ->and($item['categories'][0]['name'])->toBe('Tech')
        ->and($item['categories'][1]['name'])->toBe('Laravel')
        ->and($item['tags'])->toHaveCount(2)
        ->and($item['tags'][0]['name'])->toBe('PHP')
        ->and($item['tags'][1]['name'])->toBe('Backend');
});

it('parses comments with author details', function () {
    $xml = createWxrXml([
        [
            'type' => 'post',
            'comments' => [
                [
                    'author_name' => 'Alice',
                    'author_email' => 'alice@test.com',
                    'content' => 'Excellent!',
                    'status' => 'approve',
                ],
                [
                    'author_name' => 'Bob',
                    'author_email' => 'bob@test.com',
                    'content' => 'Thanks for sharing',
                    'status' => 'hold',
                ],
            ],
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    $comments = $result['items'][0]['comments'];

    expect($comments)->toHaveCount(2)
        ->and($comments[0]['author_name'])->toBe('Alice')
        ->and($comments[0]['author_email'])->toBe('alice@test.com')
        ->and($comments[0]['content'])->toBe('Excellent!')
        ->and($comments[0]['status'])->toBe('approved')
        ->and($comments[1]['author_name'])->toBe('Bob')
        ->and($comments[1]['content'])->toBe('Thanks for sharing')
        ->and($comments[1]['status'])->toBe('pending');
});

it('parses attachment items', function () {
    $xml = createWxrXml([
        [
            'type' => 'attachment',
            'title' => 'photo.jpg',
            'slug' => 'photo',
            'status' => 'publish',
            'attachment_url' => 'https://example.com/uploads/photo.jpg',
            'post_mime_type' => 'image/jpeg',
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    expect($result['items'])->toHaveCount(1);
    $item = $result['items'][0];

    expect($item['type'])->toBe('attachment')
        ->and($item['title'])->toBe('photo.jpg')
        ->and($item['attachments'])->toHaveCount(1)
        ->and($item['attachments'][0]['url'])->toBe('https://example.com/uploads/photo.jpg')
        ->and($item['attachments'][0]['name'])->toBe('photo.jpg')
        ->and($item['attachments'][0]['mime_type'])->toBe('image/jpeg');
});

it('handles empty XML gracefully', function () {
    $parser = new WxrParser('');
    $result = $parser->parse();

    expect($result)->toBe(['channel' => [], 'items' => []]);
});

it('handles invalid XML gracefully', function () {
    $parser = new WxrParser('not valid xml <<<>>>');
    $result = $parser->parse();

    expect($result)->toBe(['channel' => [], 'items' => []]);
});

it('handles missing channel element', function () {
    $parser = new WxrParser('<?xml version="1.0"?><rss></rss>');
    $result = $parser->parse();

    expect($result)->toBe(['channel' => [], 'items' => []]);
});

it('parses multiple items of mixed types', function () {
    $xml = createWxrXml([
        [
            'type' => 'post',
            'title' => 'Blog Post',
            'status' => 'publish',
        ],
        [
            'type' => 'page',
            'title' => 'Contact Page',
            'status' => 'publish',
        ],
        [
            'type' => 'post',
            'title' => 'Draft Post',
            'status' => 'draft',
        ],
    ]);

    $parser = new WxrParser($xml);
    $result = $parser->parse();

    expect($result['items'])->toHaveCount(3)
        ->and($result['items'][0]['type'])->toBe('post')
        ->and($result['items'][0]['title'])->toBe('Blog Post')
        ->and($result['items'][1]['type'])->toBe('page')
        ->and($result['items'][1]['title'])->toBe('Contact Page')
        ->and($result['items'][2]['type'])->toBe('post')
        ->and($result['items'][2]['title'])->toBe('Draft Post')
        ->and($result['items'][2]['status'])->toBe('draft');
});

it('parses XML from a file', function () {
    $xml = createWxrXml([
        [
            'type' => 'post',
            'title' => 'File Post',
        ],
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'wxr_').'.xml';
    file_put_contents($tempFile, $xml);

    try {
        $parser = new WxrParser($tempFile);
        $result = $parser->parse();

        expect($result['items'])->toHaveCount(1)
            ->and($result['items'][0]['title'])->toBe('File Post');
    } finally {
        unlink($tempFile);
    }
});
