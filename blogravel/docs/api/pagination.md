# Pagination

Collection endpoints use cursor pagination. Pass `limit` from `1` to `100`; the default is `15`. Use the opaque `cursor` from the response's `next_page_url` for the next request.

```http
GET /api/v1/public/posts?limit=25&cursor=eyJpZCI6MTV9 HTTP/1.1
Host: acmeio.blogravel.com
Accept: application/json
```

The `fields` parameter controls the cursor ordering. Supported fields depend on the resource. For posts, valid fields include `id`, `created_at`, `published_at`, and `title`.

```javascript
const firstPage = await fetch(
  'https://acmeio.blogravel.com/api/v1/public/posts?limit=25',
).then((response) => response.json());

const nextPage = firstPage.next_page_url
  ? await fetch(firstPage.next_page_url).then((response) => response.json())
  : null;
```
