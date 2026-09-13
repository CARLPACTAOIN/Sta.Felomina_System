# Backup, Disaster Recovery, and Data Retention Manual
## Barangay Sta. Felomina Document Archiving, Verification, and Resident Profiling System

---

## 1. Disaster Recovery Principles & Architecture

Data integrity and non-repudiation are vital for Barangay Sta. Felomina's digital records. The system houses legal resolutions, municipal ordinances, resident vital demographic data, and signed PDF enactments. Loss of these assets would disrupt public service and compromise official barangay legal standing.

### Critical System Assets
The automated disaster recovery pipeline protects two distinct storage domains:
1. **Relational Database (`barangay_system`):**
   - Managed under PostgreSQL 16 (or SQLite in local testing).
   - Contains all document metadata, seven-state lifecycle records, user credentials, resident census records, household links, and audit logs.
2. **Application Storage Directory:**
   - **Private Storage (`storage/app/private/`):** Contains uploaded draft PDFs (`document-drafts/`) and immutable, approved official PDF versions (`document-officials/{id}/versions/{version_number}.pdf`).
   - **Public Storage (`storage/app/public/`):** Contains publicly accessible web assets and template graphics.

### Operational Custodianship
- **Administrative Owner:** Barangay Captain (authorizes disaster recovery restorations and off-site data archival).
- **Operational Custodian:** Barangay Secretary or Designated Systems Administrator (executes regular backup drills, monitors cron executions, and maintains physical cold-storage copies).

---

## 2. Backup Archive Architecture & Storage Convention

All backups are generated as atomic, compressed ZIP archives stored in:
`storage/app/backups/`

### Archive Naming Convention
Archives default to a UTC/local timestamped pattern:
`backup-YYYY-MM-DD_HHmmss.zip` (e.g., `backup-2026-09-13_162000.zip`).
Administrators can supply a custom filename using the `--filename` flag.

### Internal Archive Structure
Every generated backup ZIP contains the following layout:

```
backup-2026-09-13_162000.zip
├── manifest.json            <- Cryptographic verification manifest
├── database.sql             <- Plaintext SQL dump (PostgreSQL or SQLite)
└── storage/
    ├── private/             <- Mirrored private disk assets
    │   ├── document-drafts/
    │   └── document-officials/
    └── public/              <- Mirrored public disk assets
```

### `manifest.json` Specifications
The manifest contains metadata and cryptographic SHA-256 checksums to verify package integrity prior to restoration:

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

---

## 3. Command Line Interface (CLI) Specifications

The system exposes two primary Artisan commands for disaster recovery operations.

### 3.1 `php artisan backup:create`

Creates a timestamped backup archive of the database and application storage.

```bash
php artisan backup:create [options]
```

#### Command Options & Flags:
- `--filename=`: Specify a custom name for the backup archive (e.g., `pre-upgrade.zip`).
- `--path=`: Specify a custom output directory to store the archive instead of `storage/app/backups/`.
- `--only-db`: Archive only the database without storage files.
- `--only-storage`: Archive only storage files without the database.
- `--no-db`: Skip database archiving (equivalent to `--only-storage`).
- `--no-storage`: Skip storage archiving (equivalent to `--only-db`).

#### Examples:
```bash
# Full standard backup (database + private storage + public storage):
php artisan backup:create

# Database-only dump:
php artisan backup:create --only-db

# Storage-only archive:
php artisan backup:create --only-storage

# Named backup to custom destination:
php artisan backup:create --filename=annual_archive_2026.zip --path=/mnt/external_backup/
```

### 3.2 `php artisan backup:restore`

Restores database records and application storage files from a verified backup archive.

```bash
php artisan backup:restore [archive] [options]
```

#### Command Arguments & Options:
- `[archive]`: Optional filename (e.g., `backup-2026-09-13_162000.zip`) or full filesystem path to the archive. If omitted, the command defaults to the latest archive found in `storage/app/backups/`.
- `--latest`: Automatically restore the most recent backup archive in `storage/app/backups/`.
- `--force`: Bypass interactive confirmation prompts (mandatory for headless scripting and CI/CD).
- `--only-db`: Restore only database records; leaves existing storage files intact.
- `--only-storage`: Restore only storage files; leaves the database intact.
- `--no-db`: Skip database restoration (equivalent to `--only-storage`).
- `--no-storage`: Skip storage restoration (equivalent to `--only-db`).
- `--clean`: Clean existing storage directories prior to unpacking files from the archive.

#### Safety Confirmation Guard:
Restoring data overwrites active operational databases and uploaded files. When running interactively, the command displays the archive timestamp, system metadata, and prompts:

```text
Archive: backup-2026-09-13_162000.zip
System: Barangay Sta. Felomina Document Archiving System
Created at: 2026-09-13T16:20:00+08:00
WARNING: Restoring will overwrite existing database records and storage files!
Do you wish to proceed? (yes/no) [no]:
```

If the user does not respond affirmatively, the process halts immediately with `Restore cancelled.` (exit code 0). To run non-interactively, you must pass the `--force` flag.

---

## 4. Automation & Scheduled Backups

To guarantee continuous protection without manual intervention, automated wrapper scripts are provided in `scripts/`.

### Available Scripts:
- `scripts/backup.sh`: Autonomous Bash runner for Linux, Docker, and cron schedules.
- `scripts/restore.sh`: Linux disaster recovery runner featuring maintenance mode safeguards.
- `scripts/backup.bat`: Batch script for Windows workstations and Windows Task Scheduler.
- `scripts/restore.bat`: Batch script for Windows disaster recovery.

---

### 4.1 Linux Server & Docker Automated Scheduling (Cron)

On Ubuntu/Debian production servers or Docker host machines, configure cron using `crontab -e`:

```cron
# Run daily full backup at 02:00 AM Philippine Standard Time (PST / UTC+8)
0 2 * * * /bin/bash /var/www/barangay-system/scripts/backup.sh >> /var/log/barangay-backup.log 2>&1

# Run mid-day database snapshot at 12:00 PM
0 12 * * * /bin/bash /var/www/barangay-system/scripts/backup.sh --only-db >> /var/log/barangay-backup.log 2>&1
```

#### What `scripts/backup.sh` Executes:
1. Detects runtime context (bare-metal host, inside Docker container, or Docker host invoking `docker compose exec -T app`).
2. Calls `php artisan backup:create --no-interaction "$@"`.
3. Performs automated retention cleanup: prunes any `.zip` archives inside `storage/app/backups/` older than 30 days.
4. Emits structured log output with timestamps and process exit codes.

---

### 4.2 Windows Workstation Automated Scheduling (Task Scheduler)

For local installations running under Laragon on Windows 10/11:

1. Open **Windows Task Scheduler** (`taskschd.msc`).
2. Click **Create Basic Task...** in the Actions pane.
3. Set Name to `Barangay Sta. Felomina Daily Backup`.
4. Choose Trigger: **Daily** at `02:00:00 AM`.
5. Choose Action: **Start a program**.
6. Set Program/script to:
   `C:\laragon\www\Barangay-System\scripts\backup.bat`
7. Set Start in (optional) to:
   `C:\laragon\www\Barangay-System`
8. Check **Run whether user is logged on or not** and configure with administrative privileges.

---

### 4.3 Data Retention & Off-Site Mirroring Policy
- **Primary Disk Retention:** Local backups inside `storage/app/backups/` are retained for 30 days.
- **Weekly Cold Off-Site Copy:** Every Friday afternoon, the Barangay Secretary or IT Administrator copies the week's backup archives to an encrypted external USB drive stored in the barangay fireproof safe.
- **Cloud/Off-Site Mirroring (Optional):** Use `rclone` or secure SCP to sync `storage/app/backups/` to off-site cloud storage:
  ```bash
  rclone sync /var/www/barangay-system/storage/app/backups remote:barangay-backups-secure/
  ```

---

## 5. Disaster Recovery Runbooks

Follow these incident response procedures when recovering from data loss events.

---

### Scenario A: Accidental Data Deletion or Corruption (Accidental Deletion / Overwrite)

**Problem:** An administrative user accidentally deletes or corrupts resident records, or replaces a critical document draft with the wrong file.

**Step-by-Step Resolution:**
1. Put the application into maintenance mode to prevent new transactions:
   ```bash
   php artisan down --render="errors::503"
   ```
2. Locate the most recent healthy backup archive:
   ```bash
   ls -lt storage/app/backups/*.zip
   ```
3. If only database records were affected and storage files are intact:
   ```bash
   php artisan backup:restore backup-2026-09-13_162000.zip --only-db --force
   ```
4. If only storage files were corrupted:
   ```bash
   php artisan backup:restore backup-2026-09-13_162000.zip --only-storage --clean --force
   ```
5. If both database and storage require restoration:
   ```bash
   php artisan backup:restore backup-2026-09-13_162000.zip --clean --force
   ```
6. Clear cached views and configuration:
   ```bash
   php artisan cache:clear
   php artisan view:clear
   ```
7. Bring the application back online:
   ```bash
   php artisan up
   ```

---

### Scenario B: Hardware Failure or Total Server Loss (Hardware Failure / Bare-Metal)

**Problem:** Physical host server fails due to power surge, disk failure, or severe disaster. You are deploying to a freshly provisioned replacement server.

**Step-by-Step Resolution:**
1. Provision replacement server with PHP 8.4, PostgreSQL 16, Nginx, and Git (or Docker Compose).
2. Clone repository code:
   ```bash
   git clone https://github.com/barangay/Barangay-System.git /var/www/barangay-system
   cd /var/www/barangay-system
   ```
3. Set up environment file:
   ```bash
   cp .env.example .env
   # Configure DB_PASSWORD, APP_KEY, and database credentials
   ```
4. Install dependencies and compile assets:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```
5. Prepare directory permissions:
   ```bash
   mkdir -p storage/app/backups storage/app/private storage/app/public bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   php artisan storage:link
   ```
6. Initialize database schema:
   ```bash
   php artisan migrate --force
   ```
7. Retrieve the latest off-site backup archive from cold storage (e.g. `backup-2026-09-13_162000.zip`) and copy it into `storage/app/backups/`.
8. Execute full system restoration:
   ```bash
   php artisan backup:restore backup-2026-09-13_162000.zip --force
   ```
9. Verify system operation using the Restore Verification Checklist below.

---

### Scenario C: Database Corruption (PostgreSQL Engine / Corruption)

**Problem:** PostgreSQL reports database corruption, bad disk blocks, or unrecoverable transaction log damage.

**Step-by-Step Resolution:**
1. Stop the application and web server:
   ```bash
   php artisan down
   systemctl stop nginx
   ```
2. Drop and recreate the corrupt PostgreSQL database:
   ```bash
   sudo -u postgres psql -c "DROP DATABASE IF EXISTS barangay_system;"
   sudo -u postgres psql -c "CREATE DATABASE barangay_system OWNER barangay;"
   ```
3. Run migrations to rebuild empty schema tables:
   ```bash
   php artisan migrate --force
   ```
4. Restore data from the most recent verified archive:
   ```bash
   php artisan backup:restore backup-2026-09-13_162000.zip --only-db --force
   ```
5. Restart web services and restore application availability:
   ```bash
   systemctl start nginx
   php artisan up
   ```

---

## 6. Step-by-Step Restore Verification Procedure

After executing any restore operation, verify system integrity before opening access to users:

1. **Verify Database Connectivity & Records:**
   - Execute query to verify resident counts:
     ```bash
     php artisan tinker --execute="echo 'Residents: ' . App\Models\Resident::count() . ', Documents: ' . App\Models\DocumentRecord::count();"
     ```
   - Confirm records match pre-disaster totals.
2. **Inspect Private Storage PDFs:**
   - Confirm official PDF versions exist in `storage/app/private/document-officials/`:
     ```bash
     ls -la storage/app/private/document-officials/
     ```
   - Confirm draft PDFs exist in `storage/app/private/document-drafts/`.
3. **Verify Public QR Verification Route:**
   - Open browser and navigate to `/verify/{token}` for a published document.
   - Confirm green status badge and metadata render correctly.
   - Click "View Official Document" and ensure the PDF streams properly.
4. **Log In as Barangay Captain & Secretary:**
   - Access `/dashboard`.
   - Verify approvals queue at `/approvals` and resident census at `/residents`.
5. **Inspect System Audit Logs:**
   - Access `/audit-logs` as Barangay Captain and confirm historical entries are intact.

---

## 7. Staging and Test Recovery Drills

Quarterly disaster recovery simulations must be conducted in an isolated staging environment:
- Administrators can test seeder functionality and recovery using:
  ```bash
  php artisan db:seed --class=DemoDataSeeder
  php artisan backup:create --filename=drill_test.zip
  php artisan backup:restore drill_test.zip --force
  ```
- This confirms that automated backups and restorations remain functional under active code changes.
