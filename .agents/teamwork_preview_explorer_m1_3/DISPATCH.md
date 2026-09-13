## 2026-09-13T08:24:49Z
You are teamwork_preview_explorer (M1 Explorer 3: Documents, Lifecycle & Storage Plan).
Your working directory is: C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_3
Your parent is: 1a0f86b1-f133-4d9d-81e5-8f9757cf60a2

MANDATORY FIRST STEPS:
1. Read C:\laragon\www\Barangay-System\.agents\ORIGINAL_REQUEST.md
2. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_orchestrator_1\PROJECT.md
3. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_survey_db\survey_db_models.md

Objective:
Design the complete data specification and code patterns for Official Documents, Lifecycle States, Storage Files, Audit Logs, and Notifications in `DemoDataSeeder.php`.
Provide exact structures and code patterns for:
1. Official Documents: 28-32 realistic documents across 2024, 2025, and 2026.
   - Document types: Resolutions (`RES`), Ordinances (`ORD`), Executive Orders (`EO`), Certifications (`CERT`).
   - Categories: Health, Infrastructure, Peace & Order, Education, Disaster Risk Reduction, Governance.
   - Lifecycle states: Cover all 7 states: `Draft`, `Pending Approval`, `Needs Revision`, `Published`, `Archived`, `Revoked`, `Superseded`.
   - Superseded chaining: Order of seeding where replacement documents (Published) are created first, then superseded documents link `superseded_by_document_record_id` to them.
2. Storage and File Versions:
   - For Published, Archived, Revoked, and Superseded documents: create `DocumentFileVersion` records (v1) with `approved_at`, `approved_by_id` (Captain), and generate a minimal physical PDF file at `storage/app/private/document-officials/{id}/versions/1.pdf` using `Storage::disk('local')->put()`.
   - For Draft / Needs Revision documents: generate draft PDF at `storage/app/private/document-drafts/{hash}.pdf`.
3. Audit Logs & Notifications:
   - Create historical audit log entries (`audit_logs`) for document approvals, rejections, resident registrations.
   - Create notification records (`notifications`) for Captain, Secretary, and Purok Chairpersons.
4. Idempotency: Deterministic lookup by `['document_type_id', 'document_year', 'document_number']`.

Deliverables:
- Write report to C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_3\documents_storage_plan.md
- Write handoff to C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_3\handoff.md
- Send message to parent (1a0f86b1-f133-4d9d-81e5-8f9757cf60a2) when complete.
