<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PostgreSqlBackupProvider
{
    /**
     * Dump the PostgreSQL database to the specified SQL file.
     *
     * @param  array{host?: string, port?: int|string, database: string, username?: string, password?: string}  $config
     */
    public function dump(string $targetSql, array $config): void
    {
        $binary = $this->resolvePgDumpBinary();

        $command = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 5432),
            '--username='.($config['username'] ?? 'postgres'),
            '--clean',
            '--if-exists',
            '--no-owner',
            '--file='.$targetSql,
            $config['database'],
        ];

        $env = [];
        if (! empty($config['password'])) {
            $env['PGPASSWORD'] = $config['password'];
        }

        $result = Process::env($env)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException("PostgreSQL pg_dump failed (exit code {$result->exitCode()}): ".$result->errorOutput());
        }
    }

    /**
     * Restore the PostgreSQL database from the specified SQL file.
     *
     * @param  array{host?: string, port?: int|string, database: string, username?: string, password?: string}  $config
     */
    public function restore(string $sqlFile, array $config): void
    {
        $binary = $this->resolvePsqlBinary();

        $command = [
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 5432),
            '--username='.($config['username'] ?? 'postgres'),
            '--dbname='.$config['database'],
            '--file='.$sqlFile,
        ];

        $env = [];
        if (! empty($config['password'])) {
            $env['PGPASSWORD'] = $config['password'];
        }

        $result = Process::env($env)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException("PostgreSQL psql restore failed (exit code {$result->exitCode()}): ".$result->errorOutput());
        }
    }

    /**
     * Resolve the path to the pg_dump executable.
     */
    protected function resolvePgDumpBinary(): string
    {
        $custom = config('database.connections.pgsql.dump.dump_binary_path');
        if (is_string($custom) && $custom !== '' && file_exists($custom)) {
            return $custom;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
                'C:\\Program Files\\PostgreSQL\\15\\bin\\pg_dump.exe',
                'C:\\laragon\\bin\\postgresql\\current\\bin\\pg_dump.exe',
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return 'pg_dump';
    }

    /**
     * Resolve the path to the psql executable.
     */
    protected function resolvePsqlBinary(): string
    {
        $custom = config('database.connections.pgsql.dump.psql_binary_path');
        if (is_string($custom) && $custom !== '' && file_exists($custom)) {
            return $custom;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'C:\\Program Files\\PostgreSQL\\16\\bin\\psql.exe',
                'C:\\Program Files\\PostgreSQL\\15\\bin\\psql.exe',
                'C:\\laragon\\bin\\postgresql\\current\\bin\\psql.exe',
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return 'psql';
    }
}
