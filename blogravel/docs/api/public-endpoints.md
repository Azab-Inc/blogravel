# Public endpoints

Public content endpoints do not require an API key. The tenant is resolved from a tenant-specific request host or `?tenant=<tenant-id>` on a shared deployment.

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/public/{resource}` | List `posts`, `pages`, `categories`, or `tags` |
| `GET` | `/api/v1/public/{resource}/{id}` | Show one public resource |
| `POST` | `/api/v1/subscribe` | Subscribe an email address |
| `GET` | `/api/v1/confirm/{token}` | Confirm a subscription |
| `GET` | `/api/v1/unsubscribe/{token}` | Unsubscribe with a token |
| `DELETE` | `/api/v1/subscribers/{token}` | Delete a subscriber with a token |

## Subscribe

```javascript
const response = await fetch(
  'https://acmeio.blogravel.com/api/v1/subscribe',
  {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: 'reader@example.com' }),
  },
);

console.log(await response.json());
```
