# Specification and Business Rules Survey: Sta. Felomina Barangay System Capstone V1 Remaining Modules

**Survey Date:** 2026-09-13  
**Auditor Archetype:** `teamwork_preview_spec_miner` (Specification and Rules Miner)  
**Workspace:** `C:\laragon\www\Barangay-System`  
**Output Path:** `.agents\teamwork_preview_spec_miner_survey\survey_specs.md`  
**Target Milestone:** Capstone V1 Completion (Modules R1, R2, R3)  

---

## 1. Executive Summary and Product Context

The **Web-Based Barangay Document Archiving, Verification, and Resident Profiling System for Barangay Sta. Felomina, Makilala, Cotabato** is a Laravel 12 application on PHP 8.4 with PostgreSQL 16, Livewire 4, Flux UI, Tailwind CSS 4, and Pest 5.

Based on the authoritative specifications (`PRD_V1.md`, `ORIGINAL_REQUEST.md`, `AGENTS.md`, `MEMORY.md`, `PRD_PROGRESS_AUDIT.md`, and `.ai/rules/`), the core domain modules (Authentication, Staff Administration, Purok Isolation, Document Records, QR Verification, File Versions, Approval Workflow, Rule-Based Categorization, Resident/Household Profiling, Operational Dashboards, and Reporting/Print Export) are already implemented and functionally verified by 160 automated tests.

The remaining Capstone V1 scope consists of three operational and turnover modules:
1. **R1: Comprehensive Demo Dataset Seeder (`DemoDataSeeder`)**
2. **R2: Automated Backup and Disaster Recovery System**
3. **R3: Deployment and Administrative Turnover Documentation**

This survey exhaustively enumerates all specifications, database schemas, model relationships, lifecycle states, business invariants, safety constraints, command interfaces, edge cases, and documentation structures required to implement and verify R1, R2, and R3.

---

## 2. Authoritative Specification Hierarchy

Per `AGENTS.md` and `MEMORY.md`:
1. **User Current Request (`ORIGINAL_REQUEST.md`):** Controls the immediate task requirements and acceptance criteria.
2. **`PRD_V1.md`:** Authoritative product truth for Capstone V1 (scope, business rules, workflows, acceptance criteria).
3. **`AGENTS.md` & `.ai/rules/`:** Cross-cutting and path-scoped engineering guardrails (server-side authorization, no unapproved dependencies, typed PHP 8.4, Pest feature tests, Pint formatting).
4. **Codebase & Existing Tests:** Proof of implemented contracts, models, enums, migrations, and assertions.
5. **`docs/barangay_document_system_ui_prototype.html`:** Visual interaction reference only; fictional sample data, not implementation truth.

---

## 3. Module R1: Comprehensive Demo Dataset Seeder (`DemoDataSeeder`)

### 3.1 Objective & Role
Provide an idempotent database seeder (`database/seeders/DemoDataSeeder.php`) runnable via:
```bash
php artisan db:seed --class=DemoDataSeeder
```
It must populate realistic, coherent barangay records without violating unique constraints on multiple runs.

### 3.2 Required Data Domains & Specifications

#### 3.2.1 Document Records (`document_records`)
- **Document Types (`document_types`):**
  - Seeded types: `Resolution` (`RES`), `Ordinance` (`ORD`), `Certificate` (`CERT`), `Memorandum` (`MEM`), `Correspondence Record` (`COR`), `Meeting Minutes` (`MIN`).
  - Support `Executive Order` (`EO`) as requested.
  - Idempotent `updateOrCreate` on `code`.
- **Document Categories (`document_categories`):**
  - Existing taxonomy: `Governance`, `Health`, `Infrastructure`, `Social Services`, `Public Safety`, `Education`.
  - Also ensure categories/keywords mentioned in request: `Peace & Order` (can map to Public Safety or distinct category), `Disaster Risk Reduction` (can map to Public Safety or distinct category).
- **Years Covered:** Multi-year distribution spanning **2024, 2025, and 2026**.
- **Lifecycle States Covered:** Must include records for all 7 states of `App\Enums\DocumentStatus`:
  1. `Draft`: Working record created by Secretary; `draft_pdf_path` present or null; permanent `verification_token` exists; resolves to "Draft / Not Yet Verified".
  2. `Pending Approval`: Submitted by Secretary; `draft_pdf_path` is required; `submitted_at` and `submitted_by_id` populated; awaits Captain approval.
  3. `Needs Revision`: Returned by Captain; `return_comment` populated (mandatory non-empty reason); `returned_at` and `returned_by_id` populated.
  4. `Published`: Approved by Captain; `approved_at` and `approved_by_id` populated; permanent `verification_token` resolves to "Verified Official Barangay Document"; official immutable PDF version created in `document_file_versions` (`is_current = true`, `version_number = 1`, `file_path = document-officials/{id}/versions/1.pdf`).
  5. `Archived`: Published document archived by Captain; `archived_at`, `archived_by_id`, and mandatory `archive_reason` populated; verification page remains active ("Archived Official Record"); public PDF download permitted.
  6. `Revoked`: Published document revoked by Captain; `revoked_at`, `revoked_by_id`, and mandatory `revoke_reason` populated; verification page remains active ("Revoked Document"); public PDF download disabled.
  7. `Superseded`: Published document superseded by Captain; `superseded_at`, `superseded_by_id`, and mandatory `supersede_reason` populated; points to replacement `DocumentRecord` or `supersede_reference`; verification page remains active ("Superseded Document"); public PDF download disabled.
- **Numbering Uniqueness Constraint:**
  - Unique composite index: `['document_type_id', 'document_year', 'document_number']`.
  - Format: `{PREFIX}-{YEAR}-{NUMBER}`, e.g., `RES-2024-001`, `ORD-2025-004`, `CERT-2026-012`.
- **Physical File Generation for Demo Records:**
  - For `PendingApproval`, `NeedsRevision`, `Published`, `Archived`, `Revoked`, and `Superseded` demo records, working PDF files must physically exist in `Storage::disk('local')` (under `storage/app/private/document-drafts/` and `storage/app/private/document-officials/{id}/versions/{version}.pdf`) so that public verification download and administrative PDF viewing do not abort 404 during review.
  - PDFs can be valid minimal PDF headers (`%PDF-1.4\n%...\n%%EOF`) or generated demo binary streams.

#### 3.2.2 Households and Residents (`households`, `residents`)
- **Purok Distribution:** Distributed across **all 7 Puroks** (`Purok 1` through `Purok 7`) of Barangay Sta. Felomina.
- **Household Specifications (`households`):**
  - Unique `household_code` (e.g. `HH-P01-001` or generated `HH-XXXXXXXXXX`).
  - `purok_id` linked to valid Purok (1 to 7).
  - Realistic local addresses (e.g., "Purok 1, Barangay Sta. Felomina, Makilala, Cotabato").
  - `household_head_name` string.
  - Linked to creator user (Purok Chairperson or Secretary).
- **Resident Specifications (`residents`):**
  - Unique `resident_code` (e.g. `RES-P01-001` or generated `RES-XXXXXXXXXX`).
  - Linked to valid `purok_id` and `household_id` within the SAME Purok.
  - Demographics: realistic Filipino names, sex (`male`, `female`), realistic birthdates (spanning youth, working age, senior citizens), civil statuses (`single`, `married`, `widowed`, `separated`).
  - Voter Registration: realistic mix of `is_registered_voter = true` and `is_registered_voter = false`.
  - Status Distribution: Must include all values of `App\Enums\ResidentStatus`:
    - `Active` (majority)
    - `Transferred` (moved out of barangay)
    - `Deceased` (recorded deceased resident)
    - `Inactive` (long-term unreachable/unverified)
  - Realistic occupations: Farmer, Rubber Tapper, Public School Teacher, Barangay Health Worker, Tricycle Driver, Small Business Owner, Student, Retired.
  - Normalized full name automatically computed via `Resident::normalizeFullName()`.

#### 3.2.3 Historical Audit Logs (`audit_logs`)
- Must reflect authentic barangay operational history with historical timestamps matching the document and resident lifecycles (2024–2026).
- Action types:
  - Document lifecycle: `document_created`, `document_submitted`, `document_approved`, `document_returned`, `document_archived`, `document_revoked`, `document_superseded`.
  - Categorization: `document_category_suggested`, `document_category_overridden`.
  - Resident/Household: `household_created`, `resident_created`, `resident_status_changed`.
  - Staff administration: `user_created`, `user_activated`, `password_reset`.
- Populates `actor_id` (Captain, Secretary, Purok Chair), `auditable_type`, `auditable_id`, `summary`, and `context` JSON.

#### 3.2.4 In-App Notifications (`notifications`)
- Populates Laravel database notifications table for Captain (`captain@stafelomina.local`) and Secretary (`secretary@stafelomina.local`).
- Instances of `App\Notifications\DocumentWorkflowNotification` for pending approvals, returns, and approvals.

### 3.3 Idempotency Architecture
- **Rule:** Running the seeder multiple times MUST NOT throw duplicate key constraint exceptions (`unique` constraints on `document_records`, `residents`, `households`, `users`, `puroks`, `document_types`, `document_categories`).
- **Implementation Strategy:**
  - Documents: Keyed on `['document_type_id' => $typeId, 'document_year' => $year, 'document_number' => $number]`.
  - Households: Keyed on `['household_code' => $code]`.
  - Residents: Keyed on `['resident_code' => $code]`.
  - File Versions: Keyed on `['document_record_id' => $docId, 'version_number' => $version]`.
  - Audit Logs: Check existence before inserting or use deterministic natural keying.

---

## 4. Module R2: Automated Backup and Disaster Recovery System

### 4.1 System Requirements & Architecture
The system must provide an enterprise-grade, resilient backup and restore mechanism that protects:
1. **PostgreSQL Database (`barangay_system`)**: Table definitions, sequences, triggers, constraints, records.
2. **Application Storage Files (`storage/app/private/`)**: Uploaded draft PDFs (`document-drafts/`), official immutable approved PDF versions (`document-officials/`), and related files.
3. **Backup Manifest (`manifest.json`)**: Contains archive metadata, timestamp, application version, database driver, database name, file count, and SHA-256 hashes of the contents for integrity validation.

### 4.2 Artisan Commands Specification

#### 4.2.1 `php artisan backup:create`
- **Signature:**
  ```text
  backup:create 
      {--destination= : The custom directory to write the backup archive}
      {--filename= : Custom filename for the backup archive}
      {--only-db : Backup database only}
      {--only-storage : Backup storage files only}
  ```
- **Inputs:** Database credentials from `config/database.php` / `.env`, filesystem path from `config/filesystems.php`.
- **Outputs:** Timestamped ZIP archive: `backup-YYYY-MM-DD-His.zip` located in `storage/app/private/backups/` (or specified destination).
- **Archive Internal Structure:**
  ```text
  backup-2026-09-13-163000.zip
  ├── manifest.json
  ├── database.sql (or database.dump)
  └── storage/
      ├── document-drafts/
      └── document-officials/
  ```
- **Integrity Validation:** Computes SHA-256 hashes of `database.sql` and stored files into `manifest.json`.
- **Exit Codes:** `0` on success; non-zero (`1`) on connection failure or archive creation error.

#### 4.2.2 `php artisan backup:restore`
- **Signature:**
  ```text
  backup:restore 
      {archive? : Path or filename of the backup archive to restore (defaults to latest backup)}
      {--force : Force restoration without confirmation prompt (required in non-interactive environments)}
      {--only-db : Restore database only}
      {--only-storage : Restore storage files only}
  ```
- **Inputs:** Path to target ZIP archive, `--force` flag.
- **Safety Validations (Mandatory Pre-flight Checks):**
  1. **Archive Existence:** Verifies file exists and is readable.
  2. **Archive Format & Integrity:** Inspects ZIP container, checks for `manifest.json` and `database.sql`, verifies SHA-256 checksums if manifest is present.
  3. **Database Connection Pre-flight:** Executes a ping/connection check to ensure the database is reachable before any destructive wipe or restore step.
  4. **Confirmation Guard:** Unless `--force` is specified, prompts:
     `"WARNING: This will overwrite current database records and private storage files. Do you wish to continue?"`
     If answered 'no', restoration halts immediately with exit code `1` and no data is modified.
- **Restoration Sequence:**
  1. Temporary extraction to an isolated directory in `storage/framework/cache` or system temp.
  2. Database restore: Executes SQL dump against the active connection.
  3. Storage restore: Restores private files into `storage/app/private/` preserving hierarchy.
  4. Post-restore cleanup & cache flushing: Calls `cache:clear`, `view:clear`, `config:clear`.
- **Outputs:** Informative CLI progress bar/steps, success report with summary of restored tables and files.
- **Exit Codes:** `0` on success; `1` on error or aborted confirmation.

### 4.3 Script Interfaces (`scripts/`)

#### 4.3.1 `scripts/backup.sh`
- POSIX-compliant shell script for Linux/macOS and Docker environments.
- Supports execution:
  - Outside Docker (host environment): calls `php artisan backup:create`.
  - Inside Docker container: runs directly inside `app` container or via `docker compose exec -T app php artisan backup:create`.
- Supports automated cron scheduling (logs to `storage/logs/backup.log`).
- Retention pruning: optional retention parameter (e.g. keeping the last 7 daily archives).

#### 4.3.2 `scripts/restore.sh`
- POSIX-compliant shell script for disaster recovery.
- Accepts archive path parameter and optional `-f` / `--force` flag.
- Detects whether running inside container or on host.
- Verifies exit code and prints clear status.

### 4.4 Multi-Driver Database Consideration
- In production and Docker, `DB_CONNECTION=pgsql` (PostgreSQL 16) with host tools `pg_dump` and `psql` (or direct PDO SQL dumps).
- In automated test suite (`tests/Feature/`), `DB_CONNECTION=sqlite` with `:memory:` (or temporary file).
- **Architecture Requirement:** The backup service/commands must gracefully support PostgreSQL dump/restore when running against `pgsql`, while also supporting SQLite export/restore when running under SQLite testing, allowing 100% automated feature test coverage without requiring external services during test runs.

---

## 5. Module R3: Deployment and Administrative Turnover Documentation

### 5.1 Documentation Deliverables Overview
All documentation must be placed in `docs/` in clean, professional GitHub-flavored Markdown:
1. `docs/ADMIN_OPERATOR_MANUAL.md` — Administrative Guide & Operator Manual
2. `docs/BACKUP_AND_RESTORE.md` — Backup and Disaster Recovery Manual
3. `docs/DEPLOYMENT_GUIDE.md` — Deployment and Maintenance Guide

### 5.2 `docs/ADMIN_OPERATOR_MANUAL.md` Structure & Specifications
- **Target Audience:** Barangay Captain, Barangay Secretary, Purok Chairpersons.
- **Section 1: System Overview & Access**
  - System architecture summary, official URL, browser compatibility (Chrome, Edge, Firefox, mobile).
  - User roles & permission matrix (§7-8 of PRD).
  - First-time login, security settings (2FA, passkeys, password update).
- **Section 2: Staff Account Management (Captain Only)**
  - Creating staff accounts (Secretary, Purok Chairpersons).
  - Assigning Puroks (Purok 1 through 7).
  - One-time temporary password display and initial login handover.
  - Activating, deactivating, and resetting staff passwords.
  - Last-active Captain safeguards (preventing lockout).
- **Section 3: Document Archiving Workflow (Secretary & Captain)**
  - Document types and category taxonomy.
  - Creating a Draft record without PDF: required fields, numbering standards (`RES-YYYY-NNN`, etc.).
  - Generating and downloading permanent QR codes (Draft stage SVG download).
  - Physical document preparation: embedding QR code into document layout prior to signing.
  - Uploading final signed PDF (PDF only, max 10MB, private storage).
  - Rule-based categorization: automated category suggestions, reviewing matched terms, manual category override.
  - Managing categorization rules (Captain/Secretary: adding keywords, setting priority).
- **Section 4: Document Review, Approval & Publication Workflow**
  - Secretary submitting Draft/Needs Revision with PDF for review.
  - Captain review workspace: pending approvals, viewing metadata and draft PDF.
  - Captain returning document for revision: mandatory return comments, status transition to Needs Revision.
  - Captain approving document: status transition to Published, immutable official version preservation, database notifications.
  - Post-publication lifecycle management (Captain only):
    - Archiving: historical retention with recorded reason.
    - Revoking: invalidating document issued in error with recorded reason.
    - Superseding: replacing document with a newer published record or reference.
- **Section 5: Public QR Code Document Verification**
  - Public scan workflow (no login required, mobile-friendly).
  - Permanent URL `/verify/{token}`.
  - Verification states:
    - Draft / Pending / Needs Revision: "Draft / Not Yet Verified", no PDF access.
    - Published: "Verified Official Barangay Document", public PDF view and download.
    - Archived: "Archived Official Record", verification valid, public PDF download.
    - Revoked: "Revoked Document", clear alert, revocation reason, NO public PDF download.
    - Superseded: "Superseded Document", links to replacement document, NO public PDF download.
- **Section 6: Purok Resident & Household Profiling (Purok Chairpersons)**
  - Strict assigned-Purok data isolation.
  - Household management: auto-generated code `HH-XXXXXXXXXX`, address, household head name, family notes.
  - Resident management: auto-generated code `RES-XXXXXXXXXX`, demographics, birthdate, civil status, registered voter status.
  - Duplicate detection warning: matching full name + birthdate + purok with explicit confirmation override.
  - Resident status tracking: Active, Transferred, Deceased, Inactive (soft lifecycle, preventing record destruction).
- **Section 7: Operational Reporting & Official Print/PDF Output**
  - Reports workspace: Document Reports vs Resident Reports.
  - Filtering by date ranges, puroks, categories, voter status, civil status.
  - Official Printable Report layout (`reports.print`): Barangay Sta. Felomina letterhead, filter criteria, summary metrics, signature blocks (prepared by, attested by Barangay Captain).
  - Print-to-PDF via browser print engine.
  - Purok Chairperson strict 403 restriction on document reports.
- **Section 8: Accountability & Audit Logs**
  - Automatic event tracking for all sensitive actions.
  - Captain-only audit log viewer.

### 5.3 `docs/BACKUP_AND_RESTORE.md` Structure & Specifications
- **Section 1: Backup System Architecture & Policy**
  - Scope: PostgreSQL database (`barangay_system`) + private storage files (`storage/app/private/`).
  - Frequency & retention recommendations: daily automated backups, 30-day rolling retention.
  - Designated responsible person: Barangay Captain / IT Administrator.
- **Section 2: Manual Backup Procedures**
  - Running via Artisan: `php artisan backup:create` with available flags.
  - Running via Shell Script: `./scripts/backup.sh`.
  - Running inside Docker: `docker compose exec -T app php artisan backup:create`.
  - Expected archive location and naming format (`storage/app/private/backups/backup-YYYY-MM-DD-His.zip`).
- **Section 3: Scheduled Automated Backups**
  - Linux cron configuration (`crontab -e`).
  - Windows Task Scheduler configuration.
  - Docker Compose scheduling alternatives.
  - Log inspection (`storage/logs/backup.log`).
- **Section 4: Archive Verification & Integrity Checks**
  - Verifying archive structure (ZIP contents, `manifest.json`, `database.sql`, `storage/`).
  - Validating SHA-256 checksums against manifest.
- **Section 5: Disaster Recovery & Restore Procedures**
  - Pre-flight readiness check (database server reachable, storage writable).
  - Running restore via Artisan: `php artisan backup:restore {archive-path} --force`.
  - Running restore via Shell Script: `./scripts/restore.sh {archive-path}`.
  - Safety confirmation prompts and overwrite behavior.
  - Post-restore verification checklist: verifying database records, testing uploaded PDF retrieval, cache clearance.
- **Section 6: Troubleshooting & Edge Cases**
  - Database connection refused.
  - Storage directory permission denied (`chown -R www-data:www-data storage`).
  - Corrupted or incomplete backup archive.
  - Version incompatibility warnings.

### 5.4 `docs/DEPLOYMENT_GUIDE.md` Structure & Specifications
- **Section 1: Deployment Topologies & Prerequisites**
  - Hardware requirements: 2 CPU cores, 4 GB RAM, 20 GB SSD storage.
  - Software stack: PHP 8.4 (with `pdo_pgsql`, `pgsql`, `zip`, `opcache`, `bcmath`, `fileinfo`), PostgreSQL 16, Node.js 22 & npm, Composer 2, Nginx.
  - Topology A: Docker Compose Deployment (Recommended for containerized deployment).
  - Topology B: Dedicated Ubuntu/Debian Linux Server (Production host).
  - Topology C: Local Development / Evaluation (Windows Laragon / PHP CLI).
- **Section 2: Docker Compose Setup (Step-by-Step)**
  - Docker & Docker Compose prerequisites.
  - Environment file preparation (`.env.docker`).
  - Starting services: `docker compose up -d --build`.
  - Container health checks (`pg_isready` for database).
  - Automated entrypoint sequence: migrations, seeders, storage link, cache warmup.
  - Stopping and resetting volumes (`docker compose down -v`).
- **Section 3: Dedicated Linux Server Installation**
  - System package installation (PHP 8.4-FPM, PostgreSQL 16, Nginx).
  - Repository cloning and directory permissions (`/var/www/barangay-system`).
  - Environment configuration (`APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`).
  - Composer production build: `composer install --no-dev --optimize-autoloader`.
  - Asset compilation: `npm ci && npm run build`.
  - PostgreSQL database creation and user grant.
  - Database migration and seeding: `php artisan migrate --force`, `php artisan db:seed --class=AccountSeeder`.
  - Storage linking and directory ownership (`storage/` and `bootstrap/cache`).
  - Laravel cache warmup: `config:cache`, `route:cache`, `view:cache`.
  - Nginx virtual host configuration (reverse proxy to PHP-FPM, SSL/TLS setup via Certbot).
- **Section 4: Post-Deployment Verification & Maintenance**
  - Verification test run: `php artisan test --compact`.
  - Supervisor daemon for queue workers (if background queues enabled).
  - Log rotation and monitoring (`storage/logs/laravel.log`).
  - Routine update procedures (git pull, migrate, build, cache clear).
  - Troubleshooting guide: HTTP 500/502 errors, PostgreSQL authentication issues, storage permission errors, Vite asset 404s.

---

## 6. Comprehensive Features Discovered Table

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | Demo Seeding | Multi-Year Document Seeding | Populates official documents across years 2024, 2025, and 2026 | `php artisan db:seed --class=DemoDataSeeder` | Persisted `document_records` rows across 2024-2026 | DB exception on duplicate `[type, year, number]` if not idempotent | `ORIGINAL_REQUEST.md`, `PRD_V1.md` §28 |
| 2 | Demo Seeding | Comprehensive Document Types | Seeds records for all supported types: Resolution, Ordinance, Certificate, Memorandum, Correspondence, Meeting Minutes, Executive Order | Taxonomy seed definitions | Seeded `document_types` and linked `document_records` | Fails if foreign key `document_type_id` is missing | `PRD_V1.md` §6.1, `DocumentTaxonomySeeder.php` |
| 3 | Demo Seeding | Categorical Diversity | Seeds records across categories: Governance, Health, Infrastructure, Social Services, Public Safety, Education, Disaster Risk Reduction | Document taxonomy models | Seeded `document_categories` and categorized documents | Fails if category foreign key missing | `ORIGINAL_REQUEST.md`, `PRD_V1.md` §16.4 |
| 4 | Demo Seeding | Full Lifecycle State Representation | Creates demo records for all 7 statuses: Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded | Status enum values & state-specific attributes | Document records in exact lifecycle states | Throws `ValidationException` if state transitions or required attributes violate rules | `App\Enums\DocumentStatus`, `PRD_V1.md` §12 |
| 5 | Demo Seeding | Demo PDF Storage Files | Stores physical sample PDF files for demo records in private storage | Valid PDF binary or `%PDF-1.4` text stream | Files in `storage/app/private/document-drafts/` and `document-officials/` | 404 abort on public verification or show page if file missing | `DocumentRecord.php`, `DocumentPublishedPdfController.php` |
| 6 | Demo Seeding | Multi-Purok Resident Profiling | Generates households and residents distributed across all 7 Puroks (Purok 1-7) | Demographics data, Purok models | Linked `households` and `residents` rows | Foreign key error if `purok_id` does not match household `purok_id` | `ORIGINAL_REQUEST.md`, `PRD_V1.md` §19-21 |
| 7 | Demo Seeding | Complete Resident Status Coverage | Creates residents for all statuses: Active, Transferred, Deceased, Inactive | Resident factory/seeder data | Records reflecting all `ResidentStatus` cases | Fails if status string does not match enum | `App\Enums\ResidentStatus`, `PRD_V1.md` §19 |
| 8 | Demo Seeding | Operational Audit Log History | Seeds historical audit log entries reflecting creation, submission, approval, revision, status change | Historical timestamps, actors, auditable entities | Rows in `audit_logs` table | Fails if `actor_id` or `auditable_type` invalid | `App\Models\AuditLog`, `PRD_V1.md` §25 |
| 9 | Demo Seeding | In-App Workflow Notifications | Seeds database notifications for Captain and Secretary | Notification parameters (`DocumentWorkflowNotification`) | Rows in `notifications` table | Fails if notifiable user missing | `App\Notifications\DocumentWorkflowNotification`, `PRD_V1.md` §24 |
| 10 | Demo Seeding | Seeder Idempotency | Ensures repeated runs of `DemoDataSeeder` succeed without unique constraint violations | Consecutive seeder execution | Zero duplicate errors, stable record count | Violates Acceptance Criteria if constraint exception thrown | `ORIGINAL_REQUEST.md` Acceptance Criteria |
| 11 | Backup & Recovery | Artisan Backup Command | Generates timestamped ZIP archive containing DB dump, private storage, and manifest | `php artisan backup:create` with optional `--destination`, `--filename` | `backup-YYYY-MM-DD-His.zip` in target destination | Returns exit code 1 on DB connection or filesystem failure | `ORIGINAL_REQUEST.md` R2, `PRD_V1.md` §27 |
| 12 | Backup & Recovery | Database Dump Generation | Exports database schema and data into SQL dump format | Active database connection | `database.sql` within backup archive | Fails if database unreachable or dump process exits non-zero | `ORIGINAL_REQUEST.md` R2 |
| 13 | Backup & Recovery | Private Storage Archiving | Packages uploaded PDFs (`document-drafts`, `document-officials`) into backup | `storage/app/private` filesystem | `storage/` directory inside backup ZIP | Skips missing files or logs warning; fails on read denial | `config/filesystems.php`, `DocumentRecord.php` |
| 14 | Backup & Recovery | Manifest & Integrity Hashing | Creates `manifest.json` with file counts, timestamps, DB type, and SHA-256 hashes | Backup contents | `manifest.json` embedded in archive | Fails if checksum generation fails | `ORIGINAL_REQUEST.md` R2 Safety Validations |
| 15 | Backup & Recovery | Artisan Restore Command | Restores database and storage files from specified backup archive | `php artisan backup:restore {archive?}` | Restored database tables and storage files | Fails on invalid archive, missing DB dump, or DB connection error | `ORIGINAL_REQUEST.md` R2 |
| 16 | Backup & Recovery | Restore Confirmation Guard | Prompts user before overwriting existing data; bypassable via `--force` | Interactive confirmation or `--force` flag | Aborts execution without touching data if user denies | Halts restoration and exits with non-zero code | `ORIGINAL_REQUEST.md` R2 Safety Validations |
| 17 | Backup & Recovery | Restore Pre-flight Validation | Validates archive integrity, ZIP structure, manifest, and DB connectivity before modifying system | Target archive file | Pre-flight validation status | Halts immediately if DB unreachable or archive corrupt | `ORIGINAL_REQUEST.md` R2 |
| 18 | Backup & Recovery | Post-Restore Cache Clearance | Flushes application, configuration, and view caches after restoration | System commands / cache repository | Cleared caches (`cache:clear`, `config:clear`, `view:clear`) | Logs warning if cache flush encounters permissions error | `docker/php/entrypoint.sh` pattern |
| 19 | Backup & Recovery | Shell Backup Script | POSIX script for scheduled/manual backup execution inside/outside Docker | `./scripts/backup.sh` | Backup archive + execution log | Exits non-zero on failure | `ORIGINAL_REQUEST.md` R2 |
| 20 | Backup & Recovery | Shell Restore Script | POSIX script for disaster recovery inside/outside Docker | `./scripts/restore.sh [archive]` | Restored system | Prompts for confirmation; exits non-zero on error | `ORIGINAL_REQUEST.md` R2 |
| 21 | Backup & Recovery | Automated Feature Tests | Pest tests proving backup creation, archive inspection, confirmation guards, and restoration | Test suite execution (`vendor/bin/pest`) | Passing assertions on backup/restore | Test failure if archive invalid or records not restored | `ORIGINAL_REQUEST.md` Acceptance Criteria |
| 22 | Documentation | Admin & Operator Manual | Client-ready manual covering Captain, Secretary, and Purok Chairperson workflows | `docs/ADMIN_OPERATOR_MANUAL.md` | Comprehensive Markdown operator manual | Incomplete if missing required workflows | `ORIGINAL_REQUEST.md` R3 |
| 23 | Documentation | Backup & Recovery Manual | Operational manual detailing exact commands, scheduling, integrity checks, and restore procedures | `docs/BACKUP_AND_RESTORE.md` | Comprehensive Markdown DR manual | Incomplete if missing commands or DR steps | `ORIGINAL_REQUEST.md` R3 |
| 24 | Documentation | Deployment & Maintenance Guide | Technical guide for local, Docker Compose, and production Linux server setup | `docs/DEPLOYMENT_GUIDE.md` | Comprehensive Markdown deployment guide | Incomplete if missing environment, Docker, or troubleshooting | `ORIGINAL_REQUEST.md` R3 |

---

## 7. Edge Cases and Observed/Expected Behaviors

| # | Feature | Input | Observed / Required Behavior |
|---|---------|-------|------------------------------|
| 1 | `DemoDataSeeder` Idempotency | Run `php artisan db:seed --class=DemoDataSeeder` twice consecutively | Seeder completes successfully on second run with 0 errors; database counts remain consistent; no duplicate key constraint exceptions. |
| 2 | `DemoDataSeeder` Unique Constraints | Multiple documents generated with same type and year | Seeder uses sequential/deterministic document numbers (`RES-2024-001`, `RES-2024-002`, etc.) preventing collisions on `['document_type_id', 'document_year', 'document_number']`. |
| 3 | `DemoDataSeeder` Resident Normalization | Resident name with extra spaces or lowercase/uppercase | `Resident::normalizeFullName()` squishes whitespace and converts to lowercase; duplicate check `scopeLikelyDuplicate` correctly matches existing residents without SQL errors. |
| 4 | `DemoDataSeeder` Purok Scoping | Resident assigned to a household belonging to a different Purok | Seeder must strictly assign `resident.purok_id` equal to `household.purok_id`; mismatch causes data corruption and breaks Purok Chairperson isolation. |
| 5 | `DemoDataSeeder` Lifecycle Integrity | Published/Archived/Revoked/Superseded documents created without `document_file_versions` | Public verification and PDF viewing fail; seeder must create matching `DocumentFileVersion` record (`version_number = 1`, `is_current = true`) and ensure physical file exists on disk. |
| 6 | `DemoDataSeeder` Superseded Document Linking | Superseded document referencing another document | Target replacement document must be in `Published` status and must have a different ID than the superseded record; self-supersession is rejected. |
| 7 | `backup:create` Empty Private Storage | Application has no uploaded PDFs in `storage/app/private/` | Backup succeeds; archive includes empty `storage/` directory or notes zero files in `manifest.json`; does not crash. |
| 8 | `backup:create` Destination Unwritable | `--destination=/invalid/read_only_path` | Command outputs error message: "Destination directory is not writable", cleans up temporary files, and exits with code 1. |
| 9 | `backup:create` DB Connection Down | Database server offline or invalid credentials | Command catches connection exception, reports "Database connection failed", cleans up temporary files, and exits with code 1 without creating a partial/corrupt archive. |
| 10 | `backup:restore` Non-existent Archive | `php artisan backup:restore /path/to/missing.zip` | Command outputs error: "Backup archive not found: /path/to/missing.zip", halts immediately, and exits with code 1. |
| 11 | `backup:restore` Corrupted Archive | Corrupted ZIP or empty text file renamed to `.zip` | Command tests ZIP validity (`$zip->open()`); outputs "Invalid or corrupted backup archive", halts, and exits with code 1. |
| 12 | `backup:restore` Missing Manifest/Dump | ZIP archive missing `database.sql` or `manifest.json` | Pre-flight validation detects missing required archive components; aborts before modifying database or storage files. |
| 13 | `backup:restore` Unconfirmed Execution | User runs `php artisan backup:restore archive.zip` and types 'no' at prompt | Command reports "Restoration cancelled by user", leaves database and storage completely untouched, and exits with code 1. |
| 14 | `backup:restore` Non-Interactive Without Force | Command run in non-interactive mode (e.g. cron/CI) without `--force` | Command fails safely with error: "Confirmation required in non-interactive mode. Use --force to proceed", preventing automated accidental overwrites. |
| 15 | `backup:restore` Multi-Driver Testing (SQLite) | Restore command executed in Pest test environment (`DB_CONNECTION=sqlite`) | Command recognizes SQLite connection, executes SQL dump or restores database file into SQLite memory/connection, allowing automated feature tests to pass cleanly. |
| 16 | `scripts/backup.sh` Docker Container Environment | Script executed inside Docker `app` container where container is Alpine/Debian | Script detects environment, runs `php artisan backup:create`, handles standard output/errors, and writes logs to `storage/logs/backup.log`. |

---

## 8. Recommended Implementation Plan & Verifications

### 8.1 Step 1: Implementation of `DemoDataSeeder`
1. Create `database/seeders/DemoDataSeeder.php`:
   - Import and invoke `DocumentTaxonomySeeder`, `PurokSeeder`, and `AccountSeeder`.
   - Seed realistic document records across 2024, 2025, 2026 covering all document types, categories, and lifecycle states (Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded).
   - Generate sample physical PDF files in `storage/app/private/document-drafts/` and `storage/app/private/document-officials/{id}/versions/1.pdf`.
   - Seed households and residents across all 7 Puroks with realistic demographics, voter flags, and statuses (Active, Transferred, Deceased, Inactive).
   - Seed historical audit log entries and workflow notifications.
   - Use idempotent `updateOrCreate` or keyed lookups.
2. Add automated feature test `tests/Feature/DemoDataSeederTest.php`:
   - Verify records populated across all years, types, categories, statuses.
   - Verify households and residents populated across Puroks 1-7.
   - Verify idempotency by calling seeder twice and asserting zero duplicate key exceptions and identical record counts.

### 8.2 Step 2: Implementation of Backup and Disaster Recovery System
1. Create Backup Service (`app/Services/BackupService.php`):
   - Archive generation: packages database dump, storage directory (`storage/app/private`), and `manifest.json` with SHA-256 checksums into ZIP archive.
   - Archive extraction and validation: pre-flight checks, checksum verification.
   - Multi-driver database dump and restore handlers (PostgreSQL via `pg_dump`/`psql` or SQL dump, SQLite for automated testing).
2. Create Artisan Commands:
   - `app/Console/Commands/BackupCreateCommand.php` (`php artisan backup:create`).
   - `app/Console/Commands/BackupRestoreCommand.php` (`php artisan backup:restore`).
3. Create Scripts:
   - `scripts/backup.sh` and `scripts/restore.sh`.
4. Add automated feature test `tests/Feature/BackupAndRestoreTest.php`:
   - Test backup creation produces valid ZIP with manifest and database dump.
   - Test archive inspection validates contents and checksums.
   - Test restore command requires `--force` or interactive confirmation.
   - Test successful restoration of database records and storage files.

### 8.3 Step 3: Creation of Turnover Documentation
1. Create `docs/ADMIN_OPERATOR_MANUAL.md`:
   - Full operator guide covering Captain, Secretary, and Purok Chairperson workflows, document creation, QR embedding, approval, verification, resident profiling, reporting, and audit logs.
2. Create `docs/BACKUP_AND_RESTORE.md`:
   - Step-by-step backup and recovery procedures, scheduling recommendations, integrity verification, and disaster recovery scenarios.
3. Create `docs/DEPLOYMENT_GUIDE.md`:
   - Complete technical deployment guide covering Docker Compose, dedicated Linux server setup, local evaluation, environment configuration, migrations, asset builds, and troubleshooting.

---

## 9. Conclusion
All requirements, schema constraints, lifecycle invariants, command interfaces, edge cases, and documentation structures for Modules R1, R2, and R3 have been completely surveyed and documented. The project is fully ready for safe, verifiable implementation.
