# Authentication

## API keys

Create an API key in the Blogravel admin panel. Send the plaintext key only in the `X-Api-Key` header. Blogravel stores a hash of the key and never accepts it as a query parameter.

```http
GET /api/v1/posts HTTP/1.1
Host: your-tenant.example
X-Api-Key: br_live_your_key
Accept: application/json
```

API keys can have these abilities:

| Ability | Used for |
| --- | --- |
| `read` | Reading posts, pages, categories, and tags |
| `write` | Creating, updating, and deleting content |
| `draft_read` | Reading authenticated drafts |

Keys may expire and are rate limited. A missing, invalid, or expired key returns a problem response with status `401`. A key without the required ability returns `403`.

## Session tokens

The login endpoint returns a Laravel Sanctum token for clients that authenticate with a user account.

```http
POST /api/v1/login HTTP/1.1
Host: your-tenant.example
Content-Type: application/json
Accept: application/json

{"email":"editor@example.com","password":"your-password"}
```

Use the returned token with `Authorization: Bearer <token>` for session-authenticated requests, then revoke it with `POST /api/v1/logout`.
