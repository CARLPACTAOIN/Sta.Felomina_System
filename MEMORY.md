# Project Memory

## Purpose

This file preserves compact, durable, verified context across agents and
sessions. It is not a chat transcript, progress tracker, backlog, or handoff.
If a fact is obvious from code or belongs in the PRD, link to that source instead
of copying it here.

**Last verified:** 2026-09-09

## Product Context

- Product: Web-Based Barangay Document Archiving, Verification, and Resident
  Profiling System for Barangay Sta. Felomina, Makilala, Cotabato.
- Target final completion: October 2026; the exact client-confirmed date remains
  unknown.
- `PRD_V1.md` is the authoritative Capstone V1 product specification.
- `docs/barangay_document_system_ui_prototype.html` is a visual interaction
  reference containing fictional sample data. It does not define requirements
  and does not demonstrate implemented behavior.

## Product Invariants Worth Keeping in Working Context

Read the relevant PRD sections for complete detail. These constraints have broad
architectural impact:

- Accounts are internal-only: Captain, Secretary, and Purok Chairperson. There is
  no public registration or resident self-service account in V1.
- A QR identity belongs permanently to one document record, begins at Draft, is
  unpredictable, and must not change across approval or file replacement.
- Draft and Pending Approval records are never represented as verified.
- The Captain is the final digital approver; the Secretary cannot self-approve.
- Published files and verification history must be preserved. Archive, revoke,
  and supersede retain a working verification URL.
- Public access is by verification token only; V1 has no public browse/search portal.
- Purok Chair access to resident data is enforced server-side by assigned purok.
- Categorization is explainable, rule-based, metadata-driven, and overridable;
  OCR and AI classification are out of scope.
- V1 notifications are in-app only. SMS and email notification infrastructure
  are out of scope.

## Confirmed Technical Baseline

Values below describe the installed workspace on 2026-09-09, not future package
guarantees. Confirm installed versions again before using version-sensitive APIs.

| Area | Confirmed value |
|---|---|
| Runtime | PHP 8.4.5 |
| Backend | Laravel 13.31.0 |
| Authentication | Fortify 1.39.0 |
| Reactive UI | Livewire 4.4.4 |
| Component UI | Flux 2.19.0 |
| Blade optimization | Blaze 1.0.18 |
| CSS/build | Tailwind CSS 4; Vite Plus scripts (`vp`) |
| Tests | Pest 5.1.4 with Laravel plugin 5.0.1 |
| Static analysis | Larastan 3.11.0 / `phpstan.neon` |
| Local/default data store | PostgreSQL 16 |

Useful project commands:

```text
composer run dev
npm run build
php artisan test --compact <test-path-or-filter>
composer run types:check
vendor/bin/pint --dirty --format agent
```

Use the narrowest relevant test command during a slice. The full Composer test
pipeline also checks formatting and static analysis.

## Durable Decisions

| ID | Decision | Basis | Recorded |
|---|---|---|---|
| D-001 | `PRD_V1.md` is product truth until an explicitly approved addendum or newer PRD supersedes it | PRD §1, §37, §39 | 2026-09-09 |
| D-002 | The HTML prototype is visual reference only; code and tests determine implementation status | Prototype labels its actions/sample data as prototype-only | 2026-09-09 |
| D-003 | Coordination files have non-overlapping roles: operating contract, scope audit, durable memory, and current handoff | AI-native workflow design | 2026-09-09 |
| D-004 | Progress is reported by evidence-backed statuses, not invented percentages | Avoids false precision and repeated re-auditing | 2026-09-09 |
| D-005 | Root `AGENTS.md` contains only cross-cutting rules; detailed framework guidance loads through skills and path-scoped `.ai/rules` | Reduces recurring context while preserving task-specific guidance | 2026-09-09 |

## Memory Maintenance

- Add only verified facts or approved decisions expected to matter across tasks.
- Prefer a source path or decision ID over repeated explanatory prose.
- Replace stale facts; mark a decision superseded and link its replacement rather
  than leaving contradictory active entries.
- Never add task status, speculative architecture, command logs, secrets,
  credentials, production data, or personal resident information.
- Review this file when the PRD version, runtime stack, architecture, deployment
  model, or a cross-cutting decision changes.
