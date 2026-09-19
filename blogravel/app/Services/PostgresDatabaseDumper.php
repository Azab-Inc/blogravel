<?php

namespace App\Services;

class PostgresDatabaseDumper implements DatabaseDumper
{
    public function dump(string $directory): string
    {
        $dbConfig = config('database.connections.'.config('database.default'));
        $dumpFile = $directory.'/database.sql';
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl -f %s 2>&1',
            escapeshellarg($dbConfig['password'] ?? ''),
            escapeshellarg($dbConfig['host'] ?? 'localhost'),
            escapeshellarg($dbConfig['port'] ?? 5432),
            escapeshellarg($dbConfig['username'] ?? 'postgres'),
            escapeshellarg($dbConfig['database'] ?? 'blogravel'),
            escapeshellarg($dumpFile),
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Database dump failed: '.implode("\n", $output));
        }

        return $dumpFile;
    }
}
