<?php

namespace App\Models;

use App\Enums\AiProviderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'type', 'name', 'api_key', 'base_url', 'model', 'temperature', 'max_tokens', 'custom_template', 'enabled'])]
class AiProvider extends BaseModel
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AiProviderType::class,
            'api_key' => 'encrypted',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
