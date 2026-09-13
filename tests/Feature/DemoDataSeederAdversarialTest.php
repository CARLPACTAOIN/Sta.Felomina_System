<?php

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Models\AuditLog;
use App\Models\DocumentCategory;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Empirical Challenger: DemoDataSeeder Stress & Adversarial Suite
|--------------------------------------------------------------------------
*/

test('adversarial: consecutive 3x seeding maintains exact table counts across all 10 core tables', function () {
    // Run 1
    $this->seed(DemoDataSeeder::class);

    $tables = [
        'puroks' => fn () => Purok::query()->count(),
        'users' => fn () => User::query()->count(),
        'document_types' => fn () => DocumentType::query()->count(),
        'document_categories' => fn () => DocumentCategory::query()->count(),
        'document_records' => fn () => DocumentRecord::query()->count(),
        'document_file_versions' => fn () => DocumentFileVersion::query()->count(),
        'households' => fn () => Household::query()->count(),
        'residents' => fn () => Resident::query()->count(),
        'audit_logs' => fn () => AuditLog::query()->count(),
        'notifications' => fn () => DB::table('notifications')->count(),
    ];

    $countsRun1 = [];
    foreach ($tables as $name => $counter) {
        $countsRun1[$name] = $counter();
    }

    // Run 2
    $this->seed(DemoDataSeeder::class);

    $countsRun2 = [];
    foreach ($tables as $name => $counter) {
        $countsRun2[$name] = $counter();
        expect($countsRun2[$name])->toBe(
            $countsRun1[$name],
            "Table [{$name}] count inflated after 2nd seed run (Run 1: {$countsRun1[$name]}, Run 2: {$countsRun2[$name]})"
        );
    }

    // Run 3
    $this->seed(DemoDataSeeder::class);

    foreach ($tables as $name => $counter) {
        $countRun3 = $counter();
        expect($countRun3)->toBe(
            $countsRun1[$name],
            "Table [{$name}] count inflated after 3rd seed run (Run 1: {$countsRun1[$name]}, Run 3: {$countRun3})"
        );
    }
});

test('adversarial: no duplicate business keys or unique violations across all 10 core tables', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    // 1. Puroks: code must be unique
    $duplicatePurokCodes = Purok::query()
        ->select('code', DB::raw('count(*) as count'))
        ->groupBy('code')
        ->havingRaw('count(*) > 1')
        ->pluck('code');
    expect($duplicatePurokCodes->all())->toBeEmpty();

    // 2. Users: email must be unique
    $duplicateEmails = User::query()
        ->select('email', DB::raw('count(*) as count'))
        ->groupBy('email')
        ->havingRaw('count(*) > 1')
        ->pluck('email');
    expect($duplicateEmails->all())->toBeEmpty();

    // 3. Document types: code must be unique
    $duplicateTypeCodes = DocumentType::query()
        ->select('code', DB::raw('count(*) as count'))
        ->groupBy('code')
        ->havingRaw('count(*) > 1')
        ->pluck('code');
    expect($duplicateTypeCodes->all())->toBeEmpty();

    // 4. Document categories: slug must be unique
    $duplicateCategorySlugs = DocumentCategory::query()
        ->select('slug', DB::raw('count(*) as count'))
        ->groupBy('slug')
        ->havingRaw('count(*) > 1')
        ->pluck('slug');
    expect($duplicateCategorySlugs->all())->toBeEmpty();

    // 5. Document records: (document_type_id, document_year, document_number) must be unique
    $duplicateDocNumbers = DocumentRecord::query()
        ->select('document_type_id', 'document_year', 'document_number', DB::raw('count(*) as count'))
        ->groupBy('document_type_id', 'document_year', 'document_number')
        ->havingRaw('count(*) > 1')
        ->get();
    expect($duplicateDocNumbers->all())->toBeEmpty();

    // 6. Document file versions: (document_record_id, version_number) must be unique
    $duplicateVersions = DocumentFileVersion::query()
        ->select('document_record_id', 'version_number', DB::raw('count(*) as count'))
        ->groupBy('document_record_id', 'version_number')
        ->havingRaw('count(*) > 1')
        ->get();
    expect($duplicateVersions->all())->toBeEmpty();

    // 7. Households: household_code must be unique
    $duplicateHouseholdCodes = Household::query()
        ->select('household_code', DB::raw('count(*) as count'))
        ->groupBy('household_code')
        ->havingRaw('count(*) > 1')
        ->pluck('household_code');
    expect($duplicateHouseholdCodes->all())->toBeEmpty();

    // 8. Residents: resident_code must be unique
    $duplicateResidentCodes = Resident::query()
        ->select('resident_code', DB::raw('count(*) as count'))
        ->groupBy('resident_code')
        ->havingRaw('count(*) > 1')
        ->pluck('resident_code');
    expect($duplicateResidentCodes->all())->toBeEmpty();

    // 9. Audit logs: auditable_type + auditable_id + action must be unique
    $duplicateAuditLogs = AuditLog::query()
        ->select('auditable_type', 'auditable_id', 'action', DB::raw('count(*) as count'))
        ->groupBy('auditable_type', 'auditable_id', 'action')
        ->havingRaw('count(*) > 1')
        ->get();
    expect($duplicateAuditLogs->all())->toBeEmpty();

    // 10. Notifications: notification id must be unique
    $duplicateNotifications = DB::table('notifications')
        ->select('id', DB::raw('count(*) as count'))
        ->groupBy('id')
        ->havingRaw('count(*) > 1')
        ->pluck('id');
    expect($duplicateNotifications->all())->toBeEmpty();
});

test('adversarial: voter registration boundary - zero minors are registered voters and registered voters are >= 18', function () {
    $this->seed(DemoDataSeeder::class);

    $allResidents = Resident::query()->get();
    expect($allResidents->count())->toBeGreaterThanOrEqual(80);

    $now = Carbon::now();

    $minors = $allResidents->filter(fn (Resident $r) => $r->birthdate->diffInYears($now) < 18);
    expect($minors->count())->toBeGreaterThan(0);

    foreach ($minors as $minor) {
        expect($minor->is_registered_voter)
            ->toBeFalse("Minor resident [{$minor->resident_code}] ({$minor->first_name} {$minor->last_name}, born {$minor->birthdate->format('Y-m-d')}) has is_registered_voter = true");
    }

    $registeredVoters = $allResidents->filter(fn (Resident $r) => $r->is_registered_voter);
    expect($registeredVoters->count())->toBeGreaterThan(0);

    foreach ($registeredVoters as $voter) {
        $age = $voter->birthdate->diffInYears($now);
        expect($age)->toBeGreaterThanOrEqual(
            18,
            "Registered voter [{$voter->resident_code}] is under 18 years old (calculated age: {$age})"
        );
    }
});

test('adversarial: household head boundary - every household head is a valid, living, adult resident in the household', function () {
    $this->seed(DemoDataSeeder::class);

    $households = Household::query()->with('residents')->get();
    expect($households->count())->toBe(28);

    $now = Carbon::now();

    foreach ($households as $household) {
        expect($household->household_head_name)->not->toBeEmpty();

        $matchingResident = $household->residents->first(function (Resident $resident) use ($household) {
            $fullName = trim("{$resident->first_name} {$resident->middle_name} {$resident->last_name} {$resident->suffix}");
            $fullNameNoSuffix = trim("{$resident->first_name} {$resident->middle_name} {$resident->last_name}");
            $firstLast = trim("{$resident->first_name} {$resident->last_name}");

            return $fullName === $household->household_head_name
                || $fullNameNoSuffix === $household->household_head_name
                || $firstLast === $household->household_head_name
                || $resident->normalized_full_name === Resident::normalizeFullName($household->household_head_name, null, '', null);
        });

        expect($matchingResident)->not->toBeNull(
            "Household [{$household->household_code}] has head name '{$household->household_head_name}' with no matching resident in the household."
        );

        expect($matchingResident->status)->not->toBe(
            ResidentStatus::Deceased,
            "Household [{$household->household_code}] head '{$household->household_head_name}' is marked Deceased."
        );

        $age = $matchingResident->birthdate->diffInYears($now);
        expect($age)->toBeGreaterThanOrEqual(
            18,
            "Household [{$household->household_code}] head '{$household->household_head_name}' is a minor (age: {$age})."
        );
    }
});

test('adversarial: relational integrity - zero orphaned records across all models and foreign keys', function () {
    $this->seed(DemoDataSeeder::class);

    // 1. Residents: household_id exists and matches purok_id
    $residents = Resident::query()->with(['household', 'purok'])->get();
    foreach ($residents as $resident) {
        expect($resident->household)->not->toBeNull("Resident [{$resident->resident_code}] has invalid or null household_id");
        expect($resident->purok)->not->toBeNull("Resident [{$resident->resident_code}] has invalid or null purok_id");
        expect($resident->purok_id)->toBe($resident->household->purok_id, "Resident [{$resident->resident_code}] purok_id ({$resident->purok_id}) does not match household purok_id ({$resident->household->purok_id})");
    }

    // 2. Households: purok_id exists
    $households = Household::query()->with('purok')->get();
    foreach ($households as $household) {
        expect($household->purok)->not->toBeNull("Household [{$household->household_code}] has invalid or null purok_id");
    }

    // 3. DocumentRecords: type, category, and creator exist
    $docs = DocumentRecord::query()->with(['type', 'category', 'creator', 'supersededByDocumentRecord'])->get();
    foreach ($docs as $doc) {
        expect($doc->type)->not->toBeNull("Document [{$doc->document_number}] has invalid or null document_type_id");
        expect($doc->category)->not->toBeNull("Document [{$doc->document_number}] has invalid or null document_category_id");
        expect($doc->creator)->not->toBeNull("Document [{$doc->document_number}] has invalid or null created_by_id");

        if ($doc->status === DocumentStatus::Superseded) {
            expect($doc->superseded_by_document_record_id)->not->toBeNull("Superseded document [{$doc->document_number}] missing superseded_by_document_record_id");
            expect($doc->supersededByDocumentRecord)->not->toBeNull("Superseded document [{$doc->document_number}] points to non-existent replacement record");
            expect($doc->supersededByDocumentRecord->status)->toBe(DocumentStatus::Published, "Replacement document for [{$doc->document_number}] is not Published");
        }
    }

    // 4. DocumentFileVersions: record exists and physical file exists
    $versions = DocumentFileVersion::query()->with('documentRecord')->get();
    foreach ($versions as $version) {
        expect($version->documentRecord)->not->toBeNull("DocumentFileVersion [{$version->id}] has null documentRecord");
        expect(Storage::disk('local')->exists($version->file_path))->toBeTrue("DocumentFileVersion [{$version->id}] physical file does not exist: {$version->file_path}");
    }

    // 5. Working documents draft PDF physical files exist
    $workingDocs = DocumentRecord::query()->whereIn('status', [
        DocumentStatus::Draft,
        DocumentStatus::PendingApproval,
        DocumentStatus::NeedsRevision,
    ])->get();
    foreach ($workingDocs as $doc) {
        expect($doc->draft_pdf_path)->not->toBeNull("Working document [{$doc->document_number}] missing draft_pdf_path");
        expect(Storage::disk('local')->exists($doc->draft_pdf_path))->toBeTrue("Working document [{$doc->document_number}] draft PDF does not exist: {$doc->draft_pdf_path}");
    }

    // 6. AuditLogs: auditable target exists
    $auditLogs = AuditLog::query()->get();
    foreach ($auditLogs as $log) {
        $target = $log->auditable_type::find($log->auditable_id);
        expect($target)->not->toBeNull("AuditLog [{$log->id}] points to non-existent auditable: {$log->auditable_type} #{$log->auditable_id}");
        if ($log->actor_id) {
            expect(User::find($log->actor_id))->not->toBeNull("AuditLog [{$log->id}] points to non-existent actor: #{$log->actor_id}");
        }
    }

    // 7. Notifications: notifiable user exists and referenced doc exists
    $notifications = DB::table('notifications')->get();
    foreach ($notifications as $notification) {
        $user = User::find($notification->notifiable_id);
        expect($user)->not->toBeNull("Notification [{$notification->id}] points to non-existent user #{$notification->notifiable_id}");

        $data = json_decode($notification->data, true);
        if (isset($data['document_record_id'])) {
            $doc = DocumentRecord::find($data['document_record_id']);
            expect($doc)->not->toBeNull("Notification [{$notification->id}] points to non-existent document #{$data['document_record_id']}");
        }
    }
});
