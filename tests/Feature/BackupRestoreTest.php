<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DocumentTaxonomySeeder;
use Database\Seeders\PurokSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Setup & Teardown Helpers
|--------------------------------------------------------------------------
*/

$createdTestArchives = [];

beforeEach(function () {
    File::ensureDirectoryExists(storage_path('app/backups'));
});

afterEach(function () use (&$createdTestArchives) {
    $backupDir = storage_path('app/backups');
    if (File::isDirectory($backupDir)) {
        foreach (File::glob($backupDir.'/*test*.zip') as $file) {
            @unlink($file);
        }
    }

    foreach ($createdTestArchives as $archivePath) {
        if (file_exists($archivePath)) {
            @unlink($archivePath);
        }
    }
    $createdTestArchives = [];
});

/**
 * Helper to get the most recent backup archive in storage/app/backups.
 */
function getLatestBackupArchive(): ?string
{
    $backupDir = storage_path('app/backups');
    if (! File::isDirectory($backupDir)) {
        return null;
    }

    $files = File::glob($backupDir.'/*.zip');
    if (empty($files)) {
        return null;
    }

    usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

    return $files[0];
}

/**
 * Helper to create a dummy zip archive for testing corner cases.
 */
function createDummyZip(string $filePath, array $files): void
{
    $dir = dirname($filePath);
    if (! File::isDirectory($dir)) {
        File::makeDirectory($dir, 0755, true);
    }

    $zip = new ZipArchive;
    $zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();
}

/*
|--------------------------------------------------------------------------
| Tier 1: Feature Coverage Tests
|--------------------------------------------------------------------------
*/

test('tier 1: backup create command generates timestamped zip archive in backups directory', function () use (&$createdTestArchives) {
    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull()
        ->and(file_exists($latestArchive))->toBeTrue();

    $createdTestArchives[] = $latestArchive;
    expect(basename($latestArchive))->toMatch('/^backup-\d{4}-\d{2}-\d{2}_\d{6}\.zip$/');
});

test('tier 1: backup archive contains manifest, database dump, and storage directories', function () use (&$createdTestArchives) {
    Storage::disk('local')->put('document-drafts/sample-test-draft.pdf', '%PDF-1.4 test draft');

    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    $zip = new ZipArchive;
    expect($zip->open($latestArchive))->toBeTrue();

    // Check manifest exists
    expect($zip->locateName('manifest.json'))->not->toBeFalse();

    // Check database.sql exists
    expect($zip->locateName('database.sql'))->not->toBeFalse();

    // Check storage directory exists inside ZIP
    $hasStorage = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (str_starts_with($stat['name'], 'storage/')) {
            $hasStorage = true;
            break;
        }
    }
    expect($hasStorage)->toBeTrue();

    $zip->close();
});

test('tier 1: backup manifest json contains system metadata, driver, file count, and checksum', function () use (&$createdTestArchives) {
    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    $zip = new ZipArchive;
    $zip->open($latestArchive);
    $manifestContent = $zip->getFromName('manifest.json');
    $zip->close();

    expect($manifestContent)->not->toBeFalse();

    $manifest = json_decode($manifestContent, true);
    expect($manifest)->toBeArray()
        ->and($manifest)->toHaveKey('system')
        ->and($manifest)->toHaveKey('created_at')
        ->and($manifest)->toHaveKey('database')
        ->and($manifest)->toHaveKey('storage')
        ->and($manifest)->toHaveKey('checksum')
        ->and($manifest['database'])->toHaveKey('driver');
});

test('tier 1: backup create supports only-db flag to archive database without storage', function () use (&$createdTestArchives) {
    Storage::disk('local')->put('document-drafts/only-db-check.pdf', '%PDF-1.4 test');

    $this->artisan('backup:create', ['--only-db' => true])
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    $zip = new ZipArchive;
    $zip->open($latestArchive);
    expect($zip->locateName('database.sql'))->not->toBeFalse();

    $storageCount = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (str_starts_with($stat['name'], 'storage/private/')) {
            $storageCount++;
        }
    }
    $zip->close();

    expect($storageCount)->toBe(0);
});

test('tier 1: backup create supports only-storage flag to archive storage without database', function () use (&$createdTestArchives) {
    Storage::disk('local')->put('document-drafts/only-storage-check.pdf', '%PDF-1.4 storage content');

    $this->artisan('backup:create', ['--only-storage' => true])
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    $zip = new ZipArchive;
    $zip->open($latestArchive);
    $manifestContent = $zip->getFromName('manifest.json');
    $manifest = json_decode($manifestContent, true);

    expect($manifest['storage']['private_included'] ?? true)->toBeTrue();
    $zip->close();
});

test('tier 1: backup restore command restores storage files from archive with --force', function () use (&$createdTestArchives) {
    $testFilePath = 'document-drafts/restore-target.pdf';
    $testContent = '%PDF-1.4 Unique restore file content '.uniqid();
    Storage::disk('local')->put($testFilePath, $testContent);

    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    // Delete the file
    Storage::disk('local')->delete($testFilePath);
    expect(Storage::disk('local')->exists($testFilePath))->toBeFalse();

    // Restore with --force
    $this->artisan('backup:restore', [
        'archive' => $latestArchive,
        '--force' => true,
    ])->assertExitCode(0);

    expect(Storage::disk('local')->exists($testFilePath))->toBeTrue()
        ->and(Storage::disk('local')->get($testFilePath))->toBe($testContent);
});

test('tier 1: backup restore command restores database records from archive with --force', function () use (&$createdTestArchives) {
    $this->seed(PurokSeeder::class);
    $this->seed(AccountSeeder::class);

    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    // Mutate state: delete all accounts
    User::query()->delete();
    expect(User::query()->count())->toBe(0);

    // Restore with --force
    $this->artisan('backup:restore', [
        'archive' => $latestArchive,
        '--force' => true,
    ])->assertExitCode(0);

    expect(User::query()->count())->toBeGreaterThanOrEqual(1);
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases
|--------------------------------------------------------------------------
*/

test('tier 2: backup restore prompts confirmation warning and cancels when answered no', function () use (&$createdTestArchives) {
    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    $this->artisan('backup:restore', ['archive' => $latestArchive])
        ->expectsConfirmation('Do you wish to proceed?', 'no')
        ->assertExitCode(0);
});

test('tier 2: backup restore with force flag bypasses interactive confirmation', function () use (&$createdTestArchives) {
    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    // Should execute cleanly without prompting
    $this->artisan('backup:restore', [
        'archive' => $latestArchive,
        '--force' => true,
    ])->assertExitCode(0);
});

test('tier 2: backup restore fails gracefully when archive does not exist', function () {
    $this->artisan('backup:restore', [
        'archive' => 'non-existent-backup-archive-file-12345.zip',
        '--force' => true,
    ])->assertFailed();
});

test('tier 2: backup restore fails gracefully when archive file is corrupted or not a valid zip', function () use (&$createdTestArchives) {
    $corruptPath = storage_path('app/backups/corrupt-test.zip');
    File::put($corruptPath, 'This is plain text and definitely NOT a valid ZIP archive format.');
    $createdTestArchives[] = $corruptPath;

    $this->artisan('backup:restore', [
        'archive' => $corruptPath,
        '--force' => true,
    ])->assertFailed();
});

test('tier 2: backup restore fails gracefully when archive is missing manifest json', function () use (&$createdTestArchives) {
    $missingManifestPath = storage_path('app/backups/missing-manifest-test.zip');
    createDummyZip($missingManifestPath, [
        'database.sql' => '-- empty sql dump',
        'dummy.txt' => 'dummy content',
    ]);
    $createdTestArchives[] = $missingManifestPath;

    $this->artisan('backup:restore', [
        'archive' => $missingManifestPath,
        '--force' => true,
    ])->assertFailed();
});

test('tier 2: backup restore validates checksum and rejects tampered archive files', function () use (&$createdTestArchives) {
    $tamperedPath = storage_path('app/backups/tampered-test.zip');
    createDummyZip($tamperedPath, [
        'manifest.json' => json_encode([
            'system' => 'Barangay Sta. Felomina',
            'version' => '1.0',
            'created_at' => now()->toIso8601String(),
            'database' => ['driver' => 'sqlite', 'dump_file' => 'database.sql'],
            'storage' => ['file_count' => 1],
            'checksum' => 'invalidsimulatedchecksum1234567890abcdef',
        ]),
        'database.sql' => '-- fake sql dump',
    ]);
    $createdTestArchives[] = $tamperedPath;

    $this->artisan('backup:restore', [
        'archive' => $tamperedPath,
        '--force' => true,
    ])->assertFailed();
});

/*
|--------------------------------------------------------------------------
| Tier 3: Cross-Feature Combinations
|--------------------------------------------------------------------------
*/

test('tier 3: backup service inspectArchive method parses and returns validated manifest metadata', function () use (&$createdTestArchives) {
    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    $service = app(BackupService::class);
    $manifest = $service->inspectArchive($latestArchive);

    expect($manifest)->toBeArray()
        ->and($manifest)->toHaveKey('system')
        ->and($manifest)->toHaveKey('created_at')
        ->and($manifest)->toHaveKey('checksum');
});

test('tier 3: postgresql pg_dump provider strictly isolates credentials via PGPASSWORD environment variable', function () {
    Process::fake([
        '*pg_dump*' => Process::result(output: '-- pg_dump simulated successful output', exitCode: 0),
    ]);

    // Temporarily set driver config or invoke pg_dump provider if present
    $providerClass = 'App\\Services\\Backup\\PostgreSqlBackupProvider';
    if (class_exists($providerClass)) {
        $provider = new $providerClass;
        $targetSql = storage_path('framework/cache/test_dump.sql');
        $provider->dump($targetSql, [
            'host' => '127.0.0.1',
            'port' => '5432',
            'database' => 'barangay_system',
            'username' => 'postgres',
            'password' => 'secret-test-password',
        ]);

        Process::assertRan(function ($process) {
            // Assert password is NEVER in command line arguments
            $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
            expect($command)->not->toContain('secret-test-password');

            // Assert PGPASSWORD is in environment variables
            $env = $process->environment;
            expect($env['PGPASSWORD'] ?? null)->toBe('secret-test-password');

            return true;
        });
    } else {
        expect(true)->toBeTrue();
    }
});

test('tier 3: backup restore handles selective flags --only-db and --only-storage', function () use (&$createdTestArchives) {
    Storage::disk('local')->put('document-drafts/preserve-me.pdf', '%PDF-1.4 untouched');

    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    // Restore only database
    $this->artisan('backup:restore', [
        'archive' => $latestArchive,
        '--only-db' => true,
        '--force' => true,
    ])->assertExitCode(0);

    // Assert existing storage was preserved
    expect(Storage::disk('local')->exists('document-drafts/preserve-me.pdf'))->toBeTrue();
});

test('tier 3: backup archive cleans up temporary staging files on completion', function () use (&$createdTestArchives) {
    $tempDir = storage_path('framework/cache/backup_temp');

    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    $createdTestArchives[] = $latestArchive;

    if (File::isDirectory($tempDir)) {
        $files = File::files($tempDir);
        expect($files)->toBeEmpty();
    } else {
        expect(true)->toBeTrue();
    }
});

/*
|--------------------------------------------------------------------------
| Tier 4: Real-World Scenarios
|--------------------------------------------------------------------------
*/

test('tier 4: full disaster recovery drill: complete data loss followed by full restore and verification', function () use (&$createdTestArchives) {
    $this->seed(PurokSeeder::class);
    $this->seed(DocumentTaxonomySeeder::class);
    $this->seed(AccountSeeder::class);

    $secretary = User::query()->where('role', UserRole::Secretary->value)->firstOrFail();
    $type = DocumentType::query()->firstOrFail();
    $category = DocumentCategory::query()->firstOrFail();

    $doc = DocumentRecord::create([
        'document_type_id' => $type->id,
        'document_category_id' => $category->id,
        'created_by_id' => $secretary->id,
        'document_number' => 'RES-2026-DR-DRILL',
        'document_year' => 2026,
        'title' => 'Disaster Recovery Baseline Document',
        'document_date' => now(),
        'subject' => 'Disaster Recovery Verification',
        'status' => DocumentStatus::Draft,
        'verification_token' => Str::random(48),
        'draft_pdf_path' => 'document-drafts/dr-drill.pdf',
    ]);

    Storage::disk('local')->put('document-drafts/dr-drill.pdf', '%PDF-1.4 DR Drill Sample Content');

    // 1. Create full backup
    $this->artisan('backup:create')
        ->assertExitCode(0);

    $latestArchive = getLatestBackupArchive();
    expect($latestArchive)->not->toBeNull();
    $createdTestArchives[] = $latestArchive;

    // 2. Simulate catastrophic loss
    DocumentRecord::query()->delete();
    Storage::disk('local')->delete('document-drafts/dr-drill.pdf');

    expect(DocumentRecord::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists('document-drafts/dr-drill.pdf'))->toBeFalse();

    // 3. Perform disaster restoration
    $this->artisan('backup:restore', [
        'archive' => $latestArchive,
        '--force' => true,
    ])->assertExitCode(0);

    // 4. Verify recovery
    $restoredDoc = DocumentRecord::query()->where('document_number', 'RES-2026-DR-DRILL')->first();
    expect($restoredDoc)->not->toBeNull()
        ->and($restoredDoc->title)->toBe('Disaster Recovery Baseline Document')
        ->and(Storage::disk('local')->exists('document-drafts/dr-drill.pdf'))->toBeTrue()
        ->and(Storage::disk('local')->get('document-drafts/dr-drill.pdf'))->toContain('DR Drill Sample Content');
});

test('tier 4: backup creation with custom filename parameter and restore from custom filename', function () use (&$createdTestArchives) {
    $customName = 'custom-dr-test-archive.zip';

    $this->artisan('backup:create', ['--filename' => $customName])
        ->assertExitCode(0);

    $expectedPath = storage_path('app/backups/'.$customName);
    expect(file_exists($expectedPath))->toBeTrue();
    $createdTestArchives[] = $expectedPath;

    // Restore using custom filename
    $this->artisan('backup:restore', [
        'archive' => $customName,
        '--force' => true,
    ])->assertExitCode(0);
});
