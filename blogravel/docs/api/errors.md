# Errors

API exceptions use [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem details when the request path starts with `/api/`. Validation and authentication failures retain their standard Laravel JSON shapes. Rate-limit responses are also a JSON exception with `message` and `retry_after` fields plus rate-limit headers.

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json

{
  "type": "https://tools.ietf.org/html/rfc9110#section-15.6.1",
  "title": "Error",
  "status": 401,
  "detail": "API key required."
}
```

Handle the HTTP status before decoding a success payload:

```typescript
const response = await fetch(url);
const body = await response.json();

if (!response.ok) {
  throw new Error(`${body.title}: ${body.detail}`);
}
```

Common statuses are `400` for malformed requests, `401` for missing or invalid credentials, `403` for insufficient abilities, `404` for missing resources, `409` for conflicts, `422` for validation errors, and `429` for rate limits. Validation responses retain Laravel's JSON shape with an `errors` object. Rate-limited responses also include `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining` headers.
