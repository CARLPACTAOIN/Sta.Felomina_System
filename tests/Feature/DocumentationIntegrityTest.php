<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Setup & Helper Methods
|--------------------------------------------------------------------------
*/

function getDocContent(string $filename): string
{
    $path = base_path('docs/'.$filename);
    expect(File::exists($path))->toBeTrue("Expected documentation file {$filename} to exist.");

    return File::get($path);
}

/*
|--------------------------------------------------------------------------
| Tier 1: Feature Coverage Tests
|--------------------------------------------------------------------------
*/

test('tier 1: administrative operator manual exists and contains substantial content', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    expect(strlen($content))->toBeGreaterThan(3000)
        ->and($content)->toContain('# Administrative Guide & Operator Manual');
});

test('tier 1: backup and disaster recovery manual exists and contains substantial content', function () {
    $content = getDocContent('BACKUP_AND_RESTORE.md');

    expect(strlen($content))->toBeGreaterThan(3000)
        ->and($content)->toContain('# Backup, Disaster Recovery, and Data Retention Manual');
});

test('tier 1: deployment and maintenance guide exists and contains substantial content', function () {
    $content = getDocContent('DEPLOYMENT_GUIDE.md');

    expect(strlen($content))->toBeGreaterThan(3000)
        ->and($content)->toContain('# Production Deployment, Docker Configuration, and Systems Maintenance Guide');
});

test('tier 1: admin operator manual covers captain, secretary, and purok chairperson roles', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    expect($content)
        ->toContain('Barangay Captain')
        ->toContain('Barangay Secretary')
        ->toContain('Purok Chairperson');
});

test('tier 1: backup manual covers backup create, restore commands, and automation scripts', function () {
    $content = getDocContent('BACKUP_AND_RESTORE.md');

    expect($content)
        ->toContain('backup:create')
        ->toContain('backup:restore')
        ->toContain('backup.sh')
        ->toContain('restore.sh')
        ->toContain('backup.bat');
});

test('tier 1: deployment guide covers docker, linux server, and local workstation environments', function () {
    $content = getDocContent('DEPLOYMENT_GUIDE.md');

    expect($content)
        ->toContain('Docker')
        ->toContain('PostgreSQL')
        ->toContain('Nginx')
        ->toContain('php artisan migrate')
        ->toContain('php artisan storage:link');
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases
|--------------------------------------------------------------------------
*/

test('tier 2: no documentation file contains uncompleted placeholders or todo markers', function () {
    $docFiles = ['ADMIN_OPERATOR_MANUAL.md', 'BACKUP_AND_RESTORE.md', 'DEPLOYMENT_GUIDE.md'];

    foreach ($docFiles as $file) {
        $content = getDocContent($file);

        expect($content)->not->toContain('TODO:')
            ->and($content)->not->toContain('FIXME:')
            ->and($content)->not->toContain('TBD')
            ->and($content)->not->toContain('[placeholder]')
            ->and($content)->not->toContain('lorem ipsum');
    }
});

test('tier 2: admin operator manual details all seven document lifecycle states', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    expect($content)
        ->toContain('Draft')
        ->toContain('Pending Approval')
        ->toContain('Needs Revision')
        ->toContain('Published')
        ->toContain('Archived')
        ->toContain('Revoked')
        ->toContain('Superseded');
});

test('tier 2: admin operator manual documents return and revocation mandatory reasons', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    expect($content)
        ->toContain('return comment')
        ->toContain('reason')
        ->toContain('audit');
});

test('tier 2: admin operator manual documents resident profiling duplicate detection rules', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    expect($content)
        ->toContain('duplicate')
        ->toContain('birthdate')
        ->toContain('Purok');
});

test('tier 2: backup manual details safety confirmation guard and force override options', function () {
    $content = getDocContent('BACKUP_AND_RESTORE.md');

    expect($content)
        ->toContain('--force')
        ->toContain('confirmation');
});

test('tier 2: deployment guide details essential security environment variables and directory permissions', function () {
    $content = getDocContent('DEPLOYMENT_GUIDE.md');

    expect($content)
        ->toContain('APP_KEY')
        ->toContain('APP_DEBUG=false')
        ->toContain('DB_PASSWORD')
        ->toContain('storage');
});

/*
|--------------------------------------------------------------------------
| Tier 3: Cross-Feature Combinations
|--------------------------------------------------------------------------
*/

test('tier 3: documentation cross-references valid seeder and script paths', function () {
    $adminContent = getDocContent('ADMIN_OPERATOR_MANUAL.md');
    $backupContent = getDocContent('BACKUP_AND_RESTORE.md');
    $deployContent = getDocContent('DEPLOYMENT_GUIDE.md');

    $allContent = $adminContent."\n".$backupContent."\n".$deployContent;

    expect($allContent)
        ->toContain('DemoDataSeeder')
        ->toContain('scripts/backup.sh')
        ->toContain('storage/app/backups');
});

test('tier 3: backup manual disaster recovery runbooks cover all failure scenarios', function () {
    $content = getDocContent('BACKUP_AND_RESTORE.md');

    expect($content)
        ->toContain('Accidental')
        ->toContain('Hardware')
        ->toContain('Corruption');
});

test('tier 3: deployment guide provides production nginx configuration template', function () {
    $content = getDocContent('DEPLOYMENT_GUIDE.md');

    expect($content)
        ->toContain('server {')
        ->toContain('fastcgi_pass')
        ->toContain('try_files');
});

test('tier 3: deployment guide documents database migration and taxonomy seeding sequence', function () {
    $content = getDocContent('DEPLOYMENT_GUIDE.md');

    expect($content)
        ->toContain('migrate')
        ->toContain('seed');
});

/*
|--------------------------------------------------------------------------
| Tier 4: Real-World Scenarios
|--------------------------------------------------------------------------
*/

test('tier 4: administrative manual navigation and actions align with registered application routes', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    // Route checks: verification.show, verification.document, dashboard
    expect(Route::has('verification.show'))->toBeTrue()
        ->and(Route::has('verification.document'))->toBeTrue()
        ->and(Route::has('dashboard'))->toBeTrue();

    expect($content)
        ->toContain('/verify/')
        ->toContain('/dashboard');
});

test('tier 4: qr code verification workflow matches public route security contract', function () {
    $content = getDocContent('ADMIN_OPERATOR_MANUAL.md');

    expect($content)
        ->toContain('Public')
        ->toContain('QR')
        ->toContain('SVG');
});
