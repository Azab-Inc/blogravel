<?php

namespace App\Models;

use App\Enums\BackupStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'backup_rule_id', 'filename', 'path', 'size_bytes',
    'disk', 'status', 'encrypted', 'delivered_via', 'error_message',
    'completed_at',
])]
class Backup extends BaseModel
{
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function backupRule(): BelongsTo
    {
        return $this->belongsTo(BackupRule::class);
    }

    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'encrypted' => 'boolean',
            'delivered_via' => 'array',
            'size_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function sizeFormatted(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
