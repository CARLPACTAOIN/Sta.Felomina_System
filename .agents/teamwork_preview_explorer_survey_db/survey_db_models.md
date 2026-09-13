# Technical Specification & Database Model Survey for R1 DemoDataSeeder

**Workspace:** `C:\laragon\www\Barangay-System`  
**Database Engine:** PostgreSQL 16 (`pgsql` default connection)  
**Target Delivery:** `DemoDataSeeder` (`database/seeders/DemoDataSeeder.php`)  
**Investigated By:** teamwork_preview_explorer (Database and Model Explorer)  
**Date:** September 13, 2026  

---

## Executive Summary

This report establishes the exhaustive database schema, Eloquent model architecture, factory patterns, seeder conventions, and technical requirements needed to implement `DemoDataSeeder` for the **Web-Based Barangay Document Archiving, Verification, and Resident Profiling System for Barangay Sta. Felomina**.

The seeder must populate a realistic, coherent, and fully interconnected demo dataset that exercises every core feature of Capstone V1:
1. **Document Records**: Official documents spanning 4 core types (*Resolutions, Ordinances, Executive Orders, Certificates*), 5+ categories (*Health, Infrastructure, Peace & Order, Education, Disaster Risk Reduction*), 3 years (*2024–2026*), and all 7 lifecycle states (*Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded*).
2. **Resident Profiling & Households**: Distributed across all 7 Puroks of Barangay Sta. Felomina with authentic Filipino demographics, household head relationships, civil statuses, registered voter flags, and statuses (*Active, Transferred, Deceased, Inactive*).
3. **Audit Trails & Notifications**: Rich historical audit logs (`audit_logs`) and workflow notifications (`notifications`) reflecting authentic barangay operations.
4. **Physical Storage**: Concrete demo PDF files in `storage/app/private/` matching `DocumentFileVersion` and draft paths to ensure public verification download routes (`/verify/{token}/document`) return valid `200 OK` stream responses.
5. **100% Idempotency**: Zero unique constraint collisions on successive runs through deterministic identifiers (`household_code`, `resident_code`, document composite key, deterministic UUIDs/check-first for notifications and audit logs).

---

## 1. Schema & Migration Architecture

### 1.1 Complete Entity Relationship Map

```
┌─────────────────────────────────────────────────────────┐
│                         puroks                          │
│  id, name (UQ), code (UQ), sort_order, is_active       │
└──────────────┬──────────────────┬───────────────────────┘
               │                  │
    (set null) │       (restrict) │
               ▼                  ▼
┌──────────────────────────┐    ┌─────────────────────────────────────────┐
│          users           │    │               households                │
│ id, name, email (UQ),    │    │ id, household_code (UQ), purok_id (FK), │
│ password, role, purok_id │    │ address, household_head_name, notes,    │
└──────────────┬───────────┘    │ created_by_id (FK), updated_by_id (FK)  │
               │                └────────────────────┬────────────────────┘
               │                                     │
               │                          (restrict) │
               │                                     ▼
               │                ┌─────────────────────────────────────────┐
               │                │                residents                │
               │                │ id, resident_code (UQ), purok_id (FK),  │
               │                │ household_id (FK), first/last_name, sex,│
               │                │ birthdate, civil_status, voter, status  │
               │                └─────────────────────────────────────────┘
               │
    (restrict) │
               ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                             document_types                              │
│  id, name (UQ), code (UQ), number_prefix (UQ), description, is_active   │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │ (restrict)
┌───────────────────────────────────┐  │
│        document_categories        │  │
│  id, name (UQ), slug (UQ),        │  │
│  description, is_active           │  │
└─────────────────┬─────────────────┘  │
       (restrict) │                    │
                  ▼                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          categorization_rules                           │
│  id, document_category_id (FK), document_type_id (FK, opt), name (UQ),  │
│  keywords (JSON), priority, is_active                                   │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │ (restrict)
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                            document_records                             │
│  id, document_type_id (FK), document_category_id (FK), created_by_id,   │
│  document_number, document_year, title, document_date, subject,         │
│  status, verification_token (UQ), draft_pdf_path,                       │
│  submitted_at/by_id, approved_at/by_id, returned_at/by_id/comment,      │
│  archived_at/by_id/reason, revoked_at/by_id/reason,                     │
│  superseded_at/by_id/reason/superseded_by_document_record_id,          │
│  suggested_document_category_id, category_suggestion_rule_id           │
│  UQ: [document_type_id, document_year, document_number]                 │
└──────────────────────────────────────┬──────────────────────────────────┘
                                       │ (cascade)
                                       ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         document_file_versions                          │
│  id, document_record_id (FK), version_number, file_path, original_name, │
│  mime_type, size, is_current, approved_at, approved_by_id (FK)          │
│  UQ: [document_record_id, version_number]                               │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────┐  ┌───────────────────────────────┐
│              audit_logs              │  │         notifications         │
│ id, actor_id (FK, set null), action, │  │ id (UUID), type, notifiable,  │
│ auditable_type, auditable_id,        │  │ data (JSON text), read_at     │
│ summary, context (JSON)              │  └───────────────────────────────┘
└──────────────────────────────────────┘
```

### 1.2 Table Specifications & Constraint Details

#### 1. `puroks`
- **Migration:** `2026_09_10_120216_create_puroks_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `name`: varchar(255) — **UNIQUE**
  - `code`: varchar(255) — **UNIQUE**
  - `sort_order`: smallint
  - `is_active`: boolean (default `true`)
  - `created_at`, `updated_at`: timestamps
- **Existing Seed Data:** `PurokSeeder` seeds 7 puroks: `purok-1` to `purok-7`, named "Purok 1" through "Purok 7".

#### 2. `users`
- **Migrations:**
  - `0001_01_01_000000_create_users_table.php`
  - `2025_08_14_170933_add_two_factor_columns_to_users_table.php`
  - `2026_09_09_064551_add_staff_identity_fields_to_users_table.php`
  - `2026_09_10_120217_add_purok_id_to_users_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `name`: varchar(255)
  - `email`: varchar(255) — **UNIQUE**
  - `email_verified_at`: timestamp (nullable)
  - `password`: varchar(255) (hashed)
  - `role`: varchar(255) (`UserRole` enum: `captain`, `secretary`, `purok_chairperson`)
  - `is_active`: boolean (default `true`)
  - `purok_id`: bigint (FK -> `puroks.id`, `nullOnDelete()`, nullable)
  - `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`: nullable
- **Existing Seed Data:** `AccountSeeder` seeds 10 accounts:
  - Captain: `captain@stafelomina.local` (Juan D. Cruz)
  - Secretary: `secretary@stafelomina.local` (Maria L. Santos)
  - 7 Purok Chairs: `purok1@stafelomina.local` through `purok7@stafelomina.local` (each linked to `purok-1` through `purok-7`)
  - 1 Inactive Secretary: `inactive@stafelomina.local`

#### 3. `document_types`
- **Migration:** `2026_09_09_084754_create_document_types_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `name`: varchar(255) — **UNIQUE**
  - `code`: varchar(24) — **UNIQUE**
  - `number_prefix`: varchar(16) — **UNIQUE**
  - `description`: text (nullable)
  - `is_active`: boolean (default `true`)
- **Existing Seed Data (`DocumentTaxonomySeeder`):**
  - Resolution (`resolution`, `RES`)
  - Ordinance (`ordinance`, `ORD`)
  - Certificate (`certificate`, `CERT`)
  - Memorandum (`memorandum`, `MEM`)
  - Correspondence Record (`correspondence_record`, `COR`)
  - Meeting Minutes (`meeting_minutes`, `MIN`)
- **Required Additional Type for R1:**
  - Executive Order (`executive_order`, `EO`, prefix: `EO`)

#### 4. `document_categories`
- **Migration:** `2026_09_09_084756_create_document_categories_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `name`: varchar(255) — **UNIQUE**
  - `slug`: varchar(255) — **UNIQUE**
  - `description`: text (nullable)
  - `is_active`: boolean (default `true`)
- **Existing Seed Data (`DocumentTaxonomySeeder`):**
  - Governance (`governance`)
  - Health (`health`)
  - Infrastructure (`infrastructure`)
  - Social Services (`social-services`)
  - Public Safety (`public-safety`)
  - Education (`education`)
- **Required Additional Categories for R1:**
  - Peace & Order (`peace-and-order`)
  - Disaster Risk Reduction (`disaster-risk-reduction`)

#### 5. `categorization_rules`
- **Migration:** `2026_09_12_000001_create_categorization_rules_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `document_category_id`: bigint (FK -> `document_categories.id`, `restrictOnDelete()`)
  - `document_type_id`: bigint (FK -> `document_types.id`, `restrictOnDelete()`, nullable)
  - `name`: varchar(255) — **UNIQUE**
  - `keywords`: json array
  - `priority`: smallint (default `100`, lower number = evaluated earlier)
  - `is_active`: boolean (default `true`)

#### 6. `households`
- **Migration:** `2026_09_10_123624_create_households_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `household_code`: varchar(255) — **UNIQUE** (auto-generated format: `HH-XXXXXXXXXX`)
  - `purok_id`: bigint (FK -> `puroks.id`, `restrictOnDelete()`)
  - `address`: varchar(255)
  - `household_head_name`: varchar(255)
  - `notes`: text (nullable)
  - `created_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `updated_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `created_at`, `updated_at`: timestamps
- **Indexes:**
  - `['purok_id', 'household_head_name']`
  - `['purok_id', 'created_at']`

#### 7. `residents`
- **Migration:** `2026_09_10_123625_create_residents_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `resident_code`: varchar(255) — **UNIQUE** (auto-generated format: `RES-XXXXXXXXXX`)
  - `first_name`: varchar(255)
  - `middle_name`: varchar(255) (nullable)
  - `last_name`: varchar(255)
  - `suffix`: varchar(255) (nullable)
  - `normalized_full_name`: varchar(255) (computed on saving: lowercase trimmed squished name)
  - `sex`: varchar(255) (`male`, `female`)
  - `birthdate`: date (`YYYY-MM-DD`)
  - `civil_status`: varchar(255) (`single`, `married`, `widowed`, `separated`)
  - `contact_number`: varchar(255) (nullable)
  - `address`: varchar(255)
  - `purok_id`: bigint (FK -> `puroks.id`, `restrictOnDelete()`)
  - `household_id`: bigint (FK -> `households.id`, `restrictOnDelete()`)
  - `is_registered_voter`: boolean (default `false`)
  - `status`: varchar(255) (default `active`, enum: `active`, `transferred`, `deceased`, `inactive`)
  - `birthplace`: varchar(255) (nullable)
  - `occupation`: varchar(255) (nullable)
  - `remarks`: text (nullable)
  - `created_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `updated_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `created_at`, `updated_at`: timestamps
- **Indexes:**
  - `['household_id', 'status']`
  - `['purok_id', 'normalized_full_name', 'birthdate']`
  - `['purok_id', 'status', 'last_name']`

#### 8. `document_records`
- **Migrations:**
  - `2026_09_09_084757_create_document_records_table.php`
  - `2026_09_10_105217_add_draft_pdf_fields_to_document_records_table.php`
  - `2026_09_10_110804_add_approval_fields_to_document_records_table.php`
  - `2026_09_12_000002_add_category_suggestion_fields_to_document_records_table.php`
  - `2026_09_12_075825_add_lifecycle_fields_to_document_records_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `document_type_id`: bigint (FK -> `document_types.id`, `restrictOnDelete()`)
  - `document_category_id`: bigint (FK -> `document_categories.id`, `restrictOnDelete()`)
  - `suggested_document_category_id`: bigint (FK -> `document_categories.id`, `restrictOnDelete()`, nullable)
  - `category_suggestion_rule_id`: bigint (FK -> `categorization_rules.id`, `restrictOnDelete()`, nullable)
  - `created_by_id`: bigint (FK -> `users.id`, `restrictOnDelete()`)
  - `document_number`: varchar(64) (e.g. `RES-2024-001`)
  - `document_year`: smallint (e.g. `2024`, `2025`, `2026`)
  - `title`: varchar(255)
  - `document_date`: date (`YYYY-MM-DD`)
  - `subject`: text
  - `keywords`: text (nullable)
  - `category_suggestion_terms`: json (nullable)
  - `category_suggestion_reason`: text (nullable)
  - `category_suggested_at`: timestamp (nullable)
  - `category_overridden_at`: timestamp (nullable)
  - `category_overridden_by_id`: bigint (FK -> `users.id`, `restrictOnDelete()`, nullable)
  - `status`: varchar(255) (enum: `draft`, `pending_approval`, `needs_revision`, `published`, `archived`, `revoked`, `superseded`)
  - `verification_token`: varchar(64) — **UNIQUE** (48 alphanumeric chars, e.g. `Str::random(48)`)
  - `draft_pdf_path`: varchar(255) (nullable)
  - `draft_pdf_original_name`: varchar(255) (nullable)
  - `draft_pdf_mime_type`: varchar(255) (nullable)
  - `draft_pdf_size`: bigint (nullable)
  - `draft_pdf_uploaded_at`: timestamp (nullable)
  - `draft_pdf_uploaded_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `submitted_at`: timestamp (nullable)
  - `submitted_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `approved_at`: timestamp (nullable)
  - `approved_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `return_comment`: text (nullable)
  - `returned_at`: timestamp (nullable)
  - `returned_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `archived_at`: timestamp (nullable)
  - `archived_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `archive_reason`: text (nullable)
  - `revoked_at`: timestamp (nullable)
  - `revoked_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `revoke_reason`: text (nullable)
  - `superseded_at`: timestamp (nullable)
  - `superseded_by_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `supersede_reason`: text (nullable)
  - `superseded_by_document_record_id`: bigint (FK -> `document_records.id`, `nullOnDelete()`, nullable)
  - `supersede_reference`: text (nullable)
  - `created_at`, `updated_at`: timestamps
- **Unique Constraints:**
  - `['document_type_id', 'document_year', 'document_number']` — **COMPOSITE UNIQUE**
  - `['verification_token']` — **UNIQUE**

#### 9. `document_file_versions`
- **Migration:** `2026_09_10_110803_create_document_file_versions_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `document_record_id`: bigint (FK -> `document_records.id`, `cascadeOnDelete()`)
  - `version_number`: integer (1, 2, ...)
  - `file_path`: varchar(255) (convention: `document-officials/{id}/versions/{version_number}.pdf`)
  - `original_name`: varchar(255) (e.g. `RES-2024-001.pdf`)
  - `mime_type`: varchar(255) (`application/pdf`)
  - `size`: bigint (in bytes)
  - `is_current`: boolean (default `true`)
  - `approved_at`: timestamp (**NOT NULL**)
  - `approved_by_id`: bigint (FK -> `users.id`, `restrictOnDelete()`)
  - `created_at`, `updated_at`: timestamps
- **Unique Constraint:**
  - `['document_record_id', 'version_number']` — **COMPOSITE UNIQUE**

#### 10. `audit_logs`
- **Migration:** `2026_09_10_110803_create_audit_logs_table.php`
- **Columns:**
  - `id`: bigint (PK)
  - `actor_id`: bigint (FK -> `users.id`, `nullOnDelete()`, nullable)
  - `action`: varchar(255)
  - `auditable_type`: varchar(255) (polymorphic class, e.g. `App\Models\DocumentRecord`, `App\Models\Resident`, `App\Models\Household`)
  - `auditable_id`: bigint
  - `summary`: varchar(255)
  - `context`: json (nullable)
  - `created_at`, `updated_at`: timestamps
- **Indexes:**
  - `['auditable_type', 'auditable_id']`
  - `['action', 'created_at']`

#### 11. `notifications`
- **Migration:** `2026_09_10_110802_create_notifications_table.php`
- **Columns:**
  - `id`: uuid (PK)
  - `type`: varchar(255) (`App\Notifications\DocumentWorkflowNotification`)
  - `notifiable_type`: varchar(255) (`App\Models\User`)
  - `notifiable_id`: bigint
  - `data`: text (JSON payload)
  - `read_at`: timestamp (nullable)
  - `created_at`, `updated_at`: timestamps

---

## 2. Eloquent Models, Enums, & Behavior Details

### 2.1 Enums
1. **`App\Enums\DocumentStatus`**
   - Cases: `Draft = 'draft'`, `PendingApproval = 'pending_approval'`, `NeedsRevision = 'needs_revision'`, `Published = 'published'`, `Archived = 'archived'`, `Revoked = 'revoked'`, `Superseded = 'superseded'`
   - Helper methods: `label()`, `verificationHeading()`, `verificationDescription()`, `tone()`, `isPubliclyVerified()` (`true` only for `Published`).
2. **`App\Enums\ResidentStatus`**
   - Cases: `Active = 'active'`, `Transferred = 'transferred'`, `Deceased = 'deceased'`, `Inactive = 'inactive'`
   - Helper method: `label()`
3. **`App\Enums\UserRole`**
   - Cases: `Captain = 'captain'`, `Secretary = 'secretary'`, `PurokChairperson = 'purok_chairperson'`
   - Helper methods: `label()`, `canManageDocuments()`, `canViewBarangayResidents()`

### 2.2 Model Boot Hooks & Behaviors
- **`Resident` Model:**
  - `creating`: Auto-generates `resident_code = 'RES-' . Str::upper(Str::random(10))` if blank.
  - `saving`: Computes `normalized_full_name = Str::of("$first $middle $last $suffix")->squish()->lower()->toString()`.
  - Relationship `household`: `belongsTo(Household::class)`
  - Relationship `purok`: `belongsTo(Purok::class)`
  - Scope: `likelyDuplicate($normalizedFullName, $birthdate, $purokId)`
- **`Household` Model:**
  - `creating`: Auto-generates `household_code = 'HH-' . Str::upper(Str::random(10))` if blank.
  - Relationship `residents`: `hasMany(Resident::class)`
  - Relationship `purok`: `belongsTo(Purok::class)`
- **`DocumentRecord` Model:**
  - `creating`: Sets status default to `DocumentStatus::Draft` if blank; auto-generates 48-char alphanumeric `verification_token` if blank.
  - Number format convention: `{$type->number_prefix}-{$year}-{$sequence:03d}` (e.g. `RES-2024-001`, `ORD-2025-002`, `EO-2026-001`, `CERT-2024-001`).
  - Verification routes: `route('verification.show', $token)` -> `/verify/{token}`; `route('verification.document', $token)` -> `/verify/{token}/document`.
  - Lifecycle state machine invariants:
    - `Draft` -> can have draft PDF uploaded.
    - `PendingApproval` -> requires uploaded draft PDF.
    - `NeedsRevision` -> requires `return_comment`, `returned_at`, `returned_by_id`.
    - `Published` -> requires `approved_at`, `approved_by_id`, active `DocumentFileVersion`.
    - `Archived` -> requires prior publication, `archived_at`, `archived_by_id`, `archive_reason`.
    - `Revoked` -> requires prior publication, `revoked_at`, `revoked_by_id`, `revoke_reason`.
    - `Superseded` -> requires prior publication, `superseded_at`, `superseded_by_id`, `supersede_reason`, and links to a `Published` replacement document (`superseded_by_document_record_id`).
- **`DocumentType` Model:**
  - Method `nextDocumentNumber(int $year)` evaluates existing records and formats `$prefix-$year-$maxSeq+1`.

---

## 3. Existing Seeders & Baseline State

| Seeder | Purpose | Key Objects Created |
|---|---|---|
| `DocumentTaxonomySeeder` | Types, categories, & keyword rules | 6 types (`resolution`, `ordinance`, `certificate`, `memorandum`, `correspondence_record`, `meeting_minutes`), 6 categories (`governance`, `health`, `infrastructure`, `social-services`, `public-safety`, `education`), 7 categorization rules |
| `PurokSeeder` | Purok geographic boundaries | 7 puroks: `purok-1` through `purok-7`, sort order 1-7 |
| `AccountSeeder` | Official barangay staff accounts | 10 accounts: Captain (`captain@stafelomina.local`), Secretary (`secretary@stafelomina.local`), 7 Purok Chairs (`purok1`..`purok7@stafelomina.local`), 1 Inactive Secretary |
| `DatabaseSeeder` | Orchestrates seeds | Calls `DocumentTaxonomySeeder`, `PurokSeeder`, `AccountSeeder` |

---

## 4. Exact Specifications for `DemoDataSeeder`

### 4.1 Document Records Matrix (2024–2026)

To fully satisfy R1 and verify dashboard/report filters, the demo dataset must include **at least 28 to 32 official documents** representing:
- **4 Types**: Resolution (`RES`), Ordinance (`ORD`), Executive Order (`EO`), Certification (`CERT`).
- **5+ Categories**: Health, Infrastructure, Peace & Order, Education, Disaster Risk Reduction, Governance, Social Services.
- **3 Years**: 2024, 2025, 2026.
- **All 7 Lifecycle States**: Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded.

#### Recommended Distribution Matrix

| Document Number | Year | Type | Category | Status | Submitter / Creator | Approver / Overrider | Historical Context / Reason |
|---|---|---|---|---|---|---|---|
| **RES-2024-001** | 2024 | Resolution | Infrastructure | Archived | Secretary | Captain | Concreting of Purok 1 to Purok 3 Farm-to-Market Road. Archived: "Completed project retention period reached." |
| **ORD-2024-001** | 2024 | Ordinance | Peace & Order | Superseded | Secretary | Captain | 2024 Barangay Curfew Hours for Minors. Superseded by ORD-2026-001: "Superseded by updated 2026 omnibus youth protection ordinance." |
| **EO-2024-001** | 2024 | Executive Order | Disaster Risk Reduction | Published | Secretary | Captain | Reorganization of Barangay Disaster Risk Reduction and Management Committee (BDRRMC). |
| **CERT-2024-001** | 2024 | Certificate | Governance | Published | Secretary | Captain | Annual Barangay Good Financial Housekeeping Compliance Certification. |
| **RES-2024-002** | 2024 | Resolution | Health | Revoked | Secretary | Captain | Procurement of Medical Supplies for Health Center. Revoked: "Revoked pursuant to Municipal Audit finding on duplicate supplier bidding." |
| **ORD-2024-002** | 2024 | Ordinance | Infrastructure | Published | Secretary | Captain | Regulating Solid Waste Segregation at Purok Level. |
| **RES-2025-001** | 2025 | Resolution | Disaster Risk Reduction | Published | Secretary | Captain | Allocation of 5% Calamity Fund for Flood Mitigation in Purok 4 and 5. |
| **ORD-2025-001** | 2025 | Ordinance | Health | Published | Secretary | Captain | Anti-Dengue Clean-Up Drive and Community Sanitation Guidelines. |
| **EO-2025-001** | 2025 | Executive Order | Peace & Order | Published | Secretary | Captain | Mobilization of Barangay Tanods for Purok Boundary Checkpoints. |
| **CERT-2025-001** | 2025 | Certificate | Social Services | Published | Secretary | Captain | Indigency Masterlist Certification for DSWD Assistance Beneficiaries. |
| **RES-2025-002** | 2025 | Resolution | Education | Archived | Secretary | Captain | Barangay Financial Assistance Program for Out-of-School Youth. Archived: "FY 2025 cycle closed." |
| **ORD-2025-002** | 2025 | Ordinance | Peace & Order | Revoked | Secretary | Captain | Temporary Tricycle Route Restriction. Revoked: "Revoked after public hearing opposition from transport sector." |
| **EO-2025-002** | 2025 | Executive Order | Governance | Superseded | Secretary | Captain | Designating OIC for Barangay Records Custody. Superseded by EO-2026-001: "Superseded by new permanent administrative appointment." |
| **RES-2025-003** | 2025 | Resolution | Infrastructure | Published | Secretary | Captain | Installation of Solar Streetlights along Sitio Boundary Road. |
| **ORD-2026-001** | 2026 | Ordinance | Peace & Order | Published | Secretary | Captain | Comprehensive Youth Protection and Barangay Night Hours Ordinance (Replaces ORD-2024-001). |
| **EO-2026-001** | 2026 | Executive Order | Governance | Published | Secretary | Captain | Appointment and Duties of Barangay Archivist and Data Privacy Officer (Replaces EO-2025-002). |
| **RES-2026-001** | 2026 | Resolution | Infrastructure | Published | Secretary | Captain | Construction of Purok 6 Multipurpose Evacuation Center. |
| **RES-2026-002** | 2026 | Resolution | Health | Published | Secretary | Captain | Barangay Supplemental Nutrition and Child Immunization Drive. |
| **CERT-2026-001** | 2026 | Certificate | Governance | Published | Secretary | Captain | Certification of Barangay Residency and Moral Integrity for Civil Service Applicants. |
| **RES-2026-003** | 2026 | Resolution | Education | Pending Approval | Secretary | None (Pending) | Establishment of Community Learning Center and Free Wi-Fi Hub in Purok 2. |
| **ORD-2026-002** | 2026 | Ordinance | Disaster Risk Reduction | Pending Approval | Secretary | None (Pending) | Mandatory Fire and Earthquake Evacuation Protocols for Commercial Establishments. |
| **EO-2026-002** | 2026 | Executive Order | Peace & Order | Pending Approval | Secretary | None (Pending) | Creating Barangay Anti-Drug Abuse Council Special Task Force. |
| **RES-2026-004** | 2026 | Resolution | Infrastructure | Needs Revision | Secretary | Captain (Returned) | Drainage Canal Desilting and Rehabilitation. Return comment: "Please re-check budget estimate in Section 4 with Municipal Engineering standards." |
| **ORD-2026-003** | 2026 | Ordinance | Health | Needs Revision | Secretary | Captain (Returned) | Food Sanitation Inspection in Local Eateries. Return comment: "Specify compliance period and penalty schedule clearly." |
| **RES-2026-005** | 2026 | Resolution | Social Services | Draft | Secretary | None | Senior Citizen and PWD Quarterly Grocery Subsidy Program. |
| **ORD-2026-004** | 2026 | Ordinance | Infrastructure | Draft | Secretary | None | Purok Road Right-of-Way Regulation. |
| **EO-2026-003** | 2026 | Executive Order | Disaster Risk Reduction | Draft | Secretary | None | Pre-emptive Evacuation Guidelines during Severe Weather Bulletins. |
| **CERT-2026-002** | 2026 | Certificate | Peace & Order | Draft | Secretary | None | Barangay Clearance Certification for Local Business Operation. |

### 4.2 Residents and Households Specification

#### Distribution Across Puroks 1–7
- **Households:** Exactly 4 households per Purok = **28 households**.
- **Household Codes:** Deterministic codes `HH-P01-001` through `HH-P07-004`.
- **Residents:** 10–14 residents per Purok across all 7 Puroks = **80–90 residents total**.
- **Resident Codes:** Deterministic codes `RES-P01-001` through `RES-P07-013`.
- **Creators:**
  - Households and Residents created by their corresponding Purok Chairperson (`purok1@stafelomina.local` through `purok7@stafelomina.local`).
- **Demographic Realism:**
  - **Sex:** ~50% `male`, ~50% `female`.
  - **Civil Status:** `single`, `married`, `widowed`, `separated`.
  - **Voter Registration:**
    - Minors (age < 18, birthdate after 2008-09-13): `is_registered_voter = false`.
    - Adults (age >= 18): ~85% `true`, ~15% `false`.
  - **Resident Status:**
    - `active`: ~85% (normal living residents)
    - `transferred`: ~6% (moved to Davao/Cebu/Manila)
    - `deceased`: ~4% (elderly with deceased status)
    - `inactive`: ~5% (overseas OFW or uncontactable)
  - **Household Head Relationship:**
    - The household's `household_head_name` matches the full name of an adult resident in that household.
    - Family members in that household share the surname, address, and appropriate ages (head ~40-60, spouse ~38-58, children ~5-25, elderly grandparent ~65-80).
    - Addresses are specific to Barangay Sta. Felomina: e.g. "Sitio Centro, Purok 1", "Sitio Malipayon, Purok 2", "National Highway Junction, Purok 3", "Riverside Road, Purok 4", "Sitio Maharlika, Purok 5", "Upper Hills, Purok 6", "Sitio Pag-asa, Purok 7".

### 4.3 Audit Logs Specification

Audit logs must capture realistic historical operations:
1. **Document Actions:**
   - `document_submitted` (Secretary submits draft for approval)
   - `document_returned` (Captain returns document for revision with comment context)
   - `document_approved` (Captain approves and publishes document with file version context)
   - `document_archived` (Captain archives document with reason context)
   - `document_revoked` (Captain revokes document with reason context)
   - `document_superseded` (Captain supersedes document with replacement ID context)
2. **Resident Actions:**
   - `resident_created` (Purok Chair creates resident record)
   - `resident_status_changed` (Purok Chair changes status, e.g. active to transferred/deceased)
3. **Household Actions:**
   - `household_created` (Purok Chair creates household record)

### 4.4 In-App Notifications Specification

Notifications stored in `notifications` table:
- **Recipient:** Captain (`captain@stafelomina.local`)
  - Awaiting approval notifications for `RES-2026-003`, `ORD-2026-002`, `EO-2026-002`.
  - Mixture of read notifications (`read_at` set) and unread notifications (`read_at = null`) to test notification badge indicator.
- **Recipient:** Secretary (`secretary@stafelomina.local`)
  - Approval notifications for published documents.
  - Returned for revision notifications for `RES-2026-004` and `ORD-2026-003`.
- **Payload Schema:**
  ```json
  {
      "document_record_id": 15,
      "document_number": "RES-2026-003",
      "title": "Establishment of Community Learning Center...",
      "message": "Document awaiting approval.",
      "url": "/documents/15"
  }
  ```

### 4.5 File Storage Requirements

For public verification (`/verify/{token}/document`) and draft viewing (`/documents/{id}/draft-pdf`):
- For all **Published**, **Archived**, **Revoked**, and **Superseded** documents:
  - Create a valid dummy PDF file in `storage/app/private/document-officials/{id}/versions/1.pdf`.
- For all **Drafts**, **Pending**, and **Needs Revision** documents with draft paths:
  - Create a valid dummy PDF in `storage/app/private/document-drafts/{slug}.pdf`.
- Format: Valid standard PDF stream header (`%PDF-1.4 ... %%EOF`) so browser PDF viewers and HTTP download responses succeed without corrupt file errors.

---

## 5. Seeding Dependency Hierarchy

To prevent foreign key constraint violations and missing relation errors, the seeder must execute strictly in this dependency sequence:

```
Step 1: Foundational Taxonomy & Core References
  ├─ Call DocumentTaxonomySeeder (or ensure base types & categories exist)
  ├─ Upsert additional DocumentType: Executive Order ('executive_order', 'EO')
  ├─ Upsert additional DocumentCategories: Peace & Order ('peace-and-order'), Disaster Risk Reduction ('disaster-risk-reduction')
  └─ Call PurokSeeder (creates Purok 1 - 7)

Step 2: Core Staff Accounts
  └─ Call AccountSeeder (creates Captain, Secretary, 7 Purok Chairs, Inactive Staff)

Step 3: Households
  └─ For each Purok (1 to 7), create 4 Households (using deterministic household_code)

Step 4: Residents
  └─ For each Household, create 2 to 5 Residents (using deterministic resident_code)
  └─ Set household_head_name to matching head resident

Step 5: Document Records (Phase 1: Non-superseded / Replacement documents)
  ├─ Create Published documents for 2024, 2025, 2026
  ├─ Create Archived documents
  ├─ Create Revoked documents
  ├─ Create Draft documents
  ├─ Create Pending Approval documents
  ├─ Create Needs Revision documents
  └─ For Published/Archived/Revoked: create DocumentFileVersion (v1) and put private PDF file

Step 6: Document Records (Phase 2: Superseded documents)
  ├─ Create Superseded documents (ORD-2024-001, EO-2025-002)
  ├─ Link superseded_by_document_record_id to the replacement records created in Phase 1
  └─ Create DocumentFileVersion (v1) and put private PDF file

Step 7: Audit Logs
  └─ Upsert audit logs for document lifecycle transitions, resident creations, and status changes

Step 8: Notifications
  └─ Upsert in-app notifications for Captain and Secretary
```

---

## 6. Idempotency & Constraint Violation Prevention

### 6.1 Unique Key Constraints in Scope
1. `document_types`: `name`, `code`, `number_prefix` -> match on `code`.
2. `document_categories`: `name`, `slug` -> match on `slug`.
3. `categorization_rules`: `name` -> match on `name`.
4. `puroks`: `name`, `code` -> match on `code`.
5. `users`: `email` -> match on `email`.
6. `households`: `household_code` -> match on `household_code` (e.g. `HH-P01-001`).
7. `residents`: `resident_code` -> match on `resident_code` (e.g. `RES-P01-001`).
8. `document_records`:
   - Composite Unique: `['document_type_id', 'document_year', 'document_number']`.
   - Unique: `verification_token`.
   - **Idempotency Strategy:** Query by composite unique `['document_type_id', 'document_year', 'document_number']`. If existing, preserve its `verification_token`. If new, assign deterministic or secure 48-character token.
9. `document_file_versions`:
   - Composite Unique: `['document_record_id', 'version_number']`.
   - **Idempotency Strategy:** `updateOrCreate(['document_record_id' => $doc->id, 'version_number' => 1], [...])`.
10. `audit_logs`:
    - **Idempotency Strategy:** `AuditLog::firstOrCreate(['auditable_type' => $type, 'auditable_id' => $id, 'action' => $action, 'actor_id' => $actorId], [...])`.
11. `notifications`:
    - **Idempotency Strategy:** Check `DB::table('notifications')->where('notifiable_id', $userId)->where('data->document_record_id', $docId)->where('data->message', $message)->exists()`. Only insert if missing, generating deterministic UUID.

### 6.2 Foreign Key Delete Cascades vs Restricts
- `document_records.created_by_id`: **RESTRICT** (cannot delete user without deleting documents first).
- `document_records.document_type_id`: **RESTRICT**.
- `document_records.document_category_id`: **RESTRICT**.
- `document_records.superseded_by_document_record_id`: **SET NULL**.
- `document_file_versions.document_record_id`: **CASCADE**.
- `residents.purok_id`, `residents.household_id`: **RESTRICT**.
- `households.purok_id`: **RESTRICT**.
- Because our idempotency approach uses `updateOrCreate` and `firstOrCreate`, it avoids destructive `TRUNCATE` or `DELETE` statements, ensuring zero foreign key restriction errors regardless of database state.

---

## 7. Verification Method

Once implemented by the delivery agent, the seeder can be verified independently using the following commands and test assertions:

### 7.1 Seeding Execution
```powershell
php artisan db:seed --class=DemoDataSeeder
```
*Expected: Clean completion with zero warnings, exceptions, or constraint violations.*

### 7.2 Idempotency Test
```powershell
php artisan db:seed --class=DemoDataSeeder
```
*Expected: Second run executes cleanly; record counts in `residents`, `households`, `document_records`, `document_file_versions`, `audit_logs`, and `notifications` remain identical.*

### 7.3 Automated Feature Test Suite
Create `tests/Feature/DemoDataSeederTest.php` with assertions:
1. `DemoDataSeeder` populates documents across types (`resolution`, `ordinance`, `executive_order`, `certificate`), categories (`Health`, `Infrastructure`, `Peace & Order`, `Education`, `Disaster Risk Reduction`), years (`2024`, `2025`, `2026`), and all 7 lifecycle statuses.
2. All 7 Puroks have households and residents with valid foreign keys and matching household heads.
3. Rerunning seeder produces zero unique key violations and keeps counts constant.
4. Public verification route `/verify/{token}` resolves for each seeded document without error.
5. Published document PDF route `/verify/{token}/document` streams `200 OK` for published and archived documents.
6. Captain and Secretary dashboards show populated metrics and recent activity.
7. Document and resident reports in `ReportEngine` filter correctly by year, category, purok, voter status, and resident status.

Run tests:
```powershell
php artisan test --filter=DemoDataSeederTest
```
