<?php

namespace App\Jobs;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\BackupRule;
use App\Models\Tenant;
use App\Notifications\BackupReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $backupRuleId,
    ) {}

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(): void
    {
        $rule = BackupRule::findOrFail($this->backupRuleId);
        $tenant = $rule->tenant;

        $backup = Backup::create([
            'tenant_id' => $tenant->id,
            'backup_rule_id' => $rule->id,
            'filename' => $this->generateFilename($rule),
            'path' => '',
            'status' => BackupStatus::Running,
        ]);

        try {
            $archivePath = $this->createArchive($rule, $tenant, $backup);

            // Encrypt with tenant key
            $encryptionKey = $this->getTenantKey($tenant);
            $encryptedPath = $archivePath.'.enc';
            $plainContent = Storage::disk('local')->get($archivePath);
            $encryptedContent = openssl_encrypt(
                $plainContent,
                'aes-256-cbc',
                $encryptionKey,
                0,
                substr(md5($tenant->id), 0, 16)
            );
            Storage::disk('local')->put($encryptedPath, $encryptedContent);
            Storage::disk('local')->delete($archivePath);

            $size = Storage::disk('local')->size($encryptedPath);

            // Check size limit
            $maxSizeMb = $tenant->plan->limit('backup_max_size_mb');
            if ($maxSizeMb && ($size / 1024 / 1024) > $maxSizeMb) {
                Storage::disk('local')->delete($encryptedPath);
                throw new \RuntimeException("Backup exceeds plan size limit of {$maxSizeMb}MB");
            }

            $backup->update([
                'path' => $encryptedPath,
                'size_bytes' => $size,
                'encrypted' => true,
                'status' => BackupStatus::Completed,
                'completed_at' => now(),
            ]);

            // Deliver
            $deliveredVia = $this->deliver($rule, $tenant, $backup);
            $backup->update(['delivered_via' => $deliveredVia]);

            // Update rule
            $rule->update(['last_run_at' => now()]);

            // Dispatch pruning
            PruneBackupsJob::dispatch($tenant->id);

        } catch (\Throwable $e) {
            $backup->update([
                'status' => BackupStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function generateFilename(BackupRule $rule): string
    {
        $content = $rule->backup_content->value;
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "{$content}-backup-{$timestamp}.tar.gz";
    }

    private function createArchive(BackupRule $rule, Tenant $tenant, Backup $backup): string
    {
        $tempDir = storage_path('app/private/tmp-backup-'.$tenant->id);
        app('files')->makeDirectory($tempDir, 0755, true, true);

        // Database dump
        if (in_array($rule->backup_content->value, ['database', 'both'])) {
            $this->dumpDatabase($tenant, $tempDir);
        }

        // Media files
        if (in_array($rule->backup_content->value, ['files', 'both'])) {
            $mediaPath = storage_path('app/public/media');
            if (is_dir($mediaPath)) {
                app('files')->copyDirectory($mediaPath, $tempDir.'/media');
            }
        }

        $archivePath = 'backups/'.$backup->filename;
        $fullArchivePath = storage_path('app/private/'.$archivePath);
        app('files')->makeDirectory(dirname($fullArchivePath), 0755, true, true);

        // Create tar.gz
        $command = sprintf(
            'tar -czf %s -C %s . 2>&1',
            escapeshellarg($fullArchivePath),
            escapeshellarg($tempDir)
        );
        exec($command, $output, $returnCode);

        // Cleanup temp
        app('files')->deleteDirectory($tempDir);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to create backup archive: '.implode("\n", $output));
        }

        return $archivePath;
    }

    private function dumpDatabase(Tenant $tenant, string $tempDir): string
    {
        $dbConfig = config('database.connections.'.config('database.default'));
        $dumpFile = $tempDir.'/database.sql';

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl -f %s 2>&1',
            escapeshellarg($dbConfig['password'] ?? ''),
            escapeshellarg($dbConfig['host'] ?? 'localhost'),
            escapeshellarg($dbConfig['port'] ?? 5432),
            escapeshellarg($dbConfig['username'] ?? 'postgres'),
            escapeshellarg($dbConfig['database'] ?? 'blogravel'),
            escapeshellarg($dumpFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Database dump failed: '.implode("\n", $output));
        }

        return $dumpFile;
    }

    private function getTenantKey(Tenant $tenant): string
    {
        return hash('sha256', config('app.key').$tenant->id);
    }

    private function deliver(BackupRule $rule, Tenant $tenant, Backup $backup): array
    {
        $deliveredVia = [];

        if (in_array($rule->destination->value, ['email', 'both'])) {
            $this->deliverViaEmail($rule, $tenant, $backup);
            $deliveredVia[] = 'email';
        }

        if (in_array($rule->destination->value, ['ftp', 'both']) && $rule->ftp_host) {
            $this->deliverViaFtp($rule, $backup);
            $deliveredVia[] = 'ftp';
        }

        return $deliveredVia;
    }

    private function deliverViaEmail(BackupRule $rule, Tenant $tenant, Backup $backup): void
    {
        $recipient = $rule->email_recipient
            ?: $tenant->users()->where('role', 'admin')->value('email');

        if (! $recipient) {
            throw new \RuntimeException('No email recipient is configured for this backup rule.');
        }

        Notification::route('mail', $recipient)
            ->notify(new BackupReadyNotification($backup));
    }

    private function deliverViaFtp(BackupRule $rule, Backup $backup): void
    {
        $conn = ftp_connect($rule->ftp_host, $rule->ftp_port);
        if (! $conn) {
            throw new \RuntimeException('FTP connection failed to '.$rule->ftp_host);
        }

        if (! ftp_login($conn, $rule->ftp_user, $rule->ftp_pass)) {
            ftp_close($conn);
            throw new \RuntimeException('FTP login failed');
        }

        ftp_pasv($conn, true);
        ftp_mkdir($conn, $rule->ftp_path);
        $localPath = storage_path('app/private/'.$backup->path);
        $remotePath = $rule->ftp_path.'/'.$backup->filename;

        if (! ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
            ftp_close($conn);
            throw new \RuntimeException('FTP upload failed');
        }

        ftp_close($conn);
    }
}
