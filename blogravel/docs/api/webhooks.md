# Webhooks

| Method | Path | Authentication | Purpose |
| --- | --- | --- | --- |
| `POST` | `/api/v1/webhooks/soro` | HMAC signature | Receive Soro events |
| `POST` | `/api/v1/webhooks/stripe` | Stripe signature | Receive Stripe events |

Webhook requests are machine-to-machine endpoints, not API-key endpoints. Configure the corresponding secret and send the provider's signature headers. Do not expose webhook secrets in client-side code.
