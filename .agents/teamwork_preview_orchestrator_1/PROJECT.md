# Project: Sta. Felomina Barangay System Capstone V1 (Remaining Modules)

## Architecture
- **Database Layer**: PostgreSQL 16 (production/Laragon/Docker) with SQLite in-memory driver abstraction for automated Pest feature tests.
- **Storage Layer**: Local filesystem disk rooted at `storage/app/private/` storing document drafts (`document-drafts/`) and approved versions (`document-officials/{id}/versions/{v}.pdf`). Dynamic QR SVG generation via `DocumentQrCodeController`.
- **Seeding Pipeline**: `DemoDataSeeder` extends baseline seeders (`PurokSeeder`, `DocumentTaxonomySeeder`, `AccountSeeder`) to populate multi-year documents (2024-2026), all 7 document lifecycle states, physical PDF storage files, households and residents across Puroks 1-7, and operational audit logs/notifications with strict idempotency.
- **Backup & Disaster Recovery Pipeline**: `BackupService` coordinates database dumping (PostgreSQL via `pg_dump` with `PGPASSWORD` environment isolation; SQLite for test execution) and storage directory archiving into timestamped ZIP files with SHA-256 verified `manifest.json`. `backup:create` and `backup:restore` artisan commands include pre-flight connectivity checks, archive integrity validation, and interactive confirmation / `--force` flags. Automated shell and batch scripts in `scripts/` provide host and container execution support.
- **Documentation Architecture**: Complete client-ready Markdown documentation in `docs/` covering administrative operations (`docs/ADMIN_OPERATOR_MANUAL.md`), backup/restore runbooks (`docs/BACKUP_AND_RESTORE.md`), and deployment procedures (`docs/DEPLOYMENT_GUIDE.md`).

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| F1 | Multi-Year Official Document Seeding | Seed documents across 2024-2026 for resolutions, ordinances, executive orders, and certifications | M1 | ORIGINAL_REQUEST §R1 |
| F2 | Multi-Category Document Seeding | Cover Health, Infrastructure, Peace & Order, Education, Disaster Risk Reduction categories | M1 | ORIGINAL_REQUEST §R1 |
| F3 | All Lifecycle States Representation | Seed records for Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded | M1 | ORIGINAL_REQUEST §R1 |
| F4 | Physical Storage PDF Generation | Generate mock PDF files in private storage for approved and draft documents to prevent 404 errors | M1 | Survey DB/Specs |
| F5 | Puroks 1-7 Households & Residents | Seed 28+ households and 80+ residents across all 7 Puroks with realistic demographics, voter flags, and statuses | M1 | ORIGINAL_REQUEST §R1 |
| F6 | Historical Audit Logs & Notifications | Seed realistic audit log entries and notifications reflecting document approvals and profiling events | M1 | ORIGINAL_REQUEST §R1 |
| F7 | Seeder Idempotency Guarantee | Ensure DemoDataSeeder can rerun repeatedly without duplicate key constraint violations | M1 | ORIGINAL_REQUEST §R1 |
| F8 | Backup Creation Artisan Command | `php artisan backup:create` generating timestamped ZIP archives with DB dump, storage, and manifest.json | M2 | ORIGINAL_REQUEST §R2 |
| F9 | Backup Restore Artisan Command | `php artisan backup:restore` restoring DB and storage with connection checks, manifest validation, and confirmation guard | M2 | ORIGINAL_REQUEST §R2 |
| F10 | Cross-Platform Automation Scripts | Shell and batch scripts in `scripts/` (`backup.sh`, `restore.sh`, `backup.bat`, `restore.bat`) for host and Docker | M2 | ORIGINAL_REQUEST §R2 |
| F11 | Docker Client Integration | Update Dockerfile to include `postgresql-client` in app container for pg_dump/psql execution | M2 | Survey Ops |
| F12 | Admin Operator Manual | `docs/ADMIN_OPERATOR_MANUAL.md` detailing Captain, Secretary, and Purok Chairperson workflows | M3 | ORIGINAL_REQUEST §R3 |
| F13 | Backup & Restore Manual | `docs/BACKUP_AND_RESTORE.md` detailing backup operations, scheduling, verification, and disaster recovery | M3 | ORIGINAL_REQUEST §R3 |
| F14 | Deployment Guide | `docs/DEPLOYMENT_GUIDE.md` covering prerequisites, Docker Compose, Linux server setup, and troubleshooting | M3 | ORIGINAL_REQUEST §R3 |
| F15 | Opaque-Box E2E Testing Suite | Comprehensive Pest test suite across Tiers 1-4 for Seeder, Backup/Restore, and Documentation verification | E2E Track | ORIGINAL_REQUEST §Acceptance |
| F16 | Adversarial Hardening & Integrity Audit | Tier 5 edge case testing, code formatting via Pint, and forensic integrity audit | Final Milestone | AGENTS.md / Orchestrator |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Demo Dataset Seeder | `database/seeders/DemoDataSeeder.php`, `database/seeders/DatabaseSeeder.php` integration, taxonomy expansions | none | DONE |
| M2 | Automated Backup & Disaster Recovery | `app/Services/BackupService.php`, `app/Console/Commands/BackupCreateCommand.php`, `app/Console/Commands/BackupRestoreCommand.php`, `scripts/*`, `Dockerfile` | none | DONE |
| M3 | Administrative & Deployment Docs | `docs/ADMIN_OPERATOR_MANUAL.md`, `docs/BACKUP_AND_RESTORE.md`, `docs/DEPLOYMENT_GUIDE.md` | M1, M2 (for concrete references) | DONE |
| E2E | E2E Testing Suite | `tests/Feature/DemoDataSeederTest.php`, `tests/Feature/BackupRestoreTest.php`, `tests/Feature/DocumentationIntegrityTest.php`, `TEST_INFRA.md`, `TEST_READY.md` | none (requirement-driven) | DONE |
| Final | Verification & Hardening | 100% E2E test pass (Tiers 1-4), Tier 5 adversarial tests, Pint formatting, forensic audit | M1, M2, M3, E2E | DONE |

## Interface Contracts

### 1. DemoDataSeeder Contract
- **Class**: `Database\Seeders\DemoDataSeeder extends Illuminate\Database\Seeder`
- **Execution**: Invoked via `php artisan db:seed --class=DemoDataSeeder` or chained in `DatabaseSeeder.php`.
- **Pre-requisites**: Runs `PurokSeeder`, `DocumentTaxonomySeeder`, `AccountSeeder` if tables are empty.
- **Idempotency**: All records looked up or created via unique deterministic keys:
  - Document records: `['document_type_id', 'document_year', 'document_number']`
  - Households: `['household_code']` (e.g., `HH-P1-001`)
  - Residents: `['resident_code']` (e.g., `RES-P1-0001`)
  - Verification tokens: Preserved on existing records; generated once on create.
  - File storage: Uses `Storage::disk('local')->put()` to generate real placeholder PDFs at paths referenced by `document_file_versions.file_path` and `document_records.draft_file_path`.

### 2. Backup & Restore Contract
- **Service**: `App\Services\BackupService`
  - `createBackup(?string $customName = null): string` -> returns absolute path of created archive.
  - `restoreBackup(string $archivePath, bool $skipDatabase = false, bool $skipStorage = false): array` -> returns status summary.
  - `inspectArchive(string $archivePath): array` -> reads and validates `manifest.json`.
- **Archive Format**: Timestamped ZIP file stored in `storage/app/backups/backup-YYYY-MM-DD_HHmmss.zip`.
  - Contents:
    - `manifest.json` (version, timestamp, db_driver, app_version, checksums of files)
    - `database.sql` (plain text SQL dump)
    - `storage/private/` (mirrors `storage/app/private/`)
    - `storage/public/` (mirrors `storage/app/public/`)
- **Commands**:
  - `php artisan backup:create [--filename=] [--no-db] [--no-storage]` (Exit 0 on success).
  - `php artisan backup:restore {archive?} [--latest] [--force] [--no-db] [--no-storage]`
    - Safety guard: Prompts `Are you sure you want to restore? This will overwrite the current database and storage.` if `--force` is omitted.
    - Aborts if archive does not exist, manifest checksum fails, or database connection is unreachable.
- **Scripts**:
  - `scripts/backup.sh` and `scripts/backup.bat`: Wraps artisan command with exit code checks.
  - `scripts/restore.sh` and `scripts/restore.bat`: Wraps restore command with confirmation flags.

### 3. Documentation Contract
- Markdown files in `docs/`:
  - `docs/ADMIN_OPERATOR_MANUAL.md`: Complete operator manual covering Captain, Secretary, and Purok Chairperson roles with workflows, status transitions, QR verification, resident profiling, reporting, and print exports.
  - `docs/BACKUP_AND_RESTORE.md`: Runbook with automated scheduling (cron), manual execution, integrity verification, and disaster recovery procedures.
  - `docs/DEPLOYMENT_GUIDE.md`: Deployment guide covering prerequisites, Docker Compose, production Nginx/PHP-FPM/PostgreSQL setup, environment configuration, migrations, asset builds, and troubleshooting.

## Code Layout
- `app/Console/Commands/BackupCreateCommand.php`
- `app/Console/Commands/BackupRestoreCommand.php`
- `app/Services/BackupService.php`
- `database/seeders/DemoDataSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `scripts/backup.sh`
- `scripts/restore.sh`
- `scripts/backup.bat`
- `scripts/restore.bat`
- `Dockerfile` (modified for postgresql-client)
- `docs/ADMIN_OPERATOR_MANUAL.md`
- `docs/BACKUP_AND_RESTORE.md`
- `docs/DEPLOYMENT_GUIDE.md`
- `tests/Feature/DemoDataSeederTest.php`
- `tests/Feature/BackupRestoreTest.php`
- `tests/Feature/DocumentationIntegrityTest.php`
- `TEST_INFRA.md`
- `TEST_READY.md`
