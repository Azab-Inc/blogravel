<?php

namespace Database\Factories;

use App\Enums\BackupContent;
use App\Enums\BackupDestination;
use App\Models\BackupRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupRule>
 */
class BackupRuleFactory extends Factory
{
    protected $model = BackupRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true).' backup',
            'backup_content' => fake()->randomElement(BackupContent::cases()),
            'schedule' => '0 2 * * *',
            'destination' => BackupDestination::Email,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function withFtp(): static
    {
        return $this->state(fn () => [
            'destination' => BackupDestination::Both,
            'ftp_host' => 'ftp.example.com',
            'ftp_port' => 21,
            'ftp_user' => 'user',
            'ftp_pass' => 'pass',
            'ftp_path' => '/backups',
        ]);
    }
}
