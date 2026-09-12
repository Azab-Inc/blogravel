<?php

namespace App\Models;

use App\Enums\BackupContent;
use App\Enums\BackupDestination;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'name', 'backup_content', 'schedule', 'destination',
    'email_recipient', 'ftp_host', 'ftp_port', 'ftp_user', 'ftp_pass', 'ftp_path',
    'enabled', 'last_run_at', 'next_run_at',
])]
class BackupRule extends BaseModel
{
    use HasFactory;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    protected function casts(): array
    {
        return [
            'backup_content' => BackupContent::class,
            'destination' => BackupDestination::class,
            'enabled' => 'boolean',
            'ftp_port' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
