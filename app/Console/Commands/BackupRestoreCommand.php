<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class BackupRestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore
        {archive? : Name or path of the backup archive to restore}
        {--latest : Restore the most recent backup archive}
        {--force : Bypass confirmation prompt}
        {--only-db : Restore only database records}
        {--only-storage : Restore only storage files}
        {--no-db : Skip database restore (same as --only-storage)}
        {--no-storage : Skip storage restore (same as --only-db)}
        {--clean : Clean existing storage directories before restore}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore database records and application storage files from a backup archive';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        /** @var string|null $archiveInput */
        $archiveInput = $this->argument('archive');
        $archivePath = null;

        if (! empty($archiveInput)) {
            if (file_exists($archiveInput)) {
                $archivePath = realpath($archiveInput) ?: $archiveInput;
            } elseif (file_exists(storage_path('app/backups/'.$archiveInput))) {
                $archivePath = storage_path('app/backups/'.$archiveInput);
            } else {
                $this->error("Backup archive not found: {$archiveInput}");

                return 1;
            }
        } else {
            $backupDir = storage_path('app/backups');
            $files = File::isDirectory($backupDir) ? File::glob($backupDir.'/*.zip') : [];
            if (empty($files)) {
                $this->error('No backup archives found in storage/app/backups.');

                return 1;
            }

            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
            $archivePath = $files[0];
        }

        // Inspect and validate archive integrity
        try {
            $manifest = $backupService->inspectArchive($archivePath);
        } catch (Throwable $e) {
            $this->error('Archive inspection failed: '.$e->getMessage());

            return 1;
        }

        $this->line('<info>Archive:</info> '.basename($archivePath));
        $this->line('<info>System:</info> '.($manifest['system'] ?? 'Unknown'));
        $this->line('<info>Created at:</info> '.($manifest['created_at'] ?? 'Unknown'));

        // Confirmation safety guard
        if (! $this->option('force')) {
            $this->warn('WARNING: Restoring will overwrite existing database records and storage files!');
            if (! $this->confirm('Do you wish to proceed?', false)) {
                $this->info('Restore cancelled.');

                return 0;
            }
        }

        $onlyDb = (bool) ($this->option('only-db') || $this->option('no-storage'));
        $onlyStorage = (bool) ($this->option('only-storage') || $this->option('no-db'));

        if ($onlyDb && $onlyStorage) {
            $this->error('Cannot specify both --only-db and --only-storage.');

            return 1;
        }

        try {
            $this->info('Restoring backup...');
            $backupService->restoreBackup(
                archivePath: $archivePath,
                skipDatabase: $onlyStorage,
                skipStorage: $onlyDb,
                cleanStorage: (bool) $this->option('clean')
            );

            $this->info('Restore completed successfully!');

            return 0;
        } catch (Throwable $e) {
            $this->error('Restore failed: '.$e->getMessage());

            return 1;
        }
    }
}
