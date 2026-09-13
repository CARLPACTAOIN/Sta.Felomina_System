## 2026-09-13T08:29:24Z

You are teamwork_preview_worker (Demo Data Seeder Implementer).
Your working directory is: C:\laragon\www\Barangay-System\.agents\teamwork_preview_worker_m1
Your parent is: 1a0f86b1-f133-4d9d-81e5-8f9757cf60a2

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

MANDATORY FIRST STEPS:
1. Read C:\laragon\www\Barangay-System\.agents\ORIGINAL_REQUEST.md
2. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_orchestrator_1\PROJECT.md
3. Read the three M1 Explorer blueprints:
   - C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_1\taxonomy_plan.md
   - C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_2\residents_data_plan.md
   - C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_3\documents_storage_plan.md

File Ownership (You own exclusively):
- database/seeders/DemoDataSeeder.php
- database/seeders/DatabaseSeeder.php
- database/seeders/DocumentTaxonomySeeder.php
- storage/app/private/ (generating mock PDF storage files for seeded documents)
DO NOT touch tests/, app/Services/, app/Console/, docs/, or scripts/.

Objective:
Implement `DemoDataSeeder.php` and integrate it into `DatabaseSeeder.php` according to the blueprints:
1. Prerequisite handling: In `DemoDataSeeder`, use `$this->callOnce([DocumentTaxonomySeeder::class, PurokSeeder::class, AccountSeeder::class])` and ensure taxonomy expansions (`Executive Order` type, `Peace & Order` category, `Disaster Risk Reduction` category, and categorization keyword rules).
2. Update `DocumentTaxonomySeeder.php` so these taxonomies are part of the baseline.
3. Populate 28 households across Puroks 1-7 with realistic data and codes (`HH-P1-001` ... `HH-P7-004`).
4. Populate 84 residents across Puroks 1-7 with realistic Filipino demographics, all 4 statuses (`active`, `transferred`, `deceased`, `inactive`), voter flags, and head of household matching.
5. Populate 32 official documents across 2024-2026 covering 4 types (`RES`, `ORD`, `EO`, `CERT`), all categories, and all 7 lifecycle states (`Draft`, `Pending Approval`, `Needs Revision`, `Published`, `Archived`, `Revoked`, `Superseded`).
6. Implement two-phase seeding for superseded records: seed replacement published records first, then link superseded records via `superseded_by_document_record_id`.
7. Generate real minimal PDF files in `storage/app/private/document-officials/{id}/versions/1.pdf` for all approved records (with `DocumentFileVersion` entries), and `storage/app/private/document-drafts/` for draft records.
8. Seed realistic historical audit logs (`audit_logs`) and staff notifications (`notifications`).
9. Enforce strict idempotency on all records so repeat seeding succeeds without unique constraint violations.
10. Update `DatabaseSeeder.php` to include `DemoDataSeeder::class`.

Verification:
- Run `php artisan db:seed --class=DemoDataSeeder`
- Run `php artisan db:seed --class=DemoDataSeeder` again to prove idempotency
- Run `vendor/bin/pest tests/Feature/DemoDataSeederTest.php` to verify all 20 tests pass!
- Run `vendor/bin/pint --dirty --format agent`

Deliverable:
- Write comprehensive handoff report to: C:\laragon\www\Barangay-System\.agents\teamwork_preview_worker_m1\handoff.md with commands and test output.
- Send message to parent (1a0f86b1-f133-4d9d-81e5-8f9757cf60a2) when complete.
