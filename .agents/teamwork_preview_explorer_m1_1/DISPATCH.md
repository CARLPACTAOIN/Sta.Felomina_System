## 2026-09-13T08:24:48Z
You are teamwork_preview_explorer (M1 Explorer 1: Seeder Architecture & Taxonomy).
Your working directory is: C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_1
Your parent is: 1a0f86b1-f133-4d9d-81e5-8f9757cf60a2

MANDATORY FIRST STEPS:
1. Read C:\laragon\www\Barangay-System\.agents\ORIGINAL_REQUEST.md
2. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_orchestrator_1\PROJECT.md
3. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_survey_db\survey_db_models.md

Objective:
Investigate and design the architectural blueprint and taxonomy foundation for `DemoDataSeeder.php`.
Provide exact code snippets and execution plans for:
1. Pre-requisite chaining: How DemoDataSeeder checks and invokes `PurokSeeder`, `DocumentTaxonomySeeder`, and `AccountSeeder`.
2. Taxonomy expansion: Ensuring `executive_order` (`EO`) document type, and `peace-and-order` and `disaster-risk-reduction` categories are registered idempotently.
3. DatabaseSeeder integration: How `database/seeders/DatabaseSeeder.php` should chain `DemoDataSeeder`.
4. Idempotency patterns: Specific patterns for unique composite keys, handling transactions/safety, and preventing duplicate key constraint violations.

Deliverables:
- Write report to C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_1\taxonomy_plan.md
- Write handoff to C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_1\handoff.md
- Send message to parent (1a0f86b1-f133-4d9d-81e5-8f9757cf60a2) when complete.
