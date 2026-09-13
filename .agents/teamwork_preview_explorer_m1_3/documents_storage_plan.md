# Official Documents, Lifecycle States, Storage Files, Audit Logs, and Notifications Specification for DemoDataSeeder

**Workspace:** `C:\laragon\www\Barangay-System`  
**Milestone:** M1 (Demo Dataset Seeder)  
**Author:** teamwork_preview_explorer (M1 Explorer 3: Documents, Lifecycle & Storage Plan)  
**Target Delivery File:** `database/seeders/DemoDataSeeder.php`  
**Date:** September 13, 2026  

---

## 1. Executive Summary & Design Overview

This technical specification provides the complete dataset architecture, lifecycle mechanics, storage hierarchy, and idempotent PHP implementation blueprint for **Official Documents, Lifecycle States, Storage Files, Audit Trails, and Notifications** to be incorporated into `DemoDataSeeder.php`.

### 1.1 Scope & Core Requirements
1. **Multi-Year Official Documents**: Exactly 32 realistic official documents distributed across calendar years **2024, 2025, and 2026**.
2. **Comprehensive Document Types**: 4 document types:
   - Resolutions (`RES`)
   - Ordinances (`ORD`)
   - Executive Orders (`EO`)
   - Certifications (`CERT`)
3. **Core Barangay Categories**: 7 categories including Health, Infrastructure, Peace & Order, Education, Disaster Risk Reduction, Governance, and Social Services.
4. **All 7 Lifecycle States**:
   - `draft` (3 documents)
   - `pending_approval` (3 documents)
   - `needs_revision` (2 documents)
   - `published` (14 documents)
   - `archived` (4 documents)
   - `revoked` (3 documents)
   - `superseded` (3 documents)
5. **Superseded Chaining & Two-Phase Seeding**:
   - Replacement documents (`published`) are seeded in **Phase 1**.
   - Superseded documents are seeded in **Phase 2**, linking `superseded_by_document_record_id` directly to the replacement document's ID.
6. **Physical Storage & PDF Generation**:
   - For all 24 approved documents (`published`, `archived`, `revoked`, `superseded`), `DocumentFileVersion` records (`version_number = 1`, `is_current = true`, `approved_by_id = $captain->id`) are created, and valid physical PDF files are created at `storage/app/private/document-officials/{id}/versions/1.pdf` via `Storage::disk('local')->put()`.
   - For all 8 working documents (`draft`, `pending_approval`, `needs_revision`), physical draft PDF files are created at `storage/app/private/document-drafts/{doc_number}-draft.pdf`.
   - All PDF streams are valid standard PDF files to prevent 404 or corrupt file errors during public QR verification (`/verify/{token}/document`) and draft previews (`/documents/{id}/draft-pdf`).
7. **Rich Audit Trails (`audit_logs`)**:
   - Historical audit logs for document submissions, approvals, returns/rejections, archives, revocations, supersessions, and resident/household registrations.
8. **In-App Notifications (`notifications`)**:
   - Notification records for Captain, Secretary, and Purok Chairpersons 1–7.
   - Mix of read (`read_at` set) and unread (`read_at = null`) records to exercise the navigation badge and notification views.
9. **Strict 100% Idempotency**:
   - Documents are matched deterministically on the composite key `['document_type_id', 'document_year', 'document_number']`.
   - Verification tokens are preserved across re-runs.
   - Audit logs use `firstOrCreate` on `['auditable_type', 'auditable_id', 'action']`.
   - Notifications use deterministic UUIDs with `updateOrInsert`.

---

## 2. Taxonomy & Schema Prerequisites

Before seeding documents, `DemoDataSeeder` must ensure prerequisite types and categories exist. While `DocumentTaxonomySeeder` provides baseline data, the following additions must be ensured:

### 2.1 Missing Document Type: Executive Order (`EO`)
In `DocumentTaxonomySeeder`, Executive Order is not defined. `DemoDataSeeder` must register:
```php
DocumentType::updateOrCreate(
    ['code' => 'executive_order'],
    [
        'name' => 'Executive Order',
        'number_prefix' => 'EO',
        'description' => 'Executive orders and administrative directives issued by the Punong Barangay.',
        'is_active' => true,
    ]
);
```

### 2.2 Missing Categories: Peace & Order and Disaster Risk Reduction
In `DocumentTaxonomySeeder`, the categories are Governance, Health, Infrastructure, Social Services, Public Safety, and Education. To satisfy the prompt's explicit requirement for **Peace & Order** and **Disaster Risk Reduction**:
```php
$additionalCategories = [
    [
        'name' => 'Peace & Order',
        'slug' => 'peace-and-order',
        'description' => 'Barangay peace, public order, and neighborhood security initiatives.',
    ],
    [
        'name' => 'Disaster Risk Reduction',
        'slug' => 'disaster-risk-reduction',
        'description' => 'Disaster preparedness, flood mitigation, emergency response, and calamity management.',
    ],
];

foreach ($additionalCategories as $cat) {
    DocumentCategory::updateOrCreate(
        ['slug' => $cat['slug']],
        [
            'name' => $cat['name'],
            'description' => $cat['description'],
            'is_active' => true,
        ]
    );
}
```

### 2.3 Supplemental Categorization Rules
```php
$additionalRules = [
    [
        'name' => 'Peace & Order Keywords',
        'category_slug' => 'peace-and-order',
        'type_code' => null,
        'keywords' => ['curfew', 'tanod', 'patrol', 'checkpoint', 'security', 'crime', 'peace and order'],
        'priority' => 25,
    ],
    [
        'name' => 'Disaster Risk Reduction Keywords',
        'category_slug' => 'disaster-risk-reduction',
        'type_code' => null,
        'keywords' => ['disaster', 'calamity', 'flood', 'typhoon', 'evacuation', 'bdrrmc', 'rescue'],
        'priority' => 15,
    ],
];

foreach ($additionalRules as $rule) {
    $category = DocumentCategory::where('slug', $rule['category_slug'])->first();
    if ($category) {
        CategorizationRule::updateOrCreate(
            ['name' => $rule['name']],
            [
                'document_category_id' => $category->id,
                'document_type_id' => null,
                'keywords' => $rule['keywords'],
                'priority' => $rule['priority'],
                'is_active' => true,
            ]
        );
    }
}
```

---

## 3. Official Documents Dataset Specification (32 Documents)

The dataset contains 32 realistic official documents of Barangay Sta. Felomina across 2024, 2025, and 2026.

### 3.1 Distribution Breakdown
- **By Year**: 2024 (7 docs), 2025 (9 docs), 2026 (16 docs).
- **By Type**:
  - Resolutions (`RES`): 12 docs
  - Ordinances (`ORD`): 10 docs
  - Executive Orders (`EO`): 6 docs
  - Certificates (`CERT`): 4 docs
- **By Category**:
  - Infrastructure: 8 docs
  - Peace & Order: 6 docs
  - Health: 5 docs
  - Disaster Risk Reduction: 5 docs
  - Governance: 4 docs
  - Education: 2 docs
  - Social Services: 2 docs
- **By Lifecycle State**:
  - Published: 14 docs
  - Archived: 4 docs
  - Revoked: 3 docs
  - Superseded: 3 docs
  - Pending Approval: 3 docs
  - Needs Revision: 2 docs
  - Draft: 3 docs

---

### 3.2 Master Document Catalog

#### Year 2024 (Historical - 7 Records)

| # | Doc Number | Type | Category | Status | Date | Title | Key Details / Lifecycle Context |
|---|---|---|---|---|---|---|---|
| 1 | **RES-2024-001** | `resolution` | `infrastructure` | `archived` | 2024-03-15 | Concreting of Purok 1 to Purok 3 Farm-to-Market Road | Approved 2024-03-18 by Captain. Archived 2025-01-10 by Captain: *"Project construction completed and audited; administrative retention period archived."* |
| 2 | **ORD-2024-001** | `ordinance` | `peace-and-order` | `superseded` | 2024-04-20 | Barangay Curfew Hours and Nighttime Safety Restrictions for Minors | Approved 2024-04-25 by Captain. Superseded 2026-01-14 by Captain: *"Superseded by updated 2026 omnibus youth protection ordinance."* Replacement: **ORD-2026-001**. |
| 3 | **EO-2024-001** | `executive_order` | `disaster-risk-reduction` | `published` | 2024-06-05 | Reorganization and Mobilization of the Barangay Disaster Risk Reduction and Management Committee (BDRRMC) | Approved 2024-06-07 by Captain. Restructures emergency response teams and volunteer rescue units. |
| 4 | **CERT-2024-001** | `certificate` | `governance` | `published` | 2024-07-12 | Annual Good Financial Housekeeping Full Compliance Certification | Approved 2024-07-14 by Captain. Full compliance with DILG financial transparency posting rules for FY 2023. |
| 5 | **RES-2024-002** | `resolution` | `disaster-risk-reduction` | `superseded` | 2024-08-22 | Barangay Calamity Preparedness and Emergency Response Framework of 2024 | Approved 2024-08-25 by Captain. Superseded 2026-01-19 by Captain: *"Superseded by comprehensive 2026-2028 multi-year disaster risk reduction master plan."* Replacement: **RES-2026-001**. |
| 6 | **ORD-2024-002** | `ordinance` | `infrastructure` | `published` | 2024-09-18 | Comprehensive Solid Waste Management and Mandatory Purok Waste Segregation | Approved 2024-09-22 by Captain. Institutionalizes waste sorting and creates composting points across Puroks 1-7. |
| 7 | **RES-2024-003** | `resolution` | `health` | `revoked` | 2024-11-04 | Emergency Procurement of Health Center Medical Equipment | Approved 2024-11-06 by Captain. Revoked 2024-12-01 by Captain: *"Revoked pursuant to Municipal Audit finding regarding duplicate bidding procedures."* |

---

#### Year 2025 (Recent Past - 9 Records)

| # | Doc Number | Type | Category | Status | Date | Title | Key Details / Lifecycle Context |
|---|---|---|---|---|---|---|---|
| 8 | **RES-2025-001** | `resolution` | `disaster-risk-reduction` | `published` | 2025-01-28 | Allocation of 5% Local Calamity Fund for Flood Mitigation in Puroks 4 and 5 | Approved 2025-01-30 by Captain. Authorizes river dredging and purchase of motorized rescue boats. |
| 9 | **ORD-2025-001** | `ordinance` | `health` | `published` | 2025-03-10 | Anti-Dengue Prevention Campaign and Community Sanitation Clean-Up Directive | Approved 2025-03-14 by Captain. Mandates weekly search-and-destroy larval inspection drives across all households. |
| 10 | **EO-2025-001** | `executive_order` | `peace-and-order` | `published` | 2025-04-15 | Mobilization and Deployment of Barangay Tanod Brigade for Boundary Checkpoints | Approved 2025-04-18 by Captain. Night patrol deployments along highway intersections and purok boundary outposts. |
| 11 | **CERT-2025-001** | `certificate` | `social-services` | `published` | 2025-05-20 | Masterlist Certification of Indigent Families for National DSWD 4Ps Program | Approved 2025-05-22 by Captain. Endorses masterlist of 120 indigent households to Municipal Social Welfare. |
| 12 | **RES-2025-002** | `resolution` | `education` | `archived` | 2025-06-18 | Educational Subsidy and School Supplies Allowance for Out-of-School Youth | Approved 2025-06-22 by Captain. Archived 2026-01-05 by Captain: *"Program cycle for Academic Year 2024-2025 formally closed and audited."* |
| 13 | **ORD-2025-002** | `ordinance` | `peace-and-order` | `revoked` | 2025-08-11 | Experimental Route and Terminal Restriction for Tricycles and Pedicabs | Approved 2025-08-15 by Captain. Revoked 2025-10-02 by Captain: *"Revoked following Sangguniang Bayan review and opposition from local transport cooperatives."* |
| 14 | **EO-2025-002** | `executive_order` | `governance` | `superseded` | 2025-09-02 | Interim Designation of Records Custodian and Document Handler | Approved 2025-09-05 by Captain. Superseded 2026-01-15 by Captain: *"Superseded by permanent administrative appointment and data protection restructuring."* Replacement: **EO-2026-001**. |
| 15 | **RES-2025-003** | `resolution` | `infrastructure` | `published` | 2025-10-19 | Installation of Solar-Powered Streetlights along Sitio Riverside and Purok 6 | Approved 2025-10-23 by Captain. Authorizes 45 solar street illumination posts to enhance night road safety. |
| 16 | **CERT-2025-002** | `certificate` | `peace-and-order` | `published` | 2025-11-28 | Lupon Tagapamayapa Dispute Settlement Compliance Certification | Approved 2025-11-30 by Captain. Certifies amicable settlement of boundary dispute Lupon Case No. 2025-014. |

---

#### Year 2026 (Current Year - 16 Records)

| # | Doc Number | Type | Category | Status | Date | Title | Key Details / Lifecycle Context |
|---|---|---|---|---|---|---|---|
| 17 | **ORD-2026-001** | `ordinance` | `peace-and-order` | `published` | 2026-01-10 | Omnibus Youth Protection, Curfew Hours, and Child Welfare Ordinance of 2026 | Approved 2026-01-14 by Captain. **Replacement for ORD-2024-001**. Enacts updated curfew and parental responsibility rules. |
| 18 | **EO-2026-001** | `executive_order` | `governance` | `published` | 2026-01-12 | Designation of Barangay Data Protection Officer and Official Records Custodian | Approved 2026-01-15 by Captain. **Replacement for EO-2025-002**. Formally appoints permanent DPO under RA 10173. |
| 19 | **RES-2026-001** | `resolution` | `disaster-risk-reduction` | `published` | 2026-01-16 | Barangay Disaster Risk Reduction Master Plan and Evacuation Protocol for 2026-2028 | Approved 2026-01-19 by Captain. **Replacement for RES-2024-002**. Multi-year evacuation roadmap and river warning sensors. |
| 20 | **RES-2026-002** | `resolution` | `health` | `published` | 2026-02-03 | Barangay Supplemental Nutrition and Child Immunization Drive for 2026 | Approved 2026-02-06 by Captain. Joint program with Municipal Health Office for quarterly child feeding and vaccines. |
| 21 | **CERT-2026-001** | `certificate` | `governance` | `published` | 2026-02-18 | Certification of Barangay Residency and Good Moral Standing | Approved 2026-02-20 by Captain. Standardized template certifying residency and clearance from local civil disputes. |
| 22 | **ORD-2026-002** | `ordinance` | `infrastructure` | `published` | 2026-03-01 | Purok Drainage Maintenance and Anti-Littering Ordinance | Approved 2026-03-05 by Captain. Mandates quarterly canal declogging and penalties for dumping waste into waterways. |
| 23 | **RES-2026-003** | `resolution` | `infrastructure` | `archived` | 2026-03-08 | Authorization for Construction of Purok 6 Multipurpose Evacuation Center | Approved 2026-03-12 by Captain. Archived 2026-07-15 by Captain: *"Replaced by direct provincial grant agreement; barangay funding file closed and archived."* |
| 24 | **ORD-2026-003** | `ordinance` | `governance` | `revoked` | 2026-04-02 | Temporary Moratorium on Special Cockfighting and Commercial Amusement Permits | Approved 2026-04-05 by Captain. Revoked 2026-04-20 by Captain: *"Revoked due to legal conflict with existing municipal licensing ordinance Section 12."* |
| 25 | **RES-2026-004** | `resolution` | `education` | `pending_approval` | 2026-08-10 | Establishment of Community Learning Center and Free Wi-Fi Hub in Purok 2 | Submitted 2026-08-12 by Secretary. Awaiting Captain approval. Draft PDF uploaded. |
| 26 | **ORD-2026-004** | `ordinance` | `disaster-risk-reduction` | `pending_approval` | 2026-08-18 | Mandatory Fire and Earthquake Evacuation Protocols for Commercial Establishments | Submitted 2026-08-20 by Secretary. Awaiting Captain approval. Draft PDF uploaded. |
| 27 | **EO-2026-002** | `executive_order` | `peace-and-order` | `pending_approval` | 2026-08-25 | Creating Barangay Anti-Drug Abuse Council (BADAC) Special Task Force | Submitted 2026-08-26 by Secretary. Awaiting Captain approval. Draft PDF uploaded. |
| 28 | **RES-2026-005** | `resolution` | `infrastructure` | `needs_revision` | 2026-08-28 | Drainage Canal Desilting and Culvert Rehabilitation along Purok 4 Creek | Submitted 2026-08-29 by Secretary. Returned 2026-08-30 by Captain: *"Please re-check the cost estimate in Section 4 against Municipal Engineering schedule of rates before resubmission."* Draft PDF uploaded. |
| 29 | **ORD-2026-005** | `ordinance` | `health` | `needs_revision` | 2026-09-01 | Food Sanitation and Hygiene Certification Standards for Local Eateries and Carinderias | Submitted 2026-09-02 by Secretary. Returned 2026-09-03 by Captain: *"Specify compliance grace period (30 days) and exact penalty fee tiers under Section 8."* Draft PDF uploaded. |
| 30 | **RES-2026-006** | `resolution` | `social-services` | `draft` | 2026-09-05 | Senior Citizen and PWD Quarterly Grocery Subsidy Program | Created 2026-09-05 by Secretary. In drafting stage. Draft PDF uploaded. |
| 31 | **ORD-2026-006** | `ordinance` | `infrastructure` | `draft` | 2026-09-08 | Purok Road Right-of-Way Clearing and Obstruction Removal Regulation | Created 2026-09-08 by Secretary. In drafting stage. Draft PDF uploaded. |
| 32 | **EO-2026-003** | `executive_order` | `disaster-risk-reduction` | `draft` | 2026-09-10 | Pre-emptive Evacuation Guidelines during Severe Weather Bulletins | Created 2026-09-10 by Secretary. In drafting stage. Draft PDF uploaded. |

---

## 4. Superseded Chaining & Two-Phase Seeding Logic

The database contains a foreign key constraint on `document_records.superseded_by_document_record_id` referencing `document_records.id`. Furthermore, the domain rule in `DocumentRecord::supersede()` requires that the replacement document be `Published`.

To avoid foreign key violations and guarantee ordering:
1. **Phase 1 Seeding (Non-Superseded Documents)**:
   - Seed all 29 non-superseded documents: all 14 `published` documents (including `ORD-2026-001`, `EO-2026-001`, `RES-2026-001`), the 4 `archived` documents, 3 `revoked` documents, 3 `pending_approval` documents, 2 `needs_revision` documents, and 3 `draft` documents.
2. **Phase 2 Seeding (Superseded Documents)**:
   - Seed the 3 `superseded` documents. For each, look up the replacement document ID from the database using its deterministic document number:
     ```php
     // Example for ORD-2024-001
     $replacement = DocumentRecord::where('document_number', 'ORD-2026-001')->firstOrFail();
     
     $supersededDoc = DocumentRecord::updateOrCreate(
         [
             'document_type_id' => $ordType->id,
             'document_year' => 2024,
             'document_number' => 'ORD-2024-001',
         ],
         [
             // ...
             'status' => DocumentStatus::Superseded,
             'superseded_at' => Carbon::parse('2026-01-14 10:00:00'),
             'superseded_by_id' => $captain->id,
             'supersede_reason' => 'Superseded by updated 2026 omnibus youth protection ordinance.',
             'superseded_by_document_record_id' => $replacement->id,
             'supersede_reference' => 'Ordinance No. ORD-2026-001',
         ]
     );
     ```

### Superseded Document Pairs
1. **ORD-2024-001** (Minors Curfew) $\rightarrow$ replaced by **ORD-2026-001** (Omnibus Youth Protection)
2. **EO-2025-002** (Interim Records Custodian) $\rightarrow$ replaced by **EO-2026-001** (Data Protection Officer & Custodian)
3. **RES-2024-002** (2024 Calamity Framework) $\rightarrow$ replaced by **RES-2026-001** (2026-2028 DRR Master Plan)

---

## 5. Storage Architecture & Physical PDF Generation

### 5.1 Storage Layer Mechanics
In `config/filesystems.php`, the `'local'` disk root is configured as `storage_path('app/private')`:
```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => true,
    'throw' => false,
    'report' => false,
],
```
This means:
- `Storage::disk('local')->put('document-officials/5/versions/1.pdf', $bytes)` creates a physical file at:
  `C:\laragon\www\Barangay-System\storage\app\private\document-officials\5\versions\1.pdf`
- `Storage::disk('local')->put('document-drafts/RES-2026-004-draft.pdf', $bytes)` creates a physical file at:
  `C:\laragon\www\Barangay-System\storage\app\private\document-drafts\RES-2026-004-draft.pdf`
- Controllers `DocumentPublishedPdfController` and `DocumentDraftPdfController` check `Storage::exists($filePath)` on this exact disk.

### 5.2 Official Version Storage Requirements
For all 24 approved documents (`published`, `archived`, `revoked`, `superseded`):
1. Create/Update a `DocumentFileVersion` record:
   - `document_record_id`: `$doc->id`
   - `version_number`: `1`
   - `file_path`: `"document-officials/{$doc->id}/versions/1.pdf"`
   - `original_name`: `"{$doc->document_number}.pdf"`
   - `mime_type`: `'application/pdf'`
   - `size`: size of the generated PDF in bytes
   - `is_current`: `true`
   - `approved_at`: `$doc->approved_at`
   - `approved_by_id`: `$captain->id`
2. Generate the physical PDF file via `Storage::disk('local')->put("document-officials/{$doc->id}/versions/1.pdf", $pdfContent)`.

### 5.3 Draft File Storage Requirements
For all 8 working documents (`pending_approval`, `needs_revision`, `draft`):
1. Update `document_records` attributes:
   - `draft_pdf_path`: `"document-drafts/{$doc->document_number}-draft.pdf"`
   - `draft_pdf_original_name`: `"{$doc->document_number}-draft.pdf"`
   - `draft_pdf_mime_type`: `'application/pdf'`
   - `draft_pdf_size`: size in bytes
   - `draft_pdf_uploaded_at`: `$doc->created_at`
   - `draft_pdf_uploaded_by_id`: `$secretary->id`
2. Generate the physical draft PDF file via `Storage::disk('local')->put("document-drafts/{$doc->document_number}-draft.pdf", $pdfContent)`.

### 5.4 Standard-Compliant Minimal PDF Generator Pattern
To ensure the files are valid PDF documents that can be streamed, downloaded, and opened in browser viewers without corrupt-file warnings, use this 100% compliant minimal 1-page PDF template:

```php
private function generateMinimalPdf(string $title, string $docNumber): string
{
    $content = sprintf(
        "BT\n/F1 14 Tf\n72 720 Td\n(Republic of the Philippines) Tj\n" .
        "0 -20 Td\n(Province of Ilocos Norte - City of Laoag) Tj\n" .
        "0 -20 Td\n(BARANGAY STA. FELOMINA) Tj\n" .
        "0 -35 Td\n/F1 12 Tf\n(Official Record: %s) Tj\n" .
        "0 -20 Td\n(Title: %s) Tj\n" .
        "0 -30 Td\n(This is an official digitized barangay document record.) Tj\nET",
        addcslashes($docNumber, "()\\"),
        addcslashes(substr($title, 0, 60), "()\\")
    );

    $contentLength = strlen($content);

    $pdf = "%PDF-1.4\n" .
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n" .
        "4 0 obj\n<< /Length " . $contentLength . " >>\nstream\n" .
        $content . "\nendstream\nendobj\n" .
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n" .
        "xref\n0 6\n" .
        "0000000000 65535 f \n" .
        "0000000009 00000 n \n" .
        "0000000058 00000 n \n" .
        "0000000115 00000 n \n" .
        "0000000252 00000 n \n" .
        "0000000320 00000 n \n" .
        "trailer\n<< /Size 6 /Root 1 0 R >>\n" .
        "startxref\n390\n%%EOF";

    return $pdf;
}
```

---

## 6. Historical Audit Logs Specification (`audit_logs`)

### 6.1 Database Schema Mapping
- `id`: bigint (auto-incrementing PK)
- `actor_id`: bigint (FK to `users.id`, nullable)
- `action`: string (`document_submitted`, `document_approved`, `document_archived`, `document_revoked`, `document_superseded`, `document_returned`, `household_created`, `resident_created`, `resident_status_updated`)
- `auditable_type`: string (`App\Models\DocumentRecord`, `App\Models\Household`, `App\Models\Resident`)
- `auditable_id`: bigint
- `summary`: string
- `context`: json (nullable)
- `created_at`, `updated_at`: timestamps

### 6.2 Audit Events Matrix to Seed
For every document based on its lifecycle history:
1. **Submissions (`document_submitted`)**:
   - Seeded for all 29 non-draft documents (`pending_approval`, `needs_revision`, `published`, `archived`, `revoked`, `superseded`).
   - Actor: Secretary (`Maria L. Santos`).
   - Summary: `"Document submitted for Captain approval."`
   - Timestamp: Match `$doc->submitted_at`.
2. **Returns for Revision (`document_returned`)**:
   - Seeded for the 2 `needs_revision` documents (`RES-2026-005`, `ORD-2026-005`).
   - Actor: Captain (`Juan D. Cruz`).
   - Summary: `"Document returned for revision."`
   - Context: `['comment' => $doc->return_comment]`
   - Timestamp: Match `$doc->returned_at`.
3. **Approvals (`document_approved`)**:
   - Seeded for all 24 approved documents (`published`, `archived`, `revoked`, `superseded`).
   - Actor: Captain (`Juan D. Cruz`).
   - Summary: `"Document approved and published."`
   - Context: `['version_number' => 1]`
   - Timestamp: Match `$doc->approved_at`.
4. **Archives (`document_archived`)**:
   - Seeded for the 4 `archived` documents (`RES-2024-001`, `RES-2025-002`, `RES-2026-003`, etc.).
   - Actor: Captain (`Juan D. Cruz`).
   - Summary: `"Document archived."`
   - Context: `['reason' => $doc->archive_reason]`
   - Timestamp: Match `$doc->archived_at`.
5. **Revocations (`document_revoked`)**:
   - Seeded for the 3 `revoked` documents (`RES-2024-003`, `ORD-2025-002`, `ORD-2026-003`).
   - Actor: Captain (`Juan D. Cruz`).
   - Summary: `"Document revoked."`
   - Context: `['reason' => $doc->revoke_reason]`
   - Timestamp: Match `$doc->revoked_at`.
6. **Supersessions (`document_superseded`)**:
   - Seeded for the 3 `superseded` documents (`ORD-2024-001`, `RES-2024-002`, `EO-2025-002`).
   - Actor: Captain (`Juan D. Cruz`).
   - Summary: `"Document superseded."`
   - Context: `['reason' => $doc->supersede_reason, 'replacement_document_record_id' => $doc->superseded_by_document_record_id]`
   - Timestamp: Match `$doc->superseded_at`.
7. **Resident Profiling Events**:
   - Seeded for sampled residents and households across Puroks 1-7.
   - Actions: `household_created` (Actor: Purok Chair), `resident_created` (Actor: Purok Chair), `resident_status_updated` (for transferred or deceased residents).

### 6.3 Audit Log Idempotency Strategy
```php
AuditLog::firstOrCreate(
    [
        'auditable_type' => DocumentRecord::class,
        'auditable_id' => $doc->id,
        'action' => $action,
    ],
    [
        'actor_id' => $actor->id,
        'summary' => $summary,
        'context' => $context,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]
);
```

---

## 7. In-App Notifications Specification (`notifications`)

### 7.1 Database Schema Mapping
- `id`: uuid (PK)
- `type`: string (`App\Notifications\DocumentWorkflowNotification`)
- `notifiable_type`: string (`App\Models\User`)
- `notifiable_id`: bigint
- `data`: text (JSON payload matching `DocumentWorkflowNotification::toArray()`)
- `read_at`: timestamp (nullable; `null` = unread)
- `created_at`, `updated_at`: timestamps

### 7.2 UI Rendering Contract
In `resources/views/notifications/index.blade.php`:
- `data_get($notification->data, 'message')` $\rightarrow$ Notification heading
- `data_get($notification->data, 'document_number')` $\cdot$ `data_get($notification->data, 'title')` $\rightarrow$ Subheading
- `data_get($notification->data, 'url')` $\rightarrow$ Link target (`/documents/{id}`)
- `$notification->unread()` $\rightarrow$ Green highlight styling (`bg-emerald-50/60`)
- Bell icon in sidebar displays notification presence.

### 7.3 Role Distribution Matrix

| Recipient Role | Recipient Email | Notification Event / Message | Target Document | Read State | Rationale |
|---|---|---|---|---|---|
| **Captain** | `captain@stafelomina.local` | "Document awaiting approval." | `RES-2026-004` | **Unread** (`read_at = null`) | Tests Captain pending approval badge |
| **Captain** | `captain@stafelomina.local` | "Document awaiting approval." | `ORD-2026-004` | **Unread** (`read_at = null`) | Tests Captain pending approval badge |
| **Captain** | `captain@stafelomina.local` | "Document awaiting approval." | `EO-2026-002` | **Unread** (`read_at = null`) | Tests Captain pending approval badge |
| **Captain** | `captain@stafelomina.local` | "Document awaiting approval." | `ORD-2026-002` | **Read** | Historical notification test |
| **Captain** | `captain@stafelomina.local` | "Document awaiting approval." | `RES-2026-002` | **Read** | Historical notification test |
| **Secretary** | `secretary@stafelomina.local` | "Document returned for revision." | `RES-2026-005` | **Unread** (`read_at = null`) | Tests Secretary revision alert |
| **Secretary** | `secretary@stafelomina.local` | "Document returned for revision." | `ORD-2026-005` | **Unread** (`read_at = null`) | Tests Secretary revision alert |
| **Secretary** | `secretary@stafelomina.local` | "Document approved and published." | `RES-2026-001` | **Unread** (`read_at = null`) | Tests Secretary approval alert |
| **Secretary** | `secretary@stafelomina.local` | "Document approved and published." | `ORD-2026-001` | **Read** | Historical notification test |
| **Secretary** | `secretary@stafelomina.local` | "Document approved and published." | `EO-2026-001` | **Read** | Historical notification test |
| **Purok 1 Chair** | `purok1@stafelomina.local` | "New published ordinance available for implementation." | `ORD-2026-001` | **Unread** (`read_at = null`) | Curfew implementation in Purok 1 |
| **Purok 1 Chair** | `purok1@stafelomina.local` | "New official resolution approved and published." | `RES-2024-001` | **Read** | Road concreting Purok 1 to 3 |
| **Purok 2 Chair** | `purok2@stafelomina.local` | "New published ordinance available for implementation." | `ORD-2026-001` | **Unread** (`read_at = null`) | Curfew implementation in Purok 2 |
| **Purok 2 Chair** | `purok2@stafelomina.local` | "New official certificate issued." | `CERT-2025-002` | **Read** | Lupon dispute resolution in Purok 2 |
| **Purok 3 Chair** | `purok3@stafelomina.local` | "New published ordinance available for implementation." | `ORD-2024-002` | **Read** | Solid waste segregation in Purok 3 |
| **Purok 4 Chair** | `purok4@stafelomina.local` | "New official resolution approved and published." | `RES-2025-001` | **Unread** (`read_at = null`) | Calamity flood mitigation Purok 4 |
| **Purok 5 Chair** | `purok5@stafelomina.local` | "New official resolution approved and published." | `RES-2025-001` | **Unread** (`read_at = null`) | Calamity flood mitigation Purok 5 |
| **Purok 6 Chair** | `purok6@stafelomina.local` | "New official resolution approved and published." | `RES-2025-003` | **Read** | Solar streetlights in Purok 6 |
| **Purok 7 Chair** | `purok7@stafelomina.local` | "New executive order deployed for boundary checkpoints." | `EO-2025-001` | **Unread** (`read_at = null`) | Boundary checkpoints in Purok 7 |

### 7.4 Deterministic UUID & Idempotent Upsert Pattern
To maintain strict cross-database compatibility (PostgreSQL validates UUID syntax, SQLite accepts text) and prevent duplicate notifications on repeated seeder runs:

```php
private function seedNotification(
    User $user,
    DocumentRecord $doc,
    string $message,
    bool $isRead,
    Carbon $createdAt
): void {
    // Generate deterministic 32-hex string formatted as 8-4-4-4-12 UUID
    $hash = md5("notification:{$user->id}:{$doc->id}:{$message}");
    $uuid = sprintf(
        '%s-%s-%s-%s-%s',
        substr($hash, 0, 8),
        substr($hash, 8, 4),
        substr($hash, 12, 4),
        substr($hash, 16, 4),
        substr($hash, 20, 12)
    );

    $readAt = $isRead ? $createdAt->copy()->addHours(3) : null;

    DB::table('notifications')->updateOrInsert(
        ['id' => $uuid],
        [
            'type' => DocumentWorkflowNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'document_record_id' => $doc->id,
                'document_number' => $doc->document_number,
                'title' => $doc->title,
                'message' => $message,
                'url' => route('documents.show', $doc, absolute: false),
            ]),
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]
    );
}
```

---

## 8. Complete Code Blueprint for `DemoDataSeeder.php`

The following blueprint provides the exact methods and data structures to integrate into `DemoDataSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Baseline Seeders have run
        $this->call([
            DocumentTaxonomySeeder::class,
            PurokSeeder::class,
            AccountSeeder::class,
        ]);

        // 2. Expand Document Taxonomy for R1
        $this->seedTaxonomyExpansions();

        // 3. Fetch Core Actors
        $captain = User::where('role', UserRole::Captain->value)->firstOrFail();
        $secretary = User::where('role', UserRole::Secretary->value)->where('is_active', true)->firstOrFail();

        // 4. Seed Official Documents & Files (Phase 1: Non-superseded)
        $this->seedOfficialDocumentsPhase1($captain, $secretary);

        // 5. Seed Official Documents & Files (Phase 2: Superseded Chaining)
        $this->seedOfficialDocumentsPhase2($captain, $secretary);

        // 6. Seed Audit Logs
        $this->seedDocumentAuditLogs($captain, $secretary);

        // 7. Seed In-App Notifications
        $this->seedNotifications($captain, $secretary);
    }

    /**
     * Add missing types, categories, and keyword rules required by R1.
     */
    private function seedTaxonomyExpansions(): void
    {
        DocumentType::updateOrCreate(
            ['code' => 'executive_order'],
            [
                'name' => 'Executive Order',
                'number_prefix' => 'EO',
                'description' => 'Executive orders and administrative directives issued by the Punong Barangay.',
                'is_active' => true,
            ]
        );

        $categories = [
            [
                'name' => 'Peace & Order',
                'slug' => 'peace-and-order',
                'description' => 'Barangay peace, public order, and neighborhood security initiatives.',
            ],
            [
                'name' => 'Disaster Risk Reduction',
                'slug' => 'disaster-risk-reduction',
                'description' => 'Disaster preparedness, flood mitigation, emergency response, and calamity management.',
            ],
        ];

        foreach ($categories as $cat) {
            DocumentCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'is_active' => true,
                ]
            );
        }

        $rules = [
            [
                'name' => 'Peace & Order Keywords',
                'category_slug' => 'peace-and-order',
                'keywords' => ['curfew', 'tanod', 'patrol', 'checkpoint', 'security', 'crime', 'peace and order'],
                'priority' => 25,
            ],
            [
                'name' => 'Disaster Risk Reduction Keywords',
                'category_slug' => 'disaster-risk-reduction',
                'keywords' => ['disaster', 'calamity', 'flood', 'typhoon', 'evacuation', 'bdrrmc', 'rescue'],
                'priority' => 15,
            ],
        ];

        foreach ($rules as $rule) {
            $category = DocumentCategory::where('slug', $rule['category_slug'])->first();
            if ($category) {
                CategorizationRule::updateOrCreate(
                    ['name' => $rule['name']],
                    [
                        'document_category_id' => $category->id,
                        'document_type_id' => null,
                        'keywords' => $rule['keywords'],
                        'priority' => $rule['priority'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Seed Phase 1: All documents except superseded records.
     */
    private function seedOfficialDocumentsPhase1(User $captain, User $secretary): void
    {
        $documents = $this->getPhase1DocumentsData();

        foreach ($documents as $data) {
            $type = DocumentType::where('code', $data['type_code'])->firstOrFail();
            $category = DocumentCategory::where('slug', $data['category_slug'])->firstOrFail();

            $existing = DocumentRecord::query()
                ->where('document_type_id', $type->id)
                ->where('document_year', $data['year'])
                ->where('document_number', $data['number'])
                ->first();

            $token = $existing?->verification_token ?? Str::random(48);

            $draftPdfPath = in_array($data['status'], [
                DocumentStatus::Draft,
                DocumentStatus::PendingApproval,
                DocumentStatus::NeedsRevision,
            ], true) ? "document-drafts/{$data['number']}-draft.pdf" : null;

            $record = DocumentRecord::updateOrCreate(
                [
                    'document_type_id' => $type->id,
                    'document_year' => $data['year'],
                    'document_number' => $data['number'],
                ],
                [
                    'document_category_id' => $category->id,
                    'created_by_id' => $secretary->id,
                    'title' => $data['title'],
                    'document_date' => $data['date'],
                    'subject' => $data['subject'],
                    'keywords' => $data['keywords'],
                    'status' => $data['status'],
                    'verification_token' => $token,
                    'draft_pdf_path' => $draftPdfPath,
                    'draft_pdf_original_name' => $draftPdfPath ? "{$data['number']}-draft.pdf" : null,
                    'draft_pdf_mime_type' => $draftPdfPath ? 'application/pdf' : null,
                    'draft_pdf_size' => $draftPdfPath ? 1024 : null,
                    'draft_pdf_uploaded_at' => $draftPdfPath ? Carbon::parse($data['date'])->addHours(8) : null,
                    'draft_pdf_uploaded_by_id' => $draftPdfPath ? $secretary->id : null,
                    'submitted_at' => $data['submitted_at'] ?? null,
                    'submitted_by_id' => isset($data['submitted_at']) ? $secretary->id : null,
                    'approved_at' => $data['approved_at'] ?? null,
                    'approved_by_id' => isset($data['approved_at']) ? $captain->id : null,
                    'return_comment' => $data['return_comment'] ?? null,
                    'returned_at' => $data['returned_at'] ?? null,
                    'returned_by_id' => isset($data['returned_at']) ? $captain->id : null,
                    'archived_at' => $data['archived_at'] ?? null,
                    'archived_by_id' => isset($data['archived_at']) ? $captain->id : null,
                    'archive_reason' => $data['archive_reason'] ?? null,
                    'revoked_at' => $data['revoked_at'] ?? null,
                    'revoked_by_id' => isset($data['revoked_at']) ? $captain->id : null,
                    'revoke_reason' => $data['revoke_reason'] ?? null,
                ]
            );

            // Generate physical files and version records
            $this->seedStorageForRecord($record, $captain, $secretary);
        }
    }

    /**
     * Seed Phase 2: Superseded documents linked to Phase 1 replacement documents.
     */
    private function seedOfficialDocumentsPhase2(User $captain, User $secretary): void
    {
        $supersededDocs = $this->getPhase2SupersededDocumentsData();

        foreach ($supersededDocs as $data) {
            $type = DocumentType::where('code', $data['type_code'])->firstOrFail();
            $category = DocumentCategory::where('slug', $data['category_slug'])->firstOrFail();
            $replacement = DocumentRecord::where('document_number', $data['replacement_number'])->firstOrFail();

            $existing = DocumentRecord::query()
                ->where('document_type_id', $type->id)
                ->where('document_year', $data['year'])
                ->where('document_number', $data['number'])
                ->first();

            $token = $existing?->verification_token ?? Str::random(48);

            $record = DocumentRecord::updateOrCreate(
                [
                    'document_type_id' => $type->id,
                    'document_year' => $data['year'],
                    'document_number' => $data['number'],
                ],
                [
                    'document_category_id' => $category->id,
                    'created_by_id' => $secretary->id,
                    'title' => $data['title'],
                    'document_date' => $data['date'],
                    'subject' => $data['subject'],
                    'keywords' => $data['keywords'],
                    'status' => DocumentStatus::Superseded,
                    'verification_token' => $token,
                    'submitted_at' => $data['submitted_at'],
                    'submitted_by_id' => $secretary->id,
                    'approved_at' => $data['approved_at'],
                    'approved_by_id' => $captain->id,
                    'superseded_at' => $data['superseded_at'],
                    'superseded_by_id' => $captain->id,
                    'supersede_reason' => $data['supersede_reason'],
                    'superseded_by_document_record_id' => $replacement->id,
                    'supersede_reference' => $data['supersede_reference'],
                ]
            );

            $this->seedStorageForRecord($record, $captain, $secretary);
        }
    }

    /**
     * Manage physical file generation and DocumentFileVersion records.
     */
    private function seedStorageForRecord(DocumentRecord $record, User $captain, User $secretary): void
    {
        $pdfBytes = $this->generateMinimalPdf($record->title, $record->document_number);

        // For Approved (Published, Archived, Revoked, Superseded) records:
        if (in_array($record->status, [
            DocumentStatus::Published,
            DocumentStatus::Archived,
            DocumentStatus::Revoked,
            DocumentStatus::Superseded,
        ], true)) {
            $officialPath = "document-officials/{$record->id}/versions/1.pdf";
            Storage::disk('local')->put($officialPath, $pdfBytes);

            DocumentFileVersion::updateOrCreate(
                [
                    'document_record_id' => $record->id,
                    'version_number' => 1,
                ],
                [
                    'file_path' => $officialPath,
                    'original_name' => "{$record->document_number}.pdf",
                    'mime_type' => 'application/pdf',
                    'size' => strlen($pdfBytes),
                    'is_current' => true,
                    'approved_at' => $record->approved_at ?? now(),
                    'approved_by_id' => $captain->id,
                ]
            );
        }

        // For Working (Draft, Pending Approval, Needs Revision) records:
        if (filled($record->draft_pdf_path)) {
            Storage::disk('local')->put($record->draft_pdf_path, $pdfBytes);
        }
    }

    /**
     * Seed historical audit logs for documents.
     */
    private function seedDocumentAuditLogs(User $captain, User $secretary): void
    {
        $documents = DocumentRecord::all();

        foreach ($documents as $doc) {
            // Submission log
            if ($doc->submitted_at) {
                AuditLog::firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_submitted',
                    ],
                    [
                        'actor_id' => $secretary->id,
                        'summary' => 'Document submitted for Captain approval.',
                        'context' => null,
                        'created_at' => $doc->submitted_at,
                        'updated_at' => $doc->submitted_at,
                    ]
                );
            }

            // Return log
            if ($doc->returned_at) {
                AuditLog::firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_returned',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document returned for revision.',
                        'context' => ['comment' => $doc->return_comment],
                        'created_at' => $doc->returned_at,
                        'updated_at' => $doc->returned_at,
                    ]
                );
            }

            // Approval log
            if ($doc->approved_at) {
                AuditLog::firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_approved',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document approved and published.',
                        'context' => ['version_number' => 1],
                        'created_at' => $doc->approved_at,
                        'updated_at' => $doc->approved_at,
                    ]
                );
            }

            // Archive log
            if ($doc->archived_at) {
                AuditLog::firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_archived',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document archived.',
                        'context' => ['reason' => $doc->archive_reason],
                        'created_at' => $doc->archived_at,
                        'updated_at' => $doc->archived_at,
                    ]
                );
            }

            // Revoke log
            if ($doc->revoked_at) {
                AuditLog::firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_revoked',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document revoked.',
                        'context' => ['reason' => $doc->revoke_reason],
                        'created_at' => $doc->revoked_at,
                        'updated_at' => $doc->revoked_at,
                    ]
                );
            }

            // Supersede log
            if ($doc->superseded_at) {
                AuditLog::firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_superseded',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document superseded.',
                        'context' => [
                            'reason' => $doc->supersede_reason,
                            'replacement_document_record_id' => $doc->superseded_by_document_record_id,
                        ],
                        'created_at' => $doc->superseded_at,
                        'updated_at' => $doc->superseded_at,
                    ]
                );
            }
        }
    }

    /**
     * Seed notifications for Captain, Secretary, and Purok Chairpersons.
     */
    private function seedNotifications(User $captain, User $secretary): void
    {
        // 1. Captain Notifications
        $pendingDocs = DocumentRecord::where('status', DocumentStatus::PendingApproval)->get();
        foreach ($pendingDocs as $doc) {
            $this->seedNotification($captain, $doc, 'Document awaiting approval.', false, $doc->submitted_at ?? now());
        }

        $publishedHistorical = DocumentRecord::where('status', DocumentStatus::Published)->take(2)->get();
        foreach ($publishedHistorical as $doc) {
            $this->seedNotification($captain, $doc, 'Document submitted for Captain approval.', true, $doc->submitted_at ?? now());
        }

        // 2. Secretary Notifications
        $returnedDocs = DocumentRecord::where('status', DocumentStatus::NeedsRevision)->get();
        foreach ($returnedDocs as $doc) {
            $this->seedNotification($secretary, $doc, 'Document returned for revision.', false, $doc->returned_at ?? now());
        }

        $publishedSecretary = DocumentRecord::where('status', DocumentStatus::Published)->latest('approved_at')->take(4)->get();
        $isFirst = true;
        foreach ($publishedSecretary as $doc) {
            $this->seedNotification($secretary, $doc, 'Document approved and published.', ! $isFirst, $doc->approved_at ?? now());
            $isFirst = false;
        }

        // 3. Purok Chairpersons Notifications
        for ($p = 1; $p <= 7; $p++) {
            $purokChair = User::where('email', "purok{$p}@stafelomina.local")->first();
            if (! $purokChair) {
                continue;
            }

            $featuredOrdinance = DocumentRecord::where('document_number', 'ORD-2026-001')->first();
            if ($featuredOrdinance) {
                $this->seedNotification(
                    $purokChair,
                    $featuredOrdinance,
                    'New published ordinance available for implementation.',
                    false, // Unread badge test
                    $featuredOrdinance->approved_at ?? now()
                );
            }

            $featuredResolution = DocumentRecord::where('status', DocumentStatus::Published)
                ->where('document_type_id', DocumentType::where('code', 'resolution')->value('id'))
                ->first();
            if ($featuredResolution) {
                $this->seedNotification(
                    $purokChair,
                    $featuredResolution,
                    'New official resolution approved and published.',
                    true, // Read test
                    $featuredResolution->approved_at ?? now()
                );
            }
        }
    }

    /**
     * Helper to upsert idempotent notification with valid UUID.
     */
    private function seedNotification(
        User $user,
        DocumentRecord $doc,
        string $message,
        bool $isRead,
        Carbon $createdAt
    ): void {
        $hash = md5("notif:{$user->id}:{$doc->id}:{$message}");
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );

        $readAt = $isRead ? $createdAt->copy()->addHours(2) : null;

        DB::table('notifications')->updateOrInsert(
            ['id' => $uuid],
            [
                'type' => DocumentWorkflowNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'document_record_id' => $doc->id,
                    'document_number' => $doc->document_number,
                    'title' => $doc->title,
                    'message' => $message,
                    'url' => route('documents.show', $doc, absolute: false),
                ]),
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]
        );
    }

    /**
     * Generates a valid standard 1-page PDF string.
     */
    private function generateMinimalPdf(string $title, string $docNumber): string
    {
        $content = sprintf(
            "BT\n/F1 14 Tf\n72 720 Td\n(Republic of the Philippines) Tj\n" .
            "0 -20 Td\n(Province of Ilocos Norte - City of Laoag) Tj\n" .
            "0 -20 Td\n(BARANGAY STA. FELOMINA) Tj\n" .
            "0 -35 Td\n/F1 12 Tf\n(Official Record: %s) Tj\n" .
            "0 -20 Td\n(Title: %s) Tj\n" .
            "0 -30 Td\n(This is an official digitized barangay document record.) Tj\nET",
            addcslashes($docNumber, "()\\"),
            addcslashes(substr($title, 0, 60), "()\\")
        );

        $contentLength = strlen($content);

        return "%PDF-1.4\n" .
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n" .
            "4 0 obj\n<< /Length " . $contentLength . " >>\nstream\n" .
            $content . "\nendstream\nendobj\n" .
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n" .
            "xref\n0 6\n" .
            "0000000000 65535 f \n" .
            "0000000009 00000 n \n" .
            "0000000058 00000 n \n" .
            "0000000115 00000 n \n" .
            "0000000252 00000 n \n" .
            "0000000320 00000 n \n" .
            "trailer\n<< /Size 6 /Root 1 0 R >>\n" .
            "startxref\n390\n%%EOF";
    }
}
```

---

## 9. Verification & Quality Assurance Method

To verify the documents, lifecycle states, physical storage files, audit trails, and notifications:

### 9.1 Artisan Seeding Execution
```powershell
php artisan db:seed --class=DemoDataSeeder
```
*Verification criteria:*
- Completes with 0 errors or exceptions.
- `document_records` count equals 32.
- `document_file_versions` count equals 24.
- All 7 statuses are represented in `document_records`:
  `select status, count(*) from document_records group by status;`
- Physical files exist on disk:
  - `storage/app/private/document-officials/{id}/versions/1.pdf` exists for each of the 24 approved records.
  - `storage/app/private/document-drafts/*.pdf` exists for the 8 draft/review records.

### 9.2 Strict Idempotency Check
```powershell
php artisan db:seed --class=DemoDataSeeder
```
*Verification criteria:*
- Zero duplicate key violations.
- Document record count remains strictly 32.
- File version count remains strictly 24.
- Audit logs and notifications counts remain unchanged.

### 9.3 Pest Automated Feature Assertions
Include the following assertions in `tests/Feature/DemoDataSeederTest.php`:
```php
test('demo data seeder populates complete official document catalog and storage files', function () {
    $this->artisan('db:seed', ['--class' => 'DemoDataSeeder'])->assertSuccessful();

    // Verify Document Counts
    expect(DocumentRecord::count())->toBe(32)
        ->and(DocumentRecord::where('document_year', 2024)->count())->toBe(7)
        ->and(DocumentRecord::where('document_year', 2025)->count())->toBe(9)
        ->and(DocumentRecord::where('document_year', 2026)->count())->toBe(16);

    // Verify all 7 Lifecycle States
    foreach (DocumentStatus::cases() as $status) {
        expect(DocumentRecord::where('status', $status)->exists())->toBeTrue();
    }

    // Verify Superseded Chaining
    $supersededOrd = DocumentRecord::where('document_number', 'ORD-2024-001')->firstOrFail();
    $replacementOrd = DocumentRecord::where('document_number', 'ORD-2026-001')->firstOrFail();
    expect($supersededOrd->superseded_by_document_record_id)->toBe($replacementOrd->id)
        ->and($supersededOrd->status)->toBe(DocumentStatus::Superseded)
        ->and($replacementOrd->status)->toBe(DocumentStatus::Published);

    // Verify Physical File Storage
    $published = DocumentRecord::where('status', DocumentStatus::Published)->firstOrFail();
    $fileVersion = $published->currentFileVersion();
    expect($fileVersion)->not->toBeNull()
        ->and(Storage::disk('local')->exists($fileVersion->file_path))->toBeTrue();

    // Verify Public Verification PDF Stream
    $this->get(route('verification.document', $published->verification_token))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    // Verify Audit Logs Exist
    expect(AuditLog::where('action', 'document_approved')->count())->toBeGreaterThanOrEqual(24)
        ->and(AuditLog::where('action', 'document_submitted')->count())->toBeGreaterThanOrEqual(29);

    // Verify Notifications Exist
    $captain = User::where('role', UserRole::Captain->value)->firstOrFail();
    expect($captain->unreadNotifications()->count())->toBeGreaterThanOrEqual(3);

    // Idempotency: Run Again
    $this->artisan('db:seed', ['--class' => 'DemoDataSeeder'])->assertSuccessful();
    expect(DocumentRecord::count())->toBe(32);
});
```
