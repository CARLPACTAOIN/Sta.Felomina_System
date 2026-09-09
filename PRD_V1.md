# PRD_V1 — Web-Based Barangay Document Archiving, Verification, and Resident Profiling System

**Project Type:** Capstone System  
**Primary Deployment Context:** Barangay Sta. Felomina, Makilala, Cotabato  
**Target Final Completion:** October 2026 (exact date to be confirmed by client)  
**Document Status:** Authoritative Product Requirements Document (PRD)  
**Purpose:** Product truth and long-term context for human developers and AI coding agents.

---

## 1. Document Purpose

This PRD defines the intended behavior, scope, rules, workflows, roles, data requirements, and acceptance criteria of the system.

It is specifically written to serve as a **source of truth for development agents**. An agent implementing the system must use this document to understand:

- what problem the system solves;
- who the users are;
- what each user is allowed to do;
- how documents move through the system;
- how QR verification works;
- how rule-based categorization works;
- how resident profiling works;
- what is included in the capstone scope;
- what is explicitly out of scope;
- what business rules must never be violated.

If implementation code, UI ideas, old diagrams, or earlier notes conflict with this PRD, this PRD takes precedence unless it is explicitly superseded by a newer approved PRD version.

---

# 2. Product Summary

The system is a web-based barangay information system focused on:

1. digital archiving and retrieval of official barangay documents;
2. QR-based public document verification;
3. rule-based document categorization;
4. controlled document approval and publication;
5. resident profiling by purok;
6. searchable records and reporting;
7. accountability through audit logs;
8. simple administrative account management.

The system replaces fragmented manual filing practices with a centralized digital workflow while preserving the barangay's existing real-world document preparation and approval practices.

The system is **not intended to replace Microsoft Word or become a full legislative document authoring suite**. Official document content may continue to be prepared using the barangay's existing office tools. The system manages the official record, metadata, approval state, QR verification identity, final file, and archival history.

---

# 3. Product Problem

The current barangay process relies heavily on paper files, logbooks, filing cabinets, and manual retrieval.

This creates the following problems:

- documents are difficult and slow to retrieve;
- documents may be misplaced, damaged, duplicated, or lost;
- residents and officials have no convenient way to verify whether a printed document is authentic;
- document categorization is inconsistent;
- there is no centralized digital record of approvals and changes;
- resident information may need to be repeatedly collected;
- reporting requires manual consolidation.

The system must improve record organization, retrieval, verification, accountability, and access without unnecessarily overcomplicating barangay workflows.

---

# 4. Product Goals

## 4.1 Primary Goals

The system shall:

1. centralize official barangay document records;
2. provide reliable metadata-based search and filtering;
3. assign each document a permanent QR verification identity;
4. allow the public to verify published documents through QR scanning without logging in;
5. support a controlled Secretary-to-Captain approval workflow;
6. automatically suggest document categories using configurable rules;
7. preserve approved document history instead of silently overwriting official records;
8. allow Purok Chairpersons to maintain resident records for their assigned purok;
9. provide simple operational dashboards and reports;
10. provide accountability through automatic audit logs.

## 4.2 Secondary Goals

The system should:

- be simple enough for non-technical barangay users;
- work on modern desktop and mobile browsers;
- minimize repeated manual encoding;
- prevent accidental duplicate records where practical;
- be maintainable and modular;
- allow future expansion without redesigning the core system.

---

# 5. Non-Goals

The following are explicitly **not goals for Capstone V1**:

- full Microsoft Word-like document editing;
- advanced OCR of scanned PDFs;
- full-text search inside scanned documents;
- AI-based categorization;
- SMS notifications;
- email notification infrastructure;
- biometric authentication;
- public account registration;
- resident self-service accounts;
- public browsing of all barangay documents;
- blockchain-based verification;
- bulk historical encoding performed by the developer;
- complex multi-stage legislative voting workflows;
- unlimited post-capstone feature development;
- advanced enterprise records-management compliance features beyond the capstone scope.

---

# 6. Scope

## 6.1 Official Document Types

The document module shall support at least:

- Resolution
- Ordinance
- Certificate
- Memorandum
- Correspondence Record
- Meeting Minutes

The architecture should allow new document types to be added later without major redesign.

## 6.2 Resident Profiling

Resident profiling is part of the required system.

Purok Chairpersons manage residents belonging to their assigned purok. Barangay-wide authorized users may view residents across all puroks according to role permissions.

---

# 7. User Roles

The system shall use the following roles.

## 7.1 Barangay Captain

The Barangay Captain is the highest administrative role in Capstone V1.

The Captain may:

- log in;
- view the full dashboard;
- view all document records;
- review pending documents;
- approve documents;
- return documents for revision;
- add review comments;
- edit document metadata when necessary;
- archive, revoke, or supersede published documents;
- manage staff accounts;
- activate/deactivate staff accounts;
- reset staff passwords;
- manage categorization rules;
- view all resident records;
- view reports;
- view audit logs;
- initiate/download allowed backups if a backup UI is provided.

The Captain is the **final digital approver** of official documents.

## 7.2 Barangay Secretary

The Secretary is the primary document operations user.

The Secretary may:

- log in;
- create document records;
- edit Draft and Needs Revision records;
- enter document metadata;
- generate/download the permanent QR associated with a document record;
- upload the final PDF;
- submit documents for Captain approval;
- view Captain comments;
- resubmit returned documents;
- manage document metadata according to status rules;
- search and retrieve document records;
- manage categorization rules if authorized;
- manually override suggested categories;
- view residents across the barangay;
- view reports;
- view relevant audit history.

The Secretary may **not** self-approve documents.

## 7.3 Purok Chairperson

The Purok Chairperson is a restricted authenticated user.

The Purok Chairperson may:

- log in;
- view residents in the assigned purok only;
- create resident records for the assigned purok;
- edit resident records for the assigned purok;
- update household information for the assigned purok;
- search residents in the assigned purok;
- change resident status where allowed;
- view simple purok-level resident summaries.

The Purok Chairperson may **not**:

- manage barangay document records;
- approve documents;
- manage users;
- access residents from another purok;
- manage global categorization rules;
- access global audit logs.

## 7.4 Barangay Officials

Barangay Officials do **not** require authenticated accounts in Capstone V1.

They may verify and view published documents through QR scanning in the same way as the public.

Any real-world review or recommendation performed by officials before Captain approval remains outside the authenticated digital workflow in V1.

## 7.5 Resident / Public User

Residents and public users do not log in.

They may:

- scan a document QR code;
- open the public verification page;
- see the verification status;
- view approved public metadata;
- view the final published document when available;
- download the final published PDF.

There is no public searchable document portal in Capstone V1.

---

# 8. Permissions Matrix

| Capability | Captain | Secretary | Purok Chair | Official | Resident/Public |
|---|---:|---:|---:|---:|---:|
| Login | Yes | Yes | Yes | No | No |
| Create document record | Yes | Yes | No | No | No |
| Edit draft document | Yes | Yes | No | No | No |
| Generate/download document QR | Yes | Yes | No | No | No |
| Upload final PDF | Yes | Yes | No | No | No |
| Submit for approval | Yes | Yes | No | No | No |
| Approve document | Yes | No | No | No | No |
| Return for revision | Yes | No | No | No | No |
| Archive/revoke/supersede published document | Yes | Limited/No by default | No | No | No |
| View all document records | Yes | Yes | No | QR only | QR only |
| Manage categorization rules | Yes | Yes | No | No | No |
| Override suggested category | Yes | Yes | No | No | No |
| Manage staff accounts | Yes | No | No | No | No |
| View all residents | Yes | Yes | No | No | No |
| Manage residents in assigned purok | Optional | Optional | Yes | No | No |
| View reports | Yes | Yes | Purok-limited | No | No |
| View audit logs | Yes | Relevant records | No | No | No |
| QR verification | Yes | Yes | Yes | Yes | Yes |

---

# 9. Authentication and Account Management

## 9.1 Account Creation

There is no public registration.

Staff accounts are created internally by the Barangay Captain.

Supported authenticated roles:

- Captain
- Secretary
- Purok Chairperson

## 9.2 Account Lifecycle

The system shall support:

- account creation;
- temporary/default password assignment;
- password change;
- password reset by Captain;
- account activation;
- account deactivation.

A deactivated account cannot log in.

## 9.3 Password Rules

At minimum:

- passwords must be securely hashed;
- plaintext passwords must never be stored;
- password validation must enforce a reasonable minimum length;
- sensitive authentication actions must be audited.

---

# 10. Core Document Model

A document record exists independently from its final uploaded PDF.

Each document record must have a permanent identity.

## 10.1 Required Document Fields

At minimum:

- internal record ID;
- document type;
- document number;
- title;
- document date;
- subject/description;
- status;
- primary category;
- optional tags;
- permanent verification token;
- QR code reference;
- created by;
- created at;
- updated at.

## 10.2 Optional / Conditional Metadata

Depending on document type or context:

- keywords;
- approval date;
- approved by;
- project-related flag;
- project name;
- project location;
- approved budget;
- project start date;
- target completion date;
- remarks;
- attachment metadata.

These project-related fields must be optional and must not be required for documents unrelated to projects.

---

# 11. Document Numbering

Each document type shall support a unique numbering format.

Recommended default format:

- `RES-2026-001`
- `ORD-2026-001`
- `MEM-2026-001`
- `CERT-2026-001`
- `COR-2026-001`
- `MIN-2026-001`

Rules:

1. numbering is unique per document type and year;
2. the system should auto-suggest the next number;
3. authorized users may manually override the number when encoding historical records;
4. duplicate document numbers within the same applicable namespace are rejected;
5. changing a document number after a QR has been generated must require confirmation and must not change the permanent QR token.

---

# 12. Document Lifecycle

The authoritative V1 lifecycle is:

`Draft → Pending Approval → Published`

with alternate/supporting states:

- `Needs Revision`
- `Archived`
- `Revoked`
- `Superseded`

## 12.1 Draft

A document record has been created but is not yet approved.

The Secretary may:

- edit metadata;
- download the permanent QR;
- prepare the real document externally;
- upload/replace the working final PDF before submission;
- run categorization;
- submit for approval.

A Draft document is **not verified**.

## 12.2 Pending Approval

The Secretary has submitted the record to the Captain.

The Secretary should no longer freely alter approval-sensitive data while the document is pending.

The Captain may:

- inspect metadata;
- inspect the uploaded PDF;
- approve;
- return for revision with comments.

## 12.3 Needs Revision

The Captain rejected/returned the submission for correction.

The Captain must be able to leave comments.

The Secretary may:

- edit metadata;
- replace the PDF;
- update category;
- resubmit.

## 12.4 Published

The Captain approved the document.

On approval, the system shall automatically:

- store approval timestamp;
- store approving Captain;
- mark status as Published;
- activate verified public status;
- preserve the approved file version;
- make the public verification page show verified status;
- allow public viewing/downloading of the approved PDF;
- write audit events.

Published documents must not be silently overwritten.

## 12.5 Archived

The document remains historically valid but is no longer active/current.

The verification page shall still exist and clearly indicate that the document is archived.

## 12.6 Revoked

The document is no longer considered valid.

The QR shall continue resolving to the verification page, but the page must clearly show `Revoked`.

The system must not return a generic missing-page response for a revoked official record.

## 12.7 Superseded

A newer official version/replacement exists.

The old verification page remains accessible and indicates that the document has been superseded.

Where practical, the page may link to the current replacement document.

---

# 13. QR Verification Design

This is a core product feature.

## 13.1 Principle

A QR code belongs to a **document record**, not directly to a PDF file.

The QR identity is created before the final PDF exists.

This allows the Secretary to place the QR inside the document while preparing it externally.

## 13.2 QR Creation Flow

### Step 1 — Secretary Creates Draft Record

Before generating a QR, the Secretary enters:

- document type;
- document number;
- title;
- date;
- subject/description;
- other required metadata.

Example:

- Type: Resolution
- Number: RES-2026-014
- Title: Rehabilitation of Barangay Health Center

No final PDF is required yet.

### Step 2 — System Creates Permanent Verification Token

The system creates a cryptographically random or sufficiently unpredictable token such as:

`7FJ29KDX...`

Public route example:

`/verify/7FJ29KDX`

The token:

- is permanently attached to the document record;
- must not expose a simple sequential database ID;
- does not change when the QR image is re-downloaded;
- does not change when the document moves from Draft to Published;
- does not change when the file is replaced before approval.

### Step 3 — Draft Verification State

Before approval, scanning the QR shall open a public verification page showing a clear status such as:

> **Draft / Not Yet Verified**

The public page should show only safe minimal information, such as:

- document number;
- document type;
- title;
- verification status.

It must not falsely imply authenticity.

No official final PDF shall be publicly downloadable until the document is Published.

### Step 4 — Secretary Downloads QR

The Secretary can click:

**Download QR Code**

The downloadable QR image must encode the permanent verification URL.

Recommended QR presentation metadata may include the document number beneath or beside the QR when displayed in the system, but the QR image itself must primarily encode the verification URL.

### Step 5 — QR Is Placed in the Real Document

The Secretary prepares the official document using Word or the barangay's normal office tool and inserts the downloaded QR in a designated area such as:

**Document Verification**  
[QR CODE]  
RES-2026-014

The system does not need a full document editor.

### Step 6 — Final PDF Upload

After the document is prepared and appropriately signed/finalized, the Secretary uploads the final PDF containing the QR.

### Step 7 — Captain Approval

The Captain reviews the uploaded PDF and metadata.

If approved, the existing QR identity is activated as a verified public record.

The QR does **not** change.

## 13.3 Published Verification Page

For a Published document, the verification page shall show:

- prominent `Verified Official Barangay Document` status;
- document number;
- document type;
- title;
- primary category;
- approval date;
- issuing barangay;
- current status;
- public-safe metadata;
- View Document action;
- Download PDF action.

## 13.4 Re-Downloading QR

Users with permission may re-download the QR image at any time.

This is not a new QR identity.

The verification token and URL remain unchanged.

## 13.5 QR Rules

The following must always be true:

1. a QR cannot exist without a document record;
2. a QR token belongs to exactly one document record;
3. a token must never be reassigned to another document;
4. a Published document retains its verification history;
5. Archived/Revoked/Superseded documents retain functional verification URLs;
6. the public page must never label Draft or Pending Approval records as verified;
7. the QR must not point directly to a raw storage path.

---

# 14. File Management

## 14.1 Supported Official File

For Capstone V1, the official published document shall be stored as PDF.

Other temporary formats are not required.

## 14.2 File Validation

The system shall:

- validate file type;
- enforce configurable file size limits;
- store files outside publicly writable arbitrary paths;
- use controlled download/view routes where appropriate;
- preserve approved historical versions.

## 14.3 Approved File Integrity

Once a document is Published:

- the approved file must not be silently replaced;
- corrections must result in a new version or superseding record;
- audit history must show what happened.

---

# 15. Versioning and Corrections

Published official documents must preserve history.

If a Published document needs correction:

1. the original approved version remains preserved;
2. a replacement/new version is created;
3. the new version follows approval;
4. the old version becomes `Superseded` if appropriate;
5. the old QR continues to work;
6. the verification page indicates the current validity state.

The system must not rewrite historical truth by replacing a Published file with no trace.

---

# 16. Rule-Based Categorization

Rule-based categorization is a core requirement.

## 16.1 Purpose

The system automatically suggests a category based on document metadata.

It is intended to improve consistency and reduce manual sorting.

It is **not AI classification**.

## 16.2 Inputs Used for Categorization

V1 categorization shall analyze structured text fields such as:

- title;
- subject/description;
- manually entered keywords;
- document type.

The system does not require OCR or full scanned-PDF text extraction.

## 16.3 Category Model

Each document has:

- exactly one **Primary Category**;
- zero or more optional **Tags**.

Example:

Primary Category:
- Infrastructure

Tags:
- Budget
- Health Facility

This preserves clean reporting while allowing multi-topic documents.

## 16.4 Suggested Default Categories

Initial categories may include:

- Budget & Finance
- Infrastructure
- Health
- Peace and Order
- Education
- Social Services
- Environment
- Governance / Administration
- Disaster Risk Reduction
- Other

The final seed list may be adjusted during implementation without changing the underlying category model.

## 16.5 Rule Structure

A categorization rule may contain:

- rule name;
- target category;
- keywords;
- optional document type condition;
- optional priority;
- active/inactive status.

Example:

**Rule:** Infrastructure Keywords  
**Category:** Infrastructure  
**Keywords:** road, drainage, construction, rehabilitation, building

## 16.6 Rule Management

Captain and Secretary may:

- create rules;
- edit rules;
- activate/deactivate rules;
- update keywords;
- assign category;
- assign priority.

## 16.7 Categorization Behavior

The system shall:

1. evaluate applicable active rules;
2. produce a suggested Primary Category;
3. optionally show matched terms/reason;
4. allow Secretary/Captain to override the suggestion;
5. save the final selected category.

Automatic categorization must assist the user, not permanently lock an incorrect result.

---

# 17. Project-Related Metadata

The client may want to identify information such as:

- project budget;
- what is being built;
- where it will be built;
- target completion.

These should **not** be encoded only through categories.

If a document is project-related, optional structured fields should be available:

- Project Name
- Project Location
- Approved Budget
- Project Start Date
- Target Completion Date

This enables accurate filtering and reporting.

---

# 18. Search and Retrieval

Authenticated document users shall be able to search/filter records.

## 18.1 Searchable Fields

At minimum:

- document number;
- title;
- subject/description;
- keyword;
- year;
- document date;
- approval date;
- category;
- tag;
- document type;
- status.

Where project metadata exists:

- project name;
- project location.

## 18.2 Filtering

Filters should support combinations such as:

- Type + Year
- Category + Status
- Approval Date Range
- Year + Category
- Project-related only

## 18.3 Sorting

At minimum:

- newest first;
- oldest first;
- document number;
- title;
- approval date.

## 18.4 Public Search

There is no general public search/browse portal in Capstone V1.

Public access is through a valid QR verification URL.

---

# 19. Resident Profiling

Resident profiling is a separate operational module.

## 19.1 Resident Fields

At minimum:

- resident ID;
- full name;
- sex;
- birthdate;
- civil status;
- contact number;
- address;
- purok;
- household;
- voter status;
- resident status;
- created at;
- updated at.

Optional practical additions may include:

- middle name;
- suffix;
- birthplace;
- occupation;
- remarks.

Sensitive fields should only be added where genuinely needed by the barangay.

## 19.2 Resident Status

Use status changes rather than permanent deletion where practical.

Recommended statuses:

- Active
- Transferred
- Deceased
- Inactive

## 19.3 Duplicate Detection

Before a new resident is saved, the system should check for likely duplicates using combinations such as:

- similar/full name;
- birthdate;
- purok.

A possible duplicate warning does not automatically block saving because legitimate duplicate names can exist.

---

# 20. Household Model

Residents should be associated with a Household record.

## 20.1 Household Fields

At minimum:

- household ID/code;
- purok;
- address;
- household head;
- optional notes.

Multiple residents may belong to one household.

This prevents repetitive encoding of the same household-level address information.

---

# 21. Purok Data Isolation

Each Purok Chairperson must be assigned to a specific purok.

The system shall enforce server-side authorization so that a Purok Chairperson:

- can only view residents in the assigned purok;
- can only create residents under the assigned purok;
- can only edit residents under the assigned purok;
- cannot modify the purok assignment to bypass access restrictions.

UI hiding alone is not sufficient. Authorization must be enforced in backend policies/logic.

---

# 22. Dashboard

The dashboard should remain simple.

## 22.1 Captain / Secretary Dashboard

Show useful summaries such as:

- total documents;
- documents by category;
- documents by year;
- pending approvals;
- recently added documents;
- published documents;
- archived/revoked documents;
- total residents;
- residents by purok.

Simple charts/cards are sufficient.

## 22.2 Purok Chair Dashboard

Show only purok-relevant information such as:

- total residents;
- active residents;
- households;
- recent resident updates.

Avoid unnecessary analytics complexity.

---

# 23. Reports

Use one reusable filtering/report engine instead of building many unrelated report screens.

## 23.1 Document Reports

The system shall support filtered reports by:

- month;
- year;
- category;
- document type;
- status;
- date range.

## 23.2 Resident Reports

Where appropriate:

- residents by purok;
- resident status;
- household summaries.

## 23.3 Output

Capstone V1 shall support:

- printable report view;
- PDF export.

Excel export is optional and not required unless specifically requested later.

---

# 24. Notifications

Capstone V1 uses **in-app notifications only**.

Examples:

Captain:
- document awaiting approval.

Secretary:
- document approved;
- document returned for revision.

No SMS or email notification infrastructure is required.

---

# 25. Audit Logs

Audit logging is automatic.

Users do not manually create audit entries.

## 25.1 Document Events

Log events such as:

- document created;
- metadata edited;
- QR identity created;
- PDF uploaded/replaced;
- category suggested;
- category overridden;
- submitted for approval;
- returned for revision;
- approved;
- published;
- archived;
- revoked;
- superseded.

## 25.2 Account Events

Log:

- account created;
- role changed;
- account activated/deactivated;
- password reset initiated by authorized admin.

## 25.3 Resident Events

At minimum, log high-level changes such as:

- resident created;
- resident updated;
- resident status changed.

## 25.4 Audit Fields

Each audit record should include:

- actor;
- action;
- entity type;
- entity ID;
- timestamp;
- relevant summary/context.

Audit history should not be casually editable by normal users.

---

# 26. Deletion and Archiving

## 26.1 Drafts

Draft records may be permanently deleted by authorized users if they were never Published.

## 26.2 Published Documents

Published official documents should not be permanently deleted through normal UI.

Allowed lifecycle actions:

- Archive
- Revoke
- Supersede

## 26.3 Resident Records

Prefer status changes to permanent deletion.

---

# 27. Backup and Recovery

Backup and recovery is required at a practical capstone level.

Recommended behavior:

- backup database and uploaded official files;
- support a documented manual backup procedure;
- production deployment should use scheduled backups where hosting supports it;
- restore procedure must be documented and tested at least once before turnover.

A complex custom backup-management subsystem is not required.

The Barangay Captain becomes the designated responsible person after turnover, with training/documentation provided.

---

# 28. Data Migration

The system shall support manual encoding of historical records.

However:

**Bulk historical document encoding/upload by the developer is outside Capstone V1 scope unless separately agreed.**

For demonstration and testing, the delivered system may include representative seed/demo records.

---

# 29. Training and Turnover

Capstone delivery includes basic turnover support.

Recommended deliverables:

- working source code;
- database schema/migrations;
- setup instructions;
- basic user guide;
- administrator guide;
- backup/restore instructions;
- basic deployment notes;
- staff orientation/training session.

A full video training course is not required.

---

# 30. Capstone Maintenance Boundary

The intended support period after final turnover is approximately **1–2 months**.

Included maintenance:

- correction of defects in features already defined in this PRD;
- reasonable fixes required for final capstone presentation/defense;
- minor usability corrections.

Not included:

- entirely new modules;
- major scope changes;
- unrelated redesigns;
- indefinite hosting support;
- ongoing data encoding;
- long-term production operations.

If the panel requests a major new module, it must be treated as a scope change and evaluated separately.

---

# 31. Non-Functional Requirements

## 31.1 Usability

The system must:

- use clear labels;
- minimize technical jargon;
- provide obvious primary actions;
- provide validation messages;
- support responsive layouts;
- be usable by non-technical barangay staff.

## 31.2 Performance

Normal operations should respond without unnecessary delay.

Search and lists should use pagination when data volume requires it.

## 31.3 Security

At minimum:

- secure password hashing;
- server-side authorization;
- CSRF protection where applicable;
- input validation;
- safe file upload validation;
- restricted administrative routes;
- unpredictable public verification tokens;
- no direct exposure of sensitive internal identifiers where avoidable.

## 31.4 Data Integrity

The system must enforce:

- unique document numbering rules;
- one QR token per document;
- one document per QR token;
- valid status transitions;
- restricted approval rights;
- preserved published history;
- purok-level resident authorization.

## 31.5 Availability

The deployed system should be reasonably available during barangay operating needs subject to hosting/internet availability.

## 31.6 Compatibility

Support current versions of major modern browsers, including:

- Chrome;
- Edge;
- Firefox;
- modern mobile browsers.

## 31.7 Maintainability

Code should be modular and readable.

Business rules should not be scattered unnecessarily across UI code.

Authorization, document workflow, QR verification, categorization, and audit behavior should have clear service/domain boundaries.

---

# 32. Recommended Domain Entities

The exact database design may vary, but the domain should conceptually support:

- User
- Role
- Purok
- Household
- Resident
- Document
- DocumentType
- DocumentVersion
- DocumentCategory
- DocumentTag
- CategorizationRule
- DocumentApproval / Review
- VerificationToken / QR Identity
- Notification
- AuditLog

Optional:

- ProjectMetadata
- BackupRecord

The implementation agent may normalize or merge supporting tables where appropriate, but must preserve all product behaviors in this PRD.

---

# 33. Critical Business Rules

These rules are mandatory.

1. No public registration.
2. Secretary cannot approve own submitted document.
3. Captain is the final digital approver.
4. Every QR belongs to an existing document record.
5. QR identity is generated at Draft stage.
6. QR identity is permanent and does not change after approval.
7. Draft/Pending documents must never display as verified.
8. Public QR verification requires no login.
9. Public users cannot browse all documents.
10. Published documents cannot be silently overwritten.
11. Archived/Revoked/Superseded documents keep working verification URLs.
12. Published records are not permanently deleted in normal operation.
13. Rule-based categorization is metadata-driven, not OCR-driven.
14. Category suggestions may be manually overridden.
15. Purok Chairpersons can only access residents in their assigned purok.
16. Resident duplicate detection warns but does not blindly block.
17. Bulk historical encoding is not part of Capstone V1 delivery.
18. SMS/email notifications are out of scope.
19. Full document authoring is out of scope.
20. Audit events are generated automatically.

---

# 34. Key End-to-End Workflows

## 34.1 Document Creation and QR Preparation

1. Secretary logs in.
2. Secretary creates a Draft Document Record.
3. Secretary enters required metadata.
4. System validates document number uniqueness.
5. System creates permanent verification token.
6. System makes QR available for download.
7. QR verification page exists immediately with `Draft / Not Yet Verified`.
8. Secretary downloads QR.
9. Secretary prepares the official document externally.
10. Secretary inserts QR into the document.
11. Secretary finalizes/signs the document according to barangay procedure.
12. Secretary uploads final PDF.

## 34.2 Categorization

1. System evaluates active rules using metadata.
2. System suggests Primary Category.
3. System may show matched keywords.
4. Secretary accepts or overrides.
5. Final category is saved.
6. Override is auditable.

## 34.3 Approval

1. Secretary submits the record.
2. Status becomes Pending Approval.
3. Captain receives in-app notification.
4. Captain reviews metadata and PDF.
5. Captain either:
   - returns with comments, or
   - approves.
6. If returned:
   - status becomes Needs Revision;
   - Secretary edits and resubmits.
7. If approved:
   - status becomes Published;
   - approval details are stored;
   - same QR becomes verified;
   - final PDF becomes publicly viewable/downloadable.

## 34.4 Public Verification

1. Resident/Official scans QR.
2. Browser opens permanent verification URL.
3. System loads document by verification token.
4. System shows current validity status.
5. If Published:
   - show Verified status;
   - show public metadata;
   - allow view/download.
6. If Draft/Pending:
   - show Not Yet Verified;
   - do not provide official PDF.
7. If Archived/Revoked/Superseded:
   - show clear status;
   - preserve verification history.

## 34.5 Resident Profiling

1. Purok Chair logs in.
2. System restricts context to assigned purok.
3. User searches resident first.
4. System checks possible duplicates.
5. User creates/selects Household.
6. User creates resident.
7. User updates resident over time.
8. User changes status instead of deleting when appropriate.

---

# 35. Acceptance Criteria by Module

## 35.1 Authentication

- Authorized staff can log in.
- Public users cannot create accounts.
- Deactivated users cannot log in.
- Captain can reset staff password.
- Role restrictions are enforced server-side.

## 35.2 Document Records

- Secretary can create Draft record without PDF.
- Document number uniqueness is validated.
- Metadata can be edited while allowed by status.
- Search/filter works using required fields.

## 35.3 QR Verification

- Draft record can receive permanent QR.
- Re-downloading QR yields the same verification identity.
- Draft QR resolves to Not Yet Verified page.
- Published QR resolves to Verified page.
- Public user can download Published PDF.
- QR route never exposes a raw storage path as the verification identity.
- Revoked/Archived/Superseded records retain functional verification pages.

## 35.4 Approval

- Secretary can submit.
- Captain can approve.
- Captain can return with comment.
- Secretary cannot self-approve.
- Approval generates correct audit history.
- Approval changes verification status without changing QR.

## 35.5 Categorization

- Rules can be created/edited by authorized users.
- System suggests category from metadata.
- Manual override works.
- Final category is searchable/reportable.

## 35.6 Residents

- Purok Chair can create/update resident in assigned purok.
- Purok Chair cannot access another purok's residents.
- Duplicate warning works.
- Household grouping works.
- Captain/Secretary can view barangay-wide residents.

## 35.7 Reports

- Users can filter records.
- Users can print report.
- Users can export PDF report.

## 35.8 Audit

- Important document lifecycle actions are logged.
- Actor and timestamp are retained.
- Normal users cannot casually alter audit history.

---

# 36. Definition of Done — Capstone V1

The system is considered complete for the capstone when:

1. all required V1 modules in this PRD are implemented;
2. core workflows pass functional testing;
3. role permissions are enforced;
4. QR Draft-to-Published verification flow works end-to-end;
5. document approval works end-to-end;
6. rule-based categorization works with editable rules and manual override;
7. resident profiling and purok isolation work;
8. search, filters, reports, and PDF export work;
9. audit logs capture required events;
10. archive/revoke/supersede behavior works;
11. the system is responsive on common desktop/mobile browsers;
12. representative demo data exists;
13. setup/deployment instructions exist;
14. basic user/admin documentation exists;
15. backup/restore procedure is documented;
16. critical defects discovered before final defense are resolved.

The following do **not** prevent Capstone V1 completion unless separately added to scope:

- OCR;
- AI categorization;
- SMS/email;
- bulk historical data entry;
- public browsing portal;
- full document editor;
- advanced analytics;
- new modules requested after scope freeze.

---

# 37. Change Control

Because capstone requirements may still change before final defense, all significant new requests should be classified as one of:

### A. Clarification
Explains existing requirement without changing scope.

### B. Minor Change
Small adjustment that does not materially alter architecture or workload.

### C. Major Scope Change
New module, major workflow, major role, external integration, or substantial redesign.

Major Scope Changes must not silently become part of this PRD. They require explicit approval and should be added in a new PRD version or approved addendum.

---

# 38. Agent Development Rules

Any AI coding agent working on this project must follow these rules:

1. Read this PRD before implementing product behavior.
2. Do not invent new roles, modules, or workflows without approval.
3. Do not implement old manuscript behavior when it conflicts with this PRD.
4. Do not remove business rules for convenience.
5. Preserve QR permanence.
6. Preserve published document history.
7. Enforce authorization on the server side.
8. Prefer simple, maintainable implementations over feature-heavy solutions.
9. Do not add OCR, AI, SMS, email, or public registration unless scope changes.
10. Do not build a rich-text document editor.
11. Treat the final uploaded PDF as the official document artifact.
12. Use structured metadata for search/categorization.
13. Keep categorization explainable and manually overridable.
14. Keep public verification privacy-conscious.
15. Record important state-changing actions in the audit log.
16. When a requirement appears ambiguous, favor the business rules and workflows explicitly defined in this PRD rather than guessing.
17. If a proposed implementation would violate this PRD, stop and flag the conflict.

---

# 39. Source Basis

This PRD consolidates and reconciles:

- the original capstone manuscript for the Web-Based Barangay Resolution Archiving System with QR-Code Document Verification and Rule-Based Categorization;
- the client's first requirement questionnaire responses;
- the client's follow-up role/workflow clarification responses;
- approved process optimizations made before PRD generation;
- the final revised QR workflow approved by the project owner.

Where earlier requirements conflicted, the decisions in this PRD are authoritative for Capstone V1.

---

# 40. Final Product Principle

The system should be judged by whether it makes barangay document handling **more organized, verifiable, searchable, accountable, and easier to operate**.

It should not become complicated merely to appear technically impressive.

Capstone V1 should favor:

**clear workflow > feature count**  
**data integrity > convenience shortcuts**  
**simple verification > unnecessary complexity**  
**maintainability > overengineering**  
**actual barangay usability > flashy features**
