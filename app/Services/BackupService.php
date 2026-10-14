<?php

namespace App\Services;

use App\Services\Backup\PostgreSqlBackupProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public function __construct(
        protected ?PostgreSqlBackupProvider $postgreSqlProvider = null
    ) {
        $this->postgreSqlProvider ??= new PostgreSqlBackupProvider;
    }

    /**
     * Create a timestamped backup archive of the database and storage.
     *
     * @param  string|null  $customName  Optional custom filename for the backup ZIP.
     * @param  bool  $onlyDb  If true, backup database only (no storage).
     * @param  bool  $onlyStorage  If true, backup storage only (no database).
     * @param  string|null  $customPath  Optional target directory.
     * @return string Absolute path to the created backup archive.
     */
    public function createBackup(
        ?string $customName = null,
        bool $onlyDb = false,
        bool $onlyStorage = false,
        ?string $customPath = null
    ): string {
        if ($onlyDb && $onlyStorage) {
            throw new InvalidArgumentException('Cannot specify both only-db and only-storage.');
        }

        $filename = $customName ?: 'backup-'.now()->format('Y-m-d_His').'.zip';
        if (! str_ends_with(strtolower($filename), '.zip')) {
            $filename .= '.zip';
        }

        $targetDirectory = $customPath ?: storage_path('app/backups');
        File::ensureDirectoryExists($targetDirectory);
        $archivePath = $targetDirectory.DIRECTORY_SEPARATOR.$filename;

        $stagingBase = storage_path('framework/cache/backup_temp');
        $stagingDir = $stagingBase.DIRECTORY_SEPARATOR.'staging_'.Str::random(12);
        File::ensureDirectoryExists($stagingDir);

        $stagedEntries = [];

        try {
            $connection = config('database.default');
            $driver = (string) config("database.connections.{$connection}.driver");

            // 1. Database backup
            if (! $onlyStorage) {
                $targetSql = $stagingDir.DIRECTORY_SEPARATOR.'database.sql';

                if ($driver === 'sqlite') {
                    $this->dumpSqlite($targetSql);
                } elseif ($driver === 'pgsql') {
                    $dbConfig = $this->getPostgreSqlConfig($connection);
                    $this->postgreSqlProvider->dump($targetSql, $dbConfig);
                } else {
                    throw new RuntimeException("Unsupported database driver for backup: {$driver}");
                }

                $stagedEntries['database.sql'] = $targetSql;
            }

            // 2. Storage backup
            if (! $onlyDb) {
                $privateDir = storage_path('app/private');
                if (File::isDirectory($privateDir)) {
                    foreach (File::allFiles($privateDir) as $file) {
                        $relPath = str_replace('\\', '/', $file->getRelativePathname());
                        $stagedEntries['storage/private/'.$relPath] = $file->getRealPath();
                    }
                }

                $publicDir = storage_path('app/public');
                if (File::isDirectory($publicDir)) {
                    foreach (File::allFiles($publicDir) as $file) {
                        $relPath = str_replace('\\', '/', $file->getRelativePathname());
                        $stagedEntries['storage/public/'.$relPath] = $file->getRealPath();
                    }
                }
            }

            // 3. Compute checksum
            ksort($stagedEntries);

            $ctx = hash_init('sha256');
            $totalBytes = 0;
            $storageFileCount = 0;

            foreach ($stagedEntries as $relPath => $absPath) {
                $fileHash = hash_file('sha256', $absPath);
                hash_update($ctx, $relPath.':'.$fileHash."\n");
                $size = filesize($absPath);
                $totalBytes += $size;
                if (str_starts_with($relPath, 'storage/')) {
                    $storageFileCount++;
                }
            }
            $checksum = hash_final($ctx);

            // 4. Generate manifest.json
            $manifest = [
                'system' => 'Barangay Sta. Felomina Document Archiving System',
                'version' => '1.0',
                'created_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'generator' => 'php artisan backup:create',
                'database' => [
                    'driver' => $driver,
                    'database' => config("database.connections.{$connection}.database"),
                    'included' => ! $onlyStorage,
                    'dump_file' => ! $onlyStorage ? 'database.sql' : null,
                ],
                'storage' => [
                    'private_included' => ! $onlyDb,
                    'public_included' => ! $onlyDb,
                    'file_count' => $storageFileCount,
                    'total_bytes' => $totalBytes,
                ],
                'checksum' => $checksum,
            ];

            $manifestPath = $stagingDir.DIRECTORY_SEPARATOR.'manifest.json';
            $jsonManifest = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($jsonManifest === false) {
                throw new RuntimeException('Failed to encode backup manifest to JSON.');
            }
            File::put($manifestPath, $jsonManifest);

            // 5. Build ZIP archive
            $zip = new ZipArchive;
            $openResult = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new RuntimeException("Could not create ZIP archive at: {$archivePath}");
            }

            $zip->addFile($manifestPath, 'manifest.json');
            foreach ($stagedEntries as $relPath => $absPath) {
                $zip->addFile($absPath, $relPath);
            }
            $zip->close();

            return $archivePath;
        } finally {
            if (File::isDirectory($stagingDir)) {
                File::deleteDirectory($stagingDir);
            }
            if (File::isDirectory($stagingBase) && empty(File::files($stagingBase)) && empty(File::directories($stagingBase))) {
                File::deleteDirectory($stagingBase);
            }
        }
    }

    /**
     * Inspect and validate a backup archive.
     *
     * @return array<string, mixed> The parsed manifest data.
     */
    public function inspectArchive(string $archivePath): array
    {
        if (! file_exists($archivePath)) {
            throw new RuntimeException("Backup archive not found: {$archivePath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Backup archive is corrupted or not a valid ZIP file: {$archivePath}");
        }

        $manifestContent = $zip->getFromName('manifest.json');
        if ($manifestContent === false) {
            $zip->close();
            throw new RuntimeException('Invalid backup archive: missing manifest.json');
        }

        $manifest = json_decode($manifestContent, true);
        if (! is_array($manifest)) {
            $zip->close();
            throw new RuntimeException('Invalid backup archive: corrupted manifest.json');
        }

        foreach (['system', 'created_at', 'checksum'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $manifest)) {
                $zip->close();
                throw new RuntimeException("Invalid backup archive manifest: missing required key '{$requiredKey}'");
            }
        }

        // Verify checksum of files inside ZIP
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = $stat['name'];
            if ($name === 'manifest.json' || str_ends_with($name, '/')) {
                continue;
            }
            $entryContent = $zip->getFromIndex($i);
            if ($entryContent === false) {
                $zip->close();
                throw new RuntimeException("Failed to read archive entry: {$name}");
            }
            $entries[$name] = hash('sha256', $entryContent);
        }
        ksort($entries);

        $ctx = hash_init('sha256');
        foreach ($entries as $path => $hash) {
            hash_update($ctx, $path.':'.$hash."\n");
        }
        $computedChecksum = hash_final($ctx);

        $zip->close();

        if (! hash_equals((string) $manifest['checksum'], $computedChecksum)) {
            throw new RuntimeException('Backup archive checksum mismatch: archive has been tampered with or corrupted.');
        }

        return $manifest;
    }

    /**
     * Restore database and storage from a backup archive.
     *
     * @return array{database_restored: bool, files_restored: int, manifest: array<string, mixed>}
     */
    public function restoreBackup(
        string $archivePath,
        bool $skipDatabase = false,
        bool $skipStorage = false,
        bool $cleanStorage = false
    ): array {
        $manifest = $this->inspectArchive($archivePath);

        // Pre-flight: verify database connection is alive
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            throw new RuntimeException('Database connection unreachable: '.$e->getMessage(), 0, $e);
        }

        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Failed to open backup archive for restoration: {$archivePath}");
        }

        $databaseRestored = false;
        $filesRestored = 0;

        try {
            // 1. Restore Database
            if (! $skipDatabase) {
                $sqlContent = $zip->getFromName('database.sql');
                if ($sqlContent !== false) {
                    $connection = config('database.default');
                    $driver = (string) config("database.connections.{$connection}.driver");

                    if ($driver === 'sqlite') {
                        $this->restoreSqlite($sqlContent);
                        $databaseRestored = true;
                    } elseif ($driver === 'pgsql') {
                        $tempSql = storage_path('framework/cache/restore_'.Str::random(12).'.sql');
                        try {
                            File::put($tempSql, $sqlContent);
                            $dbConfig = $this->getPostgreSqlConfig($connection);
                            $this->postgreSqlProvider->restore($tempSql, $dbConfig);
                            $databaseRestored = true;
                        } finally {
                            if (file_exists($tempSql)) {
                                @unlink($tempSql);
                            }
                        }
                    } else {
                        throw new RuntimeException("Unsupported database driver for restore: {$driver}");
                    }
                }
            }

            // 2. Restore Storage
            if (! $skipStorage) {
                if ($cleanStorage) {
                    $drafts = storage_path('app/private/document-drafts');
                    if (File::isDirectory($drafts)) {
                        File::cleanDirectory($drafts);
                    }
                    $officials = storage_path('app/private/document-officials');
                    if (File::isDirectory($officials)) {
                        File::cleanDirectory($officials);
                    }
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    if ($stat === false) {
                        continue;
                    }

                    $name = $stat['name'];

                    if ($name === 'manifest.json' || $name === 'database.sql' || str_ends_with($name, '/')) {
                        continue;
                    }

                    $entryContent = $zip->getFromIndex($i);
                    if ($entryContent === false) {
                        throw new RuntimeException("Failed to read archive entry for restore: {$name}");
                    }

                    if (str_starts_with($name, 'storage/private/')) {
                        $subPath = substr($name, strlen('storage/private/'));
                        $targetPath = storage_path('app/private/'.$subPath);
                        File::ensureDirectoryExists(dirname($targetPath));
                        file_put_contents($targetPath, $entryContent);
                        $filesRestored++;
                    } elseif (str_starts_with($name, 'storage/public/')) {
                        $subPath = substr($name, strlen('storage/public/'));
                        $targetPath = storage_path('app/public/'.$subPath);
                        File::ensureDirectoryExists(dirname($targetPath));
                        file_put_contents($targetPath, $entryContent);
                        $filesRestored++;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return [
            'database_restored' => $databaseRestored,
            'files_restored' => $filesRestored,
            'manifest' => $manifest,
        ];
    }

    /**
     * Dump SQLite database tables, schema, and records into SQL statements.
     */
    protected function dumpSqlite(string $targetSql): void
    {
        $pdo = DB::connection()->getPdo();
        $sqlOutput = "-- SQLite Backup Dump\n";
        $sqlOutput .= '-- Generated at: '.now()->toIso8601String()."\n";
        $sqlOutput .= "PRAGMA foreign_keys = OFF;\n";
        $sqlOutput .= "PRAGMA defer_foreign_keys = ON;\n\n";

        /** @var array<object{name: string, sql: string|null}> $tables */
        $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        // 1. Ensure all tables exist
        foreach ($tables as $table) {
            $createSql = $table->sql;
            if (empty($createSql)) {
                continue;
            }
            $createSql = (string) preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $createSql);
            $sqlOutput .= "{$createSql};\n";
        }
        $sqlOutput .= "\n";

        // 2. Clear existing rows in reverse order of tables
        $reversedTables = array_reverse($tables);
        foreach ($reversedTables as $table) {
            $sqlOutput .= "DELETE FROM \"{$table->name}\";\n";
        }
        $sqlOutput .= "\n";

        // 3. Insert rows in natural order
        foreach ($tables as $table) {
            $tableName = $table->name;
            /** @var array<object> $rows */
            $rows = DB::select("SELECT * FROM \"{$tableName}\"");
            if (empty($rows)) {
                continue;
            }

            $sqlOutput .= "-- Data for: {$tableName}\n";
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                if (empty($rowArray)) {
                    continue;
                }

                $columns = array_keys($rowArray);
                $quotedColumns = array_map(fn ($col) => "\"{$col}\"", $columns);

                $values = [];
                foreach ($rowArray as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_int($val) || is_float($val)) {
                        $values[] = (string) $val;
                    } else {
                        $values[] = $pdo->quote((string) $val);
                    }
                }

                $sqlOutput .= "INSERT INTO \"{$tableName}\" (".implode(', ', $quotedColumns).') VALUES ('.implode(', ', $values).");\n";
            }
            $sqlOutput .= "\n";
        }

        // 4. Create indexes if not exists
        /** @var array<object{sql: string|null}> $indexes */
        $indexes = DB::select("SELECT sql FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%' AND sql IS NOT NULL");
        foreach ($indexes as $index) {
            if (! empty($index->sql)) {
                $indexSql = (string) preg_replace('/^CREATE\s+(UNIQUE\s+)?INDEX\s+/i', 'CREATE $1INDEX IF NOT EXISTS ', $index->sql);
                $sqlOutput .= "{$indexSql};\n";
            }
        }

        $sqlOutput .= "\nPRAGMA defer_foreign_keys = OFF;\n";
        $sqlOutput .= "PRAGMA foreign_keys = ON;\n";

        File::put($targetSql, $sqlOutput);
    }

    /**
     * Restore SQLite database from SQL dump.
     */
    protected function restoreSqlite(string $sqlContent): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('PRAGMA foreign_keys = OFF;');
        $pdo->exec('PRAGMA defer_foreign_keys = ON;');
        $pdo->exec($sqlContent);
        $pdo->exec('PRAGMA defer_foreign_keys = OFF;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }

    /**
     * Resolve and validate PostgreSQL connection configuration for backup/restore.
     *
     * @return array{host?: string, port?: int|string, database: string, username?: string, password?: string}
     */
    protected function getPostgreSqlConfig(string $connection): array
    {
        $database = config("database.connections.{$connection}.database");
        if (! is_string($database) || $database === '') {
            throw new RuntimeException("Database name not configured for connection [{$connection}].");
        }

        $config = ['database' => $database];

        $host = config("database.connections.{$connection}.host");
        if (is_string($host) && $host !== '') {
            $config['host'] = $host;
        }

        $port = config("database.connections.{$connection}.port");
        if (is_int($port) || (is_string($port) && $port !== '')) {
            $config['port'] = $port;
        }

        $username = config("database.connections.{$connection}.username");
        if (is_string($username) && $username !== '') {
            $config['username'] = $username;
        }

        $password = config("database.connections.{$connection}.password");
        if (is_string($password) && $password !== '') {
            $config['password'] = $password;
        }

        return $config;
    }
}
