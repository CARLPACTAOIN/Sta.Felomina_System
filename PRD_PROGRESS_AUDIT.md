# PRD Progress Audit

## Purpose

This is the current evidence ledger for `PRD_V1.md`. It answers what is actually
implemented, what remains, and what evidence supports each claim. It is not a
roadmap, changelog, task diary, or substitute for the PRD.

**Audited:** 2026-09-12
**PRD:** `PRD_V1.md` (Capstone V1)
**Repository state:** Staff identity, Captain-managed staff accounts, persisted
Purok assignments, PRD-aligned frontend foundation, Draft document records, QR
SVG download, controlled Draft PDF upload, approval workflow, in-app document
notifications, audit events, controlled public approved-PDF access, and
resident/household CRUD with assigned-Purok isolation, rule-based document
categorization suggestions, metadata search/filter refinements, and
Captain-managed document archive/revoke/supersede lifecycle actions
**Audit basis:** routes, models, migrations, application views, configuration,
and automated tests present in the workspace. The audit describes the current
working tree and is not bound to a particular commit.

## Status Vocabulary

| Status | Meaning |
|---|---|
| `Verified` | Required behavior exists and relevant automated checks pass |
| `Partial` | Some required behavior exists, but acceptance criteria or evidence are incomplete |
| `Not started` | No substantive implementation evidence exists |
| `Blocked` | Work cannot proceed until a named external decision or dependency is resolved |
| `Approved out` | An explicit approved scope decision excludes the item |

Mockups, placeholders, generated scaffolding, and file presence alone do not
qualify as `Verified`.

## Current Baseline Evidence

- Document type, document category, and document record models now exist with a
  Draft status, permanent verification token, PostgreSQL-backed uniqueness for
  document type/year/number, QR SVG download, Draft PDF metadata, approval
  metadata, immutable approved PDF versions, and terminal lifecycle metadata for
  Archived/Revoked/Superseded official records.
- Users now have an enum-backed staff role, active state, optional Purok
  assignment, and Captain-managed account creation/update/activation/password
  reset workflow.
- Households and residents now have persisted records, generated codes,
  household grouping, resident statuses, duplicate warnings, and server-side
  assigned-Purok scoping for Purok Chairpersons.
- Categorization rules now have persisted priority, active state, target
  category, optional document type condition, and normalized keywords; document
  creation stores suggestion evidence and audit-backed manual overrides.
- Authenticated routes provide a responsive, role-aware UI foundation for the
  PRD modules. Unimplemented domains use explicit notices, empty states, and
  disabled actions rather than prototype records or simulated writes.
- The dashboard has Captain/Secretary and Purok Chair layouts but no query-backed
  domain counts yet.
- Existing tests cover starter authentication, security settings, profile
  settings, dashboard access, document draft creation, numbering uniqueness,
  QR download, Draft PDF upload/replace validation, submit/return/approve
  workflow rules, archive/revoke/supersede lifecycle actions, staff account
  administration, Purok assignment, resident and household CRUD, assigned-Purok
  isolation, duplicate warnings, categorization rule management, deterministic
  category suggestion and override audit events, document archive
  search/filtering, resident search refinement, in-app workflow notifications,
  audit entries, and public verification/PDF access rules.
- `docs/barangay_document_system_ui_prototype.html` is an interactive static
  prototype with sample data and prototype-only actions. It is design evidence,
  not implementation evidence.

## Module Audit

| PRD area | Status | Verified evidence | Material gap / next proof needed |
|---|---|---|---|
| Authentication and account management (§7-9, §35.1) | `Partial` | Public registration is disabled; Fortify login excludes inactive accounts; password reset/change, email verification, 2FA setup/recovery codes, passkeys, enum role state, settings UX, Captain-only staff account creation/update/activate/deactivate/password reset, one-time generated temporary password display, and focused tests exist; `StaffAccessTest`, `StaffAccountManagementTest`, and `AccountSeederTest` pass | PRD §9.4 Spatie persisted permission alignment remains approved for later unless dependency approval is granted |
| Roles, permissions, and purok isolation (§7-8, §21) | `Partial` | `puroks` table/model/factory/seeder exist; `users.purok_id` stores Purok Chair assignment; seeded Purok Chairpersons map to Purok 1-7; `UserRole`, `RequireRole`, `EnsureUserIsActive`, role-aware navigation, Captain-only route denial, Purok Chair document-route denial, and Purok-scoped resident/household access are tested | PRD §9.4 persisted permission alignment remains later; apply the same record-level discipline to future reports/search/export |
| Document records and numbering (§10-12, §17-18, §35.2) | `Partial` | `document_types`, `document_categories`, and `document_records` migrations/models exist; `DocumentStatus::Draft` is the default; `DocumentTaxonomySeeder` seeds default types/categories/rules; `pages::documents.create` creates Draft records without PDFs; database uniqueness enforces type/year/number; document archive supports metadata search plus type/category/status/year/sort filters; Published records can be archived/revoked/superseded by Captains with persisted reason/actor/timestamp; `DocumentRecordCreationTest`, `DocumentArchiveSearchTest`, `DocumentLifecycleTest`, and full suite pass | Metadata editing, project metadata, and broader record policies remain |
| QR verification (§13, §35.3) | `Verified` | `DocumentRecord` creates one 48-character permanent token; public `verification.show` route resolves `/verify/{token}` without auth; Draft/Pending/Needs Revision states remain Not Yet Verified with no PDF action; Published state shows Verified metadata and controlled PDF actions; Archived/Revoked/Superseded states keep functional verification URLs with public-safe lifecycle history; Revoked/Superseded remove public PDF actions; authenticated `documents.qr-code` downloads deterministic QR SVG for the existing verification URL; `PublicDocumentVerificationTest`, `DocumentQrCodeDownloadTest`, `PublishedDocumentVerificationTest`, `DocumentLifecycleTest`, and full suite pass | None for Capstone V1 QR verification |
| Files, versions, and corrections (§14-15, §26) | `Partial` | Draft PDF fields exist on `document_records`; `pages::documents.show` validates PDF-only uploads up to 10 MB, stores working files outside public routes, records uploader metadata, replaces previous Draft/Needs Revision working files, blocks Published replacement, copies approved PDFs to immutable `document_file_versions`, shows official PDF version history newest first, serves public PDFs for Published/Archived only, and supports Superseded replacement document/reference context; `DocumentDraftPdfUploadTest`, `DocumentApprovalWorkflowTest`, `PublishedDocumentVerificationTest`, and `DocumentLifecycleTest` pass | Report/export integration and broader retention/backup operations remain separate PRD areas |
| Approval workflow (§12, §24, §34.3, §35.4) | `Partial` | `document_records` has submitted/approved/returned and archive/revoke/supersede metadata; `DocumentRecord` implements submit, return, approve, archive, revoke, and supersede transitions; `pages::documents.show` exposes state-aware Submit/Return/Approve actions plus Captain-only lifecycle final actions; Captain-only return/approve/lifecycle and Purok Chair denial are tested; approval and lifecycle actions preserve `verification_token`, create audit logs, and approval sends database notifications; `DocumentApprovalWorkflowTest` and `DocumentLifecycleTest` pass | Broader approval dashboard interactions and escalation rules beyond the current PRD slice remain |
| Rule-based categorization (§16, §35.5) | `Partial` | `categorization_rules` model/table/factory exists with target category, optional document type condition, normalized keywords, priority, and active state; Captain/Secretary manage rules through `pages::categorization-rules.index`; document creation deterministically evaluates active rules by priority, shows suggestion reason/matched terms, stores suggestion fields, allows manual override, and writes `document_category_suggested` / `document_category_overridden` audit events; `CategorizationRuleManagementTest` and `DocumentCategorySuggestionTest` pass | Optional tags and report/export use remain |
| Residents and households (§19-21, §35.6) | `Verified` | `households` and `residents` migrations/models/factories exist; `ResidentStatus` covers Active/Transferred/Deceased/Inactive; `pages::households.index` and `pages::residents.index` provide list/search/create/update/status/voter workflows; Purok Chair actions force assigned `purok_id`; duplicate warning requires explicit override; resident search includes household code/contact/full-name matching; `HouseholdManagementTest` and `ResidentManagementTest` pass | Dashboard/report integration, import/export, and richer resident history remain separate PRD areas |
| Dashboards (§22) | `Verified` | `DashboardMetricsService` and `DashboardController` compute live query-backed metrics; Captain/Secretary see total documents, pending approvals, published documents, total residents, category distribution, purok resident distribution, and recent audit activity; Purok Chairperson is strictly scoped to assigned purok (residents, active, households, 7-day updates) with zero document exposure; `DashboardTest` passes | None for Capstone V1 operational dashboards |
| Search, reports, and PDF export (§18, §23, §35.7) | `Verified` | Document archive metadata search/filter/sort is backed by `DocumentRecordController` and covered by `DocumentArchiveSearchTest`; resident search/filter refinements are covered by `ResidentManagementTest`; `ReportEngine` provides unified query filtering and metrics calculation; `pages::reports.index` provides interactive Livewire reporting with real-time filters and summary stats; `ReportPrintController` renders official printable layout (`reports.print`) with letterhead, criteria, table, and signature blocks; print-to-PDF is supported via `@media print` and `auto_print=1`; Purok Chair document report access is strictly blocked (403); `ReportTest` passes | None for Capstone V1 reports and PDF export |
| In-app notifications (§24) | `Partial` | Laravel database notifications table exists; submit notifies active Captains; return/approve notify the document creator; notifications page lists authenticated user database notifications; workflow notification counts are covered in `DocumentApprovalWorkflowTest` | Read-state actions and non-document notification sources remain |
| Audit logs (§25, §35.8) | `Partial` | `audit_logs` table/model exist; submit/return/approve/archive/revoke/supersede, category suggested/overridden, categorization rule create/update/activate/deactivate, staff account create/update/role-change/activate/deactivate/password-reset, household create/update, and resident create/update/status-change create actor/action/entity/context entries; Captain-only audit page lists recent immutable entries; workflow audit creation is covered in `DocumentApprovalWorkflowTest`, `DocumentLifecycleTest`, `DocumentCategorySuggestionTest`, `CategorizationRuleManagementTest`, `StaffAccountManagementTest`, `HouseholdManagementTest`, and `ResidentManagementTest` | Broader audit filtering/export remain |
| Backup, recovery, and migration support (§27-28) | `Verified` | `BackupService.php` and `PostgreSqlBackupProvider.php` provide automated database dumps (PostgreSQL & SQLite) and private storage archiving into timestamped ZIP files with SHA-256 manifest verification; Artisan commands `backup:create` and `backup:restore` enforce connection checks and interactive confirmation / `--force` flags; cross-platform automation scripts (`scripts/backup.sh`, `scripts/restore.sh`, `backup.bat`, `restore.bat`) and Dockerfile `postgresql-client` integration exist; `BackupRestoreTest` passes (19/19 passed, 77 assertions) | None for Capstone V1 backup and disaster recovery |
| Training, turnover, deployment docs (§29-30, §36) | `Verified` | Comprehensive, client-ready markdown manuals exist in `docs/`: `ADMIN_OPERATOR_MANUAL.md` (21 KB, role-based workflows for Captain, Secretary, Purok Chair), `BACKUP_AND_RESTORE.md` (14 KB, scheduling, commands, DR runbooks), and `DEPLOYMENT_GUIDE.md` (14 KB, Docker Compose, bare-metal, troubleshooting); `DocumentationIntegrityTest` passes (18/18 passed, 94 assertions, 0 placeholder tokens) | None for Capstone V1 turnover documentation |
| Non-functional requirements (§31) | `Partial` | Framework security primitives plus a responsive shared application/auth/settings design system, dark mode, semantic labels, focus states, loading states, and compiled assets | Domain authorization, upload safety, integrity constraints, pagination/performance, real-browser compatibility/accessibility review, and deployment availability remain unverified |

## Critical Findings

1. **Role-level routing is not record-level authorization.** The staff role and
   active-account boundaries protect foundation routes, while resident and
   household records now have tested assigned-Purok scoping and document
   lifecycle actions have Captain-only proof. Future reports and exports still
   need equivalent record-level proof.
2. **Resident isolation is now testable and covered.** Purok Chair accounts point
   to Purok records, and resident/household actions force or filter by assigned
   Purok server-side.
3. **The static prototype must not inflate progress.** It usefully expresses the
   desired interface, but its sample records and button actions are not backed by
   application routes, persistence, authorization, or tests.
4. **The remaining PRD core risk is cross-module integrity.** QR permanence,
   first approval rights, and first published-file preservation are now covered
   by tests, and categorization suggestions/overrides plus terminal document
   lifecycle states now have vertical proof, but report/export scoping still
   needs workflow proof.

## Recommended Delivery Order

Dependencies favor this sequence; it is not a promise of scope or schedule:

1. Role-scoped dashboards, reports, PDF export, demo data, and turnover operations.
2. Backup/restore, deployment, and turnover documentation.
3. Final PRD acceptance audit after every required criterion is verified or approved out.

## Audit Maintenance

Update a row only when its meaning changes. Cite concrete paths and tests in the
evidence cell once work exists. Mark a module `Verified` only when all applicable
PRD acceptance criteria are implemented and the relevant tests pass. Regressions
must lower the status immediately. Record active execution details in
`HANDOFF.md`, not here.
