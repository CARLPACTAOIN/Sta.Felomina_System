<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\ResidentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CategorizationRule;
use App\Models\DocumentCategory;
use App\Models\DocumentFileVersion;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->ensurePrerequisites();
        $this->ensureTaxonomyExpanded();

        $captain = User::role(UserRole::Captain->value)->firstOrFail();
        $secretary = User::role(UserRole::Secretary->value)->where('is_active', true)->firstOrFail();

        $puroks = Purok::query()->where('is_active', true)->get()->keyBy('code');
        $chairpersons = User::query()
            ->role(UserRole::PurokChairperson->value)
            ->with('purok')
            ->get()
            ->keyBy(fn (User $user): string => $user->purok->code ?? '');

        DB::transaction(function () use ($captain, $secretary, $puroks, $chairpersons): void {
            $this->seedHouseholdsAndResidents($puroks, $chairpersons);
            $this->seedOfficialDocumentsPhase1($captain, $secretary);
            $this->seedOfficialDocumentsPhase2($captain, $secretary);
            $this->seedAuditLogs($captain, $secretary);
            $this->seedNotifications($captain, $secretary);
        });
    }

    /**
     * Ensure baseline lookup tables and staff accounts are seeded.
     */
    protected function ensurePrerequisites(): void
    {
        $this->callOnce([
            DocumentTaxonomySeeder::class,
            PurokSeeder::class,
            RolePermissionSeeder::class,
            AccountSeeder::class,
        ]);

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

    /**
     * Expand Document Taxonomy for required types, categories, and keyword rules.
     */
    protected function ensureTaxonomyExpanded(): void
    {
        DocumentType::query()->updateOrCreate(
            ['code' => 'executive_order'],
            [
                'name' => 'Executive Order',
                'number_prefix' => 'EO',
                'description' => 'Official executive orders and administrative directives issued by the Punong Barangay.',
                'is_active' => true,
            ]
        );

        $peaceAndOrder = DocumentCategory::query()->updateOrCreate(
            ['slug' => 'peace-and-order'],
            [
                'name' => 'Peace & Order',
                'description' => 'Barangay peacekeeping, tanod operations, curfew enforcement, and community dispute resolution.',
                'is_active' => true,
            ]
        );

        $drrm = DocumentCategory::query()->updateOrCreate(
            ['slug' => 'disaster-risk-reduction'],
            [
                'name' => 'Disaster Risk Reduction',
                'description' => 'Disaster preparedness, calamity fund management, climate response, and emergency rescue.',
                'is_active' => true,
            ]
        );

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

    /**
     * Seed 28 Households and 84 Residents across Puroks 1-7.
     *
     * @param  Collection<string, Purok>  $puroks
     * @param  Collection<string, User>  $chairpersons
     */
    protected function seedHouseholdsAndResidents(Collection $puroks, Collection $chairpersons): void
    {
        $householdsByCode = [];

        foreach ($this->householdsData() as $hData) {
            $purok = $puroks->get($hData['purok_code']);
            if (! $purok) {
                continue;
            }

            $chairperson = $chairpersons->get($hData['purok_code']);

            $household = Household::query()->updateOrCreate(
                ['household_code' => $hData['household_code']],
                [
                    'purok_id' => $purok->id,
                    'address' => $hData['address'],
                    'household_head_name' => $hData['household_head_name'],
                    'notes' => $hData['notes'],
                    'created_by_id' => $chairperson?->id,
                    'updated_by_id' => $chairperson?->id,
                ]
            );

            $householdsByCode[$hData['household_code']] = $household;
        }

        foreach ($this->residentsData() as $rData) {
            $purok = $puroks->get($rData['purok_code']);
            $household = $householdsByCode[$rData['household_code']] ?? null;

            if (! $purok || ! $household) {
                continue;
            }

            $chairperson = $chairpersons->get($rData['purok_code']);

            Resident::query()->updateOrCreate(
                ['resident_code' => $rData['resident_code']],
                [
                    'first_name' => $rData['first_name'],
                    'middle_name' => $rData['middle_name'],
                    'last_name' => $rData['last_name'],
                    'suffix' => $rData['suffix'],
                    'sex' => $rData['sex'],
                    'birthdate' => $rData['birthdate'],
                    'civil_status' => $rData['civil_status'],
                    'contact_number' => $rData['contact_number'],
                    'address' => $rData['address'],
                    'purok_id' => $purok->id,
                    'household_id' => $household->id,
                    'is_registered_voter' => $rData['is_registered_voter'],
                    'status' => $rData['status'],
                    'birthplace' => $rData['birthplace'],
                    'occupation' => $rData['occupation'],
                    'remarks' => $rData['remarks'],
                    'created_by_id' => $chairperson?->id,
                    'updated_by_id' => $chairperson?->id,
                ]
            );
        }
    }

    /**
     * Phase 1: Seed 29 Non-Superseded Documents.
     */
    protected function seedOfficialDocumentsPhase1(User $captain, User $secretary): void
    {
        foreach ($this->phase1DocumentsData() as $data) {
            $type = DocumentType::query()->where('code', $data['type_code'])->firstOrFail();
            $category = DocumentCategory::query()->where('slug', $data['category_slug'])->firstOrFail();

            $existing = DocumentRecord::query()
                ->where('document_type_id', $type->id)
                ->where('document_year', $data['year'])
                ->where('document_number', $data['number'])
                ->first();

            $token = $existing->verification_token
                ?? substr(hash('sha256', "STA-FELOMINA-DOC-{$data['number']}-{$data['year']}-VERIFY"), 0, 48);

            $isWorkingDoc = in_array($data['status'], [
                DocumentStatus::Draft,
                DocumentStatus::PendingApproval,
                DocumentStatus::NeedsRevision,
            ], true);

            $draftPdfPath = $isWorkingDoc ? "document-drafts/{$data['number']}-draft.pdf" : null;

            $isOverridden = ! empty($data['category_overridden']);
            $suggestedCategoryId = null;
            $suggestionRuleId = null;
            $suggestionTerms = null;
            $suggestionReason = null;
            $suggestedAt = null;
            $overriddenAt = null;
            $overriddenById = null;

            if (isset($data['suggested_category_slug'])) {
                $suggestedCat = DocumentCategory::query()->where('slug', $data['suggested_category_slug'])->first();
                if ($suggestedCat) {
                    $suggestedCategoryId = $suggestedCat->id;
                    $rule = CategorizationRule::query()->where('document_category_id', $suggestedCat->id)->first();
                    $suggestionRuleId = $rule?->id;
                    $suggestionTerms = $data['suggestion_terms'] ?? ($rule ? array_values(array_intersect($data['keywords'], $rule->keywords ?? [])) : null);
                    $suggestionReason = $data['suggestion_reason'] ?? ($rule ? "Matched keyword rule: {$rule->name}" : null);
                    $suggestedAt = Carbon::parse($data['date'])->addMinutes(15);

                    if ($isOverridden) {
                        $overriddenAt = Carbon::parse($data['date'])->addMinutes(30);
                        $overriddenById = $secretary->id;
                    }
                }
            }

            $record = DocumentRecord::query()->updateOrCreate(
                [
                    'document_type_id' => $type->id,
                    'document_year' => $data['year'],
                    'document_number' => $data['number'],
                ],
                [
                    'document_category_id' => $category->id,
                    'suggested_document_category_id' => $suggestedCategoryId,
                    'category_suggestion_rule_id' => $suggestionRuleId,
                    'category_suggestion_terms' => $suggestionTerms,
                    'category_suggestion_reason' => $suggestionReason,
                    'category_suggested_at' => $suggestedAt,
                    'category_overridden_at' => $overriddenAt,
                    'category_overridden_by_id' => $overriddenById,
                    'created_by_id' => $secretary->id,
                    'title' => $data['title'],
                    'document_date' => $data['date'],
                    'subject' => $data['subject'],
                    'keywords' => implode(', ', $data['keywords']),
                    'status' => $data['status'],
                    'verification_token' => $token,
                    'draft_pdf_path' => $draftPdfPath,
                    'draft_pdf_original_name' => $draftPdfPath ? "{$data['number']}-draft.pdf" : null,
                    'draft_pdf_mime_type' => $draftPdfPath ? 'application/pdf' : null,
                    'draft_pdf_size' => $draftPdfPath ? 1024 : null,
                    'draft_pdf_uploaded_at' => $draftPdfPath ? Carbon::parse($data['date'])->addHours(8) : null,
                    'draft_pdf_uploaded_by_id' => $draftPdfPath ? $secretary->id : null,
                    'submitted_at' => isset($data['submitted_at']) ? Carbon::parse($data['submitted_at']) : null,
                    'submitted_by_id' => isset($data['submitted_at']) ? $secretary->id : null,
                    'approved_at' => isset($data['approved_at']) ? Carbon::parse($data['approved_at']) : null,
                    'approved_by_id' => isset($data['approved_at']) ? $captain->id : null,
                    'return_comment' => $data['return_comment'] ?? null,
                    'returned_at' => isset($data['returned_at']) ? Carbon::parse($data['returned_at']) : null,
                    'returned_by_id' => isset($data['returned_at']) ? $captain->id : null,
                    'archived_at' => isset($data['archived_at']) ? Carbon::parse($data['archived_at']) : null,
                    'archived_by_id' => isset($data['archived_at']) ? $captain->id : null,
                    'archive_reason' => $data['archive_reason'] ?? null,
                    'revoked_at' => isset($data['revoked_at']) ? Carbon::parse($data['revoked_at']) : null,
                    'revoked_by_id' => isset($data['revoked_at']) ? $captain->id : null,
                    'revoke_reason' => $data['revoke_reason'] ?? null,
                    'is_project_related' => $data['is_project_related'] ?? false,
                    'project_name' => $data['project_name'] ?? null,
                    'project_location' => $data['project_location'] ?? null,
                    'approved_budget' => $data['approved_budget'] ?? null,
                    'project_start_date' => isset($data['project_start_date']) ? Carbon::parse($data['project_start_date']) : null,
                    'target_completion_date' => isset($data['target_completion_date']) ? Carbon::parse($data['target_completion_date']) : null,
                    'project_remarks' => $data['project_remarks'] ?? null,
                ]
            );

            $this->seedStorageForRecord($record, $captain);
        }
    }

    /**
     * Phase 2: Seed 3 Superseded Documents linked to Phase 1 replacements.
     */
    protected function seedOfficialDocumentsPhase2(User $captain, User $secretary): void
    {
        foreach ($this->phase2SupersededDocumentsData() as $data) {
            $type = DocumentType::query()->where('code', $data['type_code'])->firstOrFail();
            $category = DocumentCategory::query()->where('slug', $data['category_slug'])->firstOrFail();
            $replacement = DocumentRecord::query()->where('document_number', $data['replacement_number'])->firstOrFail();

            $existing = DocumentRecord::query()
                ->where('document_type_id', $type->id)
                ->where('document_year', $data['year'])
                ->where('document_number', $data['number'])
                ->first();

            $token = $existing->verification_token
                ?? substr(hash('sha256', "STA-FELOMINA-DOC-{$data['number']}-{$data['year']}-VERIFY"), 0, 48);

            $record = DocumentRecord::query()->updateOrCreate(
                [
                    'document_type_id' => $type->id,
                    'document_year' => $data['year'],
                    'document_number' => $data['number'],
                ],
                [
                    'document_category_id' => $category->id,
                    'created_by_id' => $secretary->id,
                    'title' => $data['title'],
                    'document_date' => $data['date'],
                    'subject' => $data['subject'],
                    'keywords' => implode(', ', $data['keywords']),
                    'status' => DocumentStatus::Superseded,
                    'verification_token' => $token,
                    'submitted_at' => Carbon::parse($data['submitted_at']),
                    'submitted_by_id' => $secretary->id,
                    'approved_at' => Carbon::parse($data['approved_at']),
                    'approved_by_id' => $captain->id,
                    'superseded_at' => Carbon::parse($data['superseded_at']),
                    'superseded_by_id' => $captain->id,
                    'supersede_reason' => $data['supersede_reason'],
                    'superseded_by_document_record_id' => $replacement->id,
                    'supersede_reference' => $data['supersede_reference'],
                    'is_project_related' => $data['is_project_related'] ?? false,
                    'project_name' => $data['project_name'] ?? null,
                    'project_location' => $data['project_location'] ?? null,
                    'approved_budget' => $data['approved_budget'] ?? null,
                    'project_start_date' => isset($data['project_start_date']) ? Carbon::parse($data['project_start_date']) : null,
                    'target_completion_date' => isset($data['target_completion_date']) ? Carbon::parse($data['target_completion_date']) : null,
                    'project_remarks' => $data['project_remarks'] ?? null,
                ]
            );

            $this->seedStorageForRecord($record, $captain);
        }
    }

    /**
     * Generate storage files and DocumentFileVersion records.
     */
    protected function seedStorageForRecord(DocumentRecord $record, User $captain): void
    {
        $pdfBytes = $this->generateMinimalPdf($record->title, $record->document_number);

        if (in_array($record->status, [
            DocumentStatus::Published,
            DocumentStatus::Archived,
            DocumentStatus::Revoked,
            DocumentStatus::Superseded,
        ], true)) {
            $officialPath = "document-officials/{$record->id}/versions/1.pdf";
            Storage::disk('local')->put($officialPath, $pdfBytes);

            DocumentFileVersion::query()->updateOrCreate(
                [
                    'document_record_id' => $record->id,
                    'version_number' => 1,
                ],
                [
                    'file_path' => $officialPath,
                    'original_name' => "{$record->document_number}.pdf",
                    'mime_type' => 'application/pdf',
                    'size' => strlen($pdfBytes),
                    'is_current' => true,
                    'approved_at' => $record->approved_at ?? Carbon::parse($record->document_date)->addDays(2),
                    'approved_by_id' => $captain->id,
                ]
            );
        }

        if (filled($record->draft_pdf_path)) {
            Storage::disk('local')->put($record->draft_pdf_path, $pdfBytes);
        }
    }

    /**
     * Seed historical audit logs.
     */
    protected function seedAuditLogs(User $captain, User $secretary): void
    {
        $documents = DocumentRecord::query()->get();

        foreach ($documents as $doc) {
            AuditLog::query()->firstOrCreate(
                [
                    'auditable_type' => DocumentRecord::class,
                    'auditable_id' => $doc->id,
                    'action' => 'document_created',
                ],
                [
                    'actor_id' => $doc->created_by_id ?? $secretary->id,
                    'summary' => "Draft document record {$doc->document_number} created.",
                    'context' => [
                        'document_number' => $doc->document_number,
                        'document_type_id' => $doc->document_type_id,
                        'document_category_id' => $doc->document_category_id,
                    ],
                    'created_at' => Carbon::parse($doc->document_date)->setTime(8, 30),
                    'updated_at' => Carbon::parse($doc->document_date)->setTime(8, 30),
                ]
            );

            if ($doc->category_suggested_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_category_suggested',
                    ],
                    [
                        'actor_id' => $doc->created_by_id ?? $secretary->id,
                        'summary' => 'Document category suggested.',
                        'context' => [
                            'suggested_category_id' => $doc->suggested_document_category_id,
                            'rule_id' => $doc->category_suggestion_rule_id,
                            'matched_terms' => $doc->category_suggestion_terms,
                            'reason' => $doc->category_suggestion_reason,
                        ],
                        'created_at' => $doc->category_suggested_at,
                        'updated_at' => $doc->category_suggested_at,
                    ]
                );
            }

            if ($doc->category_overridden_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_category_overridden',
                    ],
                    [
                        'actor_id' => $doc->category_overridden_by_id ?? $secretary->id,
                        'summary' => 'Document category manually overridden.',
                        'context' => [
                            'suggested_category_id' => $doc->suggested_document_category_id,
                            'selected_category_id' => $doc->document_category_id,
                            'rule_id' => $doc->category_suggestion_rule_id,
                        ],
                        'created_at' => $doc->category_overridden_at,
                        'updated_at' => $doc->category_overridden_at,
                    ]
                );
            }

            if ($doc->draft_pdf_uploaded_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_draft_uploaded',
                    ],
                    [
                        'actor_id' => $doc->draft_pdf_uploaded_by_id ?? $secretary->id,
                        'summary' => 'Draft PDF uploaded.',
                        'context' => [
                            'original_name' => $doc->draft_pdf_original_name,
                            'size' => $doc->draft_pdf_size,
                        ],
                        'created_at' => $doc->draft_pdf_uploaded_at,
                        'updated_at' => $doc->draft_pdf_uploaded_at,
                    ]
                );
            }

            if ($doc->submitted_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_submitted',
                    ],
                    [
                        'actor_id' => $secretary->id,
                        'summary' => 'Document submitted for Captain approval.',
                        'context' => null,
                        'created_at' => $doc->submitted_at,
                        'updated_at' => $doc->submitted_at,
                    ]
                );
            }

            if ($doc->returned_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_returned',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document returned for revision.',
                        'context' => ['comment' => $doc->return_comment],
                        'created_at' => $doc->returned_at,
                        'updated_at' => $doc->returned_at,
                    ]
                );
            }

            if ($doc->approved_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_approved',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document approved and published.',
                        'context' => ['version_number' => 1],
                        'created_at' => $doc->approved_at,
                        'updated_at' => $doc->approved_at,
                    ]
                );
            }

            if ($doc->archived_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_archived',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document archived.',
                        'context' => ['reason' => $doc->archive_reason],
                        'created_at' => $doc->archived_at,
                        'updated_at' => $doc->archived_at,
                    ]
                );
            }

            if ($doc->revoked_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_revoked',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document revoked.',
                        'context' => ['reason' => $doc->revoke_reason],
                        'created_at' => $doc->revoked_at,
                        'updated_at' => $doc->revoked_at,
                    ]
                );
            }

            if ($doc->superseded_at) {
                AuditLog::query()->firstOrCreate(
                    [
                        'auditable_type' => DocumentRecord::class,
                        'auditable_id' => $doc->id,
                        'action' => 'document_superseded',
                    ],
                    [
                        'actor_id' => $captain->id,
                        'summary' => 'Document superseded.',
                        'context' => [
                            'reason' => $doc->supersede_reason,
                            'replacement_document_record_id' => $doc->superseded_by_document_record_id,
                        ],
                        'created_at' => $doc->superseded_at,
                        'updated_at' => $doc->superseded_at,
                    ]
                );
            }
        }

        $households = Household::query()->get();
        foreach ($households as $household) {
            AuditLog::query()->firstOrCreate(
                [
                    'auditable_type' => Household::class,
                    'auditable_id' => $household->id,
                    'action' => 'household_created',
                ],
                [
                    'actor_id' => $household->created_by_id,
                    'summary' => "Household {$household->household_code} created.",
                    'context' => ['household_code' => $household->household_code],
                    'created_at' => $household->created_at ?? now(),
                    'updated_at' => $household->created_at ?? now(),
                ]
            );
        }

        $residents = Resident::query()->get();
        foreach ($residents as $resident) {
            AuditLog::query()->firstOrCreate(
                [
                    'auditable_type' => Resident::class,
                    'auditable_id' => $resident->id,
                    'action' => 'resident_created',
                ],
                [
                    'actor_id' => $resident->created_by_id,
                    'summary' => "Resident {$resident->resident_code} profiled.",
                    'context' => ['resident_code' => $resident->resident_code],
                    'created_at' => $resident->created_at ?? now(),
                    'updated_at' => $resident->created_at ?? now(),
                ]
            );
        }
    }

    /**
     * Seed staff notifications with deterministic UUIDs.
     */
    protected function seedNotifications(User $captain, User $secretary): void
    {
        $pendingDocs = DocumentRecord::query()->where('status', DocumentStatus::PendingApproval)->get();
        foreach ($pendingDocs as $doc) {
            $this->createNotificationRecord($captain, $doc, 'Document awaiting approval.', false, $doc->submitted_at ?? now());
        }

        $publishedHistorical = DocumentRecord::query()->where('status', DocumentStatus::Published)->take(2)->get();
        foreach ($publishedHistorical as $doc) {
            $this->createNotificationRecord($captain, $doc, 'Document submitted for Captain approval.', true, $doc->submitted_at ?? now());
        }

        $returnedDocs = DocumentRecord::query()->where('status', DocumentStatus::NeedsRevision)->get();
        foreach ($returnedDocs as $doc) {
            $this->createNotificationRecord($secretary, $doc, 'Document returned for revision.', false, $doc->returned_at ?? now());
        }

        $publishedSecretary = DocumentRecord::query()->where('status', DocumentStatus::Published)->latest('approved_at')->take(4)->get();
        $isFirst = true;
        foreach ($publishedSecretary as $doc) {
            $this->createNotificationRecord($secretary, $doc, 'Document approved and published.', ! $isFirst, $doc->approved_at ?? now());
            $isFirst = false;
        }

        for ($p = 1; $p <= 7; $p++) {
            $purokChair = User::query()->where('email', "purok{$p}@stafelomina.local")->first();
            if (! $purokChair) {
                continue;
            }

            $featuredOrdinance = DocumentRecord::query()->where('document_number', 'ORD-2026-001')->first();
            if ($featuredOrdinance) {
                $this->createNotificationRecord(
                    $purokChair,
                    $featuredOrdinance,
                    'New published ordinance available for implementation.',
                    false,
                    $featuredOrdinance->approved_at ?? now()
                );
            }

            $featuredResolution = DocumentRecord::query()->where('document_number', 'RES-2024-001')->first();
            if ($featuredResolution) {
                $this->createNotificationRecord(
                    $purokChair,
                    $featuredResolution,
                    'New official resolution approved and published.',
                    true,
                    $featuredResolution->approved_at ?? now()
                );
            }
        }
    }

    /**
     * Create or update notification record deterministically.
     */
    protected function createNotificationRecord(
        User $user,
        DocumentRecord $doc,
        string $message,
        bool $isRead,
        Carbon|string $createdAt,
        ?string $url = null
    ): void {
        $createdAtCarbon = $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt);
        $hash = md5("notification:{$user->id}:{$doc->id}:{$message}");
        $uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );

        $readAt = $isRead ? $createdAtCarbon->copy()->addHours(2) : null;

        $targetUrl = $url ?? ($user->hasRole('captain') && $doc->status === DocumentStatus::PendingApproval
            ? '/approvals?search='.urlencode($doc->document_number)."#pending-approval-{$doc->id}"
            : "/documents/{$doc->id}");

        DB::table('notifications')->updateOrInsert(
            ['id' => $uuid],
            [
                'type' => DocumentWorkflowNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'document_record_id' => $doc->id,
                    'document_number' => $doc->document_number,
                    'title' => $doc->title,
                    'message' => $message,
                    'url' => $targetUrl,
                ], JSON_THROW_ON_ERROR),
                'read_at' => $readAt,
                'created_at' => $createdAtCarbon,
                'updated_at' => $createdAtCarbon,
            ]
        );
    }

    /**
     * Generate standard-compliant minimal 1-page PDF string with dynamic xref byte offsets.
     */
    protected function generateMinimalPdf(string $title, string $docNumber): string
    {
        $safeDocNumber = addcslashes($docNumber, '()\\');
        $safeTitle = addcslashes(substr($title, 0, 75), '()\\');

        $content = "BT\n"
            ."/F1 14 Tf\n"
            ."72 720 Td\n"
            ."(Republic of the Philippines) Tj\n"
            ."0 -18 Td\n"
            ."(Province of Ilocos Norte - City of Laoag) Tj\n"
            ."0 -18 Td\n"
            ."(BARANGAY STA. FELOMINA) Tj\n"
            ."0 -32 Td\n"
            ."/F1 12 Tf\n"
            .'(Official Document Record: '.$safeDocNumber.") Tj\n"
            ."0 -20 Td\n"
            .'(Title: '.$safeTitle.") Tj\n"
            ."0 -28 Td\n"
            ."/F1 10 Tf\n"
            ."(Official digitized repository record of Barangay Sta. Felomina.) Tj\n"
            ."0 -16 Td\n"
            ."(Certified accurate by the Punong Barangay and Sangguniang Barangay.) Tj\n"
            ."ET\n";

        $header = "%PDF-1.4\n";
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length ".strlen($content)." >>\nstream\n".$content."endstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $offset1 = strlen($header);
        $offset2 = $offset1 + strlen($obj1);
        $offset3 = $offset2 + strlen($obj2);
        $offset4 = $offset3 + strlen($obj3);
        $offset5 = $offset4 + strlen($obj4);
        $xrefOffset = $offset5 + strlen($obj5);

        $xref = sprintf(
            "xref\n0 6\n0000000000 65535 f \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n",
            $offset1,
            $offset2,
            $offset3,
            $offset4,
            $offset5
        );

        $trailer = "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF\n";

        return $header.$obj1.$obj2.$obj3.$obj4.$obj5.$xref.$trailer;
    }

    /**
     * 28 Households Master Data.
     *
     * @return array<int, array{household_code: string, purok_code: string, address: string, household_head_name: string, notes: string}>
     */
    protected function householdsData(): array
    {
        return [
            // Purok 1
            [
                'household_code' => 'HH-P1-001',
                'purok_code' => 'purok-1',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Juanito Mendoza Dela Cruz',
                'notes' => 'Monthly Income: ₱22,000 (Low-Middle Income, small business/agri); 3 members.',
            ],
            [
                'household_code' => 'HH-P1-002',
                'purok_code' => 'purok-1',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Roberto Gomez Santos',
                'notes' => 'Monthly Income: ₱16,000 (Low Income, transport services); 4 members.',
            ],
            [
                'household_code' => 'HH-P1-003',
                'purok_code' => 'purok-1',
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Elena Villafuerte Alcantara',
                'notes' => 'Monthly Income: ₱14,000 (Low Income, retail & pension); 3 members.',
            ],
            [
                'household_code' => 'HH-P1-004',
                'purok_code' => 'purok-1',
                'address' => '088 Mabini Extension, Sitio Centro, Purok 1, Sta. Felomina',
                'household_head_name' => 'Danilo Salcedo Morales',
                'notes' => 'Monthly Income: ₱32,000 (Middle Income, government pension); 2 members.',
            ],

            // Purok 2
            [
                'household_code' => 'HH-P2-001',
                'purok_code' => 'purok-2',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Manuel Castro Bautista',
                'notes' => 'Monthly Income: ₱19,000 (Low Income, carpentry & trades); 4 members.',
            ],
            [
                'household_code' => 'HH-P2-002',
                'purok_code' => 'purok-2',
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Cristina Velasco Reyes',
                'notes' => 'Monthly Income: ₱35,000 (Middle Income, education sector); 3 members.',
            ],
            [
                'household_code' => 'HH-P2-003',
                'purok_code' => 'purok-2',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Francisco Beltran Dimaculangan',
                'notes' => 'Monthly Income: ₱15,000 (Low Income, agricultural wage); 3 members.',
            ],
            [
                'household_code' => 'HH-P2-004',
                'purok_code' => 'purok-2',
                'address' => '098 Maharlika Road, Sitio Malipayon, Purok 2, Sta. Felomina',
                'household_head_name' => 'Rodrigo Villanueva Magbanua',
                'notes' => 'Monthly Income: ₱18,000 (Low Income, security services); 2 members.',
            ],

            // Purok 3
            [
                'household_code' => 'HH-P3-001',
                'purok_code' => 'purok-3',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'household_head_name' => 'Antonio Salazar Flores',
                'notes' => 'Monthly Income: ₱25,000 (Middle Income, auto services); 3 members.',
            ],
            [
                'household_code' => 'HH-P3-002',
                'purok_code' => 'purok-3',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'household_head_name' => 'Ramon Mercado Catapang',
                'notes' => 'Monthly Income: ₱13,500 (Low Income, peace & security stipend); 3 members.',
            ],
            [
                'household_code' => 'HH-P3-003',
                'purok_code' => 'purok-3',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'household_head_name' => 'Ernesto Gomez Navarro',
                'notes' => 'Monthly Income: ₱20,000 (Low-Middle Income, tire service); 4 members.',
            ],
            [
                'household_code' => 'HH-P3-004',
                'purok_code' => 'purok-3',
                'address' => '089 Junction Commercial Strip, Purok 3, Sta. Felomina',
                'household_head_name' => 'Marites Soriano Villafuerte',
                'notes' => 'Monthly Income: ₱28,000 (Middle Income, food enterprise); 2 members.',
            ],

            // Purok 4
            [
                'household_code' => 'HH-P4-001',
                'purok_code' => 'purok-4',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'household_head_name' => 'Jaime Fernandez Perez',
                'notes' => 'Monthly Income: ₱17,000 (Low Income, rice & vegetable farming); 4 members.',
            ],
            [
                'household_code' => 'HH-P4-002',
                'purok_code' => 'purok-4',
                'address' => '077 Old River Crossing, Purok 4, Sta. Felomina',
                'household_head_name' => 'Natividad Santos Tolentino',
                'notes' => 'Monthly Income: ₱9,000 (Indigent / Pensioner bracket, senior pension); 2 members.',
            ],
            [
                'household_code' => 'HH-P4-003',
                'purok_code' => 'purok-4',
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'household_head_name' => 'Andres Salazar Macaraeg',
                'notes' => 'Monthly Income: ₱14,500 (Low Income, rubber harvest); 3 members.',
            ],
            [
                'household_code' => 'HH-P4-004',
                'purok_code' => 'purok-4',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'household_head_name' => 'Leonora Bautista Salvador',
                'notes' => 'Monthly Income: ₱16,000 (Low Income, barangay health honorarium & poultry); 3 members.',
            ],

            // Purok 5
            [
                'household_code' => 'HH-P5-001',
                'purok_code' => 'purok-5',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'household_head_name' => 'Fernando Gomez Custodio',
                'notes' => 'Monthly Income: ₱21,000 (Low-Middle Income, fabrication); 3 members.',
            ],
            [
                'household_code' => 'HH-P5-002',
                'purok_code' => 'purok-5',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'household_head_name' => 'Divina Mercado Gonzales',
                'notes' => 'Monthly Income: ₱55,000 (High Income, overseas remittance); 4 members.',
            ],
            [
                'household_code' => 'HH-P5-003',
                'purok_code' => 'purok-5',
                'address' => '092 Maharlika Valley, Purok 5, Sta. Felomina',
                'household_head_name' => 'Marivic Soriano Ramos',
                'notes' => 'Monthly Income: ₱13,000 (Low Income, seamstress); 2 members.',
            ],
            [
                'household_code' => 'HH-P5-004',
                'purok_code' => 'purok-5',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'household_head_name' => 'Jaime Tolentino Aquino',
                'notes' => 'Monthly Income: ₱18,500 (Low Income, corn & banana farming); 3 members.',
            ],

            // Purok 6
            [
                'household_code' => 'HH-P6-001',
                'purok_code' => 'purok-6',
                'address' => '014 Upland Ridge, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Remedios Villafuerte Mendoza',
                'notes' => 'Monthly Income: ₱10,500 (Indigent / Pensioner bracket, senior pension); 2 members.',
            ],
            [
                'household_code' => 'HH-P6-002',
                'purok_code' => 'purok-6',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Carmela Morales Garcia',
                'notes' => 'Monthly Income: ₱24,000 (Low-Middle Income, rubber & small retail); 4 members.',
            ],
            [
                'household_code' => 'HH-P6-003',
                'purok_code' => 'purok-6',
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Jocelyn Catapang Soriano',
                'notes' => 'Monthly Income: ₱26,000 (Middle Income, fruit orchards); 3 members.',
            ],
            [
                'household_code' => 'HH-P6-004',
                'purok_code' => 'purok-6',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'household_head_name' => 'Rosalie Alcantara Valenzuela',
                'notes' => 'Monthly Income: ₱30,000 (Middle Income, healthcare & coffee); 3 members.',
            ],

            // Purok 7
            [
                'household_code' => 'HH-P7-001',
                'purok_code' => 'purok-7',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'household_head_name' => 'Rowena Perez Hernandez',
                'notes' => 'Monthly Income: ₱23,000 (Low-Middle Income, logistics & community service); 3 members.',
            ],
            [
                'household_code' => 'HH-P7-002',
                'purok_code' => 'purok-7',
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'household_head_name' => 'Angelica Navarro Pascual',
                'notes' => 'Monthly Income: ₱15,500 (Low Income, retail & livestock); 3 members.',
            ],
            [
                'household_code' => 'HH-P7-003',
                'purok_code' => 'purok-7',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'household_head_name' => 'Andres Ramos Del Rosario',
                'notes' => 'Monthly Income: ₱27,000 (Middle Income, construction); 4 members.',
            ],
            [
                'household_code' => 'HH-P7-004',
                'purok_code' => 'purok-7',
                'address' => '112 Sitio Pag-asa Edge, Purok 7, Sta. Felomina',
                'household_head_name' => 'Teresa Bautista Castillo',
                'notes' => 'Monthly Income: ₱42,000 (Middle-High Income, OFW remittance & pension); 2 members.',
            ],
        ];
    }

    /**
     * 84 Residents Master Data.
     *
     * @return array<int, array{resident_code: string, purok_code: string, household_code: string, first_name: string, middle_name: ?string, last_name: string, suffix: ?string, sex: string, birthdate: string, civil_status: string, contact_number: ?string, address: string, is_registered_voter: bool, status: ResidentStatus, birthplace: ?string, occupation: ?string, remarks: ?string}>
     */
    protected function residentsData(): array
    {
        return [
            // ==========================================
            // PUROK 1 (Sitio Centro — 12 Residents)
            // ==========================================
            // HH-P1-001 (Dela Cruz)
            [
                'resident_code' => 'RES-P1-001',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-001',
                'first_name' => 'Juanito',
                'middle_name' => 'Mendoza',
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1978-05-14',
                'civil_status' => 'married',
                'contact_number' => '0917-234-1101',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Farmer & General Merchandiser',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P1-002',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-001',
                'first_name' => 'Teresa',
                'middle_name' => 'Garcia',
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1982-11-20',
                'civil_status' => 'married',
                'contact_number' => '0917-234-1102',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Sari-Sari Store Manager',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P1-003',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-001',
                'first_name' => 'Christian',
                'middle_name' => 'Garcia',
                'last_name' => 'Dela Cruz',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2006-03-15',
                'civil_status' => 'single',
                'contact_number' => '0917-234-1103',
                'address' => '104 Magsaysay St., Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'College Student (BS Agriculture)',
                'remarks' => 'Child',
            ],

            // HH-P1-002 (Santos)
            [
                'resident_code' => 'RES-P1-004',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Roberto',
                'middle_name' => 'Gomez',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1980-08-25',
                'civil_status' => 'married',
                'contact_number' => '0920-345-2201',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Tricycle Driver',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P1-005',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Marivic',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1984-02-14',
                'civil_status' => 'married',
                'contact_number' => '0920-345-2202',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Bansalan, Davao del Sur',
                'occupation' => 'Homemaker / Dressmaker',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P1-006',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Kevin',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2008-01-10',
                'civil_status' => 'single',
                'contact_number' => '0920-345-2203',
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Senior High School Student',
                'remarks' => 'Child; Registered youth voter',
            ],
            [
                'resident_code' => 'RES-P1-007',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-002',
                'first_name' => 'Bea',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2012-07-22',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '212 Rizal Street, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P1-003 (Alcantara)
            [
                'resident_code' => 'RES-P1-008',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-003',
                'first_name' => 'Elena',
                'middle_name' => 'Villafuerte',
                'last_name' => 'Alcantara',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1960-04-18',
                'civil_status' => 'widowed',
                'contact_number' => '0928-456-3301',
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Sari-Sari Store Owner',
                'remarks' => 'Head of Household; Senior Citizen',
            ],
            [
                'resident_code' => 'RES-P1-009',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-003',
                'first_name' => 'Antonio',
                'middle_name' => 'Perez',
                'last_name' => 'Alcantara',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1957-10-12',
                'civil_status' => 'married',
                'contact_number' => null,
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Deceased,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Former Barangay Tanod',
                'remarks' => 'Deceased spouse (passed away 2024)',
            ],
            [
                'resident_code' => 'RES-P1-010',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-003',
                'first_name' => 'Grace',
                'middle_name' => 'Villafuerte',
                'last_name' => 'Alcantara',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1992-09-05',
                'civil_status' => 'single',
                'contact_number' => '0928-456-3302',
                'address' => '045 Quezon Boulevard, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Barangay Daycare Volunteer',
                'remarks' => 'Child; Dependent Caretaker',
            ],

            // HH-P1-004 (Morales)
            [
                'resident_code' => 'RES-P1-011',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-004',
                'first_name' => 'Danilo',
                'middle_name' => 'Salcedo',
                'last_name' => 'Morales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1958-12-03',
                'civil_status' => 'married',
                'contact_number' => '0939-567-4401',
                'address' => '088 Mabini Extension, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Tulunan, Cotabato',
                'occupation' => 'Retired Public School Teacher',
                'remarks' => 'Head of Household; Senior Citizen',
            ],
            [
                'resident_code' => 'RES-P1-012',
                'purok_code' => 'purok-1',
                'household_code' => 'HH-P1-004',
                'first_name' => 'Jason',
                'middle_name' => 'Aquino',
                'last_name' => 'Morales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1993-06-28',
                'civil_status' => 'single',
                'contact_number' => '0939-567-4402',
                'address' => '088 Mabini Extension, Sitio Centro, Purok 1, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Inactive,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Merchant Seaman',
                'remarks' => 'Child; Deployed on international cargo vessel',
            ],

            // ==========================================
            // PUROK 2 (Sitio Malipayon — 12 Residents)
            // ==========================================
            // HH-P2-001 (Bautista)
            [
                'resident_code' => 'RES-P2-001',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Manuel',
                'middle_name' => 'Castro',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1976-03-22',
                'civil_status' => 'married',
                'contact_number' => '0918-123-5501',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Master Carpenter',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P2-002',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Remedios',
                'middle_name' => 'Santos',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1979-09-14',
                'civil_status' => 'married',
                'contact_number' => '0918-123-5502',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Food Vendor',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P2-003',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Mark Lester',
                'middle_name' => 'Santos',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2004-11-08',
                'civil_status' => 'single',
                'contact_number' => '0918-123-5503',
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Vocational Welder Apprentice',
                'remarks' => 'Child',
            ],
            [
                'resident_code' => 'RES-P2-004',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-001',
                'first_name' => 'Princess Joy',
                'middle_name' => 'Santos',
                'last_name' => 'Bautista',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2010-05-30',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '115 Malipayon Street, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'High School Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P2-002 (Reyes)
            [
                'resident_code' => 'RES-P2-005',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-002',
                'first_name' => 'Cristina',
                'middle_name' => 'Velasco',
                'last_name' => 'Reyes',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1985-06-18',
                'civil_status' => 'married',
                'contact_number' => '0922-234-6601',
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary School Teacher',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P2-006',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-002',
                'first_name' => 'Eduardo',
                'middle_name' => 'Tan',
                'last_name' => 'Reyes',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1983-01-27',
                'civil_status' => 'married',
                'contact_number' => '0922-234-6602',
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Digos City, Davao del Sur',
                'occupation' => 'Motorcycle Repair Specialist',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P2-007',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-002',
                'first_name' => 'Ethan John',
                'middle_name' => 'Velasco',
                'last_name' => 'Reyes',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2014-04-11',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '072 Acacia Lane, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P2-003 (Dimaculangan)
            [
                'resident_code' => 'RES-P2-008',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-003',
                'first_name' => 'Francisco',
                'middle_name' => 'Beltran',
                'last_name' => 'Dimaculangan',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1974-11-05',
                'civil_status' => 'married',
                'contact_number' => '0930-345-7701',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rubber Farmer & Tapper',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P2-009',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-003',
                'first_name' => 'Luzviminda',
                'middle_name' => 'Ramos',
                'last_name' => 'Dimaculangan',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1978-08-19',
                'civil_status' => 'married',
                'contact_number' => '0930-345-7702',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Agricultural Caretaker',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P2-010',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-003',
                'first_name' => 'Gerald',
                'middle_name' => 'Ramos',
                'last_name' => 'Dimaculangan',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2005-12-02',
                'civil_status' => 'single',
                'contact_number' => '0930-345-7703',
                'address' => '034 Narra Drive, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Farm Laborer',
                'remarks' => 'Child; Unregistered young adult',
            ],

            // HH-P2-004 (Magbanua)
            [
                'resident_code' => 'RES-P2-011',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-004',
                'first_name' => 'Rodrigo',
                'middle_name' => 'Villanueva',
                'last_name' => 'Magbanua',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1977-07-09',
                'civil_status' => 'separated',
                'contact_number' => '0947-456-8801',
                'address' => '098 Maharlika Road, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Columbio, Sultan Kudarat',
                'occupation' => 'Private Security Guard',
                'remarks' => 'Head of Household; Separated',
            ],
            [
                'resident_code' => 'RES-P2-012',
                'purok_code' => 'purok-2',
                'household_code' => 'HH-P2-004',
                'first_name' => 'Angelica',
                'middle_name' => 'Cruz',
                'last_name' => 'Magbanua',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2003-02-24',
                'civil_status' => 'single',
                'contact_number' => '0947-456-8802',
                'address' => '098 Maharlika Road, Sitio Malipayon, Purok 2, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Call Center Agent',
                'remarks' => 'Child; Transferred to Kidapawan City for employment',
            ],

            // ==========================================
            // PUROK 3 (National Highway Junction — 12 Residents)
            // ==========================================
            // HH-P3-001 (Flores)
            [
                'resident_code' => 'RES-P3-001',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-001',
                'first_name' => 'Antonio',
                'middle_name' => 'Salazar',
                'last_name' => 'Flores',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1975-05-30',
                'civil_status' => 'married',
                'contact_number' => '0919-567-9901',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Auto Mechanic & Shop Owner',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P3-002',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-001',
                'first_name' => 'Clarita',
                'middle_name' => 'David',
                'last_name' => 'Flores',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1980-10-14',
                'civil_status' => 'married',
                'contact_number' => '0919-567-9902',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => "M'lang, Cotabato",
                'occupation' => 'Auto Parts Cashier',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P3-003',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-001',
                'first_name' => 'Antonette',
                'middle_name' => 'David',
                'last_name' => 'Flores',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2007-08-20',
                'civil_status' => 'single',
                'contact_number' => '0919-567-9903',
                'address' => '018 National Highway Km 114, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'IT College Student',
                'remarks' => 'Child; Youth voter',
            ],

            // HH-P3-002 (Catapang)
            [
                'resident_code' => 'RES-P3-004',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-002',
                'first_name' => 'Ramon',
                'middle_name' => 'Mercado',
                'last_name' => 'Catapang',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1970-12-16',
                'civil_status' => 'married',
                'contact_number' => '0921-678-0011',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Chief Barangay Tanod',
                'remarks' => 'Head of Household; Tanod Deputy',
            ],
            [
                'resident_code' => 'RES-P3-005',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-002',
                'first_name' => 'Teresita',
                'middle_name' => 'Ocampo',
                'last_name' => 'Catapang',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1973-04-02',
                'civil_status' => 'married',
                'contact_number' => '0921-678-0012',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Community Tailor',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P3-006',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-002',
                'first_name' => 'Raymond',
                'middle_name' => 'Ocampo',
                'last_name' => 'Catapang',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2000-09-18',
                'civil_status' => 'single',
                'contact_number' => '0921-678-0013',
                'address' => '056 Highway Bypass Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Delivery Driver',
                'remarks' => 'Child',
            ],

            // HH-P3-003 (Navarro)
            [
                'resident_code' => 'RES-P3-007',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Ernesto',
                'middle_name' => 'Gomez',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1969-02-28',
                'civil_status' => 'married',
                'contact_number' => '0932-789-1122',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Vulcanizing Shop Operator',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P3-008',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Rosanna',
                'middle_name' => 'Cruz',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1972-11-11',
                'civil_status' => 'married',
                'contact_number' => '0932-789-1123',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Small Eatery Cook',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P3-009',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Joshua',
                'middle_name' => 'Cruz',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2002-06-04',
                'civil_status' => 'single',
                'contact_number' => '0932-789-1124',
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Auto Electrician',
                'remarks' => 'Child',
            ],
            [
                'resident_code' => 'RES-P3-010',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-003',
                'first_name' => 'Jasmine',
                'middle_name' => 'Cruz',
                'last_name' => 'Navarro',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2011-01-25',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '142 Terminal Road, Purok 3, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Junior High School Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P3-004 (Villafuerte)
            [
                'resident_code' => 'RES-P3-011',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-004',
                'first_name' => 'Marites',
                'middle_name' => 'Soriano',
                'last_name' => 'Villafuerte',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1981-08-07',
                'civil_status' => 'separated',
                'contact_number' => '0945-890-2233',
                'address' => '089 Junction Commercial Strip, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Digos City, Davao del Sur',
                'occupation' => 'Neighborhood Bakery Owner',
                'remarks' => 'Head of Household; Separated',
            ],
            [
                'resident_code' => 'RES-P3-012',
                'purok_code' => 'purok-3',
                'household_code' => 'HH-P3-004',
                'first_name' => 'Dominic',
                'middle_name' => 'Soriano',
                'last_name' => 'Villafuerte',
                'suffix' => 'Jr.',
                'sex' => 'male',
                'birthdate' => '2001-04-19',
                'civil_status' => 'single',
                'contact_number' => '0945-890-2234',
                'address' => '089 Junction Commercial Strip, Purok 3, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Commercial Sales Agent',
                'remarks' => 'Child; Transferred to Davao City for employment',
            ],

            // ==========================================
            // PUROK 4 (Riverside Zone — 12 Residents)
            // ==========================================
            // HH-P4-001 (Perez)
            [
                'resident_code' => 'RES-P4-001',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Jaime',
                'middle_name' => 'Fernandez',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1973-10-10',
                'civil_status' => 'married',
                'contact_number' => '0915-901-3344',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rice & Vegetable Farmer',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P4-002',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Anita',
                'middle_name' => 'Morales',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1976-12-05',
                'civil_status' => 'married',
                'contact_number' => '0915-901-3345',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Tulunan, Cotabato',
                'occupation' => 'Vegetable Wholesaler',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P4-003',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Darwin',
                'middle_name' => 'Morales',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2003-08-14',
                'civil_status' => 'single',
                'contact_number' => '0915-901-3346',
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Farm Machinery Operator',
                'remarks' => 'Child',
            ],
            [
                'resident_code' => 'RES-P4-004',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-001',
                'first_name' => 'Jenny',
                'middle_name' => 'Morales',
                'last_name' => 'Perez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2009-03-29',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '023 Riverside Bank Road, Purok 4, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'High School Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P4-002 (Tolentino)
            [
                'resident_code' => 'RES-P4-005',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-002',
                'first_name' => 'Rogelio',
                'middle_name' => 'Mendoza',
                'last_name' => 'Tolentino',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1948-03-12',
                'civil_status' => 'married',
                'contact_number' => null,
                'address' => '077 Old River Crossing, Purok 4, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Deceased,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Retired Land Tiller',
                'remarks' => 'Deceased spouse (passed away February 2026)',
            ],
            [
                'resident_code' => 'RES-P4-006',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-002',
                'first_name' => 'Natividad',
                'middle_name' => 'Santos',
                'last_name' => 'Tolentino',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1952-09-21',
                'civil_status' => 'widowed',
                'contact_number' => '0926-012-4455',
                'address' => '077 Old River Crossing, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Social Pension Beneficiary',
                'remarks' => 'Head of Household; Surviving Spouse / Senior Citizen',
            ],

            // HH-P4-003 (Macaraeg)
            [
                'resident_code' => 'RES-P4-007',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-003',
                'first_name' => 'Andres',
                'middle_name' => 'Salazar',
                'last_name' => 'Macaraeg',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1982-04-03',
                'civil_status' => 'married',
                'contact_number' => '0935-123-5566',
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rubber Tapper',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P4-008',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-003',
                'first_name' => 'Gemma',
                'middle_name' => 'Flores',
                'last_name' => 'Macaraeg',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1986-07-28',
                'civil_status' => 'married',
                'contact_number' => '0935-123-5567',
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Fruit Vendor',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P4-009',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-003',
                'first_name' => 'Angelo',
                'middle_name' => 'Flores',
                'last_name' => 'Macaraeg',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2013-10-15',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '105 Riverview Pathway, Purok 4, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P4-004 (Salvador)
            [
                'resident_code' => 'RES-P4-010',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-004',
                'first_name' => 'Leonora',
                'middle_name' => 'Bautista',
                'last_name' => 'Salvador',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1978-01-17',
                'civil_status' => 'married',
                'contact_number' => '0948-234-6677',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Barangay Health Worker (BHW)',
                'remarks' => 'Head of Household; BHW Volunteer',
            ],
            [
                'resident_code' => 'RES-P4-011',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-004',
                'first_name' => 'Vicente',
                'middle_name' => 'Ramos',
                'last_name' => 'Salvador',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1975-06-23',
                'civil_status' => 'married',
                'contact_number' => '0948-234-6678',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Small Poultry Caretaker',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P4-012',
                'purok_code' => 'purok-4',
                'household_code' => 'HH-P4-004',
                'first_name' => 'Karen Joy',
                'middle_name' => 'Ramos',
                'last_name' => 'Salvador',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2005-02-14',
                'civil_status' => 'single',
                'contact_number' => '0948-234-6679',
                'address' => '048 Fishery Lane, Purok 4, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Nursing Student',
                'remarks' => 'Child; Registered voter',
            ],

            // ==========================================
            // PUROK 5 (Sitio Maharlika — 12 Residents)
            // ==========================================
            // HH-P5-001 (Custodio)
            [
                'resident_code' => 'RES-P5-001',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-001',
                'first_name' => 'Fernando',
                'middle_name' => 'Gomez',
                'last_name' => 'Custodio',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1979-11-19',
                'civil_status' => 'married',
                'contact_number' => '0917-345-7788',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Welder & Metal Fabricator',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P5-002',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-001',
                'first_name' => 'Cynthia',
                'middle_name' => 'Diaz',
                'last_name' => 'Custodio',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1982-05-24',
                'civil_status' => 'married',
                'contact_number' => '0917-345-7789',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Homemaker',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P5-003',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-001',
                'first_name' => 'Bryan',
                'middle_name' => 'Diaz',
                'last_name' => 'Custodio',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2006-08-30',
                'civil_status' => 'single',
                'contact_number' => '0917-345-7790',
                'address' => '031 Maharlika Central, Purok 5, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Technical Vocational Trainee',
                'remarks' => 'Child; Unregistered young adult',
            ],

            // HH-P5-002 (Gonzales)
            [
                'resident_code' => 'RES-P5-004',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Divina',
                'middle_name' => 'Mercado',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1983-09-08',
                'civil_status' => 'married',
                'contact_number' => '+966-50-123-4567',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Inactive,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Overseas Filipino Worker (Nurse in Riyadh, KSA)',
                'remarks' => 'Head of Household; OFW in Saudi Arabia since 2024',
            ],
            [
                'resident_code' => 'RES-P5-005',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Nestor',
                'middle_name' => 'Alcantara',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1980-03-14',
                'civil_status' => 'married',
                'contact_number' => '0927-456-8899',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Commercial Van Driver / Family Caretaker',
                'remarks' => 'Spouse; Managing household locally',
            ],
            [
                'resident_code' => 'RES-P5-006',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Nicole',
                'middle_name' => 'Mercado',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2008-04-12',
                'civil_status' => 'single',
                'contact_number' => '0927-456-8900',
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Grade 12 Senior High Student',
                'remarks' => 'Child; First-time registered voter',
            ],
            [
                'resident_code' => 'RES-P5-007',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-002',
                'first_name' => 'Nathan',
                'middle_name' => 'Mercado',
                'last_name' => 'Gonzales',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2015-06-25',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '064 Sitio Maharlika Upper, Purok 5, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P5-003 (Ramos)
            [
                'resident_code' => 'RES-P5-008',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-003',
                'first_name' => 'Marivic',
                'middle_name' => 'Soriano',
                'last_name' => 'Ramos',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1971-07-22',
                'civil_status' => 'widowed',
                'contact_number' => '0938-567-9900',
                'address' => '092 Maharlika Valley, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Master Tailor & Dressmaker',
                'remarks' => 'Head of Household; Solo Parent',
            ],
            [
                'resident_code' => 'RES-P5-009',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-003',
                'first_name' => 'Paolo',
                'middle_name' => 'Soriano',
                'last_name' => 'Ramos',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2001-12-10',
                'civil_status' => 'single',
                'contact_number' => '0938-567-9901',
                'address' => '092 Maharlika Valley, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Agricultural Supply Clerk',
                'remarks' => 'Child',
            ],

            // HH-P5-004 (Aquino)
            [
                'resident_code' => 'RES-P5-010',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-004',
                'first_name' => 'Jaime',
                'middle_name' => 'Tolentino',
                'last_name' => 'Aquino',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1968-01-30',
                'civil_status' => 'married',
                'contact_number' => '0949-678-0012',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Corn & Banana Grower',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P5-011',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-004',
                'first_name' => 'Corazon',
                'middle_name' => 'Beltran',
                'last_name' => 'Aquino',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1972-04-16',
                'civil_status' => 'married',
                'contact_number' => '0949-678-0013',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Produce Trader',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P5-012',
                'purok_code' => 'purok-5',
                'household_code' => 'HH-P5-004',
                'first_name' => 'Mark Anthony',
                'middle_name' => 'Beltran',
                'last_name' => 'Aquino',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1999-10-05',
                'civil_status' => 'single',
                'contact_number' => '0949-678-0014',
                'address' => '118 Maharlika Feeder Road, Purok 5, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Heavy Equipment Operator',
                'remarks' => 'Child',
            ],

            // ==========================================
            // PUROK 6 (Upper Hills / Sitio San Isidro — 12 Residents)
            // ==========================================
            // HH-P6-001 (Mendoza)
            [
                'resident_code' => 'RES-P6-001',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-001',
                'first_name' => 'Remedios',
                'middle_name' => 'Villafuerte',
                'last_name' => 'Mendoza',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1950-02-15',
                'civil_status' => 'widowed',
                'contact_number' => '0916-789-1133',
                'address' => '014 Upland Ridge, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Backyard Gardener & Pensioner',
                'remarks' => 'Head of Household; Senior Citizen / PWD',
            ],
            [
                'resident_code' => 'RES-P6-002',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-001',
                'first_name' => 'Felipe',
                'middle_name' => 'Castro',
                'last_name' => 'Mendoza',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1946-08-20',
                'civil_status' => 'widowed',
                'contact_number' => null,
                'address' => '014 Upland Ridge, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Deceased,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Retired Farmer',
                'remarks' => 'Brother / Relative; Deceased (late 2025)',
            ],

            // HH-P6-002 (Garcia)
            [
                'resident_code' => 'RES-P6-003',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Carmela',
                'middle_name' => 'Morales',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1974-10-09',
                'civil_status' => 'married',
                'contact_number' => '0928-890-2244',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rubber Planter & Storekeeper',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P6-004',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Rolando',
                'middle_name' => 'Dela Cruz',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1971-05-18',
                'civil_status' => 'married',
                'contact_number' => '0928-890-2245',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Plantation Overseer',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P6-005',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Rona',
                'middle_name' => 'Morales',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1998-03-25',
                'civil_status' => 'single',
                'contact_number' => '0928-890-2246',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Pharmacy Assistant',
                'remarks' => 'Child',
            ],
            [
                'resident_code' => 'RES-P6-006',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-002',
                'first_name' => 'Rochelle',
                'middle_name' => 'Morales',
                'last_name' => 'Garcia',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2000-11-14',
                'civil_status' => 'married',
                'contact_number' => '0928-890-2247',
                'address' => '042 San Isidro Trail, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Administrative Clerk',
                'remarks' => 'Child; Transferred to Digos City after marriage',
            ],

            // HH-P6-003 (Soriano)
            [
                'resident_code' => 'RES-P6-007',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-003',
                'first_name' => 'Jocelyn',
                'middle_name' => 'Catapang',
                'last_name' => 'Soriano',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1980-08-11',
                'civil_status' => 'married',
                'contact_number' => '0939-901-3355',
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Fruit Grower (Durian & Marang)',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P6-008',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-003',
                'first_name' => 'Noel',
                'middle_name' => 'Dimaculangan',
                'last_name' => 'Soriano',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1977-01-22',
                'civil_status' => 'married',
                'contact_number' => '0939-901-3356',
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Orchard Supervisor',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P6-009',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-003',
                'first_name' => 'Justin',
                'middle_name' => 'Catapang',
                'last_name' => 'Soriano',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2011-09-03',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '068 Mountain View Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'High School Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P6-004 (Valenzuela)
            [
                'resident_code' => 'RES-P6-010',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-004',
                'first_name' => 'Rosalie',
                'middle_name' => 'Alcantara',
                'last_name' => 'Valenzuela',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1982-12-07',
                'civil_status' => 'married',
                'contact_number' => '0946-012-4466',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Rural Health Midwife',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P6-011',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-004',
                'first_name' => 'Gregorio',
                'middle_name' => 'Santos',
                'last_name' => 'Valenzuela',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1979-04-15',
                'civil_status' => 'married',
                'contact_number' => '0946-012-4467',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Coffee Farm Manager',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P6-012',
                'purok_code' => 'purok-6',
                'household_code' => 'HH-P6-004',
                'first_name' => 'Christian',
                'middle_name' => 'Santos',
                'last_name' => 'Valenzuela',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2007-07-29',
                'civil_status' => 'single',
                'contact_number' => '0946-012-4468',
                'address' => '095 Forest Reserve Road, Sitio San Isidro, Purok 6, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Criminology Student',
                'remarks' => 'Child; Youth voter',
            ],

            // ==========================================
            // PUROK 7 (Sitio Pag-asa — 12 Residents)
            // ==========================================
            // HH-P7-001 (Hernandez)
            [
                'resident_code' => 'RES-P7-001',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-001',
                'first_name' => 'Rowena',
                'middle_name' => 'Perez',
                'last_name' => 'Hernandez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1977-03-16',
                'civil_status' => 'married',
                'contact_number' => '0918-234-5577',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => "Purok Women's Association Coordinator",
                'remarks' => 'Head of Household; Community Leader',
            ],
            [
                'resident_code' => 'RES-P7-002',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-001',
                'first_name' => 'Alberto',
                'middle_name' => 'Flores',
                'last_name' => 'Hernandez',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1975-08-20',
                'civil_status' => 'married',
                'contact_number' => '0918-234-5578',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Kidapawan City, Cotabato',
                'occupation' => 'Freight Truck Driver',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P7-003',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-001',
                'first_name' => 'Sheila',
                'middle_name' => 'Perez',
                'last_name' => 'Hernandez',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2004-06-12',
                'civil_status' => 'single',
                'contact_number' => '0918-234-5579',
                'address' => '028 Pag-asa Access Road, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Bookkeeper',
                'remarks' => 'Child',
            ],

            // HH-P7-002 (Pascual)
            [
                'resident_code' => 'RES-P7-004',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-002',
                'first_name' => 'Angelica',
                'middle_name' => 'Navarro',
                'last_name' => 'Pascual',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1984-11-23',
                'civil_status' => 'married',
                'contact_number' => '0929-345-6688',
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Sari-Sari Store & Feed Dealer',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P7-005',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-002',
                'first_name' => 'Cesar',
                'middle_name' => 'Gomez',
                'last_name' => 'Pascual',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1981-02-18',
                'civil_status' => 'married',
                'contact_number' => '0929-345-6689',
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Bansalan, Davao del Sur',
                'occupation' => 'Livestock Raiser (Swine/Poultry)',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P7-006',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-002',
                'first_name' => 'Carlito',
                'middle_name' => 'Navarro',
                'last_name' => 'Pascual',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2012-10-08',
                'civil_status' => 'single',
                'contact_number' => null,
                'address' => '055 Sitio Pag-asa Proper, Purok 7, Sta. Felomina',
                'is_registered_voter' => false,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Elementary Student',
                'remarks' => 'Child; Minor dependent',
            ],

            // HH-P7-003 (Del Rosario)
            [
                'resident_code' => 'RES-P7-007',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Andres',
                'middle_name' => 'Ramos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1973-09-04',
                'civil_status' => 'married',
                'contact_number' => '0933-456-7799',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Construction General Foreman',
                'remarks' => 'Head of Household',
            ],
            [
                'resident_code' => 'RES-P7-008',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Maria Fe',
                'middle_name' => 'Santos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1976-05-17',
                'civil_status' => 'married',
                'contact_number' => '0933-456-7800',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Tailoring Contractor',
                'remarks' => 'Spouse',
            ],
            [
                'resident_code' => 'RES-P7-009',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Francis',
                'middle_name' => 'Santos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '2002-01-29',
                'civil_status' => 'single',
                'contact_number' => '0933-456-7801',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Site Electrician',
                'remarks' => 'Child',
            ],
            [
                'resident_code' => 'RES-P7-010',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-003',
                'first_name' => 'Patricia',
                'middle_name' => 'Santos',
                'last_name' => 'Del Rosario',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '2005-07-14',
                'civil_status' => 'single',
                'contact_number' => '0933-456-7802',
                'address' => '083 Boundary Marker Street, Sitio Pag-asa, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Transferred,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'University Student (GenSan)',
                'remarks' => 'Child; Transferred to General Santos City for university studies',
            ],

            // HH-P7-004 (Castillo)
            [
                'resident_code' => 'RES-P7-011',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-004',
                'first_name' => 'Teresa',
                'middle_name' => 'Bautista',
                'last_name' => 'Castillo',
                'suffix' => null,
                'sex' => 'female',
                'birthdate' => '1963-06-30',
                'civil_status' => 'widowed',
                'contact_number' => '0947-567-8811',
                'address' => '112 Sitio Pag-asa Edge, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Active,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'Retired Seamstress & Caretaker',
                'remarks' => 'Head of Household; Senior Citizen',
            ],
            [
                'resident_code' => 'RES-P7-012',
                'purok_code' => 'purok-7',
                'household_code' => 'HH-P7-004',
                'first_name' => 'Jeffrey',
                'middle_name' => 'Bautista',
                'last_name' => 'Castillo',
                'suffix' => null,
                'sex' => 'male',
                'birthdate' => '1991-12-14',
                'civil_status' => 'single',
                'contact_number' => '+971-50-987-6543',
                'address' => '112 Sitio Pag-asa Edge, Purok 7, Sta. Felomina',
                'is_registered_voter' => true,
                'status' => ResidentStatus::Inactive,
                'birthplace' => 'Makilala, Cotabato',
                'occupation' => 'HVAC Technician in Dubai, UAE',
                'remarks' => 'Child; OFW in Dubai, UAE since 2023',
            ],
        ];
    }

    /**
     * Phase 1: 29 Non-Superseded Documents.
     *
     * @return array<int, array{number: string, type_code: string, category_slug: string, year: int, date: string, title: string, subject: string, keywords: array<string>, status: DocumentStatus, submitted_at?: string, approved_at?: string, returned_at?: string, return_comment?: string, archived_at?: string, archive_reason?: string, revoked_at?: string, revoke_reason?: string, is_project_related?: bool, project_name?: string, project_location?: string, approved_budget?: float, project_start_date?: string, target_completion_date?: string, project_remarks?: string, suggested_category_slug?: string, suggestion_terms?: array<string>, suggestion_reason?: string, category_overridden?: bool}>
     */
    protected function phase1DocumentsData(): array
    {
        return [
            // --- 2024 Records (5 docs) ---
            [
                'number' => 'RES-2024-001',
                'type_code' => 'resolution',
                'category_slug' => 'infrastructure',
                'suggested_category_slug' => 'infrastructure',
                'suggestion_terms' => ['road', 'infrastructure', 'concreting'],
                'suggestion_reason' => 'Matched keyword rule: Infrastructure Keywords',
                'year' => 2024,
                'date' => '2024-03-15',
                'title' => 'Concreting of Purok 1 to Purok 3 Farm-to-Market Road',
                'subject' => 'Infrastructure road concreting project linking agricultural production sitios',
                'keywords' => ['road', 'infrastructure', 'concreting', 'purok 1', 'purok 3'],
                'status' => DocumentStatus::Archived,
                'submitted_at' => '2024-03-16 09:00:00',
                'approved_at' => '2024-03-18 14:00:00',
                'archived_at' => '2025-01-10 10:00:00',
                'archive_reason' => 'Project construction completed and audited; administrative retention period archived.',
                'is_project_related' => true,
                'project_name' => 'Purok 1 to Purok 3 Farm-to-Market Road Concreting',
                'project_location' => 'Purok 1 to Purok 3, Barangay Sta. Felomina',
                'approved_budget' => 2500000.00,
                'project_start_date' => '2024-04-01',
                'target_completion_date' => '2024-11-30',
                'project_remarks' => 'Completed in full compliance with municipal engineering specifications.',
            ],
            [
                'number' => 'EO-2024-001',
                'type_code' => 'executive_order',
                'category_slug' => 'disaster-risk-reduction',
                'suggested_category_slug' => 'disaster-risk-reduction',
                'suggestion_terms' => ['disaster', 'bdrrmc', 'rescue', 'preparedness'],
                'suggestion_reason' => 'Matched keyword rule: Disaster Risk Reduction Keywords',
                'year' => 2024,
                'date' => '2024-06-05',
                'title' => 'Reorganization and Mobilization of the Barangay Disaster Risk Reduction and Management Committee (BDRRMC)',
                'subject' => 'Disaster preparedness committee restructuring and rescue team deployment',
                'keywords' => ['disaster', 'bdrrmc', 'rescue', 'preparedness', 'emergency'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2024-06-06 10:00:00',
                'approved_at' => '2024-06-07 15:30:00',
            ],
            [
                'number' => 'CERT-2024-001',
                'type_code' => 'certificate',
                'category_slug' => 'governance',
                'year' => 2024,
                'date' => '2024-07-12',
                'title' => 'Annual Good Financial Housekeeping Full Compliance Certification',
                'subject' => 'Financial transparency and posting compliance under DILG guidelines',
                'keywords' => ['governance', 'finance', 'compliance', 'transparency'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2024-07-13 11:00:00',
                'approved_at' => '2024-07-14 16:00:00',
            ],
            [
                'number' => 'ORD-2024-002',
                'type_code' => 'ordinance',
                'category_slug' => 'infrastructure',
                'suggested_category_slug' => 'infrastructure',
                'suggestion_terms' => ['waste', 'segregation', 'infrastructure'],
                'suggestion_reason' => 'Matched keyword rule: Infrastructure Keywords',
                'year' => 2024,
                'date' => '2024-09-18',
                'title' => 'Comprehensive Solid Waste Management and Mandatory Purok Waste Segregation',
                'subject' => 'Ecological solid waste sorting, composting centers, and segregation penalties',
                'keywords' => ['waste', 'segregation', 'sanitation', 'environment', 'infrastructure'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2024-09-20 09:30:00',
                'approved_at' => '2024-09-22 14:15:00',
                'is_project_related' => true,
                'project_name' => 'Barangay Materials Recovery Facility & Composting Center',
                'project_location' => 'Purok 5 Ecological Park, Barangay Sta. Felomina',
                'approved_budget' => 850000.00,
                'project_start_date' => '2024-10-01',
                'target_completion_date' => '2025-02-28',
                'project_remarks' => 'Materials Recovery Facility constructed and operational.',
            ],
            [
                'number' => 'RES-2024-003',
                'type_code' => 'resolution',
                'category_slug' => 'health',
                'year' => 2024,
                'date' => '2024-11-04',
                'title' => 'Emergency Procurement of Health Center Medical Equipment',
                'subject' => 'Barangay health clinic emergency diagnostic equipment acquisition',
                'keywords' => ['health', 'medical', 'clinic', 'procurement'],
                'status' => DocumentStatus::Revoked,
                'submitted_at' => '2024-11-05 10:00:00',
                'approved_at' => '2024-11-06 11:00:00',
                'revoked_at' => '2024-12-01 16:00:00',
                'revoke_reason' => 'Revoked pursuant to Municipal Audit finding regarding duplicate bidding procedures.',
            ],

            // --- 2025 Records (8 docs) ---
            [
                'number' => 'RES-2025-001',
                'type_code' => 'resolution',
                'category_slug' => 'disaster-risk-reduction',
                'suggested_category_slug' => 'disaster-risk-reduction',
                'suggestion_terms' => ['calamity', 'flood', 'bdrrmc', 'disaster'],
                'suggestion_reason' => 'Matched keyword rule: Disaster Risk Reduction Keywords',
                'year' => 2025,
                'date' => '2025-01-28',
                'title' => 'Allocation of 5% Local Calamity Fund for Flood Mitigation in Puroks 4 and 5',
                'subject' => 'Calamity fund allocation for riverbank desilting and rescue motorized boats',
                'keywords' => ['calamity', 'flood', 'bdrrmc', 'mitigation', 'disaster'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2025-01-29 08:30:00',
                'approved_at' => '2025-01-30 13:45:00',
            ],
            [
                'number' => 'ORD-2025-001',
                'type_code' => 'ordinance',
                'category_slug' => 'health',
                'year' => 2025,
                'date' => '2025-03-10',
                'title' => 'Anti-Dengue Prevention Campaign and Community Sanitation Clean-Up Directive',
                'subject' => 'Search and destroy mosquito breeding grounds inspection across all puroks',
                'keywords' => ['dengue', 'health', 'sanitation', 'clean-up', 'mosquito'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2025-03-12 10:15:00',
                'approved_at' => '2025-03-14 15:00:00',
            ],
            [
                'number' => 'EO-2025-001',
                'type_code' => 'executive_order',
                'category_slug' => 'peace-and-order',
                'year' => 2025,
                'date' => '2025-04-15',
                'title' => 'Mobilization and Deployment of Barangay Tanod Brigade for Boundary Checkpoints',
                'subject' => 'Tanod brigade night shifts and highway boundary security checkpoints',
                'keywords' => ['tanod', 'patrol', 'checkpoint', 'peace and order', 'security'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2025-04-16 11:00:00',
                'approved_at' => '2025-04-18 14:30:00',
            ],
            [
                'number' => 'CERT-2025-001',
                'type_code' => 'certificate',
                'category_slug' => 'social-services',
                'year' => 2025,
                'date' => '2025-05-20',
                'title' => 'Masterlist Certification of Indigent Families for National DSWD 4Ps Program',
                'subject' => 'Official barangay endorsement of qualified indigent families for social welfare',
                'keywords' => ['indigent', 'welfare', '4ps', 'assistance', 'social services'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2025-05-21 09:00:00',
                'approved_at' => '2025-05-22 11:30:00',
            ],
            [
                'number' => 'RES-2025-002',
                'type_code' => 'resolution',
                'category_slug' => 'education',
                'year' => 2025,
                'date' => '2025-06-18',
                'title' => 'Educational Subsidy and School Supplies Allowance for Out-of-School Youth',
                'subject' => 'Barangay scholarship stipends and school supply distributions',
                'keywords' => ['education', 'scholarship', 'youth', 'school', 'subsidy'],
                'status' => DocumentStatus::Archived,
                'submitted_at' => '2025-06-20 10:00:00',
                'approved_at' => '2025-06-22 14:00:00',
                'archived_at' => '2026-01-05 09:30:00',
                'archive_reason' => 'Program cycle for Academic Year 2024-2025 formally closed and audited.',
            ],
            [
                'number' => 'ORD-2025-002',
                'type_code' => 'ordinance',
                'category_slug' => 'peace-and-order',
                'year' => 2025,
                'date' => '2025-08-11',
                'title' => 'Experimental Route and Terminal Restriction for Tricycles and Pedicabs',
                'subject' => 'Traffic management and tricycle terminal staging at national highway junction',
                'keywords' => ['traffic', 'transport', 'terminal', 'peace and order'],
                'status' => DocumentStatus::Revoked,
                'submitted_at' => '2025-08-13 09:30:00',
                'approved_at' => '2025-08-15 16:00:00',
                'revoked_at' => '2025-10-02 10:15:00',
                'revoke_reason' => 'Revoked following Sangguniang Bayan review and opposition from local transport cooperatives.',
            ],
            [
                'number' => 'RES-2025-003',
                'type_code' => 'resolution',
                'category_slug' => 'infrastructure',
                'suggested_category_slug' => 'infrastructure',
                'suggestion_terms' => ['infrastructure', 'streetlights', 'solar'],
                'suggestion_reason' => 'Matched keyword rule: Infrastructure Keywords',
                'year' => 2025,
                'date' => '2025-10-19',
                'title' => 'Installation of Solar-Powered Streetlights along Sitio Riverside and Purok 6',
                'subject' => 'Green energy lighting installation for dark provincial road sections',
                'keywords' => ['infrastructure', 'streetlights', 'safety', 'solar'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2025-10-21 08:45:00',
                'approved_at' => '2025-10-23 15:30:00',
                'is_project_related' => true,
                'project_name' => 'Sitio Riverside & Purok 6 Solar Streetlight Installation',
                'project_location' => 'Sitio Riverside and Purok 6, Barangay Sta. Felomina',
                'approved_budget' => 750000.00,
                'project_start_date' => '2025-11-01',
                'target_completion_date' => '2025-12-15',
                'project_remarks' => '50 LED solar streetlights installed with 2-year warranty.',
            ],
            [
                'number' => 'CERT-2025-002',
                'type_code' => 'certificate',
                'category_slug' => 'peace-and-order',
                'suggested_category_slug' => 'governance',
                'category_overridden' => true,
                'suggestion_terms' => ['compliance'],
                'suggestion_reason' => 'Matched keyword rule: Governance Keywords',
                'year' => 2025,
                'date' => '2025-11-28',
                'title' => 'Lupon Tagapamayapa Dispute Settlement Compliance Certification',
                'subject' => 'Certification of amicable dispute resolution under Katarungang Pambarangay',
                'keywords' => ['lupon', 'dispute', 'peace and order', 'katarungan', 'compliance'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2025-11-29 10:00:00',
                'approved_at' => '2025-11-30 11:15:00',
            ],

            // --- 2026 Records (16 docs) ---
            [
                'number' => 'ORD-2026-001',
                'type_code' => 'ordinance',
                'category_slug' => 'peace-and-order',
                'suggested_category_slug' => 'peace-and-order',
                'suggestion_terms' => ['curfew', 'peace and order', 'minors'],
                'suggestion_reason' => 'Matched keyword rule: Peace & Order Keywords',
                'year' => 2026,
                'date' => '2026-01-10',
                'title' => 'Omnibus Youth Protection, Curfew Hours, and Child Welfare Ordinance of 2026',
                'subject' => 'Comprehensive curfew hours (10 PM - 4 AM) for minors and parental responsibility penalties',
                'keywords' => ['curfew', 'youth', 'welfare', 'peace and order', 'minors'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2026-01-12 09:00:00',
                'approved_at' => '2026-01-14 14:00:00',
            ],
            [
                'number' => 'EO-2026-001',
                'type_code' => 'executive_order',
                'category_slug' => 'governance',
                'year' => 2026,
                'date' => '2026-01-12',
                'title' => 'Designation of Barangay Data Protection Officer and Official Records Custodian',
                'subject' => 'Appointment of permanent Data Protection Officer in compliance with RA 10173',
                'keywords' => ['governance', 'data protection', 'records', 'officer'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2026-01-14 10:30:00',
                'approved_at' => '2026-01-15 16:00:00',
            ],
            [
                'number' => 'RES-2026-001',
                'type_code' => 'resolution',
                'category_slug' => 'disaster-risk-reduction',
                'suggested_category_slug' => 'disaster-risk-reduction',
                'suggestion_terms' => ['disaster', 'bdrrmc', 'evacuation', 'preparedness'],
                'suggestion_reason' => 'Matched keyword rule: Disaster Risk Reduction Keywords',
                'year' => 2026,
                'date' => '2026-01-16',
                'title' => 'Barangay Disaster Risk Reduction Master Plan and Evacuation Protocol for 2026-2028',
                'subject' => 'Triennial disaster roadmap, flood telemetry sensors, and evacuation logistics',
                'keywords' => ['disaster', 'bdrrmc', 'evacuation', 'preparedness', 'flood'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2026-01-18 09:15:00',
                'approved_at' => '2026-01-19 15:00:00',
            ],
            [
                'number' => 'RES-2026-002',
                'type_code' => 'resolution',
                'category_slug' => 'health',
                'year' => 2026,
                'date' => '2026-02-03',
                'title' => 'Barangay Supplemental Nutrition and Child Immunization Drive for 2026',
                'subject' => 'Quarterly child feeding and routine pediatric immunization schedule',
                'keywords' => ['health', 'vaccination', 'nutrition', 'clinic', 'children'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2026-02-04 10:00:00',
                'approved_at' => '2026-02-06 14:30:00',
            ],
            [
                'number' => 'CERT-2026-001',
                'type_code' => 'certificate',
                'category_slug' => 'governance',
                'year' => 2026,
                'date' => '2026-02-18',
                'title' => 'Certification of Barangay Residency and Good Moral Standing',
                'subject' => 'Standard residency verification template for employment and scholarship applicants',
                'keywords' => ['residency', 'clearance', 'governance', 'certification'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2026-02-19 09:30:00',
                'approved_at' => '2026-02-20 11:00:00',
            ],
            [
                'number' => 'ORD-2026-002',
                'type_code' => 'ordinance',
                'category_slug' => 'infrastructure',
                'suggested_category_slug' => 'infrastructure',
                'suggestion_terms' => ['drainage', 'infrastructure'],
                'suggestion_reason' => 'Matched keyword rule: Infrastructure Keywords',
                'year' => 2026,
                'date' => '2026-03-01',
                'title' => 'Purok Drainage Maintenance and Anti-Littering Ordinance',
                'subject' => 'Mandatory canal clearing and penalties for dumping agricultural runoff into waterways',
                'keywords' => ['drainage', 'anti-littering', 'infrastructure', 'sanitation', 'waterway'],
                'status' => DocumentStatus::Published,
                'submitted_at' => '2026-03-03 10:00:00',
                'approved_at' => '2026-03-05 15:15:00',
                'is_project_related' => true,
                'project_name' => 'Purok Perimeter Drainage Canal Rehabilitation',
                'project_location' => 'Purok 2 and Purok 3, Barangay Sta. Felomina',
                'approved_budget' => 600000.00,
                'project_start_date' => '2026-03-15',
                'target_completion_date' => '2026-05-30',
                'project_remarks' => 'Reinforced canal lining and silt trap construction completed.',
            ],
            [
                'number' => 'RES-2026-003',
                'type_code' => 'resolution',
                'category_slug' => 'infrastructure',
                'suggested_category_slug' => 'infrastructure',
                'suggestion_terms' => ['construction', 'facility', 'infrastructure'],
                'suggestion_reason' => 'Matched keyword rule: Infrastructure Keywords',
                'year' => 2026,
                'date' => '2026-03-08',
                'title' => 'Authorization for Construction of Purok 6 Multipurpose Evacuation Center',
                'subject' => 'Barangay counterpart fund allocation for upland concrete evacuation shelter',
                'keywords' => ['construction', 'facility', 'infrastructure', 'evacuation', 'shelter'],
                'status' => DocumentStatus::Archived,
                'submitted_at' => '2026-03-10 09:00:00',
                'approved_at' => '2026-03-12 14:00:00',
                'archived_at' => '2026-07-15 10:30:00',
                'archive_reason' => 'Replaced by direct provincial grant agreement; barangay funding file closed and archived.',
                'is_project_related' => true,
                'project_name' => 'Purok 6 Multipurpose Evacuation Center Construction',
                'project_location' => 'Purok 6 High Ground Area, Barangay Sta. Felomina',
                'approved_budget' => 4800000.00,
                'project_start_date' => '2026-04-01',
                'target_completion_date' => '2026-10-31',
                'project_remarks' => 'Barangay allocation archived following direct provincial funding agreement.',
            ],
            [
                'number' => 'ORD-2026-003',
                'type_code' => 'ordinance',
                'category_slug' => 'governance',
                'year' => 2026,
                'date' => '2026-04-02',
                'title' => 'Temporary Moratorium on Special Cockfighting and Commercial Amusement Permits',
                'subject' => 'Permit restrictions on special fiesta cockfighting events during holy week',
                'keywords' => ['governance', 'permits', 'amusement', 'moratorium'],
                'status' => DocumentStatus::Revoked,
                'submitted_at' => '2026-04-03 11:00:00',
                'approved_at' => '2026-04-05 16:30:00',
                'revoked_at' => '2026-04-20 14:00:00',
                'revoke_reason' => 'Revoked due to legal conflict with existing municipal licensing ordinance Section 12.',
            ],
            [
                'number' => 'RES-2026-004',
                'type_code' => 'resolution',
                'category_slug' => 'education',
                'suggested_category_slug' => 'education',
                'suggestion_terms' => ['education', 'learning', 'youth'],
                'suggestion_reason' => 'Matched keyword rule: Education Keywords',
                'year' => 2026,
                'date' => '2026-08-10',
                'title' => 'Establishment of Community Learning Center and Free Wi-Fi Hub in Purok 2',
                'subject' => 'Digital literacy learning hub with public fiber internet for students',
                'keywords' => ['education', 'learning', 'youth', 'facility', 'wifi'],
                'status' => DocumentStatus::PendingApproval,
                'submitted_at' => '2026-08-12 09:45:00',
            ],
            [
                'number' => 'ORD-2026-004',
                'type_code' => 'ordinance',
                'category_slug' => 'disaster-risk-reduction',
                'suggested_category_slug' => 'disaster-risk-reduction',
                'suggestion_terms' => ['fire', 'earthquake', 'evacuation', 'disaster'],
                'suggestion_reason' => 'Matched keyword rule: Disaster Risk Reduction Keywords',
                'year' => 2026,
                'date' => '2026-08-18',
                'title' => 'Mandatory Fire and Earthquake Evacuation Protocols for Commercial Establishments',
                'subject' => 'Safety inspections, fire exit signage, and quarterly disaster drill requirements',
                'keywords' => ['fire', 'earthquake', 'evacuation', 'safety', 'disaster'],
                'status' => DocumentStatus::PendingApproval,
                'submitted_at' => '2026-08-20 14:15:00',
            ],
            [
                'number' => 'EO-2026-002',
                'type_code' => 'executive_order',
                'category_slug' => 'peace-and-order',
                'suggested_category_slug' => 'peace-and-order',
                'suggestion_terms' => ['badac', 'peace and order', 'patrol'],
                'suggestion_reason' => 'Matched keyword rule: Peace & Order Keywords',
                'year' => 2026,
                'date' => '2026-08-25',
                'title' => 'Creating Barangay Anti-Drug Abuse Council (BADAC) Special Task Force',
                'subject' => 'Inter-agency drug prevention committee and neighborhood watch mobilization',
                'keywords' => ['badac', 'peace and order', 'patrol', 'security', 'task force'],
                'status' => DocumentStatus::PendingApproval,
                'submitted_at' => '2026-08-26 10:30:00',
            ],
            [
                'number' => 'RES-2026-005',
                'type_code' => 'resolution',
                'category_slug' => 'infrastructure',
                'suggested_category_slug' => 'infrastructure',
                'suggestion_terms' => ['drainage', 'culvert', 'infrastructure'],
                'suggestion_reason' => 'Matched keyword rule: Infrastructure Keywords',
                'year' => 2026,
                'date' => '2026-08-28',
                'title' => 'Drainage Canal Desilting and Culvert Rehabilitation along Purok 4 Creek',
                'subject' => 'Creek dredging and reinforced concrete box culvert construction',
                'keywords' => ['drainage', 'culvert', 'rehabilitation', 'infrastructure', 'creek'],
                'status' => DocumentStatus::NeedsRevision,
                'submitted_at' => '2026-08-29 09:00:00',
                'returned_at' => '2026-08-30 15:00:00',
                'return_comment' => 'Please re-check the cost estimate in Section 4 against Municipal Engineering schedule of rates before resubmission.',
                'is_project_related' => true,
                'project_name' => 'Purok 4 Creek Drainage Desilting & Culvert Upgrade',
                'project_location' => 'Purok 4 Creek Crossing, Barangay Sta. Felomina',
                'approved_budget' => 1200000.00,
                'project_start_date' => '2026-10-01',
                'target_completion_date' => '2026-12-15',
                'project_remarks' => 'Engineering schedule of unit costs under revision.',
            ],
            [
                'number' => 'ORD-2026-005',
                'type_code' => 'ordinance',
                'category_slug' => 'health',
                'year' => 2026,
                'date' => '2026-09-01',
                'title' => 'Food Sanitation and Hygiene Certification Standards for Local Eateries and Carinderias',
                'subject' => 'Mandatory sanitary permits and food handler health cards for roadside eateries',
                'keywords' => ['health', 'sanitation', 'food', 'cleanliness', 'eatery'],
                'status' => DocumentStatus::NeedsRevision,
                'submitted_at' => '2026-09-02 11:30:00',
                'returned_at' => '2026-09-03 16:45:00',
                'return_comment' => 'Specify compliance grace period (30 days) and exact penalty fee tiers under Section 8.',
            ],
            [
                'number' => 'RES-2026-006',
                'type_code' => 'resolution',
                'category_slug' => 'social-services',
                'suggested_category_slug' => 'social-services',
                'suggestion_terms' => ['senior citizen', 'pwd', 'assistance', 'subsidy'],
                'suggestion_reason' => 'Matched keyword rule: Social Services Keywords',
                'year' => 2026,
                'date' => '2026-09-05',
                'title' => 'Senior Citizen and PWD Quarterly Grocery Subsidy Program',
                'subject' => 'Nutrition and basic grocery assistance for registered senior citizens and PWDs',
                'keywords' => ['senior citizen', 'pwd', 'assistance', 'welfare', 'subsidy'],
                'status' => DocumentStatus::Draft,
            ],
            [
                'number' => 'ORD-2026-006',
                'type_code' => 'ordinance',
                'category_slug' => 'infrastructure',
                'year' => 2026,
                'date' => '2026-09-08',
                'title' => 'Purok Road Right-of-Way Clearing and Obstruction Removal Regulation',
                'subject' => 'Clearing illegal stalls, construction debris, and overhangs on purok roads',
                'keywords' => ['road', 'right-of-way', 'obstruction', 'infrastructure', 'clearing'],
                'status' => DocumentStatus::Draft,
            ],
            [
                'number' => 'EO-2026-003',
                'type_code' => 'executive_order',
                'category_slug' => 'disaster-risk-reduction',
                'year' => 2026,
                'date' => '2026-09-10',
                'title' => 'Pre-emptive Evacuation Guidelines during Severe Weather Bulletins',
                'subject' => 'Standard operating procedures for forced evacuation of landslide-prone sitios',
                'keywords' => ['evacuation', 'typhoon', 'calamity', 'disaster', 'weather'],
                'status' => DocumentStatus::Draft,
            ],
        ];
    }

    /**
     * Phase 2: 3 Superseded Documents.
     *
     * @return array<int, array{number: string, type_code: string, category_slug: string, year: int, date: string, title: string, subject: string, keywords: array<string>, submitted_at: string, approved_at: string, superseded_at: string, supersede_reason: string, replacement_number: string, supersede_reference: string, is_project_related?: bool, project_name?: string, project_location?: string, approved_budget?: float, project_start_date?: string, target_completion_date?: string, project_remarks?: string}>
     */
    protected function phase2SupersededDocumentsData(): array
    {
        return [
            [
                'number' => 'ORD-2024-001',
                'type_code' => 'ordinance',
                'category_slug' => 'peace-and-order',
                'year' => 2024,
                'date' => '2024-04-20',
                'title' => 'Barangay Curfew Hours and Nighttime Safety Restrictions for Minors',
                'subject' => 'Initial curfew regulation enacted for minors under 18 years old',
                'keywords' => ['curfew', 'minors', 'peace and order', 'safety'],
                'submitted_at' => '2024-04-22 09:00:00',
                'approved_at' => '2024-04-25 14:30:00',
                'superseded_at' => '2026-01-14 10:00:00',
                'supersede_reason' => 'Superseded by updated 2026 omnibus youth protection ordinance.',
                'replacement_number' => 'ORD-2026-001',
                'supersede_reference' => 'Ordinance No. ORD-2026-001',
            ],
            [
                'number' => 'RES-2024-002',
                'type_code' => 'resolution',
                'category_slug' => 'disaster-risk-reduction',
                'year' => 2024,
                'date' => '2024-08-22',
                'title' => 'Barangay Calamity Preparedness and Emergency Response Framework of 2024',
                'subject' => 'Initial emergency contingency plans and flood evacuation routes',
                'keywords' => ['calamity', 'disaster', 'emergency', 'bdrrmc'],
                'submitted_at' => '2024-08-23 10:00:00',
                'approved_at' => '2024-08-25 15:00:00',
                'superseded_at' => '2026-01-19 11:00:00',
                'supersede_reason' => 'Superseded by comprehensive 2026-2028 multi-year disaster risk reduction master plan.',
                'replacement_number' => 'RES-2026-001',
                'supersede_reference' => 'Resolution No. RES-2026-001',
            ],
            [
                'number' => 'EO-2025-002',
                'type_code' => 'executive_order',
                'category_slug' => 'governance',
                'year' => 2025,
                'date' => '2025-09-02',
                'title' => 'Interim Designation of Records Custodian and Document Handler',
                'subject' => 'Temporary assignment of barangay archives and public record requests',
                'keywords' => ['governance', 'records', 'administration', 'custodian'],
                'submitted_at' => '2025-09-03 09:00:00',
                'approved_at' => '2025-09-05 14:00:00',
                'superseded_at' => '2026-01-15 11:30:00',
                'supersede_reason' => 'Superseded by permanent administrative appointment and data protection restructuring.',
                'replacement_number' => 'EO-2026-001',
                'supersede_reference' => 'Executive Order No. EO-2026-001',
            ],
        ];
    }
}
