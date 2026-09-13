<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:create
        {--filename= : Custom name for the backup archive}
        {--path= : Custom directory to store the archive}
        {--only-db : Archive only the database without storage files}
        {--only-storage : Archive only storage files without database}
        {--no-db : Skip database archiving (same as --only-storage)}
        {--no-storage : Skip storage archiving (same as --only-db)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a timestamped backup archive of the database and application storage';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $onlyDb = (bool) ($this->option('only-db') || $this->option('no-storage'));
        $onlyStorage = (bool) ($this->option('only-storage') || $this->option('no-db'));

        if ($onlyDb && $onlyStorage) {
            $this->error('Cannot specify both --only-db and --only-storage.');

            return 1;
        }

        /** @var string|null $filename */
        $filename = $this->option('filename');
        /** @var string|null $path */
        $path = $this->option('path');

        $this->info('Starting Barangay System backup creation...');

        try {
            $archivePath = $backupService->createBackup(
                customName: $filename,
                onlyDb: $onlyDb,
                onlyStorage: $onlyStorage,
                customPath: $path
            );

            $size = number_format(filesize($archivePath) / 1024, 2).' KB';
            $this->info("Backup successfully created: {$archivePath} ({$size})");

            return 0;
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return 1;
        }
    }
}
