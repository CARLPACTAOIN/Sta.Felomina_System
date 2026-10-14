# PRD Progress Audit

## Purpose

This is the current evidence ledger for `PRD_V1.md`. It answers what is actually
implemented, what remains, and what evidence supports each claim. It is not a
roadmap, changelog, task diary, or substitute for the PRD.

**Audited:** 2026-09-22
**PRD:** `PRD_V1.md` (Capstone V1)
**Repository state:** Staff identity, Captain-managed staff accounts, persisted
Purok assignments, PRD-aligned frontend foundation, Draft document records, QR
SVG download, controlled Draft PDF upload, approval workflow, in-app document
notifications, audit events, controlled public approved-PDF access, and
resident/household CRUD with assigned-Purok isolation, rule-based document
categorization suggestions, metadata search/filter refinements, and
Captain-managed document archive/revoke/supersede lifecycle actions, Spatie
RBAC authorization, status-aware document metadata editing, and optional project
metadata, plus a Captain-only approval workbench with inline review actions
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
  document type/year/number, status-aware metadata editing, optional structured
  project metadata, QR SVG download, Draft PDF metadata, approval metadata,
  immutable approved PDF versions, and terminal lifecycle metadata for
  Archived/Revoked/Superseded official records.
- Users now have Spatie-backed persisted staff roles/permissions, active state,
  optional Purok assignment, and Captain-managed account
  creation/update/activation/password reset workflow.
- Households and residents now have persisted records, generated codes,
  household grouping, resident statuses, duplicate warnings, and server-side
  assigned-Purok scoping for Purok Chairpersons.
- Categorization rules now have persisted priority, active state, target
  category, optional document type condition, and normalized keywords; document
  creation and editable metadata automatically assign matching categories while
  preserving explainability and audit-backed manual exceptions.
- Authenticated routes provide a responsive, permission-aware UI foundation for the
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
  category suggestion and override audit events, document metadata edits,
  Captain approval workbench filtering and inline review actions,
  document archive search/filtering including project name/location, resident
  search refinement, in-app workflow notifications, audit entries, and public
  verification/PDF access rules.
- `docs/barangay_document_system_ui_prototype.html` is an interactive static
  prototype with sample data and prototype-only actions. It is design evidence,
  not implementation evidence.

## Module Audit

| PRD area | Status | Verified evidence | Material gap / next proof needed |
|---|---|---|---|
| Authentication and account management (§7-9, §35.1) | `Partial` | Public registration is disabled; Fortify login excludes inactive accounts; password reset/change, email verification, 2FA setup/recovery codes, passkeys, settings UX, Spatie-backed Captain/Secretary/Purok Chairperson roles, Captain-only staff account create/update/activate/deactivate/password reset, one-time generated temporary password display, and focused tests exist; `StaffAccessTest`, `StaffAccountManagementTest`, and `AccountSeederTest` pass | Profile/settings authorization remains ordinary authenticated-user scope |
| Roles, permissions, and purok isolation (§7-8, §21) | `Verified` | `spatie/laravel-permission` is installed with persisted roles/permissions; `RolePermissionSeeder` synchronizes least-privilege Captain, Secretary, and Purok Chairperson assignments for existing and new accounts; Captains retain document viewing/review/decision/lifecycle powers but are denied creation, metadata editing, Draft PDF upload, QR download, and submission; route, Livewire, dashboard, archive, and detail-page checks enforce the same contract; `users.purok_id` still enforces Purok Chair scoping; `AccountSeederTest`, `StaffAccessTest`, `DocumentRecordPolicyTest`, focused workflow tests, and full suite pass | None for Capstone V1 RBAC/purok isolation |
| Document records and numbering (§10-12, §17-18, §35.2) | `Verified` | `document_types`, `document_categories`, and `document_records` migrations/models exist; `DocumentStatus::Draft` is the default; `DocumentTaxonomySeeder` seeds default types/categories/rules; Secretary-only preparation is enforced by persisted permissions and `DocumentRecordPolicy` for create, Draft/Needs Revision metadata editing, Draft PDF upload, QR download, and submit/resubmit; Captains retain archive/detail viewing and Draft PDF review; existing Captain-created drafts remain available to Secretaries; identity changes require explicit confirmation; database uniqueness enforces type/year/number; published lifecycle actions remain Captain-only; `DocumentRecordPolicyTest`, `DocumentRecordCreationTest`, `DocumentMetadataEditingTest`, `DocumentDraftPdfUploadTest`, `DocumentQrCodeDownloadTest`, `DocumentArchiveSearchTest`, `DocumentLifecycleTest`, and full suite pass | None for Capstone V1 document records, numbering, and policy authorization |
| QR verification (§13, §35.3) | `Verified` | `DocumentRecord` creates one 48-character permanent token; public `verification.show` route resolves `/verify/{token}` without auth; Draft/Pending/Needs Revision states remain Not Yet Verified with no PDF action; Published state shows Verified metadata and controlled PDF actions; Archived/Revoked/Superseded states keep functional verification URLs with public-safe lifecycle history; Revoked/Superseded remove public PDF actions; authenticated `documents.qr-code` downloads deterministic QR SVG for the existing verification URL; `PublicDocumentVerificationTest`, `DocumentQrCodeDownloadTest`, `PublishedDocumentVerificationTest`, `DocumentLifecycleTest`, and full suite pass | None for Capstone V1 QR verification |
| Files, versions, and corrections (§14-15, §26) | `Partial` | Draft PDF fields exist on `document_records`; `pages::documents.show` validates PDF-only uploads up to 10 MB, stores working files outside public routes, records uploader metadata, replaces previous Draft/Needs Revision working files, blocks Published replacement, copies approved PDFs to immutable `document_file_versions`, shows official PDF version history newest first, serves public PDFs for Published/Archived only, and supports Superseded replacement document/reference context; `DocumentDraftPdfUploadTest`, `DocumentApprovalWorkflowTest`, `PublishedDocumentVerificationTest`, and `DocumentLifecycleTest` pass | Report/export integration and broader retention/backup operations remain separate PRD areas |
| Approval workflow (§12, §24, §34.3, §35.4) | `Partial` | `DocumentRecord` implements submit, return, approve, archive, revoke, and supersede transitions; only Secretaries can submit/resubmit while only Captains can return/approve and perform published lifecycle actions; `pages::approvals.index` remains a Captain-only review workbench with Draft PDF links and policy-backed actions; return and approval notifications target the latest submitter and fall back to the creator for legacy records; transitions preserve `verification_token` and create audit logs; `ApprovalDashboardTest`, `DocumentApprovalWorkflowTest`, and `DocumentLifecycleTest` pass | Broader escalation rules beyond the current PRD slice remain |
| Rule-based categorization (§16, §35.5) | `Partial` | `categorization_rules` supports target category, optional document type condition, normalized keywords, priority, and active state; Captain/Secretary manage rules; document creation and Draft/Needs Revision editing recalculate from type/title/subject/keywords, automatically persist the winning active rule category, ignore category tampering outside explicit correction mode, retain explainability fields, require an active manual category when no rule matches, and audit both corrections and no-match assignments with existing action names; priority, type-scoping, matched terms, save-time recalculation, edit transitions, and reset-to-automatic behavior are tested in `DocumentCategorySuggestionTest` and `DocumentMetadataEditingTest` | Optional tags and report/export use remain |
| Residents and households (§19-21, §35.6) | `Verified` | `households` and `residents` migrations/models/factories exist; `ResidentStatus` covers Active/Transferred/Deceased/Inactive; `pages::households.index` and `pages::residents.index` provide list/search/create/update/status/voter workflows; Purok Chair actions force assigned `purok_id`; duplicate warning requires explicit override; resident search includes household code/contact/full-name matching; `HouseholdManagementTest` and `ResidentManagementTest` pass | Dashboard/report integration, import/export, and richer resident history remain separate PRD areas |
| Dashboards (§22) | `Verified` | `DashboardMetricsService` and `DashboardController` compute live query-backed metrics; Captain/Secretary see total documents, pending approvals, published documents, total residents, category distribution, purok resident distribution, and recent audit activity; Purok Chairperson is strictly scoped to assigned purok (residents, active, households, 7-day updates) with zero document exposure; `DashboardTest` passes | None for Capstone V1 operational dashboards |
| In-app notifications (§24) | `Verified` | Laravel database notifications table exists; document submission notifies Captains with deeplink to approval workbench card; returns and approvals notify the latest submitter with creator fallback for legacy records; `NotificationController` supports read/unread filters and actions, mark-all-read, and deeplinks; header/sidebar unread badges and approval-card anchors are implemented; `NotificationManagementTest` and `DocumentApprovalWorkflowTest` pass | None for Capstone V1 in-app notifications |
| Audit logs (§25, §35.8) | `Verified` | `audit_logs` table/model exist; submit/return/approve/archive/revoke/supersede, category suggested/overridden, categorization rule create/update/activate/deactivate, staff account create/update/role-change/activate/deactivate/password-reset, household create/update, and resident create/update/status-change create actor/action/entity/context entries; Captain-only audit page supports keyword search (`q`), actor filter (including system automated), action type filter, entity type filter (`DocumentRecord`, `Resident`, `Household`, `User`, `CategorizationRule`), date range filter (`date_from`, `date_to`), sort ordering, active filter badges, and paginated history; printable view (`audit-logs.print`) renders official Barangay Sta. Felomina letterhead, metadata grid, summary cards, and certification signatures with auto-print support; CSV export (`audit-logs.export`) streams filtered records with UTF-8 BOM; `AuditLogManagementTest`, `DocumentApprovalWorkflowTest`, `DocumentLifecycleTest`, `StaffAccountManagementTest`, `HouseholdManagementTest`, and `ResidentManagementTest` pass | None for Capstone V1 audit logs |
| Backup, recovery, and migration support (§27-28) | `Verified` | `BackupService.php` and `PostgreSqlBackupProvider.php` provide automated database dumps (PostgreSQL & SQLite) and private storage archiving into timestamped ZIP files with SHA-256 manifest verification; Artisan commands `backup:create` and `backup:restore` enforce connection checks and interactive confirmation / `--force` flags; cross-platform automation scripts (`scripts/backup.sh`, `scripts/restore.sh`, `backup.bat`, `restore.bat`) and Dockerfile `postgresql-client` integration exist; `BackupRestoreTest` passes (19/19 passed, 77 assertions) | None for Capstone V1 backup and disaster recovery |
| Training, turnover, deployment docs (§29-30, §36) | `Verified` | Comprehensive, client-ready markdown manuals exist in `docs/`: `ADMIN_OPERATOR_MANUAL.md` (21 KB, role-based workflows for Captain, Secretary, Purok Chair), `BACKUP_AND_RESTORE.md` (14 KB, scheduling, commands, DR runbooks), and `DEPLOYMENT_GUIDE.md` (14 KB, Docker Compose, bare-metal, troubleshooting); `DocumentationIntegrityTest` passes (18/18 passed, 94 assertions, 0 placeholder tokens) | None for Capstone V1 turnover documentation |
| Demo data and defense turnover (§36.12) | `Verified` | `DemoDataSeeder.php` provisions realistic client-ready data for Barangay Sta. Felomina: 32 documents across all 7 lifecycle states (Draft, Pending Approval, Needs Revision, Published, Archived, Revoked, Superseded) with standard-compliant dynamic-xref sample PDFs, full infrastructure project metadata, and rule-based categorization suggestions/overrides; 28 households and 84 residents distributed across all 7 official puroks with realistic household heads, family relations, voter status, and all 4 resident statuses; 232 historical audit logs across 12 distinct action types; and 23 in-app notifications with unread/read states for Captain, Secretary, and all 7 Purok Chairpersons; `RolePermissionSeeder` reliably resets Spatie cache; `DemoDataSeederTest` (27 tests, 1,851 assertions) and `DemoDataSeederAdversarialTest` (3x consecutive rerun idempotent count assertions across 10 core tables) pass | None for Capstone V1 representative demo data |
| Non-functional requirements (§31) | `Partial` | Framework security primitives plus a responsive shared application/auth/settings design system, dark mode, semantic labels, focus states, loading states, and compiled assets | Domain authorization, upload safety, integrity constraints, pagination/performance, real-browser compatibility/accessibility review, and deployment availability remain unverified |

## Critical Findings

1. **Persisted permissions now define staff capability.** Spatie roles and
   permissions protect routes, Livewire actions, navigation, reports, and
   document workflow actions; assigned-Purok checks remain server-side domain
   scoping instead of Spatie teams.
2. **Resident isolation is now testable and covered.** Purok Chair accounts point
   to Purok records, and resident/household actions force or filter by assigned
   Purok server-side.
3. **The static prototype must not inflate progress.** It usefully expresses the
   desired interface, but its sample records and button actions are not backed by
   application routes, persistence, authorization, or tests.
4. **The remaining PRD core risk is cross-module integrity.** QR permanence,
   metadata edit windows, first approval rights, and first published-file
   preservation are now covered by tests, and categorization
   suggestions/overrides plus terminal document lifecycle states now have
   vertical proof; future slices should keep using policy/domain checks instead
   of UI-only restrictions.

## Recommended Delivery Order

Dependencies favor this sequence; it is not a promise of scope or schedule:

1. Final PRD acceptance audit after every required criterion is verified or approved out.

## Audit Maintenance

Update a row only when its meaning changes. Cite concrete paths and tests in the
evidence cell once work exists. Mark a module `Verified` only when all applicable
PRD acceptance criteria are implemented and the relevant tests pass. Regressions
must lower the status immediately. Record active execution details in
`HANDOFF.md`, not here.
