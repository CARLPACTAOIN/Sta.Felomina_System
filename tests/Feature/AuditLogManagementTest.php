<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DocumentRecord;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-10-14 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('captain can view audit logs index page with filtering and export controls', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create();

    AuditLog::record($captain, 'document_approved', $doc, 'Resolution approved by Captain.', ['version_number' => 1]);

    $response = $this->actingAs($captain)->get(route('audit-logs.index'));

    $response->assertSuccessful()
        ->assertSee('Audit Logs')
        ->assertSee('Print / Save as PDF')
        ->assertSee('Export CSV')
        ->assertSee('Search keyword')
        ->assertSee('All actors')
        ->assertSee('All actions')
        ->assertSee('All record types')
        ->assertSee('Resolution approved by Captain.')
        ->assertSee('Document Approved')
        ->assertSee('v1');
});

test('secretary and purok chairperson cannot access audit logs index', function (UserRole $role) {
    $user = match ($role) {
        UserRole::Secretary => User::factory()->secretary()->create(),
        UserRole::PurokChairperson => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };

    $this->actingAs($user)->get(route('audit-logs.index'))->assertForbidden();
})->with([
    'secretary' => UserRole::Secretary,
    'purok chairperson' => UserRole::PurokChairperson,
]);

test('guest is redirected to login from audit log routes', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'index' => 'audit-logs.index',
    'print' => 'audit-logs.print',
    'export' => 'audit-logs.export',
]);

test('captain can filter audit logs by actor including system', function () {
    $captain = User::factory()->captain()->create(['name' => 'Captain Juan']);
    $secretary = User::factory()->secretary()->create(['name' => 'Secretary Maria']);
    $doc = DocumentRecord::factory()->create();

    AuditLog::record($captain, 'document_approved', $doc, 'Approved by Captain');
    AuditLog::record($secretary, 'document_submitted', $doc, 'Submitted by Secretary');

    // Create a system event (actor_id null)
    AuditLog::query()->create([
        'actor_id' => null,
        'action' => 'document_archived',
        'auditable_type' => DocumentRecord::class,
        'auditable_id' => $doc->id,
        'summary' => 'Automated archive event',
        'context' => null,
    ]);

    // Filter by captain
    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['actor_id' => $captain->id]))
        ->assertSuccessful()
        ->assertSee('Approved by Captain')
        ->assertDontSee('Submitted by Secretary')
        ->assertDontSee('Automated archive event');

    // Filter by secretary
    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['actor_id' => $secretary->id]))
        ->assertSuccessful()
        ->assertSee('Submitted by Secretary')
        ->assertDontSee('Approved by Captain')
        ->assertDontSee('Automated archive event');

    // Filter by system
    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['actor_id' => 'system']))
        ->assertSuccessful()
        ->assertSee('Automated archive event')
        ->assertDontSee('Approved by Captain')
        ->assertDontSee('Submitted by Secretary');
});

test('captain can filter audit logs by action type', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create();
    $resident = Resident::factory()->create();

    AuditLog::record($captain, 'document_approved', $doc, 'Resolution approved.');
    AuditLog::record($captain, 'document_returned', $doc, 'Returned for revision.', ['comment' => 'Missing attachment']);
    AuditLog::record($captain, 'resident_created', $resident, 'Resident profiled.');

    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['action' => 'document_returned']))
        ->assertSuccessful()
        ->assertSee('Returned for revision.')
        ->assertSee('Missing attachment')
        ->assertDontSee('Resolution approved.')
        ->assertDontSee('Resident profiled.');
});

test('captain can filter audit logs by entity type', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create();
    $household = Household::factory()->create();
    $resident = Resident::factory()->create();

    AuditLog::record($captain, 'document_approved', $doc, 'Document event log');
    AuditLog::record($captain, 'household_created', $household, 'Household event log');
    AuditLog::record($captain, 'resident_created', $resident, 'Resident event log');

    // Filter by DocumentRecord class
    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['entity_type' => DocumentRecord::class]))
        ->assertSuccessful()
        ->assertSee('Document event log')
        ->assertDontSee('Household event log')
        ->assertDontSee('Resident event log');

    // Filter by friendly short alias 'household'
    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['entity_type' => 'household']))
        ->assertSuccessful()
        ->assertSee('Household event log')
        ->assertDontSee('Document event log')
        ->assertDontSee('Resident event log');
});

test('captain can filter audit logs by date range', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create();

    $logPast = AuditLog::record($captain, 'document_submitted', $doc, 'Past log from October 1');
    $logPast->created_at = Carbon::parse('2026-10-01 10:00:00');
    $logPast->save();

    $logTarget = AuditLog::record($captain, 'document_approved', $doc, 'Target log from October 10');
    $logTarget->created_at = Carbon::parse('2026-10-10 14:30:00');
    $logTarget->save();

    $logFuture = AuditLog::record($captain, 'document_archived', $doc, 'Later log from October 20');
    $logFuture->created_at = Carbon::parse('2026-10-20 09:00:00');
    $logFuture->save();

    $this->actingAs($captain)
        ->get(route('audit-logs.index', [
            'date_from' => '2026-10-05',
            'date_to' => '2026-10-15',
        ]))
        ->assertSuccessful()
        ->assertSee('Target log from October 10')
        ->assertDontSee('Past log from October 1')
        ->assertDontSee('Later log from October 20');
});

test('captain can search audit logs by text keyword', function () {
    $captain = User::factory()->captain()->create(['name' => 'Captain Juan']);
    $doc = DocumentRecord::factory()->create();

    AuditLog::record($captain, 'document_approved', $doc, 'Resolution passed for road construction.');
    AuditLog::record($captain, 'account_created', $captain, 'Staff member onboarded.');

    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['q' => 'road construction']))
        ->assertSuccessful()
        ->assertSee('Resolution passed for road construction.')
        ->assertDontSee('Staff member onboarded.');
});

test('empty state renders appropriate message when filters yield no results', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create();

    AuditLog::record($captain, 'document_approved', $doc, 'Existing audit log');

    $this->actingAs($captain)
        ->get(route('audit-logs.index', ['q' => 'nonexistentquery12345']))
        ->assertSuccessful()
        ->assertSee('No matching audit events')
        ->assertSee('Reset all filters');
});

test('captain can view printable audit report layout with letterhead and summary metrics', function () {
    $captain = User::factory()->captain()->create(['name' => 'Hon. Roberto Santos']);
    $secretary = User::factory()->secretary()->create(['name' => 'Maria Clara']);
    $doc = DocumentRecord::factory()->create(['title' => 'Barangay Ordinance No. 1']);

    AuditLog::record($secretary, 'document_submitted', $doc, 'Document submitted for Captain approval.');
    AuditLog::record($captain, 'document_approved', $doc, 'Document approved and published.', ['version_number' => 1]);

    $response = $this->actingAs($captain)->get(route('audit-logs.print', [
        'action' => 'document_approved',
        'auto_print' => 1,
    ]));

    $response->assertSuccessful()
        ->assertViewIs('audit-logs.print')
        ->assertSee('Official Barangay Audit Trail Report')
        ->assertSee('BARANGAY STA. FELOMINA')
        ->assertSee('Republic of the Philippines')
        ->assertSee('Document approved and published.')
        ->assertDontSee('Document submitted for Captain approval.')
        ->assertSee('Total Logged Events')
        ->assertSee('Active Actors')
        ->assertSee('Generated and Certified by:')
        ->assertSee('Hon. Roberto Santos')
        ->assertSee('Punong Barangay')
        ->assertSee('window.print()', false);
});

test('secretary and purok chairperson cannot access audit print route', function (UserRole $role) {
    $user = match ($role) {
        UserRole::Secretary => User::factory()->secretary()->create(),
        UserRole::PurokChairperson => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };

    $this->actingAs($user)->get(route('audit-logs.print'))->assertForbidden();
})->with([
    'secretary' => UserRole::Secretary,
    'purok chairperson' => UserRole::PurokChairperson,
]);

test('captain can download csv export with filtered audit logs', function () {
    $captain = User::factory()->captain()->create(['name' => 'Captain Roberto', 'email' => 'roberto@barangay.gov.ph']);
    $secretary = User::factory()->secretary()->create(['name' => 'Secretary Elena', 'email' => 'elena@barangay.gov.ph']);
    $doc = DocumentRecord::factory()->create();

    AuditLog::record($secretary, 'document_submitted', $doc, 'Document submitted for Captain review.');
    AuditLog::record($captain, 'document_approved', $doc, 'Document approved and signed.', ['version_number' => 2]);

    $response = $this->actingAs($captain)->get(route('audit-logs.export', [
        'action' => 'document_approved',
    ]));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('audit-logs-sta-felomina-');

    $content = $response->streamedContent();

    // Check BOM and headers
    expect($content)->toContain("\xEF\xBB\xBF")
        ->toContain('Log ID')
        ->toContain('Timestamp (UTC)')
        ->toContain('Actor Name')
        ->toContain('Action Description')
        ->toContain('Entity Type')
        ->toContain('Context Details')
        ->toContain('Captain Roberto')
        ->toContain('roberto@barangay.gov.ph')
        ->toContain('document_approved')
        ->toContain('Document Approved')
        ->toContain('Document approved and signed.')
        ->toContain('version_number')
        ->not->toContain('Secretary Elena')
        ->not->toContain('document_submitted');
});

test('audit logs pagination preserves query parameters', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create();

    for ($i = 1; $i <= 30; $i++) {
        AuditLog::record($captain, 'document_approved', $doc, "Approved resolution #{$i}");
    }

    $response = $this->actingAs($captain)->get(route('audit-logs.index', [
        'action' => 'document_approved',
        'sort' => 'newest',
    ]));

    $response->assertSuccessful()
        ->assertSee('action=document_approved');
});

test('secretary and purok chairperson cannot access audit export route', function (UserRole $role) {
    $user = match ($role) {
        UserRole::Secretary => User::factory()->secretary()->create(),
        UserRole::PurokChairperson => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };

    $this->actingAs($user)->get(route('audit-logs.export'))->assertForbidden();
})->with([
    'secretary' => UserRole::Secretary,
    'purok chairperson' => UserRole::PurokChairperson,
]);
