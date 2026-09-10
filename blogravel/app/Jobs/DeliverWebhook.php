<?php

namespace App\Jobs;

use App\Models\OutboundWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DeliverWebhook implements ShouldQueue
{
    use QueueableTrait;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public OutboundWebhook $webhook,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::create([
            'outbound_webhook_id' => $this->webhook->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $body = json_encode($this->payload);
        $signature = $this->webhook->secret
            ? hash_hmac('sha256', $body, $this->webhook->secret)
            : null;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Event' => $this->event,
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Timestamp' => now()->toIso8601String(),
            ])->timeout(10)->post($this->webhook->url, $this->payload);

            $delivery->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            if ($response->failed()) {
                $this->fail(new \Exception('Webhook returned status '.$response->status()));
            }
        } catch (ConnectionException $e) {
            $delivery->update([
                'status' => 'failed',
                'response_body' => $e->getMessage(),
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => now(),
            ]);

            $this->fail($e);
        }
    }
}
