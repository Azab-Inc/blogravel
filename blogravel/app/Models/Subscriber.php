<?php

namespace App\Models;

use App\Enums\SubscriberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'email', 'name', 'status'])]
class Subscriber extends Model
{
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'subscriber_category');
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
        ];
    }
}
