<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outbound_webhook_id', 'event', 'payload', 'status', 'response_code', 'response_body', 'attempts', 'last_attempt_at'])]
class WebhookDelivery extends BaseModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function outboundWebhook(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhook::class);
    }
}
