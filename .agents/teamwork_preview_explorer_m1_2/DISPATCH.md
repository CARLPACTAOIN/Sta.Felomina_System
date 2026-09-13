## 2026-09-13T08:24:48Z

You are teamwork_preview_explorer (M1 Explorer 2: Residents & Households Data Plan).
Your working directory is: C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_2
Your parent is: 1a0f86b1-f133-4d9d-81e5-8f9757cf60a2

MANDATORY FIRST STEPS:
1. Read C:\laragon\www\Barangay-System\.agents\ORIGINAL_REQUEST.md
2. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_orchestrator_1\PROJECT.md
3. Read C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_survey_db\survey_db_models.md

Objective:
Design the complete, realistic data specification for Households and Residents in `DemoDataSeeder.php`.
Provide exact array structures and code patterns for:
1. Households: 28 households across Puroks 1 to 7 (4 households per Purok). Deterministic household codes (`HH-P1-001` through `HH-P7-004`), realistic street addresses in Sta. Felomina, household income ranges.
2. Residents: 80–90 residents distributed across all 7 Puroks and households.
   - Demographics: Realistic Filipino names (first name, middle name, last name), birthdates, civil statuses (Single, Married, Widowed, Separated), gender, contact numbers, occupations.
   - Voter status: Realistic registered voter flags (`is_voter` = true/false).
   - Resident statuses: Cover all 4 `ResidentStatus` enum cases (`active`, `transferred`, `deceased`, `inactive`).
   - Household relationships: Designate head of household (`is_head_of_household` or relationship_to_head: Head, Spouse, Child, Parent, Relative).
3. Idempotent insertion: `Household::updateOrCreate()` and `Resident::updateOrCreate()` using deterministic codes.

Deliverables:
- Write report to C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_2\residents_data_plan.md
- Write handoff to C:\laragon\www\Barangay-System\.agents\teamwork_preview_explorer_m1_2\handoff.md
- Send message to parent (1a0f86b1-f133-4d9d-81e5-8f9757cf60a2) when complete.
