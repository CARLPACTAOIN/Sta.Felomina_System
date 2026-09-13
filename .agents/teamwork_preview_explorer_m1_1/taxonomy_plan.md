# Architectural Blueprint & Taxonomy Foundation for DemoDataSeeder

**Workspace:** `C:\laragon\www\Barangay-System`  
**Target Milestone:** M1 Explorer 1 (Seeder Architecture & Taxonomy)  
**Target Delivery:** `database/seeders/DemoDataSeeder.php` & `database/seeders/DatabaseSeeder.php`  
**Date:** September 13, 2026  

---

## Executive Summary

This blueprint defines the architecture, dependency sequence, taxonomy expansions, and idempotency guarantees for `DemoDataSeeder.php`. It provides exact code implementations for:
1. **Pre-requisite Chaining**: Unconditional safety via Laravel's native `callOnce()` mechanism and schema-state guards to guarantee `PurokSeeder`, `DocumentTaxonomySeeder`, and `AccountSeeder` are executed before any demo data is created.
2. **Taxonomy Expansion**: Idempotent registration of the `executive_order` (`EO`) document type and the `peace-and-order` and `disaster-risk-reduction` document categories, along with corresponding categorization rules.
3. **DatabaseSeeder Integration**: Updating `database/seeders/DatabaseSeeder.php` to cleanly orchestrate both baseline configuration and full demo datasets without redundant queries or foreign key race conditions.
4. **Comprehensive Idempotency Patterns**: Deterministic identifier strategies for composite unique keys (`[document_type_id, document_year, document_number]`, `[document_record_id, version_number]`), natural unique keys (`household_code`, `resident_code`), verification tokens, audit log deduplication, notification deduplication, and filesystem safety.

---

## 1. Pre-requisite Chaining Architecture

### 1.1 Context & Problem Statement
`DemoDataSeeder` relies heavily on foreign key references to:
- `puroks.id`: Required by `households.purok_id`, `residents.purok_id`, and `users.purok_id`.
- `document_types.id`: Required by `document_records.document_type_id`.
- `document_categories.id`: Required by `document_records.document_category_id`.
- `users.id`: Required by `document_records.created_by_id`, `approved_by_id`, `submitted_by_id`, `returned_by_id`, `archived_by_id`, `revoked_by_id`, `superseded_by_id`, `households.created_by_id`, and `residents.created_by_id`.

If a developer or automated test executes:
```powershell
php artisan db:seed --class=DemoDataSeeder
```
on a fresh or unseeded database, the seeder would fail immediately with foreign key violation errors (e.g. `SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on table "households" violates foreign key constraint "households_purok_id_foreign"`).

### 1.2 The `callOnce` Mechanism
In Laravel framework's `Illuminate\Database\Seeder`:
```php
protected static $called = [];

public function callOnce($class, $silent = false, array $parameters = [])
{
    $classes = Arr::wrap($class);

    foreach ($classes as $class) {
        if (in_array($class, static::$called)) {
            continue;
        }

        $this->call($class, $silent, $parameters);
    }
}
```
Whenever `$this->call($class)` runs, it records the class name in `static::$called`.
Using `$this->callOnce()` provides two distinct advantages:
1. When invoked via `DatabaseSeeder`, which has already called `DocumentTaxonomySeeder`, `PurokSeeder`, and `AccountSeeder`, `DemoDataSeeder` sees them in `static::$called` and **skips redundant re-execution**.
2. When invoked standalone (`php artisan db:seed --class=DemoDataSeeder`), `static::$called` is initially empty, so `DemoDataSeeder` **automatically and sequentially executes all prerequisite seeders**.

### 1.3 Exact Code Implementation for Chaining
In `database/seeders/DemoDataSeeder.php`:

```php
namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\DocumentType;
use App\Models\Purok;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensurePrerequisites();
        $this->ensureTaxonomyExpanded();

        DB::transaction(function (): void {
            $this->seedHouseholdsAndResidents();
            $this->seedDocumentRecordsAndFiles();
            $this->seedAuditLogs();
            $this->seedNotifications();
        });
    }

    /**
     * Ensure baseline lookup tables and staff accounts are seeded.
     */
    protected function ensurePrerequisites(): void
    {
        // 1. Invoke baseline seeders via callOnce to prevent duplicates in current CLI run
        $this->callOnce([
            DocumentTaxonomySeeder::class,
            PurokSeeder::class,
            AccountSeeder::class,
        ]);

        // 2. Defense-in-depth: Verify prerequisite data exists in database
        if (Purok::query()->count() < 7) {
            $this->call(PurokSeeder::class);
        }

        if (User::query()->where('is_active', true)->count() < 9) {
            $this->call(AccountSeeder::class);
        }

        if (DocumentType::query()->count() === 0 || DocumentCategory::query()->count() === 0) {
            $this->call(DocumentTaxonomySeeder::class);
        }
    }
```

---

## 2. Taxonomy Expansion Blueprint

### 2.1 Missing Taxonomy Elements in Capstone V1
An audit of `database/seeders/DocumentTaxonomySeeder.php` reveals the following baseline entries:
- **Types seeded (6)**: Resolution (`RES`), Ordinance (`ORD`), Certificate (`CERT`), Memorandum (`MEM`), Correspondence Record (`COR`), Meeting Minutes (`MIN`).
  - *Missing*: **Executive Order** (`executive_order`, prefix: `EO`).
- **Categories seeded (6)**: Governance (`governance`), Health (`health`), Infrastructure (`infrastructure`), Social Services (`social-services`), Public Safety (`public-safety`), Education (`education`).
  - *Missing*: **Peace & Order** (`peace-and-order`), **Disaster Risk Reduction** (`disaster-risk-reduction`).

### 2.2 Two-Tier Expansion Architecture

#### Tier 1: Update `database/seeders/DocumentTaxonomySeeder.php`
`DocumentTaxonomySeeder` should be updated so that any future baseline database seed or test harness includes these official classifications:

```php
// In database/seeders/DocumentTaxonomySeeder.php

$types = [
    ['Resolution', 'resolution', 'RES', 'Barangay council resolutions and formal decisions.'],
    ['Ordinance', 'ordinance', 'ORD', 'Local legislative ordinances approved by the barangay.'],
    ['Certificate', 'certificate', 'CERT', 'Official certifications issued by the barangay.'],
    ['Memorandum', 'memorandum', 'MEM', 'Internal and official memoranda.'],
    ['Correspondence Record', 'correspondence_record', 'COR', 'Incoming and outgoing official correspondence.'],
    ['Meeting Minutes', 'meeting_minutes', 'MIN', 'Minutes of barangay meetings and sessions.'],
    ['Executive Order', 'executive_order', 'EO', 'Official executive orders and administrative directives issued by the Punong Barangay.'],
];

$categories = [
    ['Governance', 'Policies, council actions, and administrative governance.'],
    ['Health', 'Health center, sanitation, medical, and public health records.'],
    ['Infrastructure', 'Roads, facilities, utilities, and public works records.'],
    ['Social Services', 'Assistance, welfare, and community support records.'],
    ['Public Safety', 'Peace and order, disaster response, and safety records.'],
    ['Education', 'School, youth, scholarship, and learning-related records.'],
    ['Peace & Order', 'Barangay peacekeeping, tanod operations, curfew enforcement, and community dispute resolution.'],
    ['Disaster Risk Reduction', 'Disaster preparedness, calamity fund management, climate response, and emergency rescue.'],
];

$rules = [
    ['Infrastructure Keywords', 'Infrastructure', null, ['road', 'drainage', 'construction', 'rehabilitation', 'building', 'facility', 'bridge', 'water system'], 10],
    ['Health Keywords', 'Health', null, ['health', 'clinic', 'medical', 'sanitation', 'vaccination', 'dengue', 'medicine'], 20],
    ['Public Safety Keywords', 'Public Safety', null, ['peace and order', 'safety', 'disaster', 'emergency', 'rescue', 'fire', 'incident'], 30],
    ['Social Services Keywords', 'Social Services', null, ['assistance', 'welfare', 'indigent', 'senior citizen', 'pwd', 'relief'], 40],
    ['Education Keywords', 'Education', null, ['school', 'student', 'youth', 'scholarship', 'learning', 'daycare'], 50],
    ['Governance Keywords', 'Governance', null, ['council', 'policy', 'administration', 'ordinance', 'resolution', 'session', 'meeting'], 60],
    ['Meeting Minutes Governance', 'Governance', 'meeting_minutes', ['minutes', 'session', 'assembly', 'agenda'], 5],
    ['Peace & Order Keywords', 'Peace & Order', null, ['peace and order', 'tanod', 'curfew', 'checkpoint', 'patrol', 'dispute', 'badac', 'lupon'], 25],
    ['Disaster Risk Reduction Keywords', 'Disaster Risk Reduction', null, ['disaster', 'calamity', 'evacuation', 'flood', 'typhoon', 'relief', 'bdrrmc', 'rescue', 'hazard', 'preparedness'], 15],
];
```

#### Tier 2: Idempotent Registration Method in `DemoDataSeeder.php`
`DemoDataSeeder` must also explicitly guarantee the presence of these taxonomies before generating documents, in case `DemoDataSeeder` is executed against an existing database where `DocumentTaxonomySeeder` was already run previously without the new entries:

```php
/**
 * Idempotently register required document types, categories, and keyword rules.
 */
protected function ensureTaxonomyExpanded(): void
{
    // 1. Executive Order Document Type
    DocumentType::query()->updateOrCreate(
        ['code' => 'executive_order'],
        [
            'name' => 'Executive Order',
            'number_prefix' => 'EO',
            'description' => 'Official executive orders and administrative directives issued by the Punong Barangay.',
            'is_active' => true,
        ]
    );

    // 2. Peace & Order Document Category
    $peaceAndOrder = DocumentCategory::query()->updateOrCreate(
        ['slug' => 'peace-and-order'],
        [
            'name' => 'Peace & Order',
            'description' => 'Barangay peacekeeping, tanod operations, curfew enforcement, and community dispute resolution.',
            'is_active' => true,
        ]
    );

    // 3. Disaster Risk Reduction Document Category
    $drrm = DocumentCategory::query()->updateOrCreate(
        ['slug' => 'disaster-risk-reduction'],
        [
            'name' => 'Disaster Risk Reduction',
            'description' => 'Disaster preparedness, calamity fund management, climate response, and emergency rescue.',
            'is_active' => true,
        ]
    );

    // 4. Categorization Rules
    CategorizationRule::query()->updateOrCreate(
        ['name' => 'Peace & Order Keywords'],
        [
            'document_category_id' => $peaceAndOrder->id,
            'document_type_id' => null,
            'keywords' => ['peace and order', 'tanod', 'curfew', 'checkpoint', 'patrol', 'dispute', 'badac', 'lupon'],
            'priority' => 25,
            'is_active' => true,
        ]
    );

    CategorizationRule::query()->updateOrCreate(
        ['name' => 'Disaster Risk Reduction Keywords'],
        [
            'document_category_id' => $drrm->id,
            'document_type_id' => null,
            'keywords' => ['disaster', 'calamity', 'evacuation', 'flood', 'typhoon', 'relief', 'bdrrmc', 'rescue', 'hazard', 'preparedness'],
            'priority' => 15,
            'is_active' => true,
        ]
    );
}
```

---

## 3. DatabaseSeeder Integration

### 3.1 Proposed Code for `database/seeders/DatabaseSeeder.php`
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DocumentTaxonomySeeder::class,
            PurokSeeder::class,
            AccountSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
```

### 3.2 Verification of Seeder Pipeline
- Running `php artisan db:seed`:
  1. `DocumentTaxonomySeeder`: Populates 7 document types, 8 categories, and 9 categorization rules.
  2. `PurokSeeder`: Populates Puroks 1–7.
  3. `AccountSeeder`: Populates Captain, Secretary, 7 Purok Chairs, and 1 Inactive Secretary.
  4. `DemoDataSeeder`:
     - Checks `callOnce` (finds all 3 baseline seeders in `static::$called`, avoids duplicate calls).
     - Confirms taxonomy expansion.
     - Seeds 28 households across Puroks 1–7.
     - Seeds 80+ residents across Puroks 1–7.
     - Seeds 30 multi-year document records (2024–2026) across all 7 statuses.
     - Writes physical PDF storage files in `storage/app/private/`.
     - Seeds audit logs and workflow notifications.
- Running `php artisan db:seed --class=DemoDataSeeder`:
  - `callOnce` detects that baseline seeders have not been run in this process and executes them first.
  - Generates the exact same state without errors.

---

## 4. Idempotency & Constraint Violation Prevention Patterns

### 4.1 Schema Constraint Matrix
The table below specifies the unique constraints and idempotency strategies across all affected models:

| Model / Table | Unique Constraint(s) | Match Strategy | Idempotency Pattern |
|---|---|---|---|
| `DocumentType` / `document_types` | `name` (UQ), `code` (UQ), `number_prefix` (UQ) | `code` | `updateOrCreate(['code' => $code], [...])` |
| `DocumentCategory` / `document_categories` | `name` (UQ), `slug` (UQ) | `slug` | `updateOrCreate(['slug' => $slug], [...])` |
| `CategorizationRule` / `categorization_rules` | `name` (UQ) | `name` | `updateOrCreate(['name' => $name], [...])` |
| `Purok` / `puroks` | `name` (UQ), `code` (UQ) | `code` | `updateOrCreate(['code' => $code], [...])` |
| `User` / `users` | `email` (UQ) | `email` | `updateOrCreate(['email' => $email], [...])` |
| `Household` / `households` | `household_code` (UQ) | `household_code` | Deterministic code (`HH-P01-001` to `HH-P07-004`) with `updateOrCreate(['household_code' => $code], [...])` |
| `Resident` / `residents` | `resident_code` (UQ) | `resident_code` | Deterministic code (`RES-P01-001` to `RES-P07-013`) with `updateOrCreate(['resident_code' => $code], [...])` |
| `DocumentRecord` / `document_records` | Composite `[document_type_id, document_year, document_number]` + `verification_token` (UQ) | Composite Key | Query by composite key; preserve existing `verification_token` or assign deterministic 48-character token; update attributes |
| `DocumentFileVersion` / `document_file_versions` | Composite `[document_record_id, version_number]` | Composite Key | `updateOrCreate(['document_record_id' => $doc->id, 'version_number' => 1], [...])` |
| `AuditLog` / `audit_logs` | No DB unique constraint | `[auditable_type, auditable_id, action]` | `firstOrCreate(['auditable_type' => $type, 'auditable_id' => $id, 'action' => $action], [...])` |
| `Notification` / `notifications` | `id` (UUID PK, no unique constraint on data) | `[notifiable_type, notifiable_id, document_number, message]` | Pre-check `DB::table('notifications')->where(...)->exists()`; insert only when missing |
| Storage Files | File path collision | File existence check | `if (! Storage::exists($path)) { Storage::put($path, $pdfContent); }` |

---

### 4.2 Detailed Implementation Patterns

#### Pattern A: DocumentRecord Composite Unique Key & Verification Token
`DocumentRecord` has a composite unique key: `['document_type_id', 'document_year', 'document_number']`. It also has a unique column `verification_token` (varchar 64).
If `updateOrCreate` is used naively with a randomly generated token in the attributes array, each run would try to update `verification_token`, which risks collisions or breaks existing external QR bookmarks.

**The Solution:**
```php
protected function seedDocumentRecord(
    DocumentType $type,
    DocumentCategory $category,
    User $creator,
    int $year,
    string $documentNumber,
    string $title,
    string $date,
    string $subject,
    DocumentStatus $status,
    array $lifecycleAttributes = []
): DocumentRecord {
    // 1. Locate existing record by the composite unique key
    $document = DocumentRecord::query()->where([
        'document_type_id' => $type->id,
        'document_year' => $year,
        'document_number' => $documentNumber,
    ])->first();

    if ($document === null) {
        $document = new DocumentRecord();
        $document->document_type_id = $type->id;
        $document->document_year = $year;
        $document->document_number = $documentNumber;
        // Deterministic 48-char alphanumeric hex token (matches /^[A-Za-z0-9]+$/ verification route constraint)
        $document->verification_token = substr(hash('sha256', "STA-FELOMINA-DOC-{$documentNumber}-{$year}-VERIFY"), 0, 48);
    }

    // 2. Assign standard and lifecycle attributes
    $document->document_category_id = $category->id;
    $document->created_by_id = $creator->id;
    $document->title = $title;
    $document->document_date = $date;
    $document->subject = $subject;
    $document->status = $status;

    foreach ($lifecycleAttributes as $key => $value) {
        $document->{$key} = $value;
    }

    $document->save();

    return $document;
}
```

#### Pattern B: DocumentFileVersion Composite Unique Key
`DocumentFileVersion` has a composite unique constraint on `['document_record_id', 'version_number']`:
```php
protected function seedDocumentFileVersion(
    DocumentRecord $document,
    User $approver,
    string $pdfBytes
): DocumentFileVersion {
    $filePath = sprintf('document-officials/%d/versions/1.pdf', $document->id);

    // Write file to storage/app/private/ if not already present
    if (! Storage::exists($filePath)) {
        Storage::put($filePath, $pdfBytes);
    }

    return DocumentFileVersion::query()->updateOrCreate(
        [
            'document_record_id' => $document->id,
            'version_number' => 1,
        ],
        [
            'file_path' => $filePath,
            'original_name' => "{$document->document_number}.pdf",
            'mime_type' => 'application/pdf',
            'size' => strlen($pdfBytes),
            'is_current' => true,
            'approved_at' => $document->approved_at ?? now(),
            'approved_by_id' => $approver->id,
        ]
    );
}
```

#### Pattern C: Deterministic Households & Residents
In `Household` and `Resident` models, the `booted()` method contains a `creating` callback:
- `Household`: If `blank($household->household_code)`, generates random `HH-XXXXXXXXXX`.
- `Resident`: If `blank($resident->resident_code)`, generates random `RES-XXXXXXXXXX`.
By explicitly passing deterministic codes, the boot callbacks do not overwrite our deterministic codes:

```php
// Household Creation Pattern
$household = Household::query()->updateOrCreate(
    ['household_code' => "HH-P0{$purokNumber}-00{$h}"],
    [
        'purok_id' => $purok->id,
        'address' => "Sitio Centro, Purok {$purokNumber}",
        'household_head_name' => $headName,
        'notes' => 'Permanent resident household.',
        'created_by_id' => $purokChair->id,
        'updated_by_id' => $purokChair->id,
    ]
);

// Resident Creation Pattern
$resident = Resident::query()->updateOrCreate(
    ['resident_code' => "RES-P0{$purokNumber}-" . sprintf('%03d', $residentIndex)],
    [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'suffix' => $suffix,
        'sex' => $sex,
        'birthdate' => $birthdate,
        'civil_status' => $civilStatus,
        'contact_number' => $contactNumber,
        'address' => $household->address,
        'purok_id' => $purok->id,
        'household_id' => $household->id,
        'is_registered_voter' => $isVoter,
        'status' => $status,
        'birthplace' => 'Sta. Felomina',
        'occupation' => $occupation,
        'remarks' => $remarks,
        'created_by_id' => $purokChair->id,
        'updated_by_id' => $purokChair->id,
    ]
);
```

#### Pattern D: AuditLog Deduplication
`audit_logs` has no database unique index. If `DemoDataSeeder` called `AuditLog::record()` or `create()` on every run, duplicate audit events would accumulate indefinitely.
Using `firstOrCreate` keyed on `['auditable_type', 'auditable_id', 'action']`:

```php
protected function seedAuditLog(
    User $actor,
    string $action,
    Model $auditable,
    string $summary,
    array $context = [],
    ?Carbon $timestamp = null
): AuditLog {
    return AuditLog::query()->firstOrCreate(
        [
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
        ],
        [
            'actor_id' => $actor->id,
            'summary' => $summary,
            'context' => $context === [] ? null : $context,
            'created_at' => $timestamp ?? now(),
            'updated_at' => $timestamp ?? now(),
        ]
    );
}
```

#### Pattern E: Notification Deduplication
`notifications` uses UUID primary keys. Calling `$user->notify(...)` instantiates a new notification with a new UUID on each run.
To prevent duplicate notifications:

```php
protected function seedNotification(
    User $recipient,
    DocumentRecord $document,
    string $message,
    bool $isRead = false,
    ?Carbon $timestamp = null
): void {
    $exists = DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $recipient->id)
        ->where('data->document_record_id', $document->id)
        ->where('data->message', $message)
        ->exists();

    if ($exists) {
        return;
    }

    $createdAt = $timestamp ?? now();

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => DocumentWorkflowNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $recipient->id,
        'data' => json_encode([
            'document_record_id' => $document->id,
            'document_number' => $document->document_number,
            'title' => $document->title,
            'message' => $message,
            'url' => route('documents.show', $document, absolute: false),
        ], JSON_THROW_ON_ERROR),
        'read_at' => $isRead ? $createdAt->copy()->addHours(2) : null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
```

#### Pattern F: Physical PDF Byte Generation
To ensure `storage/app/private/` contains valid binary PDF files that can be streamed by `DocumentPublishedPdfController` and `DocumentDraftPdfController`:

```php
protected function getValidPdfContent(string $title, string $number): string
{
    return "%PDF-1.4\n" .
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n" .
        "4 0 obj\n<< /Length 55 >>\nstream\nBT /F1 12 Tf 72 712 Td (" . addcslashes("{$number}: {$title}", "()\\") . ") Tj ET\nendstream\nendobj\n" .
        "xref\n0 5\n" .
        "0000000000 65535 f \n" .
        "0000000009 00000 n \n" .
        "0000000058 00000 n \n" .
        "0000000115 00000 n \n" .
        "0000000206 00000 n \n" .
        "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n312\n%%EOF\n";
}
```

---

## 5. Execution & Transaction Safety

1. **Transactional Boundary**: All seeder insertions are wrapped in `DB::transaction(function () { ... })`.
2. **Failure Isolation**: If any constraint fails, the database rolls back to the pre-seeding state.
3. **Pest Feature Testing Compatibility**: Fully compatible with `RefreshDatabase` and SQLite in-memory savepoints.
4. **Clean Rerun Guarantees**:
   - Initial count after first run: $N$
   - Count after second run: $N$
   - Count after $K$ runs: $N$
   - Zero duplicate key constraint exceptions.

---

## 6. Implementation Action Plan for Delivery Agents

1. **M1 Explorer 2 (Taxonomy Implementer & DemoDataSeeder Author)**:
   - Apply updates to `database/seeders/DocumentTaxonomySeeder.php` to include `executive_order`, `peace-and-order`, `disaster-risk-reduction`, and rules.
   - Implement `database/seeders/DemoDataSeeder.php` following this architecture.
   - Update `database/seeders/DatabaseSeeder.php` to chain `DemoDataSeeder`.
2. **E2E Test Runner**:
   - Implement `tests/Feature/DemoDataSeederTest.php` asserting all 4 document types, 5+ categories, 7 lifecycle states, 7 puroks, idempotent rerun, and public verification endpoints.
