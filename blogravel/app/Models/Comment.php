<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'post_id', 'author_name', 'author_email', 'content', 'status'])]
class Comment extends BaseModel
{
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }
}
