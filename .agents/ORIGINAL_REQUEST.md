# Original User Request

## 2026-09-13T08:14:49Z

Complete the remaining Capstone V1 modules for the Web-Based Barangay Document Archiving, Verification, and Resident Profiling System for Barangay Sta. Felomina: comprehensive demo dataset seeding, database and storage backup/restore procedures, and formal deployment and administrative turnover documentation.

Working directory: C:\laragon\www\Barangay-System
Integrity mode: development

## Requirements

### R1. Comprehensive Demo Dataset Seeder
Provide an idempotent database seeder (`DemoDataSeeder`) that populates the database with realistic barangay records:
- Official document records across document types (resolutions, ordinances, executive orders, certifications) and categories (Health, Infrastructure, Peace & Order, Education, Disaster Risk Reduction) across multiple years (2024–2026), covering all lifecycle states (Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded).
- Residents and households distributed across all 7 Puroks of Barangay Sta. Felomina with realistic demographics, civil statuses, registered voter flags, and statuses (Active, Transferred, Deceased, Inactive).
- Historical audit log entries and notifications reflecting real operational history.

### R2. Automated Backup and Disaster Recovery System
Implement a complete backup and restore mechanism for both the PostgreSQL database and application storage (uploaded PDFs and QR codes):
- Artisan commands (e.g. `php artisan backup:create` and `php artisan backup:restore`) that generate timestamped archives of the database and storage directory.
- Shell scripts or Docker-compatible scripts in `scripts/` supporting scheduled or manual execution inside and outside container environments.
- Safety validations preventing accidental overwrite of existing data without explicit confirmation, verifying database connection and archive integrity before restoration.

### R3. Deployment and Administrative Turnover Documentation
Produce complete, client-ready markdown documentation in `docs/`:
- Administrative Guide & Operator Manual (`docs/ADMIN_OPERATOR_MANUAL.md`): user workflows for Barangay Captain, Secretary, and Purok Chairpersons covering document archiving, approval workflows, QR verification, resident profiling, reporting, and print/PDF output.
- Backup and Disaster Recovery Manual (`docs/BACKUP_AND_RESTORE.md`): step-by-step instructions for performing, scheduling, verifying, and restoring backups.
- Deployment and Maintenance Guide (`docs/DEPLOYMENT_GUIDE.md`): production environment prerequisites, Docker Compose setup, environment configuration, migrations, asset builds, and troubleshooting.

## Acceptance Criteria

### Demo Data Seeding
- [ ] Running `php artisan db:seed --class=DemoDataSeeder` populates realistic documents across all document types, categories, years 2024-2026, and statuses.
- [ ] Seeder creates residents and households across all Puroks 1-7 with valid relationships.
- [ ] Seeding is idempotent and does not produce duplicate unique key constraint violations on rerun.

### Backup and Recovery
- [ ] Backup command creates a valid timestamped archive containing database dump and private storage files.
- [ ] Restore command restores database records and storage files from a specified backup archive.
- [ ] Restore command includes a confirmation guard or `--force` flag to prevent unintended overwrites.
- [ ] Automated feature tests prove backup creation, archive inspection, and successful restoration.

### Documentation and Turnover
- [ ] Operator manual covers Captain, Secretary, and Purok Chairperson workflows with concrete instructions.
- [ ] Backup and recovery manual details exact commands, schedule recommendations, and troubleshooting steps.
- [ ] Deployment guide covers local, Docker, and production server setup instructions.
