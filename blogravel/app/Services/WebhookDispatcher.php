<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\OutboundWebhook;

class WebhookDispatcher
{
    public function dispatch(string $event, array $payload, ?string $tenantId = null): void
    {
        $query = OutboundWebhook::where('is_active', true)
            ->whereJsonContains('events', $event);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $webhooks = $query->get();

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch($webhook, $event, $payload);
        }
    }
}
