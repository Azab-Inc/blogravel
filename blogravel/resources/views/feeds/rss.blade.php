{{ '<?xml version="1.0" encoding="UTF-8"?>' }}
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>{{ $data['title'] }}</title>
        <link>{{ $data['link'] }}</link>
        <description>{{ $data['description'] }}</description>
        <atom:link href="{{ $data['url'] }}" rel="self" type="application/rss+xml"/>
        @foreach ($data['posts'] as $post)
            <item>
                <title><![CDATA[{{ $post['title'] }}]]></title>
                <link>{{ $data['link'] }}#post-{{ $post['slug'] }}</link>
                <guid isPermaLink="false">{{ $post['id'] }}</guid>
                <pubDate>{{ $post['published_at'] }}</pubDate>
                <dc:creator>{{ $post['author'] }}</dc:creator>
                @foreach ($post['categories'] as $category)
                    <category>{{ $category }}</category>
                @endforeach
                @if ($post['excerpt'])
                    <description><![CDATA[{{ $post['excerpt'] }}]]></description>
                @endif
                <content:encoded><![CDATA[{{ $post['content'] }}]]></content:encoded>
            </item>
        @endforeach
    </channel>
</rss>
