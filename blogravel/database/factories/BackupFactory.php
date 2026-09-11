<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'backup_rule_id' => null,
            'filename' => 'backup-'.now()->timestamp.'.tar.gz',
            'path' => 'backups/backup-'.now()->timestamp.'.tar.gz',
            'size_bytes' => fake()->numberBetween(1024, 104857600),
            'disk' => 'local',
            'status' => BackupStatus::Completed,
            'encrypted' => false,
            'delivered_via' => ['email'],
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => BackupStatus::Pending,
            'completed_at' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => BackupStatus::Running,
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => BackupStatus::Failed,
            'completed_at' => null,
            'error_message' => 'Backup failed',
        ]);
    }

    public function encrypted(): static
    {
        return $this->state(fn () => ['encrypted' => true]);
    }
}
