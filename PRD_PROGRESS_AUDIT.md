# PRD Progress Audit

## Purpose

This is the current evidence ledger for `PRD_V1.md`. It answers what is actually
implemented, what remains, and what evidence supports each claim. It is not a
roadmap, changelog, task diary, or substitute for the PRD.

**Audited:** 2026-09-09  
**PRD:** `PRD_V1.md` (Capstone V1)  
**Repository state:** Staff identity and PRD-aligned frontend foundation  
**Audit basis:** routes, models, migrations, application views, configuration,
and automated tests present in the workspace. No Git metadata is available in
this workspace, so a commit identifier could not be recorded.

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

- `app/Models` contains only `User`; there are no document, resident, household,
  category, notification, or audit domain models.
- Users now have an enum-backed staff role and active state. Purok assignment and
  the full Captain-managed account lifecycle are not implemented.
- Authenticated routes provide a responsive, role-aware UI foundation for the
  PRD modules. Unimplemented domains use explicit notices, empty states, and
  disabled actions rather than prototype records or simulated writes.
- The dashboard has Captain/Secretary and Purok Chair layouts but no query-backed
  domain counts yet.
- Existing tests cover starter authentication, security settings, profile
  settings, and dashboard access.
- `docs/barangay_document_system_ui_prototype.html` is an interactive static
  prototype with sample data and prototype-only actions. It is design evidence,
  not implementation evidence.

## Module Audit

| PRD area | Status | Verified evidence | Material gap / next proof needed |
|---|---|---|---|
| Authentication and account management (§7-9, §35.1) | `Partial` | Public registration is disabled; Fortify login excludes inactive accounts; password reset/change, email verification, 2FA setup/recovery codes, passkeys, role state, settings UX, and focused tests exist | Captain-managed staff creation, activation/deactivation actions, administrative reset, and audit events remain |
| Roles, permissions, and purok isolation (§7-8, §21) | `Partial` | `UserRole`, `RequireRole`, `EnsureUserIsActive`, role-aware navigation, Captain-only route denial, and Purok Chair document-route denial are tested | Add persisted purok assignment and enforce record-level document/resident policies, especially cross-purok isolation |
| Document records and numbering (§10-12, §17-18, §35.2) | `Not started` | None | Domain schema, draft CRUD, numbering uniqueness/suggestion, lifecycle rules, project metadata, search, filters, and tests |
| QR verification (§13, §35.3) | `Not started` | None | Permanent unpredictable token, stable QR download, public state-aware route, controlled PDF access, and end-to-end tests |
| Files, versions, and corrections (§14-15, §26) | `Not started` | None | Validated PDF storage, approved version immutability, archive/revoke/supersede behavior, and preservation tests |
| Approval workflow (§12, §24, §34.3, §35.4) | `Not started` | None | Submit/return/approve transitions, Captain-only approval, review comments, notifications, and transition/authorization tests |
| Rule-based categorization (§16, §35.5) | `Not started` | None | Category/tag/rule data, deterministic matching, explanation, manual override, management UI, and tests |
| Residents and households (§19-21, §35.6) | `Not started` | None | Purok/household/resident data, scoped CRUD, status changes, duplicate warning, and isolation tests |
| Dashboards (§22) | `Partial` | Role-specific responsive dashboard layouts and navigation render for staff roles without invented statistics | Connect query-backed document/resident summaries and test their authorization and data scope |
| Search, reports, and PDF export (§18, §23, §35.7) | `Not started` | None | Reusable filter/query layer, print view, PDF output, permissions, and tests |
| In-app notifications (§24) | `Not started` | None | Pending, approved, and revision notifications with visibility/read-state tests |
| Audit logs (§25, §35.8) | `Not started` | None | Immutable application-generated events for document, account, and resident actions with actor/context tests |
| Backup, recovery, and migration support (§27-28) | `Not started` | SQLite database exists for local development | Documented and tested database/file backup and restore; representative seed data; manual historical entry support |
| Training, turnover, deployment docs (§29-30, §36) | `Not started` | None | Setup, deployment, user/admin, and backup/restore guidance plus turnover validation |
| Non-functional requirements (§31) | `Partial` | Framework security primitives plus a responsive shared application/auth/settings design system, dark mode, semantic labels, focus states, loading states, and compiled assets | Domain authorization, upload safety, integrity constraints, pagination/performance, real-browser compatibility/accessibility review, and deployment availability remain unverified |

## Critical Findings

1. **Role-level routing is not record-level authorization.** The new staff role
   and active-account boundaries protect foundation routes, but document and
   resident policies remain mandatory when those records exist.
2. **Purok assignment is not modeled yet.** Purok Chair navigation is restricted,
   but true assigned-purok isolation cannot be verified before the purok and
   resident domain slice.
3. **The static prototype must not inflate progress.** It usefully expresses the
   desired interface, but its sample records and button actions are not backed by
   application routes, persistence, authorization, or tests.
4. **The PRD's core risk is cross-module integrity.** QR permanence, published-file
   history, approval rights, and purok isolation should be built and tested as
   vertical workflows, not postponed as UI-only checks.

## Recommended Delivery Order

Dependencies favor this sequence; it is not a promise of scope or schedule:

1. Complete staff administration with Captain actions and persisted purok assignment.
2. Document taxonomy and Draft record with unique numbering and permanent token.
3. QR draft verification and controlled PDF upload.
4. Approval/revision/publish workflow with versions, notifications, and audit events.
5. Categorization rules, document search, and lifecycle actions.
6. Puroks, households, residents, duplicate warning, and isolation.
7. Role-scoped dashboards, reports, PDF export, demo data, and turnover operations.

## Audit Maintenance

Update a row only when its meaning changes. Cite concrete paths and tests in the
evidence cell once work exists. Mark a module `Verified` only when all applicable
PRD acceptance criteria are implemented and the relevant tests pass. Regressions
must lower the status immediately. Record active execution details in
`HANDOFF.md`, not here.
