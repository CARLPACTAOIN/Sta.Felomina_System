<?php

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Enums\UserRole;
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
| Tier 1: Feature Coverage Tests
|--------------------------------------------------------------------------
*/

test('tier 1: demo data seeder populates official document records across 2024 to 2026', function () {
    $this->seed(DemoDataSeeder::class);

    $years = DocumentRecord::query()->distinct()->pluck('document_year')->all();

    expect($years)
        ->toContain(2024)
        ->toContain(2025)
        ->toContain(2026)
        ->and(DocumentRecord::query()->count())->toBeGreaterThanOrEqual(24);
});

test('tier 1: demo data seeder covers all four required document types', function () {
    $this->seed(DemoDataSeeder::class);

    $prefixes = DocumentType::query()
        ->whereHas('documents')
        ->pluck('number_prefix')
        ->all();

    expect($prefixes)
        ->toContain('RES')
        ->toContain('ORD')
        ->toContain('EO')
        ->toContain('CERT');

    expect(DocumentRecord::query()->where('document_number', 'like', 'RES-%')->count())->toBeGreaterThan(0)
        ->and(DocumentRecord::query()->where('document_number', 'like', 'ORD-%')->count())->toBeGreaterThan(0)
        ->and(DocumentRecord::query()->where('document_number', 'like', 'EO-%')->count())->toBeGreaterThan(0)
        ->and(DocumentRecord::query()->where('document_number', 'like', 'CERT-%')->count())->toBeGreaterThan(0);
});

test('tier 1: demo data seeder covers all required document categories', function () {
    $this->seed(DemoDataSeeder::class);

    $categories = DocumentCategory::query()
        ->whereHas('documents')
        ->pluck('name')
        ->all();

    expect($categories)
        ->toContain('Health')
        ->toContain('Infrastructure')
        ->toContain('Peace & Order')
        ->toContain('Education')
        ->toContain('Disaster Risk Reduction');
});

test('tier 1: demo data seeder populates records for all seven lifecycle states', function () {
    $this->seed(DemoDataSeeder::class);

    $statuses = DocumentRecord::query()->distinct()->pluck('status')->map(fn ($s) => $s instanceof DocumentStatus ? $s->value : $s)->all();

    expect($statuses)
        ->toContain(DocumentStatus::Draft->value)
        ->toContain(DocumentStatus::PendingApproval->value)
        ->toContain(DocumentStatus::NeedsRevision->value)
        ->toContain(DocumentStatus::Published->value)
        ->toContain(DocumentStatus::Archived->value)
        ->toContain(DocumentStatus::Revoked->value)
        ->toContain(DocumentStatus::Superseded->value);
});

test('tier 1: demo data seeder populates households and residents across all seven puroks', function () {
    $this->seed(DemoDataSeeder::class);

    $puroks = Purok::query()->orderBy('sort_order')->get();
    expect($puroks)->toHaveCount(7);

    foreach ($puroks as $purok) {
        $householdCount = Household::query()->where('purok_id', $purok->id)->count();
        $residentCount = Resident::query()->where('purok_id', $purok->id)->count();

        expect($householdCount)->toBeGreaterThanOrEqual(4)
            ->and($residentCount)->toBeGreaterThanOrEqual(10);
    }

    expect(Household::query()->count())->toBeGreaterThanOrEqual(28)
        ->and(Resident::query()->count())->toBeGreaterThanOrEqual(80);
});

test('tier 1: demo data seeder creates historical audit logs and staff notifications', function () {
    $this->seed(DemoDataSeeder::class);

    expect(AuditLog::query()->count())->toBeGreaterThan(0);

    $auditActions = AuditLog::query()->distinct()->pluck('action')->all();
    expect($auditActions)->toContain('document_approved');

    $captain = User::role(UserRole::Captain->value)->first();
    $secretary = User::role(UserRole::Secretary->value)->first();

    expect($captain)->not->toBeNull()
        ->and($secretary)->not->toBeNull();

    $captainNotifications = DB::table('notifications')->where('notifiable_id', $captain->id)->count();
    $secretaryNotifications = DB::table('notifications')->where('notifiable_id', $secretary->id)->count();

    expect($captainNotifications + $secretaryNotifications)->toBeGreaterThan(0);

    expect($auditActions)
        ->toContain('document_created')
        ->toContain('document_submitted')
        ->toContain('document_approved')
        ->toContain('document_category_suggested')
        ->toContain('document_category_overridden')
        ->toContain('household_created')
        ->toContain('resident_created');
});

test('tier 1: demo data seeder creates standard-compliant physical pdf storage assets for documents', function () {
    $this->seed(DemoDataSeeder::class);

    $publishedDocuments = DocumentRecord::query()->where('status', DocumentStatus::Published)->get();
    expect($publishedDocuments->count())->toBeGreaterThan(0);

    foreach ($publishedDocuments as $doc) {
        $fileVersion = $doc->currentFileVersion();
        expect($fileVersion)->not->toBeNull();
        expect(Storage::disk('local')->exists($fileVersion->file_path))->toBeTrue();

        $pdfBytes = Storage::disk('local')->get($fileVersion->file_path);
        expect($pdfBytes)
            ->toStartWith('%PDF-1.4')
            ->toContain('xref')
            ->toContain('trailer')
            ->toContain('startxref')
            ->toEndWith("%%EOF\n");
    }
});

test('tier 1: demo data seeder populates infrastructure project metadata', function () {
    $this->seed(DemoDataSeeder::class);

    $projectDocs = DocumentRecord::query()->where('is_project_related', true)->get();
    expect($projectDocs->count())->toBeGreaterThanOrEqual(6);

    foreach ($projectDocs as $doc) {
        expect($doc->project_name)->not->toBeNull()
            ->and($doc->project_location)->not->toBeNull()
            ->and($doc->approved_budget)->not->toBeNull()
            ->and((float) $doc->approved_budget)->toBeGreaterThan(0);
    }

    $res2024 = DocumentRecord::query()->where('document_number', 'RES-2024-001')->firstOrFail();
    expect($res2024->is_project_related)->toBeTrue()
        ->and($res2024->project_name)->toContain('Farm-to-Market Road')
        ->and((float) $res2024->approved_budget)->toBe(2500000.00);
});

test('tier 1: demo data seeder populates rule-based categorization suggestions and overrides', function () {
    $this->seed(DemoDataSeeder::class);

    $suggestedDocs = DocumentRecord::query()->whereNotNull('suggested_document_category_id')->get();
    expect($suggestedDocs->count())->toBeGreaterThanOrEqual(10);

    $overriddenDoc = DocumentRecord::query()->whereNotNull('category_overridden_at')->first();
    expect($overriddenDoc)->not->toBeNull()
        ->and($overriddenDoc->category_overridden_by_id)->not->toBeNull()
        ->and($overriddenDoc->suggested_document_category_id)->not->toBeNull()
        ->and($overriddenDoc->document_category_id)->not->toBe($overriddenDoc->suggested_document_category_id);

    $overrideAudit = AuditLog::query()->where('action', 'document_category_overridden')->first();
    expect($overrideAudit)->not->toBeNull()
        ->and($overrideAudit->auditable_id)->toBe($overriddenDoc->id);
});

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases
|--------------------------------------------------------------------------
*/

test('tier 2: demo data seeder is strictly idempotent across consecutive reruns without duplicate key violations', function () {
    $this->seed(DemoDataSeeder::class);

    $docCountFirst = DocumentRecord::query()->count();
    $householdCountFirst = Household::query()->count();
    $residentCountFirst = Resident::query()->count();
    $fileVersionCountFirst = DocumentFileVersion::query()->count();
    $auditLogCountFirst = AuditLog::query()->count();
    $notificationCountFirst = DB::table('notifications')->count();

    // Rerun seeder immediately
    $this->seed(DemoDataSeeder::class);

    expect(DocumentRecord::query()->count())->toBe($docCountFirst)
        ->and(Household::query()->count())->toBe($householdCountFirst)
        ->and(Resident::query()->count())->toBe($residentCountFirst)
        ->and(DocumentFileVersion::query()->count())->toBe($fileVersionCountFirst)
        ->and(AuditLog::query()->count())->toBe($auditLogCountFirst)
        ->and(DB::table('notifications')->count())->toBe($notificationCountFirst);
});

test('tier 2: demo data seeder bootstraps foundational seeders if database is fresh', function () {
    // Starting with clean in-memory database without calling prior seeders manually
    $this->seed(DemoDataSeeder::class);

    expect(Purok::query()->count())->toBe(7)
        ->and(User::role(UserRole::Captain->value)->count())->toBe(1)
        ->and(User::role(UserRole::Secretary->value)->count())->toBeGreaterThanOrEqual(1)
        ->and(DocumentType::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(DocumentCategory::query()->count())->toBeGreaterThanOrEqual(5);
});

test('tier 2: voter registration boundary enforces false for minors under 18', function () {
    $this->seed(DemoDataSeeder::class);

    $minors = Resident::query()
        ->whereDate('birthdate', '>', Carbon::now()->subYears(18))
        ->get();

    expect($minors->count())->toBeGreaterThan(0);

    foreach ($minors as $minor) {
        expect($minor->is_registered_voter)->toBeFalse();
    }

    $adults = Resident::query()
        ->whereDate('birthdate', '<=', Carbon::now()->subYears(18))
        ->get();

    expect($adults->count())->toBeGreaterThan(0);
    expect($adults->where('is_registered_voter', true)->count())->toBeGreaterThan(0);
});

test('tier 2: needs revision documents contain required return comments and actor timestamps', function () {
    $this->seed(DemoDataSeeder::class);

    $returnedDocs = DocumentRecord::query()->where('status', DocumentStatus::NeedsRevision)->get();
    expect($returnedDocs->count())->toBeGreaterThan(0);

    foreach ($returnedDocs as $doc) {
        expect($doc->return_comment)->not->toBeNull()
            ->and(trim($doc->return_comment))->not->toBeEmpty()
            ->and($doc->returned_at)->not->toBeNull()
            ->and($doc->returned_by_id)->not->toBeNull();
    }
});

test('tier 2: archived and revoked documents contain required reasons and actor timestamps', function () {
    $this->seed(DemoDataSeeder::class);

    $archivedDocs = DocumentRecord::query()->where('status', DocumentStatus::Archived)->get();
    expect($archivedDocs->count())->toBeGreaterThan(0);
    foreach ($archivedDocs as $doc) {
        expect($doc->archive_reason)->not->toBeNull()
            ->and(trim($doc->archive_reason))->not->toBeEmpty()
            ->and($doc->archived_at)->not->toBeNull()
            ->and($doc->archived_by_id)->not->toBeNull();
    }

    $revokedDocs = DocumentRecord::query()->where('status', DocumentStatus::Revoked)->get();
    expect($revokedDocs->count())->toBeGreaterThan(0);
    foreach ($revokedDocs as $doc) {
        expect($doc->revoke_reason)->not->toBeNull()
            ->and(trim($doc->revoke_reason))->not->toBeEmpty()
            ->and($doc->revoked_at)->not->toBeNull()
            ->and($doc->revoked_by_id)->not->toBeNull();
    }
});

test('tier 2: pending approval documents have valid draft pdf paths assigned', function () {
    $this->seed(DemoDataSeeder::class);

    $pendingDocs = DocumentRecord::query()->where('status', DocumentStatus::PendingApproval)->get();
    expect($pendingDocs->count())->toBeGreaterThan(0);

    foreach ($pendingDocs as $doc) {
        expect($doc->draft_pdf_path)->not->toBeNull()
            ->and(Storage::disk('local')->exists($doc->draft_pdf_path))->toBeTrue();
    }
});

/*
|--------------------------------------------------------------------------
| Tier 3: Cross-Feature Combinations
|--------------------------------------------------------------------------
*/

test('tier 3: resident population satisfies all four resident statuses across puroks', function () {
    $this->seed(DemoDataSeeder::class);

    $statuses = Resident::query()->distinct()->pluck('status')->map(fn ($s) => $s instanceof ResidentStatus ? $s->value : $s)->all();

    expect($statuses)
        ->toContain(ResidentStatus::Active->value)
        ->toContain(ResidentStatus::Transferred->value)
        ->toContain(ResidentStatus::Deceased->value)
        ->toContain(ResidentStatus::Inactive->value);

    expect(Resident::query()->where('status', ResidentStatus::Active)->count())->toBeGreaterThan(50)
        ->and(Resident::query()->where('status', ResidentStatus::Transferred)->count())->toBeGreaterThan(0)
        ->and(Resident::query()->where('status', ResidentStatus::Deceased)->count())->toBeGreaterThan(0)
        ->and(Resident::query()->where('status', ResidentStatus::Inactive)->count())->toBeGreaterThan(0);
});

test('tier 3: household head integrity matches living adult resident within same household', function () {
    $this->seed(DemoDataSeeder::class);

    $households = Household::query()->with('residents')->get();
    expect($households->count())->toBeGreaterThanOrEqual(28);

    foreach ($households as $household) {
        expect($household->residents->count())->toBeGreaterThan(0);

        $matchingResident = $household->residents->first(function ($resident) use ($household) {
            return $resident->fullName() === $household->household_head_name
                || $resident->normalized_full_name === Resident::normalizeFullName($household->household_head_name, null, '', null);
        });

        expect($matchingResident)->not->toBeNull()
            ->and($matchingResident->birthdate->diffInYears(now()))->toBeGreaterThanOrEqual(18);
    }
});

test('tier 3: superseded document chain maintains valid replacement reference and target published document', function () {
    $this->seed(DemoDataSeeder::class);

    $supersededDocs = DocumentRecord::query()
        ->where('status', DocumentStatus::Superseded)
        ->get();

    expect($supersededDocs->count())->toBeGreaterThan(0);

    foreach ($supersededDocs as $doc) {
        expect($doc->supersede_reason)->not->toBeNull()
            ->and($doc->superseded_at)->not->toBeNull()
            ->and($doc->superseded_by_id)->not->toBeNull()
            ->and($doc->superseded_by_document_record_id)->not->toBeNull();

        $replacement = DocumentRecord::find($doc->superseded_by_document_record_id);
        expect($replacement)->not->toBeNull()
            ->and($replacement->status)->toBe(DocumentStatus::Published);
    }
});

test('tier 3: document file version records exist and are marked current for approved documents', function () {
    $this->seed(DemoDataSeeder::class);

    $approvedStatuses = [
        DocumentStatus::Published,
        DocumentStatus::Archived,
        DocumentStatus::Revoked,
        DocumentStatus::Superseded,
    ];

    $records = DocumentRecord::query()->whereIn('status', $approvedStatuses)->get();
    expect($records->count())->toBeGreaterThan(0);

    foreach ($records as $doc) {
        $versions = DocumentFileVersion::query()->where('document_record_id', $doc->id)->get();
        expect($versions->count())->toBeGreaterThanOrEqual(1);

        $currentVersion = $versions->firstWhere('is_current', true);
        expect($currentVersion)->not->toBeNull()
            ->and($currentVersion->approved_at)->not->toBeNull()
            ->and($currentVersion->mime_type)->toBe('application/pdf');
    }
});

/*
|--------------------------------------------------------------------------
| Tier 4: Real-World Scenarios
|--------------------------------------------------------------------------
*/

test('tier 4: public qr verification tokens on seeded documents resolve correctly via http endpoints', function () {
    $this->seed(DemoDataSeeder::class);

    $publishedDoc = DocumentRecord::query()->where('status', DocumentStatus::Published)->firstOrFail();
    $archivedDoc = DocumentRecord::query()->where('status', DocumentStatus::Archived)->firstOrFail();
    $revokedDoc = DocumentRecord::query()->where('status', DocumentStatus::Revoked)->firstOrFail();
    $supersededDoc = DocumentRecord::query()->where('status', DocumentStatus::Superseded)->firstOrFail();

    // Published verification
    $this->get(route('verification.show', $publishedDoc->verification_token))
        ->assertSuccessful()
        ->assertSee('Verified Official Barangay Document')
        ->assertSee($publishedDoc->document_number)
        ->assertSee($publishedDoc->title);

    // Archived verification
    $this->get(route('verification.show', $archivedDoc->verification_token))
        ->assertSuccessful()
        ->assertSee('Archived Official Record')
        ->assertSee($archivedDoc->document_number);

    // Revoked verification
    $this->get(route('verification.show', $revokedDoc->verification_token))
        ->assertSuccessful()
        ->assertSee('Revoked Document')
        ->assertSee($revokedDoc->document_number);

    // Superseded verification
    $this->get(route('verification.show', $supersededDoc->verification_token))
        ->assertSuccessful()
        ->assertSee('Superseded Document')
        ->assertSee($supersededDoc->document_number);
});

test('tier 4: physical storage pdf files stream cleanly for published documents and 404 for draft documents', function () {
    $this->seed(DemoDataSeeder::class);

    $publishedDoc = DocumentRecord::query()->where('status', DocumentStatus::Published)->firstOrFail();
    $draftDoc = DocumentRecord::query()->where('status', DocumentStatus::Draft)->firstOrFail();

    // Published doc streams PDF
    $this->get(route('verification.document', $publishedDoc->verification_token))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    // Draft doc 404s
    $this->get(route('verification.document', $draftDoc->verification_token))
        ->assertNotFound();
});

test('tier 4: captain and secretary dashboards display accurate seeded metrics and pending items', function () {
    $this->seed(DemoDataSeeder::class);

    $captain = User::role(UserRole::Captain->value)->firstOrFail();
    $secretary = User::role(UserRole::Secretary->value)->firstOrFail();

    $pendingCount = DocumentRecord::query()->where('status', DocumentStatus::PendingApproval)->count();
    $residentCount = Resident::query()->count();

    expect($pendingCount)->toBeGreaterThan(0)
        ->and($residentCount)->toBeGreaterThanOrEqual(80);

    // Captain dashboard shows pending approval count and resident count
    $this->actingAs($captain)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee((string) $residentCount);

    // Secretary dashboard loads successfully
    $this->actingAs($secretary)
        ->get(route('dashboard'))
        ->assertSuccessful();
});
