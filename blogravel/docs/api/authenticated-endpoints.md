# Authenticated endpoints

All content and draft routes in this section use `/api/v1` and require an API key with the listed ability. The Sanctum bearer token is only used by the logout endpoint.

## Content resources

Posts, pages, categories, and tags expose the same REST shape:

| Method | Path | Ability | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/v1/{resource}` | `read` | Cursor-paginated list |
| `GET` | `/api/v1/{resource}/{id}` | `read` | Show one resource |
| `POST` | `/api/v1/{resource}` | `write` | Create a resource |
| `PUT/PATCH` | `/api/v1/{resource}/{id}` | `write` | Update a resource |
| `DELETE` | `/api/v1/{resource}/{id}` | `write` | Delete a resource |

Replace `{resource}` with `posts`, `pages`, `categories`, or `tags`.

```php
<?php

$ch = curl_init('https://{{tenant_host}}/api/v1/posts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-Api-Key: br_live_your_key',
        'Accept: application/json',
    ],
]);

$body = json_decode(curl_exec($ch), true, flags: JSON_THROW_ON_ERROR);
curl_close($ch);
```

## Drafts

| Method | Path | Ability | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/v1/drafts` | `draft_read` | List drafts |
| `GET` | `/api/v1/drafts/{id}` | `draft_read` | Show one draft |
| `GET` | `/api/v1/drafts/{id}/preview` | Signed URL | Show a preview |
| `POST` | `/api/v1/drafts/{id}/preview-url` | `write` | Create a signed preview URL |

## Login and logout

| Method | Path | Authentication | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/login` | Credentials | Create a Sanctum token |
| `POST` | `/api/v1/logout` | Sanctum bearer token | Revoke the current token |
