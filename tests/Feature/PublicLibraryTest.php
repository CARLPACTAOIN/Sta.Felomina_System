<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('only displays listed published documents with approved files', function () {
    Storage::fake();
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->published()->create(['title' => 'Community resolution']);
    DocumentFileVersion::factory()->for($document, 'documentRecord')->for($captain, 'approver')->create(['file_path' => 'approved.pdf', 'is_current' => true]);
    Storage::put('approved.pdf', 'PDF');
    $this->get(route('home'))->assertDontSee('Community resolution');
    $document->setPublicListing($captain, true);
    $this->get(route('home', ['q' => 'Community', 'document' => $document->verification_token]))->assertSee('Community resolution')->assertSee('Open PDF');
    $document->forceFill(['status' => DocumentStatus::Revoked])->save();
    $this->get(route('home', ['document' => $document->verification_token]))->assertDontSee('Community resolution')->assertSee('Document unavailable');
});

it('restricts listing to the captain', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->secretary()->create();
    $document = DocumentRecord::factory()->published()->create();
    expect($captain->can('managePublicListing', $document))->toBeTrue();
    expect($secretary->can('managePublicListing', $document))->toBeFalse();
});

it('records listing changes and rejects records without an approved file', function () {
    Storage::fake();
    $captain = User::factory()->captain()->create();
    $document = DocumentRecord::factory()->published()->create();
    expect(fn () => $document->setPublicListing($captain, true))->toThrow(ValidationException::class);
    Storage::put('official.pdf', 'PDF');
    DocumentFileVersion::factory()->for($document, 'documentRecord')->for($captain, 'approver')->create(['file_path' => 'official.pdf', 'is_current' => true]);
    $document->setPublicListing($captain, true);
    $this->assertDatabaseHas('audit_logs', ['action' => 'document_listed_publicly', 'auditable_id' => $document->id]);
    $document->setPublicListing($captain, false);
    $this->assertDatabaseHas('audit_logs', ['action' => 'document_unlisted_publicly', 'auditable_id' => $document->id]);
});
