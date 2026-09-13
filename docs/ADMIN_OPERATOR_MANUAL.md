# Administrative Guide & Operator Manual
## Barangay Sta. Felomina Document Archiving, Verification, and Resident Profiling System

---

## 1. System Overview & Executive Purpose

The Barangay Sta. Felomina Document Archiving, Verification, and Resident Profiling System is an enterprise-grade digital governance platform built with Laravel 11, Livewire 3, Flux UI, and PostgreSQL 16. Designed specifically for the administrative operations of Barangay Sta. Felomina, the system streamlines document lifecycle management, provides cryptographic, tamper-evident QR verification for public records, and enforces structured resident profiling across all seven Puroks.

### Key Objectives
- **Digital Archival Accompanying Paper Governance:** Maintain an immutable digital mirror of physical legislative enactments, executive orders, resolutions, and certifications while preserving physical paper workflows.
- **Cryptographic Verification:** Provide unauthenticated public verification via unique deterministic QR codes rendered as scalable vector graphics (SVG) directing citizens to `/verify/{token}`.
- **Decentralized Resident Profiling:** Enable Purok Chairpersons across Puroks 1 through 7 to register households and monitor resident demographics under strict data isolation boundaries.
- **Accountability & Auditability:** Guarantee non-repudiation by logging all state changes, approvals, returns, revocations, and overrides into an append-only audit trail.

---

## 2. Role & Responsibility Matrix

The system enforces strict role-based access control (RBAC) partitioned across three distinct administrative tiers:

| Administrative Role | Primary Responsibilities | Permitted Modules & Actions | Security Boundaries |
|---|---|---|---|
| **Barangay Captain** | Chief Executive & Final Approver | Full executive dashboard, document approval queue, document archiving/revocation/superseding, staff account administration, rule management, and system-wide audit logs. | Unrestricted administrative access across all modules; sole authority to transition documents to Published, Revoked, Archived, or Superseded. |
| **Barangay Secretary** | Chief Records & Administrative Officer | Document registration, rule-based category assignment, draft PDF uploads, QR code generation/export, document return handling, and official report printing. | Unrestricted document ingestion and report generation; restricted from approving documents or modifying staff accounts. |
| **Purok Chairperson** (Puroks 1 to 7) | Local Demographic Custodian | Household registration, resident profiling, vital status tracking (Active, Transferred, Deceased, Inactive), and local Purok census reports. | Strictly scoped to their assigned Purok; zero visibility into other Puroks or legislative document repositories. |

---

## 3. Navigation Structure & Route Alignment

All authenticated staff interact with the system via standard named routes mapped in `routes/web.php`. Navigation menus automatically adapt based on the logged-in user's role:

- **Dashboard:** `/dashboard` (`route('dashboard')`) — Role-tailored landing page with key statistics, alerts, and operational queues.
- **Official Documents:** `/documents` (`route('documents.index')`) — Document registry searchable by type, category, year, and status.
- **Document Creation:** `/documents/create` (`route('documents.create')`) — Ingestion wizard for new official records.
- **Document Detail & Staging:** `/documents/{documentRecord}` (`route('documents.show')`) — Multi-tab inspection view containing metadata, draft uploads, version history, and QR vectors.
- **Approval Queue:** `/approvals` (`route('approvals.index')`) — Executive approval workspace reserved for the Barangay Captain.
- **Categorization Rules:** `/categorization-rules` (`route('categorization-rules.index')`) — Rule manager for automated document classification.
- **Resident Directory:** `/residents` (`route('residents.index')`) — Demographic directory with search, filter, and duplicate detection.
- **Household Management:** `/households` (`route('households.index')`) — Family unit registries organized by Purok.
- **Reports & Statistics:** `/reports` (`route('reports.index')`) — Analytic summaries for demographics and legislative output.
- **Printable Reports:** `/reports/print` (`route('reports.print')`) — Official printable letterhead format for physical distribution.
- **Staff Accounts:** `/staff-accounts` (`route('staff-accounts.index')`) — User provisioning and credential management (Captain only).
- **Audit Trail:** `/audit-logs` (`route('audit-logs.index')`) — Tamper-evident operational event history (Captain only).
- **Public Verification Show:** `/verify/{token}` (`route('verification.show')`) — Public unauthenticated document status verification.
- **Public Document Download:** `/verify/{token}/document` (`route('verification.document')`) — Public unauthenticated stream or download of published PDFs.

---

## 4. Official Document Ingestion & Archiving Workflow

The document archiving lifecycle models real-world barangay legislative and executive processes. Follow these standard procedures for recording documents:

### Step 1: Initial Ingestion & Metadata Registration
1. Navigate to `/documents/create` from the sidebar or click **Create Document** on `/documents`.
2. Select the **Document Type**:
   - `Resolution`: Formal expressions of opinion or legislative will by the Sangguniang Barangay.
   - `Ordinance`: Local legislative enactments prescribing permanent rules or penalties.
   - `Executive Order`: Directives issued by the Barangay Captain for administrative enforcement.
   - `Certification`: Official barangay clearances, residency certificates, or indigency records.
3. Enter the official **Document Number** following standard format conventions:
   - Format: `[TYPE]-[YEAR]-[SEQUENCE]` (e.g., `RES-2026-001`, `ORD-2026-002`, `EO-2026-001`).
   - The system checks uniqueness across `(document_type_id, document_year, document_number)` upon submission.
4. Input the **Title**, **Description**, and **Date Adopted/Enacted**.
5. Add relevant search tags separated by commas.

### Step 2: Automated Categorization Suggestions
- Upon typing the title and description, the built-in categorization engine evaluates keyword rules against configured categories:
  - *Health & Sanitation*
  - *Infrastructure & Public Works*
  - *Peace & Order / Public Safety*
  - *Education, Youth & Sports*
  - *Disaster Risk Reduction & Management (DRRM)*
- Suggested categories appear in the interface with confidence scores. The Secretary can accept the automated recommendation or manually select an alternate category.

### Step 3: Generating and Embedding Permanent QR Codes
1. Once created, the record is assigned a permanent 32-character alphanumeric `verification_token`.
2. On `/documents/{documentRecord}`, navigate to the **QR Code** tab.
3. Click **Download QR Code** (`/documents/{documentRecord}/qr-code`).
4. The system serves a crisp vector image generated as an SVG file (`[document-number]-qr.svg`) rendered by BaconQrCode.
5. The Secretary embeds this SVG vector onto the physical document footer before printing.
6. The printed document is routed for physical signatures by the Sangguniang Barangay members and the Barangay Captain.

### Step 4: Uploading the Signed Draft PDF
1. Scan the physically signed paper document into PDF format (maximum size 10MB).
2. On `/documents/{documentRecord}`, click **Upload Draft PDF**.
3. Select the scanned PDF. The file is uploaded to secure private storage at `storage/app/private/document-drafts/{hash}.pdf`.
4. Inspect the embedded PDF preview using `/documents/{documentRecord}/draft-pdf` to verify clarity, signatures, and page completeness.

### Step 5: Submitting for Executive Approval
1. Once the draft PDF is confirmed, click **Submit for Approval**.
2. The document transitions from `Draft` to `Pending Approval`.
3. An automated notification is routed to the Barangay Captain, and the item enters the queue at `/approvals`.

---

## 5. Document Lifecycle Management & State Transitions

The system rigorously enforces seven lifecycle states to prevent unvetted or outdated documents from appearing legitimate to the public:

```
                  ┌──────────────┐
                  │    Draft     │
                  └──────┬───────┘
                         │ Submit
                         ▼
        ┌──────────────────────────────────┐
        │         Pending Approval         │
        └───┬──────────────────────────┬───┘
            │                          │
     Return │ Mandatory                │ Approve
            │ Reason                   │
            ▼                          ▼
 ┌────────────────────┐       ┌─────────────────┐
 │   Needs Revision   │       │    Published    │
 └──────────┬─────────┘       └───┬─────┬─────┬─┘
            │ Resubmit            │     │     │
            └─────────►───────────┘     │     │
                 ┌──────────────────────┘     │
                 ▼ Archive (Mandatory Reason) │
        ┌─────────────────┐                   │
        │    Archived     │                   │
        └─────────────────┘                   ▼ Supersede (Mandatory Reason & Ref)
                 ┌────────────────────────────┴────────┐
                 │                                     ▼
                 │ Revoke (Mandatory Reason) ┌─────────────────┐
                 ▼                           │   Superseded    │
        ┌─────────────────┐                  └─────────────────┘
        │     Revoked     │
        └─────────────────┘
```

### Detailed State Specifications:

1. **Draft (`Draft`):**
   - The initial authoring state. Only visible to the Secretary and Captain.
   - Metadata, tags, and draft PDF files can be modified or replaced freely.

2. **Pending Approval (`Pending Approval`):**
   - The document is locked from routine editing and awaits executive review in the Barangay Captain's approvals queue (`/approvals`).
   - The Captain inspects the original draft PDF, verifies legislative tags, and checks adoption dates.

3. **Needs Revision (`Needs Revision`):**
   - Triggered when the Captain determines the draft is incomplete, contains clerical errors, or lacks supporting signatures.
   - **Mandatory Return Reason:** The Captain must provide an explicit `return comment` before returning the document. The system validates that this return comment is non-empty, records the text into the `return_comment` field, and creates an `audit` trail entry (`document_returned`) containing the actor, timestamp, and return comment.
   - The Secretary receives a real-time notification, updates the draft or metadata, and resubmits to `Pending Approval`.

4. **Published (`Published`):**
   - Triggered when the Captain executes **Approve & Publish**.
   - The system atomically copies the draft PDF to immutable official storage at `storage/app/private/document-officials/{id}/versions/{version_number}.pdf`.
   - Generates a permanent `DocumentFileVersion` record linked to the approver.
   - An `audit` log entry (`document_approved`) records the publication.
   - The public verification route (`/verify/{token}`) immediately begins reflecting the **Verified** state.

5. **Archived (`Archived`):**
   - Applied to historic enactments, expired temporary permits, or superseded policies that have concluded their active operational period.
   - **Mandatory Reason:** The Captain must provide an explicit archival `reason`. The reason is permanently logged in the document record (`archive_reason`) and written to the system `audit` logs.
   - Archived documents remain verifiable by the public with an informative sky-blue status banner.

6. **Revoked (`Revoked`):**
   - Applied when a resolution, certification, or executive order is declared null and void, repealed by higher court/legislation, or found to have been issued fraudulently.
   - **Mandatory Reason:** The Captain must submit a detailed revocation `reason` explaining the legal or factual justification.
   - The reason is recorded in `revoke_reason` and logged in the immutable `audit` trail.
   - When scanned by the public, `/verify/{token}` displays a prominent crimson-red warning banner advising the citizen that the document has been revoked.

7. **Superseded (`Superseded`):**
   - Applied when a new ordinance or resolution directly replaces an existing policy.
   - **Mandatory Reason & Replacement:** The Captain must select the active replacement Published document or enter a formal legislative replacement reference, accompanied by an explicit `supersedeReason`.
   - The system establishes a bidirectional link (`superseded_by_document_id`), records the change in `audit` logs, and displays a purple notification banner on `/verify/{token}` with a direct hyperlink to the newer enactment.

---

## 6. Public QR Code Verification Portal

The verification portal is completely unauthenticated and accessible to any citizen, business, or government agency equipped with a smartphone camera or standard web browser.

### Public Security Contract
- **No Authentication Required:** Citizens never need an account or password to inspect public records.
- **Route Definitions:**
  - Route URL: `/verify/{token}` (`Route::get('verify/{token}', ...)->name('verification.show')`)
  - Document Stream URL: `/verify/{token}/document` (`Route::get('verify/{token}/document', ...)->name('verification.document')`)
- **Token Security:** Tokens are generated as 32-character random cryptographic strings, preventing sequential enumeration attacks.
- **Status Banners on `/verify/{token}`:**
  - **Published (Verified):** Emerald green badge displaying "Official Published Record", document title, category, enactment date, and signatory details. A **View Official Document** button allows streaming or downloading the verified PDF directly from `/verify/{token}/document`.
  - **Pending Approval:** Amber badge stating "Document Under Executive Review - Not Yet Legally Effective".
  - **Draft:** Slate badge warning "Draft Record - Staging Status Only".
  - **Archived:** Sky blue badge stating "Archived Official Record - Historical Reference Only", displaying the archival date and reason.
  - **Revoked:** Crimson red badge warning "REVOKED RECORD - INVALIDATED BY BARANGAY EXECUTIVE AUTHORITY", presenting the official revocation reason and date.
  - **Superseded:** Purple badge stating "SUPERSEDED BY NEWER ENACTMENT", showing the replacement document reference and providing a direct hyperlink to the current law.

---

## 7. Resident Profiling & Household Demographics

Resident profiling empowers Barangay Sta. Felomina to maintain an up-to-date demographic census for disaster planning, social welfare aid distribution, and voter coordination.

### Geographical Scoping (Puroks 1 to 7)
The system is partitioned into the seven recognized geopolitical subdivisions of Barangay Sta. Felomina:
1. **Purok 1 — Central** (Poblacion center, commercial zone, barangay hall)
2. **Purok 2 — Riverside** (Riverfront agricultural and residential community)
3. **Purok 3 — San Isidro** (Farming sector and community chapel)
4. **Purok 4 — Bagong Buhay** (High-density residential neighborhood)
5. **Purok 5 — Maharlika** (Highway corridor and transit zone)
6. **Purok 6 — Pag-asa** (Upland residential and livelihood zone)
7. **Purok 7 — Ilaya** (Eastern boundary agricultural zone)

Purok Chairpersons can only view, create, and update records for their specifically assigned Purok.

### Household Records
- Each family residence is assigned a unique household code (e.g., `HH-P1-001`).
- Households record the physical street address, socioeconomic classification, and designated head of household.
- Family members are attached to their respective household units.

### Resident Data Fields & Statuses
Every resident record tracks:
- Full Legal Name (First Name, Middle Name, Last Name, Suffix)
- Exact `birthdate` (used for automated age calculation and seniority brackets)
- Sex and Civil Status (Single, Married, Widowed, Separated)
- Contact Number and Address
- **Voter Registration Flag (`is_registered_voter`):** Boolean indicating registration in the local COMELEC precinct.
- **Vital Status (4 Canonical Statuses):**
  - `Active`: Currently residing and participating within the barangay.
  - `Transferred`: Relocated outside Barangay Sta. Felomina.
  - `Deceased`: Passed away; record retained for historical vital statistics.
  - `Inactive`: Temporarily displaced or non-responsive.

### Resident Duplicate Detection Engine
To maintain data integrity during house-to-house census surveys, the system incorporates an automated duplicate detection safeguard:
- **Detection Algorithm:** When a new resident is submitted on `/residents`, the system queries existing records matching:
  - Exact normalized full name (`normalized_full_name`)
  - Exact matching birthdate (`birthdate`)
  - Within the same Purok (`Purok` / `purok_id`)
- **Safety Interlock:** If one or more likely duplicate records exist and the submission is not flagged as a forced entry, creation is halted. The modal displays the matching resident names, birthdates, and assigned households.
- **Administrator Override:** If the operator confirms that the individual is a legitimate namesake (e.g., Senior and Junior sharing names or twin siblings), the chairperson can confirm the override. The system processes the creation and permanently logs a `duplicate_override` flag in the `audit` log.

---

## 8. Reports & Official Print Exports

The system provides flexible reporting capabilities at `/reports` with one-click official printing at `/reports/print`:

### 1. Demographic Summaries
- Real-time population distribution across Puroks 1 through 7.
- Age distribution pyramids (infants, school-age youth, voting-age adults, senior citizens).
- Voter registration percentages by Purok.
- Vital status breakdown (Active, Transferred, Deceased, Inactive).

### 2. Legislative & Executive Document Registries
- Comprehensive listing of ordinances, resolutions, certifications, and orders.
- Filtering by category (Health, Infrastructure, Peace & Order, Education, DRRM).
- Filtering by lifecycle state and fiscal year.

### 3. Official Print View (`/reports/print`)
- Clean, CSS-optimized print layout styled for physical printing.
- Embeds the official Republic of the Philippines, Province of Ilocos Norte, and Barangay Sta. Felomina letterheads.
- Includes official certification text and signature blocks for the Barangay Secretary (Certified Correct) and Barangay Captain (Noted & Approved).

---

## 9. Administrative Tools & System Audit Logs

### Staff Account Management (`/staff-accounts`)
Reserved exclusively for the Barangay Captain:
- Create new user accounts for incoming administrative secretaries and Purok chairpersons.
- Assign roles (`Captain`, `Secretary`, `PurokChairperson`) and Purok assignments (1–7).
- Issue temporary passwords for password resets.
- Deactivate former staff accounts upon administrative transition.
- **Safety Safeguard:** The system prevents the last remaining active Barangay Captain account from being deactivated or downgraded.

### System Audit Logs (`/audit-logs`)
Chronological, searchable registry of every operational action:
- **Actor Details:** User name, email, and assigned role.
- **Action Types:** `document_created`, `document_submitted`, `document_approved`, `document_returned`, `document_archived`, `document_revoked`, `document_superseded`, `resident_created`, `resident_updated`, `staff_account_created`, etc.
- **Context Metadata:** Full JSON payloads capturing exact comments, previous values, IP addresses, and user-agent strings.

---

## 10. Demonstration Data & Training Reference

For training new barangay personnel or conducting administrative handover rehearsals:
- System administrators can execute the idempotent seeder:
  ```bash
  php artisan db:seed --class=DemoDataSeeder
  ```
- This populates realistic documents across all document types, categories, and lifecycle states (2024–2026), along with full resident and household profiles for all Puroks 1 to 7.
