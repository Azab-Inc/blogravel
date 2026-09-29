# Blogravel API

The Blogravel API is a JSON REST API for reading and managing blog content.

The examples below use `https://{{tenant_host}}`, a tenant-specific host. You can also pass `?tenant=<tenant-id>` when your deployment uses a shared host. API paths are versioned under `/api/v1`.

## Quick start

Public content does not require an API key. Resolve the tenant with the `Host` header or the `tenant` query parameter.

```http
GET /api/v1/public/posts?limit=15 HTTP/1.1
Host: {{tenant_host}}
Accept: application/json
```

Authenticated content uses an API key in the `X-Api-Key` header. See [Authentication](#authentication) for the available abilities.

## TypeScript

```typescript
type Post = { id: string; title: string; slug: string };

const response = await fetch(
  'https://{{tenant_host}}/api/v1/public/posts?limit=15',
  { headers: { Accept: 'application/json' } },
);

if (!response.ok) throw new Error(`Request failed: ${response.status}`);

const body = (await response.json()) as {
  data: Post[];
  next_page_url: string | null;
};

console.log(body.data);
```

## JavaScript

```javascript
const response = await fetch(
  'https://{{tenant_host}}/api/v1/public/posts?limit=15',
  { headers: { Accept: 'application/json' } },
);

if (!response.ok) throw new Error(`Request failed: ${response.status}`);

const body = await response.json();
console.log(body.data);
```

## PHP

```php
<?php

$ch = curl_init('https://{{tenant_host}}/api/v1/public/posts?limit=15');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);

$response = curl_exec($ch);
curl_close($ch);

$body = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
print_r($body['data']);
```

## .NET

```csharp
using System.Net.Http.Json;
using System.Text.Json;

using var client = new HttpClient();
client.DefaultRequestHeaders.Add("Accept", "application/json");

var body = await client.GetFromJsonAsync<JsonElement>(
    "https://{{tenant_host}}/api/v1/public/posts?limit=15");

Console.WriteLine(body.GetProperty("data"));
```
