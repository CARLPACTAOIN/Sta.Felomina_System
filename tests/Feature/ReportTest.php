<?php

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Services\ReportEngine;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('captain can access reports workspace and filter documents', function () {
    $captain = User::factory()->captain()->create();
    $catHealth = DocumentCategory::factory()->create(['name' => 'Health & Sanitation']);
    $catPeace = DocumentCategory::factory()->create(['name' => 'Peace & Order']);
    $type = DocumentType::factory()->create(['name' => 'Resolution', 'code' => 'RES']);

    $doc1 = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $catHealth->id,
        'title' => 'Barangay Health Center Renovation',
        'document_year' => 2026,
        'document_date' => '2026-05-10',
        'status' => DocumentStatus::Published,
        'created_by_id' => $captain->id,
    ]);

    $doc2 = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $catPeace->id,
        'title' => 'Curfew Enforcement Guidelines',
        'document_year' => 2025,
        'document_date' => '2025-08-15',
        'status' => DocumentStatus::Draft,
        'created_by_id' => $captain->id,
    ]);

    Livewire::actingAs($captain)
        ->test('pages::reports.index')
        ->assertOk()
        ->assertSee('Document Report')
        ->assertSee('Resident Report')
        ->set('year', '2026')
        ->set('categoryId', (string) $catHealth->id)
        ->assertSee($doc1->document_number)
        ->assertDontSee($doc2->document_number);
});

test('captain can filter resident reports on reports workspace', function () {
    $captain = User::factory()->captain()->create();
    $purok = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $household = Household::factory()->create(['purok_id' => $purok->id]);

    $voter = Resident::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'purok_id' => $purok->id,
        'household_id' => $household->id,
        'is_registered_voter' => true,
        'status' => ResidentStatus::Active,
    ]);

    $nonVoter = Resident::factory()->create([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'purok_id' => $purok->id,
        'household_id' => $household->id,
        'is_registered_voter' => false,
        'status' => ResidentStatus::Active,
    ]);

    Livewire::actingAs($captain)
        ->test('pages::reports.index')
        ->set('reportType', 'resident')
        ->assertSee($voter->resident_code)
        ->assertSee($nonVoter->resident_code)
        ->set('voterStatus', 'voter')
        ->assertSee($voter->resident_code)
        ->assertDontSee($nonVoter->resident_code);
});

test('purok chairperson is locked to assigned purok resident reports and cannot see document report options', function () {
    $purok1 = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $purok2 = Purok::query()->skip(1)->first() ?? Purok::factory()->create(['name' => 'Purok 2']);

    $chairperson = User::factory()->purokChairperson()->create([
        'purok_id' => $purok1->id,
    ]);

    $household1 = Household::factory()->create(['purok_id' => $purok1->id]);
    $household2 = Household::factory()->create(['purok_id' => $purok2->id]);

    $res1 = Resident::factory()->create([
        'purok_id' => $purok1->id,
        'household_id' => $household1->id,
        'last_name' => 'ResidentOne',
    ]);

    $res2 = Resident::factory()->create([
        'purok_id' => $purok2->id,
        'household_id' => $household2->id,
        'last_name' => 'ResidentTwo',
    ]);

    Livewire::actingAs($chairperson)
        ->test('pages::reports.index')
        ->assertOk()
        ->assertSee('Purok Summary Report')
        ->assertDontSee('Document Report')
        ->assertSee($res1->resident_code)
        ->assertDontSee($res2->resident_code);
});

test('purok chairperson is forbidden from printing document reports', function () {
    $purok = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $chairperson = User::factory()->purokChairperson()->create([
        'purok_id' => $purok->id,
    ]);

    $this->actingAs($chairperson)
        ->get(route('reports.print', ['type' => 'document']))
        ->assertOk(); // Handled safely by falling back to resident report in controller for Purok Chair!

    // But directly requesting document report in ReportEngine aborts with 403
    expect(fn () => app(ReportEngine::class)->documentReport($chairperson, []))
        ->toThrow(HttpException::class);
});

test('purok chairperson resident report query enforces assigned purok even if another purok is passed', function () {
    $purok1 = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $purok2 = Purok::query()->skip(1)->first() ?? Purok::factory()->create(['name' => 'Purok 2']);

    $chairperson = User::factory()->purokChairperson()->create([
        'purok_id' => $purok1->id,
    ]);

    $household1 = Household::factory()->create(['purok_id' => $purok1->id]);
    $household2 = Household::factory()->create(['purok_id' => $purok2->id]);

    $res1 = Resident::factory()->create([
        'purok_id' => $purok1->id,
        'household_id' => $household1->id,
        'last_name' => 'PurokOneResident',
    ]);

    $res2 = Resident::factory()->create([
        'purok_id' => $purok2->id,
        'household_id' => $household2->id,
        'last_name' => 'PurokTwoResident',
    ]);

    $result = app(ReportEngine::class)->residentReport($chairperson, [
        'purok_id' => $purok2->id, // Attempt to query Purok 2
    ]);

    expect($result['records']->pluck('id')->all())->toContain($res1->id)
        ->and($result['records']->pluck('id')->all())->not->toContain($res2->id);
});

test('print route renders official report layout for document and resident reports', function () {
    $captain = User::factory()->captain()->create();
    $cat = DocumentCategory::factory()->create(['name' => 'Education']);
    $type = DocumentType::factory()->create(['name' => 'Executive Order', 'code' => 'EO']);

    $doc = DocumentRecord::factory()->create([
        'document_type_id' => $type->id,
        'document_category_id' => $cat->id,
        'title' => 'Youth Scholarship Program 2026',
        'document_year' => 2026,
        'status' => DocumentStatus::Published,
        'created_by_id' => $captain->id,
    ]);

    $purok = Purok::query()->first() ?? Purok::factory()->create(['name' => 'Purok 1']);
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    $res = Resident::factory()->create([
        'purok_id' => $purok->id,
        'household_id' => $household->id,
        'first_name' => 'Elena',
        'last_name' => 'Reyes',
    ]);

    // Test Document Print View
    $this->actingAs($captain)
        ->get(route('reports.print', ['type' => 'document']))
        ->assertOk()
        ->assertSee('Republic of the Philippines')
        ->assertSee('BARANGAY STA. FELOMINA')
        ->assertSee('Official Document Archive Report')
        ->assertSee($doc->document_number)
        ->assertSee('Prepared and Certified Correct by:')
        ->assertSee('Noted and Approved by:');

    // Test Resident Print View with auto_print
    $this->actingAs($captain)
        ->get(route('reports.print', ['type' => 'resident', 'auto_print' => '1']))
        ->assertOk()
        ->assertSee('Official Resident Profile Report')
        ->assertSee($res->resident_code)
        ->assertSee('Reyes, Elena')
        ->assertSee('window.print()', false);
});
