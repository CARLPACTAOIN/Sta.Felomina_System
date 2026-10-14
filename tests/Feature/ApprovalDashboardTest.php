<?php

use App\Enums\DocumentStatus;
use App\Models\AuditLog;
use App\Models\DocumentCategory;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function storeApprovalWorkbenchDraftPdf(string $path = 'document-drafts/approval-workbench.pdf'): string
{
    Storage::disk('local')->put($path, '%PDF-1.4 approval workbench document');

    return $path;
}

test('captain can access the approval workbench', function () {
    $captain = User::factory()->captain()->create();

    $this->actingAs($captain)
        ->get(route('approvals.index'))
        ->assertOk()
        ->assertSee('Captain review queue');
});

test('secretary and purok chairperson are forbidden from approvals', function (string $role) {
    $user = match ($role) {
        'purok' => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };

    $this->actingAs($user)
        ->get(route('approvals.index'))
        ->assertForbidden();
})->with([
    'secretary' => 'secretary',
    'purok chairperson' => 'purok',
]);

test('approval workbench only lists pending approval documents', function () {
    $captain = User::factory()->captain()->create();
    $pending = DocumentRecord::factory()->pendingApproval()->create([
        'title' => 'Pending bridge ordinance',
    ]);
    $draft = DocumentRecord::factory()->create([
        'title' => 'Draft health memorandum',
    ]);

    Livewire::actingAs($captain)
        ->test('pages::approvals.index')
        ->assertSee($pending->title)
        ->assertDontSee($draft->title);
});

test('captain can search filter and sort the approval queue', function () {
    $captain = User::factory()->captain()->create();
    $resolution = DocumentType::factory()->create(['name' => 'Resolution', 'code' => 'RES']);
    $ordinance = DocumentType::factory()->create(['name' => 'Ordinance', 'code' => 'ORD']);
    $infrastructure = DocumentCategory::factory()->create(['name' => 'Infrastructure']);
    $health = DocumentCategory::factory()->create(['name' => 'Health']);

    $oldest = DocumentRecord::factory()->pendingApproval()->create([
        'document_type_id' => $resolution->id,
        'document_category_id' => $infrastructure->id,
        'document_number' => 'RES-2026-001',
        'title' => 'Drainage canal rehabilitation',
        'subject' => 'Project approval for drainage works',
        'keywords' => 'canal drainage',
        'submitted_at' => now()->subDays(3),
        'document_date' => now()->subDays(5),
    ]);

    $newest = DocumentRecord::factory()->pendingApproval()->create([
        'document_type_id' => $ordinance->id,
        'document_category_id' => $health->id,
        'document_number' => 'ORD-2026-002',
        'title' => 'Health sanitation campaign',
        'subject' => 'Barangay sanitation reminders',
        'keywords' => 'health sanitation',
        'submitted_at' => now()->subHour(),
        'document_date' => now()->subDay(),
    ]);

    $component = Livewire::actingAs($captain)
        ->test('pages::approvals.index')
        ->assertSee($oldest->title)
        ->assertSee($newest->title);

    $component
        ->set('search', 'canal')
        ->assertSee($oldest->title)
        ->assertDontSee($newest->title);

    $component
        ->set('search', '')
        ->set('typeId', (string) $ordinance->id)
        ->assertSee($newest->title)
        ->assertDontSee($oldest->title);

    $component
        ->set('typeId', '')
        ->set('categoryId', (string) $infrastructure->id)
        ->assertSee($oldest->title)
        ->assertDontSee($newest->title);

    $component
        ->set('categoryId', '')
        ->set('sort', 'oldest')
        ->assertSeeInOrder([$oldest->title, $newest->title]);
});

test('captain can approve a pending document inline', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $path = storeApprovalWorkbenchDraftPdf();
    $document = DocumentRecord::factory()
        ->pendingApproval()
        ->withDraftPdf($path)
        ->create([
            'created_by_id' => $secretary->id,
            'submitted_by_id' => $secretary->id,
            'draft_pdf_uploaded_by_id' => $secretary->id,
        ]);
    $token = $document->verification_token;

    Livewire::actingAs($captain)
        ->test('pages::approvals.index')
        ->call('approveDocument', $document->id)
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Published)
        ->and($document->approved_by_id)->toBe($captain->id)
        ->and($document->approved_at)->not->toBeNull()
        ->and($document->verification_token)->toBe($token)
        ->and(DocumentFileVersion::query()->where('document_record_id', $document->id)->count())->toBe(1)
        ->and($secretary->notifications()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'document_approved',
        'auditable_id' => $document->id,
    ]);
});

test('captain can return a pending document inline with a required comment', function () {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $document = DocumentRecord::factory()
        ->pendingApproval()
        ->withDraftPdf(storeApprovalWorkbenchDraftPdf())
        ->create([
            'created_by_id' => $secretary->id,
            'submitted_by_id' => $secretary->id,
        ]);

    $component = Livewire::actingAs($captain)
        ->test('pages::approvals.index')
        ->call('returnDocument', $document->id)
        ->assertHasErrors(["returnComments.{$document->id}" => ['required']]);

    $component
        ->set("returnComments.{$document->id}", 'Please correct the attachment footer.')
        ->call('returnDocument', $document->id)
        ->assertHasNoErrors();

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::NeedsRevision)
        ->and($document->return_comment)->toBe('Please correct the attachment footer.')
        ->and($document->returned_by_id)->toBe($captain->id)
        ->and($document->returned_at)->not->toBeNull()
        ->and($secretary->notifications()->count())->toBe(1);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $captain->id,
        'action' => 'document_returned',
        'auditable_id' => $document->id,
    ]);
});

test('inline approval actions reject stale non pending records', function (string $action) {
    Storage::fake('local');

    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()
        ->published()
        ->withDraftPdf(storeApprovalWorkbenchDraftPdf())
        ->create();

    $component = Livewire::actingAs($captain)
        ->test('pages::approvals.index');

    $component
        ->call($action, $document->id)
        ->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::Published);
    expect(AuditLog::query()->count())->toBe(0);
})->with([
    'approve' => 'approveDocument',
    'return' => 'returnDocument',
]);

test('unprivileged users cannot mount the approval workbench action surface', function (string $role) {
    $user = match ($role) {
        'purok' => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };

    Livewire::actingAs($user)
        ->test('pages::approvals.index')
        ->assertForbidden();
})->with([
    'secretary' => 'secretary',
    'purok chairperson' => 'purok',
]);

test('document record policy gates approval workflow abilities', function (string $role, DocumentStatus $status, bool $hasDraftPdf, bool $expectedApprove, bool $expectedReturn) {
    $user = match ($role) {
        'captain' => User::factory()->captain()->create(),
        'purok' => User::factory()->purokChairperson()->create(),
        default => User::factory()->create(),
    };

    $factory = DocumentRecord::factory()->state(['status' => $status]);
    if ($hasDraftPdf) {
        $factory = $factory->withDraftPdf();
    }

    $document = $factory->create();

    expect(Gate::forUser($user)->allows('approve', $document))->toBe($expectedApprove)
        ->and(Gate::forUser($user)->allows('returnForRevision', $document))->toBe($expectedReturn);
})->with([
    'captain pending with pdf' => ['captain', DocumentStatus::PendingApproval, true, true, true],
    'captain pending without pdf' => ['captain', DocumentStatus::PendingApproval, false, false, true],
    'captain draft with pdf' => ['captain', DocumentStatus::Draft, true, false, false],
    'secretary pending with pdf' => ['secretary', DocumentStatus::PendingApproval, true, false, false],
    'purok pending with pdf' => ['purok', DocumentStatus::PendingApproval, true, false, false],
]);
