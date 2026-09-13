# Comprehensive E2E Test Infrastructure & Test Architecture

**System:** Web-Based Barangay Document Archiving, Verification, and Resident Profiling System for Barangay Sta. Felomina  
**Target Scope:** Capstone V1 Final Modules (R1: Demo Dataset Seeder, R2: Backup & Disaster Recovery, R3: Deployment & Administrative Turnover Documentation)  
**Test Framework:** Pest PHP 3.x / PHPUnit 11.x on PHP 8.4  
**Date:** September 13, 2026  
**Status:** ACTIVE  

---

## 1. Executive Summary & Purpose

This document establishes the end-to-end (E2E) opaque-box test infrastructure and architecture for Barangay Sta. Felomina's Capstone V1 release. The test suite provides authoritative, repeatable, and automated verification of:
1. **DemoDataSeeder (`database/seeders/DemoDataSeeder.php`)**: Multi-year official documents (2024–2026), 4 document types, 5+ categories, 7 lifecycle states, physical PDF file storage, 28 households and 80+ residents across all 7 Puroks with realistic demographics, and full idempotency.
2. **Automated Backup & Disaster Recovery (`App\Services\BackupService`, `backup:create`, `backup:restore`, `scripts/*`)**: PostgreSQL and SQLite database archiving, physical storage preservation, SHA-256 verified `manifest.json`, interactive confirmation guards, `--force` bypass, credential shielding via `PGPASSWORD`, and disaster recovery restoration.
3. **Turnover & Administrative Documentation (`docs/ADMIN_OPERATOR_MANUAL.md`, `docs/BACKUP_AND_RESTORE.md`, `docs/DEPLOYMENT_GUIDE.md`)**: Comprehensive operational coverage of Captain, Secretary, and Purok Chairperson roles, backup runbooks, deployment guides, route alignment, and zero unfinished placeholder markers.

---

## 2. 4-Tier Testing Methodology

The test suite strictly adheres to a 4-tier testing hierarchy designed to maximize defect detection from individual feature mechanics up to full real-world disaster drills:

```
┌───────────────────────────────────────────────────────────────┐
│              Tier 4: Real-World Scenarios                     │
│  - Full Disaster Recovery Drills (Catastrophic Data Loss)     │
│  - Public QR Code Verification & Physical PDF Streaming HTTP  │
│  - Documentation to Live Route & Controller Alignment         │
└───────────────────────────────┬───────────────────────────────┘
                                │
┌───────────────────────────────┴───────────────────────────────┐
│           Tier 3: Cross-Feature Combinations                  │
│  - Pairwise: Purok 1-7 Households & Residents (All 4 Statuses)│
│  - Backup -> State Mutation -> Restore -> Integrity Verify    │
│  - Superseded Document Historical Chain & Forward Pointers    │
└───────────────────────────────┬───────────────────────────────┘
                                │
┌───────────────────────────────┴───────────────────────────────┐
│            Tier 2: Boundary & Corner Cases                    │
│  - Seeder Idempotency Reruns (Zero Collision / Stable Counts) │
│  - Confirmation Prompt Rejection vs --force Bypass            │
│  - Corrupted ZIP Archives, Tampered Checksums, Missing Manifest│
│  - Adult Voter Age Boundaries (18+ vs Minors)                 │
└───────────────────────────────┬───────────────────────────────┘
                                │
┌───────────────────────────────┴───────────────────────────────┐
│                Tier 1: Feature Coverage                       │
│  - Multi-Year, Multi-Type, Multi-Category Document Seeding    │
│  - All 7 Document Lifecycle States Seeded                     │
│  - Backup Creation (ZIP, SQL Dump, Storage, Manifest)         │
│  - Backup Restore Mechanics & Flag Filtering (--only-db)      │
│  - Documentation Existence, Structure, and Role Coverage      │
└───────────────────────────────────────────────────────────────┘
```

### Tier Definitions
- **Tier 1: Feature Coverage**: Validates the happy path and primary contracts for each feature domain (minimum 5 tests per domain). Every core function and parameter is directly exercised.
- **Tier 2: Boundary & Corner Cases**: Exercises failure modes, edge conditions, parameter boundaries, corrupted files, missing dependencies, confirmation prompt rejections, and idempotency reruns (minimum 5 tests per domain).
- **Tier 3: Cross-Feature Combinations**: Validates pairwise and multi-entity integrations, state mutations followed by restoration, relational integrity across households, residents, puroks, and document replacement chains.
- **Tier 4: Real-World Scenarios**: Simulates end-to-end user workflows, full catastrophic disaster recovery drills, public verification scans via HTTP endpoints, and documentation alignment with live application routes.

---

## 3. Test Suite Architecture & File Layout

All test files are located in `tests/Feature/` and conform to Pest PHP conventions:

| Test File | Target Domain | Tier Coverage | Key Test Focus |
|---|---|---|---|
| `tests/Feature/DemoDataSeederTest.php` | R1: DemoDataSeeder | Tiers 1–4 | Multi-year documents (2024-2026), 7 lifecycle states, Puroks 1-7, 28 households, 80+ residents, voter boundaries, physical PDFs, idempotency reruns, QR HTTP verification. |
| `tests/Feature/BackupRestoreTest.php` | R2: Backup & Recovery | Tiers 1–4 | `BackupService`, `backup:create`, `backup:restore`, ZIP compression, manifest SHA-256 verification, confirmation prompt rejection, `--force` flag, SQLite & PostgreSQL handling, disaster recovery drill. |
| `tests/Feature/DocumentationIntegrityTest.php` | R3: Turnover Docs | Tiers 1–4 | `docs/ADMIN_OPERATOR_MANUAL.md`, `docs/BACKUP_AND_RESTORE.md`, `docs/DEPLOYMENT_GUIDE.md`, role workflows, command syntax, placeholder audit, live route alignment. |

---

## 4. Feature Coverage Matrix (F1 – F16)

| ID | Feature Description | Requirement | Target Test Suite | Tiers Covered |
|---|---|---|---|---|
| **F1** | Multi-Year Official Document Seeding (2024–2026) | R1 | `DemoDataSeederTest` | Tier 1, Tier 3 |
| **F2** | Multi-Category Document Seeding (Health, Infra, Peace, etc.) | R1 | `DemoDataSeederTest` | Tier 1, Tier 3 |
| **F3** | All 7 Document Lifecycle States Seeded | R1 | `DemoDataSeederTest` | Tier 1, Tier 2, Tier 3 |
| **F4** | Physical Storage PDF Generation (Drafts & Officials) | R1 | `DemoDataSeederTest` | Tier 1, Tier 4 |
| **F5** | Puroks 1-7 Households (28+) & Residents (80+) | R1 | `DemoDataSeederTest` | Tier 1, Tier 2, Tier 3 |
| **F6** | Historical Audit Logs & Operational Notifications | R1 | `DemoDataSeederTest` | Tier 1, Tier 2 |
| **F7** | Seeder Idempotency Guarantee | R1 | `DemoDataSeederTest` | Tier 2 |
| **F8** | Backup Creation Command (`backup:create`) | R2 | `BackupRestoreTest` | Tier 1, Tier 2 |
| **F9** | Backup Restore Command (`backup:restore`) | R2 | `BackupRestoreTest` | Tier 1, Tier 2, Tier 3, Tier 4 |
| **F10** | Cross-Platform Automation Scripts (`scripts/*`) | R2 | `BackupRestoreTest`, `DocumentationIntegrityTest` | Tier 1, Tier 3 |
| **F11** | Docker Integration & PostgreSQL Client Utilities | R2 | `BackupRestoreTest`, `DocumentationIntegrityTest` | Tier 1, Tier 3 |
| **F12** | Admin Operator Manual (`docs/ADMIN_OPERATOR_MANUAL.md`) | R3 | `DocumentationIntegrityTest` | Tier 1, Tier 2, Tier 4 |
| **F13** | Backup & Restore Manual (`docs/BACKUP_AND_RESTORE.md`) | R3 | `DocumentationIntegrityTest` | Tier 1, Tier 2, Tier 3 |
| **F14** | Deployment Guide (`docs/DEPLOYMENT_GUIDE.md`) | R3 | `DocumentationIntegrityTest` | Tier 1, Tier 2, Tier 3 |
| **F15** | Opaque-Box E2E Testing Suite | ALL | ALL Feature Suites | Tiers 1–4 |
| **F16** | Adversarial Hardening & Integrity Auditing | ALL | ALL Feature Suites | Tier 2, Tier 4 |

---

## 5. Test Isolation, Drivers & Environment Handling

### 5.1 Dual Database Driver Support (SQLite vs PostgreSQL)
- In the local testing environment (`phpunit.xml`), tests run on `sqlite` `:memory:`.
- `BackupService` and commands must gracefully support both SQLite (in-memory or file dump for automated test verification) and PostgreSQL (`pg_dump` and `psql` for staging/production).
- For testing external PostgreSQL process execution inside automated feature tests without requiring live PostgreSQL daemon access, the test suite leverages `Illuminate\Support\Facades\Process::fake()`. This allows strict assertion on:
  - Binary names (`pg_dump`, `psql`).
  - Command flags (`--host`, `--port`, `--username`, `--clean`, `--no-owner`).
  - Strict credential isolation: verifying `PGPASSWORD` is set in the process environment array and NEVER exposed in command-line arguments.

### 5.2 Storage & File System Isolation
- Physical PDF files generated during seeding or backup testing are scoped to `storage/app/` or mock disks.
- Backup archives generated during test runs are placed in a dedicated testing subfolder (e.g. `storage/app/backups/test/`) and cleaned up in `afterEach()` or test teardown hooks to avoid polluting developer disk space.

### 5.3 Opaque-Box Behavioral Principles
- Tests assert exclusively on observable outputs: database state, HTTP response codes and headers, rendered HTML elements, exit codes, process commands, and file presence.
- Tests do not bind to private implementation details or mock internal service methods unnecessarily.

---

## 6. Expected Output Derivation Sources

Every test assertion in the suite is derived from authoritative specifications:
1. **`ORIGINAL_REQUEST.md`**: Approved feature requirements, demographic distributions, lifecycle states, and disaster recovery commands.
2. **`PROJECT.md`**: Interface contracts for `DemoDataSeeder`, `BackupService`, `backup:create`, `backup:restore`, and documentation paths.
3. **`survey_db_models.md`**: Database constraints, composite unique keys, foreign key rules, enum values, and seeder distribution matrices.
4. **`survey_ops.md`**: Backup ZIP structure, `manifest.json` schema, PostgreSQL invocation arguments, safety guards, and documentation outlines.

---

## 7. Execution Guide

Run all E2E feature tests:
```powershell
vendor/bin/pest tests/Feature/DemoDataSeederTest.php tests/Feature/BackupRestoreTest.php tests/Feature/DocumentationIntegrityTest.php
```

Run tests by domain:
```powershell
# R1: Demo Dataset Seeder
vendor/bin/pest tests/Feature/DemoDataSeederTest.php

# R2: Backup & Disaster Recovery
vendor/bin/pest tests/Feature/BackupRestoreTest.php

# R3: Documentation Integrity
vendor/bin/pest tests/Feature/DocumentationIntegrityTest.php
```

Run with compact output:
```powershell
php artisan test --compact tests/Feature/DemoDataSeederTest.php
```
