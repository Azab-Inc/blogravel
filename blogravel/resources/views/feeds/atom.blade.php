{{ '<?xml version="1.0" encoding="UTF-8"?>' }}
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $data['title'] }}</title>
    <link href="{{ $data['link'] }}" rel="alternate"/>
    <link href="{{ $data['url'] }}" rel="self" type="application/atom+xml"/>
    <id>{{ $data['url'] }}</id>
    <updated>{{ $data['posts'][0]['published_iso'] ?? now()->toRfc3339String() }}</updated>
    @foreach ($data['posts'] as $post)
        <entry>
            <title><![CDATA[{{ $post['title'] }}]]></title>
            <link href="{{ $data['link'] }}#post-{{ $post['slug'] }}"/>
            <id>urn:uuid:{{ $post['id'] }}</id>
            <updated>{{ $post['published_iso'] }}</updated>
            <author>
                <name>{{ $post['author'] }}</name>
            </author>
            @foreach ($post['categories'] as $category)
                <category term="{{ $category }}"/>
            @endforeach
            @if ($post['excerpt'])
                <summary><![CDATA[{{ $post['excerpt'] }}]]></summary>
            @endif
            <content type="html"><![CDATA[{{ $post['content'] }}]]></content>
        </entry>
    @endforeach
</feed>
