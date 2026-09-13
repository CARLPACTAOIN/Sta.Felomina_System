# Operations & Storage Survey Handoff Report

**Explorer:** teamwork_preview_explorer (Storage and Operations Explorer)  
**Parent:** 1a0f86b1-f133-4d9d-81e5-8f9757cf60a2  
**Date:** 2026-09-13  
**Deliverable Document:** `C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_survey_ops\survey_ops.md`  

---

## 1. Observation

1. **Database Configuration & Binaries:**
   - In `config/database.php` (lines 87–100), PostgreSQL connection uses `host`, `port`, `database`, `username`, `password`, `search_path => 'public'`.
   - In `.env` (lines 24–29), active configuration is:
     ```text
     DB_CONNECTION=pgsql
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_DATABASE=barangay_system
     DB_USERNAME=postgres
     DB_PASSWORD=sepaktakraw
     ```
   - On host system, `pg_dump.exe` and `psql.exe` version 16.14 are installed at `C:\Program Files\PostgreSQL\16\bin\` and present in `PATH`.
   - Tool execution `$env:PGPASSWORD='sepaktakraw'; pg_dump -h 127.0.0.1 -p 5432 -U postgres -d barangay_system --schema-only` ran successfully with exit code 0.
   - Tool execution `$env:PGPASSWORD='sepaktakraw'; psql -h 127.0.0.1 -p 5432 -U postgres -d barangay_system -c "SELECT current_database(), current_user;"` returned `barangay_system | postgres` with exit code 0.

2. **Storage Structure & File Paths:**
   - In `config/filesystems.php` (lines 33–48), the `local` disk root is `storage_path('app/private')`, and the `public` disk root is `storage_path('app/public')`.
   - In `resources/views/pages/documents/⚡show.blade.php` (line 53), draft PDFs are stored via `$path = $uploadedFile->store('document-drafts');` which places them in `storage/app/private/document-drafts/`.
   - In `app/Models/DocumentRecord.php` (lines 391–393), approved official PDF versions are stored in `storage/app/private/document-officials/{id}/versions/{version_number}.pdf`.
   - In `app/Http/Controllers/DocumentQrCodeController.php` (lines 17–28), QR codes are rendered dynamically as on-demand SVG responses from `bacon/bacon-qr-code` using `$documentRecord->verificationUrl()`. They do not produce static files on disk, but any private storage assets will be captured in archives.
   - Target backup destination `storage/app/backups` does not yet exist and should be created by the backup service.

3. **Artisan Commands & Console Routes:**
   - `app/Console/Commands/` is currently empty.
   - `routes/console.php` contains only the default `inspire` command.
   - PHP extensions: `php -m` confirmed `zip`, `pdo_pgsql`, `pgsql`, `pdo_sqlite`, and `sqlite3` are loaded.

4. **Docker Architecture & Dependencies:**
   - `docker-compose.yml` defines three services: `app` (`php:8.4-fpm-bookworm`), `db` (`postgres:16-alpine`), and `nginx` (`nginx:1.27-alpine`).
   - Volumes: `app_storage` is mounted to `/var/www/html/storage`, and `db_data` is mounted to `/var/lib/postgresql/data`.
   - In `Dockerfile` (lines 29–39), `apt-get install` installs `libpq-dev`, `libzip-dev`, and `unzip`, but **`postgresql-client` is not installed** in the `app` container.

5. **Test Framework & Test Environment:**
   - In `phpunit.xml` (lines 26–27), tests run with:
     ```xml
     <env name="DB_CONNECTION" value="sqlite"/>
     <env name="DB_DATABASE" value=":memory:"/>
     ```
   - In `tests/Pest.php` (lines 17–19), `pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`.
   - Laravel `Illuminate\Support\Facades\Process` and PHP `ZipArchive` are available.

6. **Documentation & User Workflows:**
   - `docs/` currently contains only `barangay_document_system_ui_prototype.html`.
   - `PRD_V1.md` (§7, §10–18, §19–23, §25, §27–31, §33, §35) defines explicit workflows for Captain (review, approve, return, archive, revoke, supersede, staff accounts, categorization rules, reports), Secretary (create, suggest category, QR download, upload draft PDF, submit for approval, reports), Purok Chairpersons (isolated resident profiling and household management for Puroks 1–7), and Public Users (unauthenticated QR verification and published PDF download).

---

## 2. Logic Chain

1. **Database Backup Strategy:**
   - From Observation 1, the production/dev database is PostgreSQL 16, and `pg_dump` can dump the schema and data cleanly.
   - Because exposing credentials on the command line creates security vulnerabilities in process logs, passwords must be passed via the `PGPASSWORD` environment variable in `Illuminate\Support\Facades\Process`.
   - Because Observation 5 shows the automated test suite uses SQLite in-memory, `BackupService` must abstract database dumping: using `PostgreSqlBackupProvider` when driver is `pgsql`, and a `SqliteBackupProvider` or `Process::fake()` when running tests.

2. **Storage Archive Strategy:**
   - From Observation 2, all confidential document drafts and immutable official approved PDF versions reside under `storage/app/private/` (`document-drafts/` and `document-officials/`).
   - Using PHP's loaded `ZipArchive` extension (Observation 3), the system can bundle `database.sql`, `storage/private/`, and `storage/public/` along with a SHA-256 validated `manifest.json` into a single timestamped zip file (`storage/app/backups/backup-YYYY-MM-DD_HHmmss.zip`).

3. **Restoration Safety Guard:**
   - A database and storage restore is inherently destructive to current operational state.
   - Therefore, `backup:restore` must require explicit confirmation (`$this->confirm()`) in interactive mode, allow bypassing via `--force`, perform connection pre-checks, validate archive zip integrity and manifest schema before touching the database or filesystem, and wipe/clean storage only after verification succeeds.

4. **Docker Container Support:**
   - From Observation 4, running `php artisan backup:create` inside the `app` container will fail if `pg_dump` is missing from the container image.
   - Adding `postgresql-client` to `Dockerfile` installs `pg_dump` and `psql` directly in the `app` image, allowing uniform execution inside containers (`docker compose exec app php artisan backup:create`) or from outside scripts.

5. **Operational Turnover Documentation:**
   - From Observation 6, `docs/` needs three complete manuals matching PRD §7, §27, §29, §31, and §36:
     - `docs/ADMIN_OPERATOR_MANUAL.md`: Step-by-step role guides for Captain, Secretary, and Purok Chairpersons.
     - `docs/BACKUP_AND_RESTORE.md`: Runbooks for backup commands, scheduling, disaster recovery, and verification.
     - `docs/DEPLOYMENT_GUIDE.md`: Linux, Docker, and Windows setup instructions, prerequisites, Nginx configs, and maintenance procedures.

---

## 3. Caveats

1. **SQLite In-Memory Limitation in Test Runner:**
   - A SQLite in-memory database (`:memory:`) cannot be backed up using an external process binary like `pg_dump` or `sqlite3` CLI because it exists exclusively in the active memory of the PHP test runner process. Automated feature tests should therefore test process execution via `Process::fake()` and test zip extraction and file restoration via temporary storage.
2. **Third-Party Backup Packages:**
   - While packages like `spatie/laravel-backup` exist, PRD V1 (§27) requires a practical capstone-level mechanism without adding unnecessary third-party package bloat or external dependencies. Native implementation with Laravel Process and ZipArchive fulfills all requirements cleanly.
3. **No Code Written:**
   - In accordance with explorer role boundaries, no application code was modified or added. All detailed designs are documented in `survey_ops.md`.

---

## 4. Conclusion

The operational survey for R2 (Backup & Disaster Recovery) and R3 (Administrative Documentation) is complete. The application storage layout, PostgreSQL tooling, Docker configuration, and test suites are fully understood and documented.

A comprehensive specification and architectural blueprint has been produced in:
`C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_survey_ops\survey_ops.md`

Key implementation blueprints delivered:
- Complete design of `BackupService`, `PostgreSqlBackupProvider`, `SqliteBackupProvider`, and `StorageArchiver`.
- Detailed Artisan command signatures and execution flows for `backup:create` and `backup:restore`.
- Specifications for cross-platform automation scripts in `scripts/` (`backup.sh`, `restore.sh`, `backup.bat`, `restore.bat`).
- Precise `Dockerfile` update to include `postgresql-client`.
- Outline and section specifications for `docs/ADMIN_OPERATOR_MANUAL.md`, `docs/BACKUP_AND_RESTORE.md`, and `docs/DEPLOYMENT_GUIDE.md`.
- Test strategy for `tests/Feature/BackupRestoreTest.php`.

---

## 5. Verification Method

To independently verify the observations and findings in this report:

1. **Verify PostgreSQL Tools on Host:**
   ```powershell
   pg_dump --version
   psql --version
   $env:PGPASSWORD='sepaktakraw'; pg_dump -h 127.0.0.1 -p 5432 -U postgres -d barangay_system --schema-only
   ```
2. **Verify PHP Zip & Database Extensions:**
   ```powershell
   php -r "echo extension_loaded('zip') && extension_loaded('pdo_pgsql') ? 'OK' : 'FAIL';"
   ```
3. **Inspect Application Storage Directories:**
   ```powershell
   Get-ChildItem -Path storage\app\
   ```
4. **Inspect Test Configuration:**
   Inspect `phpunit.xml` lines 26–28 and `tests/Pest.php` lines 17–19.
5. **Inspect Comprehensive Survey Report:**
   Read `C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_survey_ops\survey_ops.md`.
