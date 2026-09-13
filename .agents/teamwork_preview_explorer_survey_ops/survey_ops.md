# Operations & Infrastructure Survey: Backup, Recovery, and Turnover Documentation

**Explorer:** teamwork_preview_explorer (Storage and Operations Explorer)  
**Date:** 2026-09-13  
**Target Modules:**  
- **R2:** Automated Backup and Disaster Recovery System  
- **R3:** Deployment and Administrative Turnover Documentation  
- **Test Infrastructure:** Automated Feature & Unit Testing for Backup / Restore  

---

## Executive Summary

Barangay Sta. Felomina's Document Archiving, Verification, and Resident Profiling System has completed its primary application slices (authentication, document workflows, QR verification, staff account management, resident profiling, rule-based categorization, operational dashboards, and official printable report generation).

This survey establishes the complete operational architecture and technical specifications required to implement:
1. **Automated Backup and Disaster Recovery System (R2):** Native Artisan commands (`backup:create` and `backup:restore`), multi-environment shell scripts (`scripts/backup.sh`, `scripts/restore.sh`, `scripts/backup.bat`, `scripts/restore.bat`), atomic packaging via PHP `ZipArchive`, verifiable SHA-256 `manifest.json`, PostgreSQL dump/restore mechanics with credential shielding via `PGPASSWORD`, and driver fallbacks.
2. **Docker Environment Integration:** Dockerfile update installing `postgresql-client` for seamless containerized execution, volume interaction (`app_storage`, `db_data`), and scheduled automation inside/outside containers.
3. **Turnover & Operational Documentation (R3):** Comprehensive, client-ready markdown manuals (`docs/ADMIN_OPERATOR_MANUAL.md`, `docs/BACKUP_AND_RESTORE.md`, and `docs/DEPLOYMENT_GUIDE.md`) tailored to Barangay Captain, Secretary, and Purok Chairperson roles, server administrators, and disaster recovery operators.
4. **Automated Test Infrastructure:** Comprehensive Pest feature tests (`tests/Feature/BackupRestoreTest.php`) that test archive creation, manifest integrity, confirmation guards, forced overwrites, and failure recovery without risking test database pollution.

---

## 1. System Inventory & Baseline Analysis

### 1.1 Database Architecture & Connection Settings
- **Primary Database:** PostgreSQL 16 (`driver: pgsql`).
- **Configuration Path:** `config/database.php` lines 87–100.
- **Environment Keys (`.env` / `.env.example`):**
  - `DB_CONNECTION=pgsql`
  - `DB_HOST=127.0.0.1` (or `db` in Docker)
  - `DB_PORT=5432`
  - `DB_DATABASE=barangay_system`
  - `DB_USERNAME=postgres` (or `barangay` in Docker)
  - `DB_PASSWORD=sepaktakraw` (or `barangay` in Docker)
- **Local Host Binary Availability:**
  - `pg_dump.exe` version 16.14 located at `C:\Program Files\PostgreSQL\16\bin\pg_dump.exe` (confirmed in system PATH).
  - `psql.exe` version 16.14 located at `C:\Program Files\PostgreSQL\16\bin\psql.exe` (confirmed in system PATH).
  - `pg_restore.exe` version 16.14 located at `C:\Program Files\PostgreSQL\16\bin\pg_restore.exe`.
  - Connectivity verified: `$env:PGPASSWORD='sepaktakraw'; pg_dump -h 127.0.0.1 -p 5432 -U postgres -d barangay_system` executes with exit code 0.

### 1.2 Storage Directory Structure & File Assets
- **Filesystem Disks (`config/filesystems.php`):**
  - `local` disk: Rooted at `storage_path('app/private')`.
  - `public` disk: Rooted at `storage_path('app/public')`.
- **Uploaded & Managed File Paths:**
  - **Draft Document PDFs:** Uploaded by Secretary/Captain via Livewire `show.blade.php` to `document-drafts/` (`storage/app/private/document-drafts/{hash}.pdf`).
  - **Official Approved PDFs:** Frozen upon Captain approval in `DocumentRecord::approveForPublication` at `document-officials/{document_id}/versions/{version_number}.pdf` (`storage/app/private/document-officials/...`).
  - **QR Code Vectors:** As confirmed in `DocumentQrCodeController.php`, QR codes are generated dynamically on-demand as deterministic SVG vectors from the permanent verification URL (`/verify/{token}`). They do not clutter physical disk storage, but any cached or generated assets within private storage will be captured.
  - **Public Assets:** `storage/app/public` linked to `public/storage` via `php artisan storage:link`.
- **Target Backup Destination:**
  - Default: `storage/app/backups/` (`storage_path('app/backups')`).
  - Directory will be created automatically if missing and excluded from public exposure.

### 1.3 Docker & Container Infrastructure
- **Files:** `docker-compose.yml`, `Dockerfile`, `docker/php/entrypoint.sh`, `docker/nginx/default.conf`.
- **Services:**
  - `app`: PHP 8.4-FPM (`php:8.4-fpm-bookworm`), volume `app_storage:/var/www/html/storage`.
  - `db`: PostgreSQL 16 Alpine (`postgres:16-alpine`), volume `db_data:/var/lib/postgresql/data`.
  - `nginx`: Nginx 1.27 Alpine (`nginx:1.27-alpine`), port `8080:80`.
- **Key Docker Finding:**
  - In `Dockerfile` (lines 29–39), `libpq-dev`, `libzip-dev`, and `unzip` are installed, but **`postgresql-client` is not currently installed** in the `app` container.
  - **Recommendation:** Add `postgresql-client` to `Dockerfile` line 30 so that `pg_dump` and `psql` are available when running Artisan commands inside `app` (`docker compose exec app php artisan backup:create`).

### 1.4 Test Infrastructure
- **Framework:** Pest PHP 3.x / PHPUnit 11.x.
- **Base Test Setup (`phpunit.xml` & `tests/Pest.php`):**
  - `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.
  - `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`.
  - Full suite currently runs 160 tests (158 passed, 2 skipped Fortify 2FA).
- **Testing Implication for Backup/Restore:**
  - When running under SQLite in tests, calling `pg_dump` directly will fail because SQLite does not use `pg_dump`.
  - The backup system must be architected with clean abstraction:
    - Support PostgreSQL dumper via `pg_dump`/`psql`.
    - Support SQLite dumper (for SQLite databases or automated testing).
    - Support Laravel `Process::fake()` so Pest tests can verify command flags, process arguments, and exit code handling without requiring an external PostgreSQL instance in CI/test environments.

---

## 2. Technical Specification: R2 Automated Backup and Disaster Recovery

### 2.1 Architectural Overview
The backup and recovery system will be implemented natively within Laravel using dedicated service classes and Artisan commands, with shell scripts providing operational wrappers for cron jobs and container environments.

```
                  ┌─────────────────────────────────────────────────┐
                  │                 Backup Trigger                  │
                  │ (Artisan CLI / Cron Script / Host Shell Script) │
                  └───────────────────────┬─────────────────────────┘
                                          │
                                          ▼
                         ┌─────────────────────────────────┐
                         │      App\Services\Backup\       │
                         │          BackupService          │
                         └───────┬─────────────────┬───────┘
                                 │                 │
                ┌────────────────┴────────┐        └────────────────┐
                ▼                         ▼                         ▼
┌──────────────────────────────┐ ┌──────────────────┐ ┌───────────────────────────┐
│     DatabaseBackupManager    │ │  StorageArchiver │ │      ManifestManager      │
│  - PgSqlProvider (pg_dump)   │ │  - private/      │ │  - metadata, timestamps   │
│  - SqliteProvider (fallback) │ │  - public/       │ │  - SHA-256 hash, contents │
└───────────────┬──────────────┘ └────────┬─────────┘ └─────────────┬─────────────┘
                │                         │                         │
                └─────────────────────────┼─────────────────────────┘
                                          │
                                          ▼
                       ┌──────────────────────────────────────┐
                       │              ZipArchive              │
                       │ storage/app/backups/backup-*.zip     │
                       └──────────────────────────────────────┘
```

### 2.2 Backup Archive Structure
Every generated archive is a standard `.zip` file named `backup-YYYY-MM-DD_HHmmss.zip` containing:

```
backup-2026-09-13_162000.zip
├── manifest.json
├── database.sql
└── storage/
    ├── private/
    │   ├── document-drafts/
    │   │   └── ...
    │   └── document-officials/
    │       ├── 1/
    │       │   └── versions/
    │       │       └── 1.pdf
    │       └── ...
    └── public/
        └── ...
```

#### `manifest.json` Schema:
```json
{
  "system": "Barangay Sta. Felomina Document Archiving System",
  "version": "1.0",
  "created_at": "2026-09-13T16:20:00+08:00",
  "environment": "production",
  "database": {
    "driver": "pgsql",
    "database": "barangay_system",
    "dump_file": "database.sql"
  },
  "storage": {
    "private_included": true,
    "public_included": true,
    "file_count": 45,
    "total_bytes": 15829104
  },
  "checksum": "a8f5c3817e923...",
  "generator": "php artisan backup:create"
}
```

### 2.3 PostgreSQL Dump & Restore Mechanics

#### 2.3.1 Database Dump (`pg_dump`):
- **Command Invocation:**
  ```bash
  pg_dump --host={host} --port={port} --username={username} --clean --if-exists --no-owner --file={target_sql} {database}
  ```
- **Security / Password Handling:**
  - Do NOT pass passwords on the command line (to prevent leakage in `ps` process table or terminal logs).
  - Pass credentials via environment array using Laravel Process facade:
    ```php
    Process::env([
        'PGPASSWORD' => $dbConfig['password'],
    ])->run([
        $pgDumpBinary,
        '--host=' . $dbConfig['host'],
        '--port=' . $dbConfig['port'],
        '--username=' . $dbConfig['username'],
        '--clean',
        '--if-exists',
        '--no-owner',
        '--file=' . $tempSqlPath,
        $dbConfig['database'],
    ]);
    ```
- **Binary Path Resolution:**
  - Check configured path in `config/database.php` or `env('PG_DUMP_PATH')`.
  - Fall back to auto-detection: `which pg_dump` on Linux/Docker, `where.exe pg_dump` or standard `C:\Program Files\PostgreSQL\16\bin\pg_dump.exe` on Windows.
  - Fall back to plain `pg_dump` if present in system `PATH`.

#### 2.3.2 Database Restore (`psql`):
- **Command Invocation:**
  ```bash
  psql --host={host} --port={port} --username={username} --dbname={database} --file={extracted_sql}
  ```
- **Safety Pre-Flight Checks:**
  1. Connection test: Execute `SELECT 1;` before attempting restoration.
  2. Driver verification: Verify the backup manifest matches the current database connection.
  3. Interactive confirmation: Require confirmation prompt unless `--force` is passed.
- **Security / Password Handling:**
  - Pass credentials via `PGPASSWORD` environment variable.

### 2.4 Artisan Command Specifications

#### 2.4.1 `backup:create`
- **Command Signature:**
  ```text
  backup:create 
    {--filename= : Custom name for the backup archive}
    {--path= : Custom directory to store the archive}
    {--only-db : Archive only the database without storage files}
    {--only-storage : Archive only storage files without database}
  ```
- **Execution Flow:**
  1. Print startup banner with timestamp and environment.
  2. Create temporary working directory in `storage/framework/cache/backup_temp_*`.
  3. If not `--only-storage`:
     - Resolve database driver and binary paths.
     - Dump database to `database.sql`.
     - Log database dump completion and uncompressed size.
  4. If not `--only-db`:
     - Scan `storage/app/private` and `storage/app/public` (ignoring `.gitignore`).
     - Collect relative file paths and calculate total file count and bytes.
  5. Generate `manifest.json` with metadata, timestamp, file counts, and checksum.
  6. Initialize `ZipArchive`, compress `manifest.json`, `database.sql`, and all storage files.
  7. Compute final archive SHA-256 hash.
  8. Clean up temporary working directory.
  9. Display completion table: archive filename, file path, archive size, file count, and duration.
  10. Return exit code `0`.

#### 2.4.2 `backup:restore`
- **Command Signature:**
  ```text
  backup:restore 
    {archive? : Name or path of the backup archive to restore}
    {--force : Force restore without interactive confirmation prompt}
    {--only-db : Restore only database records}
    {--only-storage : Restore only storage files}
    {--clean : Clean existing storage before restoring files}
  ```
- **Execution Flow:**
  1. Locate archive:
     - If `archive` is omitted, list the 5 most recent backups in `storage/app/backups/` and prompt selection.
     - If `archive` is a filename, look in `storage/app/backups/{filename}`.
     - If `archive` is a full path, verify file existence and readability.
  2. Validate archive integrity:
     - Check file existence and zip readability (`ZipArchive::open`).
     - Extract and inspect `manifest.json`.
     - Verify database compatibility and checksum.
  3. Confirmation Guard:
     - Display summary of the archive: created at, database name, file count.
     - If `--force` is false:
       ```text
       WARNING: Restoring will overwrite existing database records and storage files!
       Do you wish to proceed? (yes/no) [no]:
       ```
     - If user declines, terminate with `Restore cancelled.` (exit code 0).
  4. Perform Pre-Flight Database Check:
     - Test connection to target database. If unreachable, abort with descriptive error.
  5. Execute Database Restoration (unless `--only-storage`):
     - Extract `database.sql` to secure temp path.
     - Execute `psql` restore with `PGPASSWORD`.
     - Verify process exit code is 0.
  6. Execute Storage Restoration (unless `--only-db`):
     - If `--clean` is set, wipe existing files in `storage/app/private/document-drafts` and `document-officials`.
     - Extract files from zip archive into `storage/app/private` and `storage/app/public`.
     - Set proper file permissions.
  7. Clear application caches:
     - `php artisan cache:clear`
     - `php artisan view:clear`
  8. Output success message with timestamp and restored summary.

---

## 3. Shell Scripts Specification (`scripts/`)

To support unattended scheduled backups and cross-platform administration, four scripts will be provided in `scripts/`:

### 3.1 `scripts/backup.sh` (Linux / Docker Host / Cron)
- **Role:** Autonomous scheduled backup runner for Linux servers and Docker hosts.
- **Capabilities:**
  - Detects if running inside Docker or on Docker host.
  - If Docker host: executes `docker compose exec -T app php artisan backup:create --no-interaction`.
  - If bare-metal Linux: executes `php artisan backup:create --no-interaction`.
  - Retention Policy: Prunes archives older than 30 days (`find $BACKUP_DIR -name "backup-*.zip" -mtime +30 -delete`).
  - Standardized exit logging to `/var/log/barangay-backup.log`.

### 3.2 `scripts/restore.sh` (Linux / Docker Host)
- **Role:** Interactive restore helper for Linux administrators.
- **Capabilities:**
  - Displays list of available backups.
  - Requires explicit confirmation (`read -p "Type RESTORE to confirm: " CONFIRM`).
  - Puts application into maintenance mode (`php artisan down`).
  - Runs `backup:restore`.
  - Brings application back up (`php artisan up`).

### 3.3 `scripts/backup.bat` (Windows / Laragon / Task Scheduler)
- **Role:** Windows native backup runner compatible with Windows Task Scheduler.
- **Capabilities:**
  - Sets PHP and PostgreSQL paths if running under Laragon.
  - Invokes `php artisan backup:create --no-interaction`.
  - Logs results to `storage\logs\backup.log`.

### 3.4 `scripts/restore.bat` (Windows / Laragon)
- **Role:** Windows native restore helper with interactive confirmation.

---

## 4. Docker Environment Enhancements

To ensure the backup and restore system functions inside Docker containers:

1. **Update `Dockerfile`:**
   Add `postgresql-client` to the `app` image package list:
   ```dockerfile
   RUN apt-get update \
       && apt-get install -y --no-install-recommends \
           libpq-dev \
           libzip-dev \
           unzip \
           postgresql-client \
       && docker-php-ext-install \
           opcache \
           pdo_pgsql \
           pgsql \
           zip \
       && rm -rf /var/lib/apt/lists/*
   ```
2. **Docker Storage Persistence:**
   - In `docker-compose.yml`, `app_storage` is already mounted to `/var/www/html/storage`.
   - Backups created in `storage/app/backups` are automatically preserved across container restarts.
   - For host-level extraction: `docker compose cp app:/var/www/html/storage/app/backups ./host-backups/` allows off-site copying.

---

## 5. Test Infrastructure Specification

### 5.1 Test Strategy & Constraints
- In default Pest testing (`phpunit.xml`), the database is `sqlite` in `:memory:`.
- To achieve 100% reliable automated feature testing:
  1. Use `Illuminate\Support\Facades\Process::fake()` to test external binary invocations (`pg_dump` and `psql`), verifying exact flags, environment variables (`PGPASSWORD`), and process error codes.
  2. Implement native SQLite backup/restore handling in `BackupService` so end-to-end testing with SQLite can run without fakes if desired.
  3. Use `Illuminate\Support\Facades\Storage::fake()` or isolated temporary directories to test `ZipArchive` generation, directory traversal, manifest verification, checksum calculation, and file restoration.

### 5.2 Proposed Test Suite (`tests/Feature/BackupRestoreTest.php`)
1. **`test_backup_create_command_generates_valid_zip_archive`:**
   - Creates fake files in private storage (`document-drafts/sample.pdf`, `document-officials/1/versions/1.pdf`).
   - Runs `php artisan backup:create`.
   - Asserts exit code 0.
   - Asserts backup file exists in backup directory.
   - Opens zip archive and asserts `manifest.json`, `database.sql`, and storage files are present.
2. **`test_backup_manifest_contains_valid_metadata_and_checksum`:**
   - Verifies manifest contains app name, driver, created timestamp, file count, and sha256 checksum.
3. **`test_backup_create_flags`:**
   - Tests `--only-db` creates archive without storage directory.
   - Tests `--only-storage` creates archive without `database.sql`.
   - Tests custom `--filename=custom-test.zip`.
4. **`test_backup_restore_requires_confirmation_without_force_flag`:**
   - Runs `backup:restore` without `--force`.
   - Expects confirmation question; when answered 'no', asserts command aborts and database/storage remain untouched.
5. **`test_backup_restore_with_force_flag_restores_storage_files`:**
   - Generates backup with known test files.
   - Deletes test files from storage.
   - Runs `backup:restore test-archive.zip --force`.
   - Asserts test files are restored to storage with identical contents.
6. **`test_backup_restore_validates_corrupted_archive`:**
   - Passes a non-zip file or corrupted zip archive.
   - Asserts command fails with clear error and exit code > 0.
7. **`test_backup_restore_validates_missing_manifest`:**
   - Passes zip archive lacking `manifest.json`.
   - Asserts command fails with message `Invalid backup archive: missing manifest.json`.

---

## 6. Technical Specification: R3 Deployment and Administrative Turnover Documentation

Three formal documentation files will be created in `docs/`:

### 6.1 Document 1: `docs/ADMIN_OPERATOR_MANUAL.md`
**Title:** Administrative Guide & Operator Manual: Barangay Sta. Felomina Document Archiving, Verification, and Resident Profiling System  
**Audience:** Barangay Captain, Barangay Secretary, Purok Chairpersons, Administrative Staff  

#### Outline & Structure:
1. **System Overview & Objectives**
   - Purpose of the system: Digital archiving, tamper-evident QR verification, resident profiling, reporting.
   - Core philosophy: Digital workflow accompanying real-world paper governance.
2. **Role & Permission Matrix**
   - Detailed breakdown of capabilities for Captain, Secretary, and Purok Chairpersons.
   - Security boundaries: Purok isolation, approval locks, audit protection.
3. **Barangay Captain Operations**
   - **Dashboard Overview:** High-level metrics, pending document count, resident totals.
   - **Approval Workflow:** Reviewing pending documents, reading Secretary notes, verifying draft PDF, one-click Approve & Publish (freezing immutable version), Returning for Revision with required comment.
   - **Document Lifecycle Management:** Archiving outdated records, Revoking invalidated documents (mandatory reason), Superseding documents with replacement links.
   - **Staff Account Administration:** Creating staff accounts, role assignment, Purok Chairperson linking, password resets with one-time temporary passwords, deactivating accounts, last-active-Captain safeguards.
   - **Categorization Rules Administration:** Managing keyword rules, priority ordering, activating/deactivating rules.
   - **System Audit Logs:** Reviewing immutable chronological audit trails with actor, action, and context.
4. **Barangay Secretary Operations**
   - **Document Ingestion:** Registering new resolutions, ordinances, certifications, memoranda, minutes; title, document number validation (format `RES-2026-001`), date, tags.
   - **Rule-Based Categorization:** Reviewing automated category suggestions, accepting or manually overriding.
   - **Permanent QR Code Workflow:** Downloading deterministic SVG QR code; embedding onto document template prior to printing and physical signing.
   - **Draft PDF Upload & Replacement:** Uploading signed draft PDF (max 10MB, PDF format), replacing drafts prior to approval.
   - **Submitting for Approval:** Forwarding draft to Captain queue; handling returned revisions.
   - **Document Search & Retrieval:** Filtering archive by category, type, year, status, keyword.
   - **Official Report Generation:** Document inventory reports, demographic summaries, official printable view with Barangay letterhead and signature blocks.
5. **Purok Chairperson Operations (Puroks 1–7)**
   - **Purok Dashboard:** Assigned Purok demographic summary, active residents, households.
   - **Resident Profiling:** Adding residents (full name, birthdate, civil status, voter status, contact), duplicate name warnings and override procedure, updating status (Active, Transferred, Deceased, Inactive).
   - **Household Management:** Creating household units, assigning household head, linking family members.
   - **Purok Reports:** Generating and printing resident lists for Purok administration.
   - **Data Privacy & Isolation:** Explaining strict isolation (no access to other Puroks or official legislative documents).
6. **Public QR Code Verification**
   - Public access without authentication.
   - Scanning QR or navigating to `/verify/{token}`.
   - Verification states: Verified (green), Pending (amber), Draft (slate), Archived (sky), Revoked (red), Superseded (purple).
   - Public download of official published PDF.

---

### 6.2 Document 2: `docs/BACKUP_AND_RESTORE.md`
**Title:** Backup, Disaster Recovery, and Data Retention Manual  
**Audience:** System Administrators, IT Support, Designated Barangay Backup Operator (Captain/Secretary)  

#### Outline & Structure:
1. **Disaster Recovery Principles**
   - Critical data assets: PostgreSQL database (`barangay_system`) and application storage (`storage/app/private/document-drafts`, `document-officials`).
   - Backup strategy: Self-contained, timestamped, encrypted-ready `.zip` archives with verifiable SHA-256 manifests.
   - Designation of operational responsibility: Barangay Captain as administrative owner, Barangay Secretary as operational custodian.
2. **Backup Storage & Naming Conventions**
   - Standard archive location: `storage/app/backups/`.
   - Naming convention: `backup-YYYY-MM-DD_HHmmss.zip`.
   - Manifest contents and integrity verification.
3. **Manual Backup Procedures**
   - Execution via Artisan:
     ```bash
     php artisan backup:create
     php artisan backup:create --only-db
     php artisan backup:create --only-storage
     ```
   - Off-site export: Copying archives to secure external media (USB drive, off-site drive).
4. **Automated & Scheduled Backups**
   - Setting up Linux Cron: Daily backup at 02:00 AM via `scripts/backup.sh`.
   - Setting up Windows Task Scheduler: Daily execution via `scripts/backup.bat`.
   - Retention policy and automatic pruning of archives older than 30 days.
5. **Restoration Procedures (Step-by-Step)**
   - Interactive recovery:
     ```bash
     php artisan backup:restore backup-2026-09-13_162000.zip
     ```
   - Safety prompts and confirmation bypass (`--force`).
   - Verifying database connectivity and archive checksum before execution.
   - Post-restore verification checklist: logging in, checking document records, verifying PDF downloads.
6. **Disaster Recovery Runbooks**
   - **Scenario A: Accidental Data Deletion / Overwrite:** Restoring specific database state or storage files.
   - **Scenario B: Hardware Failure / Server Loss:** Complete bare-metal restore procedure from scratch.
   - **Scenario C: Database Corruption:** Purging corrupt PostgreSQL database and executing clean restore.
7. **Periodic Verification & Drill Schedule**
   - Quarterly disaster recovery simulation protocol.

---

### 6.3 Document 3: `docs/DEPLOYMENT_GUIDE.md`
**Title:** Production Deployment, Docker Configuration, and Systems Maintenance Guide  
**Audience:** DevOps Engineers, Server Administrators, Capstone Evaluators  

#### Outline & Structure:
1. **Target Deployment Environments**
   - **Option 1: Dockerized Deployment (Recommended):** Multi-container setup via Docker Compose.
   - **Option 2: On-Premises Linux Server:** Ubuntu 22.04 / 24.04 LTS with Nginx, PHP 8.4-FPM, PostgreSQL 16.
   - **Option 3: Local Barangay On-Premises Workstation:** Windows 10/11 with Laragon or standalone services.
2. **System Prerequisites & Dependencies**
   - Hardware requirements: 2 CPU cores, 4GB RAM, 20GB SSD storage.
   - Software requirements: PHP 8.4+ (extensions: `pdo_pgsql`, `pgsql`, `zip`, `opcache`, `mbstring`, `xml`, `curl`), PostgreSQL 16+, Nginx 1.24+, Composer 2.x, Node.js 20+ & npm, Git.
   - PostgreSQL client utilities: `pg_dump`, `psql`.
3. **Step-by-Step Production Linux Installation**
   1. Repository cloning and file permissions (`chown -R www-data:www-data storage bootstrap/cache`).
   2. Environment configuration (`cp .env.example .env`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`).
   3. Composer installation (`composer install --no-dev --optimize-autoloader`).
   4. Application key generation (`php artisan key:generate`).
   5. PostgreSQL database and user creation.
   6. Database migrations (`php artisan migrate --force`).
   7. Initial seeders (`php artisan db:seed --class=DocumentTaxonomySeeder --force`, `AccountSeeder`).
   8. Storage symlink creation (`php artisan storage:link`).
   9. Compiling production assets (`npm ci && npm run build`).
   10. Optimization cache warmup (`php artisan config:cache`, `route:cache`, `view:cache`).
   11. Nginx virtual host configuration (complete sample `.conf` with reverse proxy to PHP-FPM, security headers, upload limit `client_max_body_size 25M`).
   12. SSL/HTTPS provisioning using Let's Encrypt / Certbot (`certbot --nginx -d ...`).
4. **Docker Compose Production Setup**
   - Configuration adjustments for production (secure `APP_KEY`, PostgreSQL passwords, volume persistence).
   - Container startup (`docker compose up -d --build`).
   - Checking service health (`docker compose ps`, `docker compose logs -f`).
   - Reverse proxying through host Nginx or Cloudflare Tunnel.
5. **Ongoing Maintenance & Operations**
   - Upgrading system and applying migrations.
   - Rotating application logs and pruning cache.
   - Monitoring storage usage.
   - Troubleshooting common operational issues:
     - 502 Bad Gateway / PHP-FPM socket errors.
     - Database connection refused / PostgreSQL authentication errors.
     - Storage permission denied errors.
     - Missing Vite manifest in production.
     - `pg_dump` binary not found.

---

## 7. Implementation Roadmap & Concrete Action Items

| Item | Component | Exact Files to Create / Modify | Dependencies |
|---|---|---|---|
| **1** | Backup Core Services | `app/Services/Backup/BackupService.php`<br>`app/Services/Backup/DatabaseBackupProvider.php`<br>`app/Services/Backup/PostgreSqlBackupProvider.php`<br>`app/Services/Backup/SqliteBackupProvider.php`<br>`app/Services/Backup/StorageArchiver.php` | PHP `ZipArchive`, `Illuminate\Support\Facades\Process` |
| **2** | Artisan Commands | `app/Console/Commands/BackupCreateCommand.php`<br>`app/Console/Commands/BackupRestoreCommand.php`<br>`routes/console.php` | `BackupService` |
| **3** | Shell & Batch Scripts | `scripts/backup.sh`<br>`scripts/restore.sh`<br>`scripts/backup.bat`<br>`scripts/restore.bat` | Artisan commands |
| **4** | Dockerfile Update | `Dockerfile` (install `postgresql-client` in app stage) | Debian package repository |
| **5** | Feature Tests | `tests/Feature/BackupRestoreTest.php` | Pest, `Process::fake()`, `Storage::fake()` |
| **6** | Operator Manual | `docs/ADMIN_OPERATOR_MANUAL.md` | Role workflows, UI prototypes |
| **7** | Backup Manual | `docs/BACKUP_AND_RESTORE.md` | Backup commands & scripts |
| **8** | Deployment Guide | `docs/DEPLOYMENT_GUIDE.md` | Nginx, Docker, PostgreSQL configs |

---

## 8. Verification Plan & Sign-Off Criteria

1. **Artisan Command Verification:**
   - Execute `php artisan backup:create` on live development PostgreSQL database.
   - Verify generated archive in `storage/app/backups/`.
   - Inspect archive contents: valid `manifest.json`, `database.sql`, and storage directory.
   - Execute `php artisan backup:restore` with `--force` on a verified archive and confirm data integrity.
2. **Script Verification:**
   - Test `scripts/backup.sh` and `scripts/backup.bat` for successful execution and proper exit code `0`.
3. **Docker Verification:**
   - Verify `pg_dump` and `psql` are available inside container image when built with updated Dockerfile.
4. **Pest Feature Suite:**
   - Run `vendor/bin/pest tests/Feature/BackupRestoreTest.php` and verify all tests pass.
   - Confirm full test suite continues to pass (`vendor/bin/pest`).
5. **Code Style & Static Analysis:**
   - `vendor/bin/pint --dirty --format agent` passes with zero formatting issues.
   - `composer run types:check` (`phpstan`) passes with zero errors.
6. **Documentation Completeness:**
   - Verify `docs/ADMIN_OPERATOR_MANUAL.md`, `docs/BACKUP_AND_RESTORE.md`, and `docs/DEPLOYMENT_GUIDE.md` are completely authored, readable, and free of placeholder text.
